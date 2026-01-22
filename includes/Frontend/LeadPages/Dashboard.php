<?php
/**
 * Lead Pages Dashboard
 *
 * Renders the "My Lead Pages" dashboard showing user's pages and leads.
 * Used on /marketing/lead-pages/ via [my_lead_pages] shortcode.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Frontend\LeadPages;

class Dashboard {

    /**
     * Asset version for cache busting
     */
    private const ASSET_VERSION = '1.0.0';

    /**
     * Render the dashboard
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render( array $atts = [] ): string {
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return self::render_login_required();
        }

        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Determine user role (convert to bool)
        $is_realtor = ! empty( array_intersect( [ 'realtor', 'realtor_partner' ], $user->roles ) );
        $is_loan_officer = ! empty( array_intersect( [ 'loan_officer', 'administrator', 'editor' ], $user->roles ) );
        $is_admin = in_array( 'administrator', $user->roles, true );

        // Get user's lead pages
        $query = self::get_user_pages_query( $current_user_id, $is_loan_officer );

        // Get user's leads
        $leads = self::get_user_leads( $current_user_id, $is_loan_officer );

        // Get analytics data
        $analytics_period = $_GET['analytics_period'] ?? '30days';
        $analytics_summary = \FRSLeadPages\Core\Analytics::get_user_stats( $current_user_id, $analytics_period );
        $analytics_pages = \FRSLeadPages\Core\Analytics::get_user_pages_stats( $current_user_id, $analytics_period );

        // Enqueue assets
        self::enqueue_assets();

        ob_start();
        include __DIR__ . '/templates/dashboard.php';
        return ob_get_clean();
    }

    /**
     * Get WP_Query for user's lead pages
     *
     * @param int  $user_id        Current user ID
     * @param bool $is_loan_officer Whether user is a loan officer
     * @return \WP_Query
     */
    private static function get_user_pages_query( int $user_id, bool $is_loan_officer ): \WP_Query {
        // Simple query - just check for realtor_id or loan_officer_id
        // Removed NOT EXISTS check as it's slow and rarely needed
        $args = [
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_frs_realtor_id',
                    'value' => $user_id,
                ],
            ],
        ];

        // Check for pages where user is the loan officer
        if ( $is_loan_officer ) {
            $args['meta_query'][] = [
                'key'   => '_frs_loan_officer_id',
                'value' => $user_id,
            ];
        }

        return new \WP_Query( $args );
    }

    /**
     * Get leads for user
     *
     * @param int  $user_id        Current user ID
     * @param bool $is_loan_officer Whether user is a loan officer
     * @return array
     */
    private static function get_user_leads( int $user_id, bool $is_loan_officer ): array {
        $leads = [];

        // Get leads from FluentForms submissions
        if ( \FRSLeadPages\Integrations\FluentForms::is_active() ) {
            $submissions = \FRSLeadPages\Integrations\FluentForms::get_submissions_for_user( $user_id );

            foreach ( $submissions as $submission ) {
                $leads[] = [
                    'id'         => $submission['id'],
                    'first_name' => $submission['first_name'],
                    'last_name'  => $submission['last_name'],
                    'email'      => $submission['email'],
                    'phone'      => $submission['phone'],
                    'source'     => $submission['lead_page_title'] ?? 'Unknown',
                    'property'   => '',
                    'status'     => $submission['status'],
                    'created_at' => $submission['created_at'],
                ];
            }
        }

        // Also get leads from wp_lead_submissions table (if available)
        $lrg_leads = self::get_lrg_leads( $user_id, $is_loan_officer ? 'loan_officer' : 'realtor' );
        $leads = array_merge( $leads, $lrg_leads );

        // Sort all leads by date (newest first)
        usort( $leads, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        });

        return $leads;
    }

    /**
     * Get leads from LRG plugin
     *
     * @param int    $user_id Current user ID
     * @param string $role    User role type
     * @return array
     */
    private static function get_lrg_leads( int $user_id, string $role ): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'lead_submissions';

        // Cache table existence check - avoid SHOW TABLES on every page load
        $table_exists_key = 'frs_lrg_table_exists';
        $table_exists = wp_cache_get( $table_exists_key );

        if ( $table_exists === false ) {
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
            wp_cache_set( $table_exists_key, $table_exists ? 'yes' : 'no', '', 3600 ); // Cache for 1 hour
        } elseif ( $table_exists === 'no' ) {
            return [];
        }

        if ( $table_exists !== 'yes' && ! $table_exists ) {
            return [];
        }

        // Query based on role
        if ( $role === 'loan_officer' ) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE loan_officer_id = %d ORDER BY created_at DESC LIMIT 100",
                    $user_id
                ),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE realtor_id = %d ORDER BY created_at DESC LIMIT 100",
                    $user_id
                ),
                ARRAY_A
            );
        }

        if ( ! $results ) {
            return [];
        }

        return array_map( function( $row ) {
            return [
                'id'         => $row['id'],
                'first_name' => $row['first_name'] ?? '',
                'last_name'  => $row['last_name'] ?? '',
                'email'      => $row['email'] ?? '',
                'phone'      => $row['phone'] ?? '',
                'source'     => $row['source'] ?? 'Calculator',
                'property'   => $row['property_address'] ?? '',
                'status'     => $row['status'] ?? 'new',
                'created_at' => $row['created_at'] ?? '',
            ];
        }, $results );
    }

    /**
     * Enqueue dashboard assets
     */
    private static function enqueue_assets(): void {
        $base_url = plugins_url( 'includes/Frontend/LeadPages/', FRS_LEAD_PAGES_PLUGIN_FILE );

        wp_enqueue_style(
            'frs-lead-pages-dashboard',
            $base_url . 'style.css',
            [],
            self::ASSET_VERSION
        );

        wp_enqueue_script(
            'frs-lead-pages-dashboard',
            $base_url . 'script.js',
            [],
            self::ASSET_VERSION,
            true
        );

        // Pass data to JS
        wp_localize_script( 'frs-lead-pages-dashboard', 'frsLeadPages', [
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'deleteLeadNonce'  => wp_create_nonce( 'frs_delete_lead' ),
            'deletePageNonce'  => wp_create_nonce( 'frs_delete_lead_page' ),
        ]);

        // QR Code library
        wp_enqueue_script(
            'qr-code-styling',
            'https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.min.js',
            [],
            '1.6.0',
            true
        );
    }

    /**
     * Render login required message
     *
     * @return string
     */
    private static function render_login_required(): string {
        ob_start();
        ?>
        <div class="frs-login-required">
            <p>Please <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">log in</a> to view your lead pages.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}
