<?php
/**
 * Shortcodes for FRS Lead Pages
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class Shortcodes {

    /**
     * Initialize hooks
     */
    public static function init() {
        // Dashboard shortcode (used on /marketing/lead-pages/)
        add_shortcode( 'my_lead_pages', [ __CLASS__, 'render_my_pages' ] );

        // Lead page rendering
        add_shortcode( 'lead_page', [ __CLASS__, 'render_lead_page' ] );
        add_shortcode( 'lead_page_submissions', [ __CLASS__, 'render_submissions' ] );

        // Auto-render template on lead page post type
        add_filter( 'the_content', [ __CLASS__, 'auto_render_lead_page' ], 10 );

        // Use custom template for lead pages (high priority to override theme)
        add_filter( 'template_include', [ __CLASS__, 'load_lead_page_template' ], 9999 );
    }

    /**
     * Automatically render lead page template on frs_lead_page post type
     */
    public static function auto_render_lead_page( string $content ): string {
        if ( ! is_singular( 'frs_lead_page' ) ) {
            return $content;
        }

        // Only run once
        if ( did_action( 'frs_lead_pages_rendered' ) ) {
            return $content;
        }
        do_action( 'frs_lead_pages_rendered' );

        return self::render_lead_page( [ 'id' => get_the_ID() ] );
    }

    /**
     * Load custom template for lead pages (full-width, no header/footer)
     */
    public static function load_lead_page_template( string $template ): string {
        if ( ! is_singular( 'frs_lead_page' ) ) {
            return $template;
        }

        // Use the new Template class loader
        $custom_template = FRS_LEAD_PAGES_PLUGIN_DIR . 'includes/Frontend/LeadPage/loader.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }

        return $template;
    }

    /**
     * Render a lead page template
     *
     * Usage: [lead_page id="123"] or used automatically on frs_lead_page post type
     */
    public static function render_lead_page( array $atts = [] ): string {
        $atts = shortcode_atts([
            'id' => get_the_ID(),
        ], $atts, 'lead_page' );

        $page_id = absint( $atts['id'] );

        if ( ! $page_id ) {
            return '';
        }

        $post = get_post( $page_id );
        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return '';
        }

        // Increment view count
        $views = (int) get_post_meta( $page_id, '_frs_page_views', true );
        update_post_meta( $page_id, '_frs_page_views', $views + 1 );

        // Build data attributes
        $data_attrs = [
            'data-component="template"',
            sprintf( 'data-page-id="%d"', $page_id ),
        ];

        return sprintf(
            '<div id="frs-lead-pages-template" class="frs-lead-pages-app" %s></div>',
            implode( ' ', $data_attrs )
        );
    }

    /**
     * Render login required message
     */
    private static function render_login_required(): string {
        $login_url = wp_login_url( get_permalink() );

        return sprintf(
            '<div class="frs-lead-pages-notice frs-lead-pages-notice--login">
                <h3>%s</h3>
                <p>%s</p>
                <a href="%s" class="frs-lead-pages-button">%s</a>
            </div>',
            esc_html__( 'Login Required', 'frs-lead-pages' ),
            esc_html__( 'Please log in to access your lead pages.', 'frs-lead-pages' ),
            esc_url( $login_url ),
            esc_html__( 'Log In', 'frs-lead-pages' )
        );
    }

    /**
     * Render access denied message
     */
    private static function render_access_denied(): string {
        return sprintf(
            '<div class="frs-lead-pages-notice frs-lead-pages-notice--denied">
                <h3>%s</h3>
                <p>%s</p>
            </div>',
            esc_html__( 'Access Denied', 'frs-lead-pages' ),
            esc_html__( 'You do not have permission to create lead pages. Please contact your administrator.', 'frs-lead-pages' )
        );
    }

    /**
     * Render My Lead Pages dashboard with tabs
     *
     * Usage: [my_lead_pages]
     */
    public static function render_my_pages( array $atts = [] ): string {
        // Delegate to the organized Dashboard class
        return \FRSLeadPages\Frontend\LeadPages\Dashboard::render( $atts );
    }

    /**
     * Render lead submissions table/dashboard
     *
     * Uses FluentForms submissions for data
     *
     * Usage: [lead_page_submissions]
     */
    public static function render_submissions( array $atts = [] ): string {
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return self::render_login_required();
        }

        $current_user_id = get_current_user_id();

        // Get all lead pages belonging to current user
        $args = [
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_frs_realtor_id',
                    'value' => $current_user_id,
                ],
            ],
            'author' => $current_user_id,
            'fields' => 'ids',
        ];

        $page_ids = get_posts( $args );

        // Get submissions from FluentForms
        $submissions = [];
        if ( \FRSLeadPages\Integrations\FluentForms::is_active() ) {
            $ff_submissions = \FRSLeadPages\Integrations\FluentForms::get_submissions_for_user( $current_user_id );

            foreach ( $ff_submissions as $submission ) {
                $submissions[] = [
                    'id'              => $submission['id'],
                    'first_name'      => $submission['first_name'],
                    'last_name'       => $submission['last_name'],
                    'email'           => $submission['email'],
                    'phone'           => $submission['phone'],
                    'lead_page_id'    => $submission['lead_page_id'] ?? '',
                    'lead_page_title' => $submission['lead_page_title'] ?? 'Unknown',
                    'property_address' => '',
                    'status'          => $submission['status'],
                    'created_at'      => $submission['created_at'],
                ];
            }
        }

        // Also get leads from wp_lead_submissions table (frs-lrg plugin)
        $lrg_leads = self::get_lrg_leads( $current_user_id, 'any' );

        // Merge and sort by date
        $all_leads = array_merge( $submissions, $lrg_leads );
        usort( $all_leads, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        });

        ob_start();
        ?>
        <div class="frs-submissions-dashboard">
            <style>
                .frs-submissions-dashboard { max-width: 1200px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                .frs-submissions-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
                .frs-submissions-table th { background: #f9fafb; padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
                .frs-submissions-table td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #374151; }
                .frs-submissions-table tr:last-child td { border-bottom: none; }
                .frs-submissions-table tr:hover td { background: #f9fafb; }
                .frs-lead-name { font-weight: 600; color: #111827; }
                .frs-lead-contact a { color: #0ea5e9; text-decoration: none; }
                .frs-lead-contact a:hover { text-decoration: underline; }
                .frs-lead-status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
                .frs-lead-status.new { background: #dcfce7; color: #166534; }
                .frs-lead-status.contacted { background: #e0f2fe; color: #0369a1; }
                .frs-lead-status.converted { background: #fef3c7; color: #92400e; }
                .frs-empty { text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 12px; }
                .frs-empty h3 { margin: 0 0 8px; font-size: 18px; color: #374151; }
                .frs-empty p { margin: 0; color: #6b7280; font-size: 14px; }
            </style>

            <?php if ( ! empty( $all_leads ) ) : ?>
                <table class="frs-submissions-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Source</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $all_leads as $lead ) : ?>
                            <tr>
                                <td><span class="frs-lead-name"><?php echo esc_html( $lead['first_name'] . ' ' . $lead['last_name'] ); ?></span></td>
                                <td class="frs-lead-contact">
                                    <a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a><br>
                                    <a href="tel:<?php echo esc_attr( $lead['phone'] ); ?>"><?php echo esc_html( $lead['phone'] ); ?></a>
                                </td>
                                <td><?php echo esc_html( $lead['lead_page_title'] ?? $lead['source'] ?? 'Unknown' ); ?></td>
                                <td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $lead['created_at'] ) ) ); ?></td>
                                <td><span class="frs-lead-status <?php echo esc_attr( $lead['status'] ); ?>"><?php echo esc_html( ucfirst( $lead['status'] ?: 'new' ) ); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="frs-empty">
                    <h3>No Submissions Yet</h3>
                    <p>When visitors submit forms on your lead pages, they'll appear here.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get leads from wp_lead_submissions table (frs-lrg plugin)
     *
     * @param int    $user_id   User ID to filter by
     * @param string $user_type 'loan_officer' or 'realtor' (unused - now queries both)
     * @return array Array of leads
     */
    private static function get_lrg_leads( int $user_id, string $user_type ): array {
        global $wpdb;

        $table_name = $wpdb->prefix . 'lead_submissions';

        // Check if table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ) );

        if ( ! $table_exists ) {
            return [];
        }

        // Query BOTH loan_officer_id AND agent_id - user could be either/both
        // Leads table stores WordPress user IDs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE loan_officer_id = %d OR agent_id = %d ORDER BY created_date DESC LIMIT 100",
                $user_id,
                $user_id
            ),
            ARRAY_A
        );

        if ( ! $results ) {
            return [];
        }

        // Map source labels
        $source_labels = [
            'mortgage_calculator'  => 'Calculator',
            'mortgage_rate_quote'  => 'Rate Quote',
            'mortgage_application' => 'Application',
            'manual_entry'         => 'Manual Entry',
            'biolink'              => 'Biolink',
            'open_house'           => 'Open House',
            'partnership'          => 'Partnership',
            'spotlight'            => 'Spotlight',
            'event'                => 'Event',
            'lead_page'            => 'Lead Page',
        ];

        $leads = [];
        foreach ( $results as $row ) {
            $source_key = $row['lead_source'] ?? 'unknown';
            $source_label = $source_labels[ $source_key ] ?? ucfirst( str_replace( '_', ' ', $source_key ) );

            $leads[] = [
                'id'         => 'lrg_' . $row['id'],
                'first_name' => $row['first_name'] ?? '',
                'last_name'  => $row['last_name'] ?? '',
                'email'      => $row['email'] ?? '',
                'phone'      => $row['phone'] ?? '',
                'source'     => $source_label,
                'property'   => $row['property_address'] ?? '',
                'status'     => $row['status'] ?? 'new',
                'created_at' => $row['created_date'] ?? '',
            ];
        }

        return $leads;
    }
}
