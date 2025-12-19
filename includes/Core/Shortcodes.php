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
        add_shortcode( 'generation_station', [ __CLASS__, 'render_wizard' ] );
        add_shortcode( 'lead_page_wizard', [ __CLASS__, 'render_wizard' ] );
        add_shortcode( 'lead_page', [ __CLASS__, 'render_lead_page' ] );
        add_shortcode( 'my_lead_pages', [ __CLASS__, 'render_my_pages' ] );
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

        // Check for custom template in plugin
        $custom_template = FRS_LEAD_PAGES_PLUGIN_DIR . 'templates/lead-page.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }

        return $template;
    }

    /**
     * Render the Generation Station wizard
     *
     * Uses FluentForms multi-step wizard forms instead of React.
     * Per SOP: The wizard IS a FluentForm.
     *
     * Usage: [generation_station] or [lead_page_wizard]
     * Usage: [generation_station type="open_house"] - for specific wizard
     */
    public static function render_wizard( array $atts = [] ): string {
        $atts = shortcode_atts([
            'type'        => '', // page type: open_house, customer_spotlight, special_event
            'show_header' => 'true',
        ], $atts, 'generation_station' );

        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return self::render_login_required();
        }

        // Check allowed roles: loan_officer, realtor_partner, administrator, editor, author, contributor
        $user = wp_get_current_user();
        $allowed_roles = [
            'administrator',
            'editor',
            'author',
            'contributor',
            'loan_officer',
            'realtor_partner',
        ];

        if ( ! array_intersect( $allowed_roles, $user->roles ) ) {
            return self::render_access_denied();
        }

        // Check if FluentForms is active
        if ( ! \FRSLeadPages\Integrations\FluentForms::is_active() ) {
            return self::render_fluentforms_required();
        }

        // Get type from URL parameter or shortcode attribute
        $type = ! empty( $atts['type'] ) ? $atts['type'] : ( $_GET['type'] ?? '' );
        $show_header = $atts['show_header'] === 'true';

        // If no type specified, show type selection
        if ( empty( $type ) ) {
            return self::render_wizard_type_selector( $show_header );
        }

        // Get wizard form ID for this type
        $form_id = \FRSLeadPages\Integrations\WizardForms::get_form_id( $type );

        if ( ! $form_id ) {
            return '<div class="frs-lead-pages-notice frs-lead-pages-notice--error">
                <p>Could not load wizard form. Please contact support.</p>
            </div>';
        }

        // Start output buffer
        ob_start();

        if ( $show_header ) {
            self::render_wizard_header( $type );
        }

        // Check for success message
        if ( isset( $_GET['created'] ) && $_GET['created'] === '1' ) {
            $page_url = isset( $_GET['page_url'] ) ? urldecode( $_GET['page_url'] ) : '';
            ?>
            <div class="frs-wizard-success">
                <div class="frs-wizard-success-icon">✓</div>
                <h2>Your Landing Page is Ready!</h2>
                <p>Your new lead page has been created and is live.</p>
                <div class="frs-wizard-success-actions">
                    <?php if ( $page_url ) : ?>
                        <a href="<?php echo esc_url( $page_url ); ?>" class="frs-btn frs-btn-primary" target="_blank">View Your Page</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( home_url( '/my-lead-pages/' ) ); ?>" class="frs-btn">View All Pages</a>
                    <a href="<?php echo esc_url( remove_query_arg( [ 'created', 'page_url' ] ) ); ?>" class="frs-btn">Create Another</a>
                </div>
            </div>
            <style>
                .frs-wizard-success {
                    max-width: 600px;
                    margin: 40px auto;
                    padding: 40px;
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .frs-wizard-success-icon {
                    width: 80px;
                    height: 80px;
                    background: #22c55e;
                    color: #fff;
                    font-size: 40px;
                    line-height: 80px;
                    border-radius: 50%;
                    margin: 0 auto 24px;
                }
                .frs-wizard-success h2 {
                    margin: 0 0 12px;
                    font-size: 28px;
                    color: #111827;
                }
                .frs-wizard-success p {
                    margin: 0 0 24px;
                    color: #6b7280;
                    font-size: 16px;
                }
                .frs-wizard-success-actions {
                    display: flex;
                    gap: 12px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .frs-btn {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #f3f4f6;
                    color: #374151;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 600;
                    transition: all 0.2s;
                }
                .frs-btn:hover {
                    background: #e5e7eb;
                }
                .frs-btn-primary {
                    background: #0ea5e9;
                    color: #fff;
                }
                .frs-btn-primary:hover {
                    background: #0284c7;
                    color: #fff;
                }
            </style>
            <?php
        } else {
            // Render FluentForm wizard
            ?>
            <div class="frs-wizard-container">
                <?php echo do_shortcode( '[fluentform id="' . $form_id . '"]' ); ?>
            </div>
            <style>
                .frs-wizard-container {
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .frs-wizard-container .fluentform {
                    background: #fff;
                    padding: 32px;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                }
                .frs-wizard-container .ff-step-header {
                    margin-bottom: 24px;
                }
                .frs-wizard-container .ff-step-titles {
                    display: flex;
                    justify-content: space-between;
                    position: relative;
                    margin-bottom: 30px;
                }
                .frs-wizard-container .ff-step-titles li {
                    flex: 1;
                    text-align: center;
                    font-weight: 600;
                    color: #9ca3af;
                    position: relative;
                    z-index: 1;
                }
                .frs-wizard-container .ff-step-titles li.ff_active,
                .frs-wizard-container .ff-step-titles li.ff_completed {
                    color: #0ea5e9;
                }
                .frs-wizard-container .ff-el-input--label label {
                    font-weight: 600;
                    color: #374151;
                    margin-bottom: 8px;
                    display: block;
                }
                .frs-wizard-container input[type="text"],
                .frs-wizard-container input[type="email"],
                .frs-wizard-container input[type="number"],
                .frs-wizard-container input[type="tel"],
                .frs-wizard-container select,
                .frs-wizard-container textarea {
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid #d1d5db;
                    border-radius: 8px;
                    font-size: 16px;
                    transition: border-color 0.2s, box-shadow 0.2s;
                }
                .frs-wizard-container input:focus,
                .frs-wizard-container select:focus,
                .frs-wizard-container textarea:focus {
                    outline: none;
                    border-color: #0ea5e9;
                    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
                }
                .frs-wizard-container .ff-btn-submit {
                    background: #0ea5e9 !important;
                    color: #fff !important;
                    padding: 14px 32px !important;
                    border-radius: 8px !important;
                    font-weight: 600 !important;
                    font-size: 16px !important;
                    border: none !important;
                    cursor: pointer;
                    transition: background 0.2s;
                }
                .frs-wizard-container .ff-btn-submit:hover {
                    background: #0284c7 !important;
                }
                .frs-wizard-container .ff-step-nav-btns {
                    display: flex;
                    gap: 12px;
                    margin-top: 24px;
                }
                .frs-wizard-container .ff-btn-prev,
                .frs-wizard-container .ff-btn-next {
                    padding: 12px 24px;
                    border-radius: 8px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .frs-wizard-container .ff-btn-next {
                    background: #0ea5e9;
                    color: #fff;
                    border: none;
                }
                .frs-wizard-container .ff-btn-next:hover {
                    background: #0284c7;
                }
                .frs-wizard-container .ff-btn-prev {
                    background: #f3f4f6;
                    color: #374151;
                    border: none;
                }
                .frs-wizard-container .ff-btn-prev:hover {
                    background: #e5e7eb;
                }
            </style>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Render wizard type selector
     */
    private static function render_wizard_type_selector( bool $show_header = true ): string {
        ob_start();

        $types = [
            'open_house' => [
                'title'       => 'Open House',
                'description' => 'Create a landing page for an open house event with property details and lead capture.',
                'icon'        => '🏠',
            ],
            'customer_spotlight' => [
                'title'       => 'Customer Spotlight',
                'description' => 'Feature success stories and target specific buyer types with personalized messaging.',
                'icon'        => '⭐',
            ],
            'special_event' => [
                'title'       => 'Special Event',
                'description' => 'Promote seminars, workshops, and community events with registration forms.',
                'icon'        => '📅',
            ],
            'mortgage_calculator' => [
                'title'       => 'Mortgage Calculator',
                'description' => 'Interactive mortgage calculator with built-in lead capture form.',
                'icon'        => '🧮',
            ],
        ];

        $current_url = home_url( add_query_arg( [], $_SERVER['REQUEST_URI'] ?? '' ) );
        ?>

        <div class="frs-wizard-selector">
            <?php if ( $show_header ) : ?>
                <div class="frs-wizard-selector-header">
                    <h1>Create a Landing Page</h1>
                    <p>Choose the type of page you want to create</p>
                </div>
            <?php endif; ?>

            <div class="frs-wizard-types">
                <?php foreach ( $types as $type => $info ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'type', $type, $current_url ) ); ?>" class="frs-wizard-type-card">
                        <div class="frs-wizard-type-icon"><?php echo $info['icon']; ?></div>
                        <h3><?php echo esc_html( $info['title'] ); ?></h3>
                        <p><?php echo esc_html( $info['description'] ); ?></p>
                        <span class="frs-wizard-type-arrow">→</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .frs-wizard-selector {
                max-width: 1000px;
                margin: 0 auto;
                padding: 40px 20px;
            }
            .frs-wizard-selector-header {
                text-align: center;
                margin-bottom: 40px;
            }
            .frs-wizard-selector-header h1 {
                margin: 0 0 12px;
                font-size: 32px;
                font-weight: 700;
                color: #111827;
            }
            .frs-wizard-selector-header p {
                margin: 0;
                font-size: 18px;
                color: #6b7280;
            }
            .frs-wizard-types {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 24px;
            }
            .frs-wizard-type-card {
                background: #fff;
                border: 2px solid #e5e7eb;
                border-radius: 16px;
                padding: 32px;
                text-decoration: none;
                color: inherit;
                transition: all 0.3s;
                position: relative;
                overflow: hidden;
            }
            .frs-wizard-type-card:hover {
                border-color: #0ea5e9;
                box-shadow: 0 8px 30px rgba(14, 165, 233, 0.15);
                transform: translateY(-4px);
            }
            .frs-wizard-type-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }
            .frs-wizard-type-card h3 {
                margin: 0 0 12px;
                font-size: 22px;
                font-weight: 700;
                color: #111827;
            }
            .frs-wizard-type-card p {
                margin: 0;
                font-size: 15px;
                color: #6b7280;
                line-height: 1.5;
            }
            .frs-wizard-type-arrow {
                position: absolute;
                bottom: 24px;
                right: 24px;
                font-size: 24px;
                color: #0ea5e9;
                opacity: 0;
                transform: translateX(-10px);
                transition: all 0.3s;
            }
            .frs-wizard-type-card:hover .frs-wizard-type-arrow {
                opacity: 1;
                transform: translateX(0);
            }
            @media (max-width: 768px) {
                .frs-wizard-types {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <?php
        return ob_get_clean();
    }

    /**
     * Render wizard header with back button
     */
    private static function render_wizard_header( string $type ): void {
        $type_labels = [
            'open_house'          => 'Open House',
            'customer_spotlight'  => 'Customer Spotlight',
            'special_event'       => 'Special Event',
            'mortgage_calculator' => 'Mortgage Calculator',
        ];

        $label = $type_labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
        $back_url = remove_query_arg( 'type' );
        ?>
        <div class="frs-wizard-header">
            <a href="<?php echo esc_url( $back_url ); ?>" class="frs-wizard-back">← Back</a>
            <h1>Create <?php echo esc_html( $label ); ?> Page</h1>
        </div>
        <style>
            .frs-wizard-header {
                max-width: 800px;
                margin: 0 auto 24px;
                padding: 20px;
            }
            .frs-wizard-back {
                display: inline-block;
                margin-bottom: 12px;
                color: #6b7280;
                text-decoration: none;
                font-weight: 500;
                transition: color 0.2s;
            }
            .frs-wizard-back:hover {
                color: #0ea5e9;
            }
            .frs-wizard-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                color: #111827;
            }
        </style>
        <?php
    }

    /**
     * Render FluentForms required message
     */
    private static function render_fluentforms_required(): string {
        return sprintf(
            '<div class="frs-lead-pages-notice frs-lead-pages-notice--error">
                <h3>%s</h3>
                <p>%s</p>
            </div>',
            esc_html__( 'Plugin Required', 'frs-lead-pages' ),
            esc_html__( 'FluentForms is required for the Generation Station wizard. Please install and activate FluentForms.', 'frs-lead-pages' )
        );
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
            esc_html__( 'Please log in to access the Generation Station wizard.', 'frs-lead-pages' ),
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
        // Check if user is logged in
        if ( ! is_user_logged_in() ) {
            return self::render_login_required();
        }

        $current_user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Determine user role
        $is_realtor = array_intersect( [ 'realtor', 'realtor_partner' ], $user->roles );
        $is_loan_officer = array_intersect( [ 'loan_officer', 'administrator', 'editor' ], $user->roles );
        $is_admin = in_array( 'administrator', $user->roles, true );

        // Query lead pages belonging to current user based on role
        $args = [
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'OR',
            ],
        ];

        // Always check for pages where user is the realtor
        $args['meta_query'][] = [
            'key'   => '_frs_realtor_id',
            'value' => $current_user_id,
        ];

        // Check for pages where user is the loan officer
        if ( $is_loan_officer ) {
            $args['meta_query'][] = [
                'key'   => '_frs_loan_officer_id',
                'value' => $current_user_id,
            ];
        }

        // Also include pages authored by this user (for backwards compatibility)
        $args['meta_query'][] = [
            'relation' => 'AND',
            [
                'key'     => '_frs_realtor_id',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key'     => '_frs_loan_officer_id',
                'compare' => 'NOT EXISTS',
            ],
        ];

        // Add author filter for the "no meta" case
        $author_pages = get_posts([
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'author'         => $current_user_id,
            'fields'         => 'ids',
        ]);

        $query = new \WP_Query( $args );

        // Get leads from FluentForms submissions based on role
        $leads = [];
        if ( \FRSLeadPages\Integrations\FluentForms::is_active() ) {
            // Get submissions for this user (works for both roles)
            $submissions = \FRSLeadPages\Integrations\FluentForms::get_submissions_for_user( $current_user_id );

            foreach ( $submissions as $submission ) {
                $leads[] = [
                    'id'              => $submission['id'],
                    'first_name'      => $submission['first_name'],
                    'last_name'       => $submission['last_name'],
                    'email'           => $submission['email'],
                    'phone'           => $submission['phone'],
                    'source'          => $submission['lead_page_title'] ?? 'Unknown',
                    'property'        => '', // FluentForms doesn't store this directly
                    'status'          => $submission['status'],
                    'created_at'      => $submission['created_at'],
                ];
            }
        }

        // Also get leads from wp_lead_submissions table (frs-lrg plugin)
        $lrg_leads = self::get_lrg_leads( $current_user_id, $is_loan_officer ? 'loan_officer' : 'realtor' );
        $leads = array_merge( $leads, $lrg_leads );

        // Sort all leads by date (newest first)
        usort( $leads, function( $a, $b ) {
            return strtotime( $b['created_at'] ) - strtotime( $a['created_at'] );
        });

        ob_start();
        ?>
        <div class="frs-dashboard">
            <style>
                .frs-dashboard { max-width: 1200px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                .frs-dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
                .frs-dashboard-header h2 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
                .frs-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #0ea5e9 !important; color: #fff !important; text-decoration: none !important; border-radius: 8px; font-weight: 600; font-size: 14px; transition: all 0.2s; border: none; cursor: pointer; }
                .frs-btn:hover { background: #0284c7 !important; color: #fff !important; }
                .frs-btn svg { width: 16px; height: 16px; stroke: #fff !important; }

                /* Dropdown using native details/summary */
                .frs-dropdown { position: relative; display: inline-block; }
                .frs-dropdown summary { list-style: none; cursor: pointer; }
                .frs-dropdown summary::-webkit-details-marker { display: none; }
                .frs-dropdown-arrow { margin-left: 4px; transition: transform 0.2s; }
                .frs-dropdown[open] .frs-dropdown-arrow { transform: rotate(180deg); }
                .frs-dropdown-menu { position: absolute; top: calc(100% + 8px); right: 0; min-width: 280px; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden; animation: frs-dropdown-in 0.15s ease; }
                @keyframes frs-dropdown-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
                .frs-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; text-decoration: none !important; color: #374151 !important; transition: background 0.15s; border: none; background: transparent; width: 100%; text-align: left; cursor: pointer; font-family: inherit; }
                .frs-dropdown-item:hover:not(:disabled) { background: #f3f4f6; }
                .frs-dropdown-item:disabled { opacity: 0.5; cursor: not-allowed; }
                .frs-dropdown-icon { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #f3f4f6; border-radius: 10px; }
                .frs-dropdown-icon svg { width: 20px; height: 20px; stroke: #374151; }
                .frs-dropdown-item:hover .frs-dropdown-icon { background: #e5e7eb; }
                .frs-dropdown-text { display: flex; flex-direction: column; }
                .frs-dropdown-text strong { font-size: 14px; font-weight: 600; color: #111827; }
                .frs-dropdown-text small { font-size: 12px; color: #6b7280; margin-top: 2px; }

                /* Tabs */
                .frs-tabs { display: flex; gap: 4px; background: #f3f4f6; padding: 4px; border-radius: 10px; margin-bottom: 24px; width: fit-content; }
                .frs-tab { padding: 10px 20px; background: transparent; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; color: #6b7280; cursor: pointer; transition: all 0.2s; }
                .frs-tab:hover { color: #374151; }
                .frs-tab.active { background: #fff; color: #0ea5e9; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .frs-tab-count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; background: #e5e7eb; border-radius: 10px; font-size: 12px; margin-left: 6px; }
                .frs-tab.active .frs-tab-count { background: #e0f2fe; color: #0369a1; }
                .frs-tab-panel { display: none; }
                .frs-tab-panel.active { display: block; }

                /* Horizontal Page Cards */
                .frs-pages-list { display: flex; flex-direction: column; gap: 12px; }
                .frs-page-row { display: flex; align-items: center; gap: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; transition: all 0.2s; }
                .frs-page-row:hover { border-color: #0ea5e9; box-shadow: 0 4px 12px rgba(14,165,233,0.1); }
                .frs-page-thumb { width: 100px; height: 70px; border-radius: 8px; object-fit: cover; background: #f3f4f6; flex-shrink: 0; display: block; }
                .frs-page-info { flex: 1; min-width: 0; }
                .frs-page-title { font-size: 16px; font-weight: 600; color: #111827; margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .frs-page-meta { display: flex; align-items: center; gap: 12px; font-size: 13px; color: #6b7280; }
                .frs-page-badge { display: inline-block; padding: 2px 8px; background: #e0f2fe; color: #0369a1; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
                .frs-page-badge.open_house { background: #e0f2fe; color: #0369a1; }
                .frs-page-badge.customer_spotlight { background: #fef3c7; color: #92400e; }
                .frs-page-badge.special_event { background: #fce7f3; color: #831843; }
                .frs-page-stats { display: flex; gap: 24px; flex-shrink: 0; }
                .frs-page-stat { text-align: center; }
                .frs-page-stat-value { font-size: 18px; font-weight: 700; color: #111827; }
                .frs-page-stat-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; }
                .frs-page-actions { display: flex; gap: 8px; flex-shrink: 0; }
                .frs-icon-btn { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; background: #f3f4f6; border: none; border-radius: 8px; color: #6b7280; cursor: pointer; transition: all 0.15s; text-decoration: none; }
                .frs-icon-btn:hover { background: #e5e7eb; color: #111827; }
                .frs-icon-btn.primary { background: #0ea5e9; color: #fff; }
                .frs-icon-btn.primary:hover { background: #0284c7; }
                .frs-icon-btn svg { width: 18px; height: 18px; }

                /* Leads Table */
                .frs-leads-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
                .frs-leads-table th { background: #f9fafb; padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
                .frs-leads-table td { padding: 14px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #374151; }
                .frs-leads-table tr:last-child td { border-bottom: none; }
                .frs-leads-table tr:hover td { background: #f9fafb; }
                .frs-lead-name { font-weight: 600; color: #111827; }
                .frs-lead-contact a { color: #0ea5e9; text-decoration: none; }
                .frs-lead-contact a:hover { text-decoration: underline; }
                .frs-lead-status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
                .frs-lead-status.new { background: #dcfce7; color: #166534; }
                .frs-lead-status.contacted { background: #e0f2fe; color: #0369a1; }
                .frs-lead-status.converted { background: #fef3c7; color: #92400e; }

                /* Lead Actions */
                .frs-lead-actions { display: flex; gap: 8px; }
                .frs-action-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; color: #6b7280; cursor: pointer; transition: all 0.15s; text-decoration: none; }
                .frs-action-btn:hover { border-color: #d1d5db; background: #f9fafb; color: #374151; }
                .frs-action-reply:hover { border-color: #0ea5e9; background: #f0f9ff; color: #0ea5e9; }
                .frs-action-delete:hover { border-color: #ef4444; background: #fef2f2; color: #ef4444; }
                .frs-action-btn svg { width: 16px; height: 16px; }

                /* Empty State */
                .frs-empty { text-align: center; padding: 60px 20px; background: #f9fafb; border-radius: 12px; }
                .frs-empty-icon { width: 64px; height: 64px; margin: 0 auto 16px; background: #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
                .frs-empty-icon svg { width: 32px; height: 32px; color: #9ca3af; }
                .frs-empty h3 { margin: 0 0 8px; font-size: 18px; color: #374151; }
                .frs-empty p { margin: 0 0 20px; color: #6b7280; font-size: 14px; }

                @media (max-width: 768px) {
                    .frs-page-row { flex-wrap: wrap; }
                    .frs-page-thumb { width: 80px; height: 56px; }
                    .frs-page-stats { width: 100%; justify-content: flex-start; gap: 32px; padding-top: 12px; border-top: 1px solid #f3f4f6; margin-top: 12px; }
                    .frs-page-actions { width: 100%; justify-content: flex-end; }
                    .frs-leads-table { display: block; overflow-x: auto; }
                }
            </style>

            <div class="frs-dashboard-header">
                <h2>Lead Pages</h2>
                <details class="frs-dropdown">
                    <summary class="frs-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        Create New Page
                        <svg class="frs-dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M6 9l6 6 6-6"/></svg>
                    </summary>
                    <div class="frs-dropdown-menu">
                        <button type="button" class="frs-dropdown-item oh-wizard-trigger">
                            <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                            <span class="frs-dropdown-text">
                                <strong>Open House</strong>
                                <small>Property showing landing page</small>
                            </span>
                        </button>
                        <button type="button" class="frs-dropdown-item cs-wizard-trigger">
                            <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                            <span class="frs-dropdown-text">
                                <strong>Customer Spotlight</strong>
                                <small>Target specific buyer types</small>
                            </span>
                        </button>
                        <button type="button" class="frs-dropdown-item se-wizard-trigger">
                            <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                            <span class="frs-dropdown-text">
                                <strong>Special Event</strong>
                                <small>Seminars, workshops, webinars</small>
                            </span>
                        </button>
                        <button type="button" class="frs-dropdown-item mc-wizard-trigger">
                            <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/><line x1="8" y1="18" x2="12" y2="18"/></svg></span>
                            <span class="frs-dropdown-text">
                                <strong>Mortgage Calculator</strong>
                                <small>Interactive calculator with leads</small>
                            </span>
                        </button>
                    </div>
                </details>
            </div>

            <div class="frs-tabs">
                <button class="frs-tab active" data-tab="pages">
                    My Pages <span class="frs-tab-count"><?php echo $query->found_posts; ?></span>
                </button>
                <button class="frs-tab" data-tab="leads">
                    My Leads <span class="frs-tab-count"><?php echo count( $leads ); ?></span>
                </button>
            </div>

            <!-- Pages Tab -->
            <div class="frs-tab-panel active" data-panel="pages">
                <?php if ( $query->have_posts() ) : ?>
                    <div class="frs-pages-list">
                        <?php while ( $query->have_posts() ) : $query->the_post();
                            $page_id = get_the_ID();
                            $page_type = get_post_meta( $page_id, '_frs_page_type', true );
                            $hero_image_url = get_post_meta( $page_id, '_frs_hero_image_url', true );
                            $page_views = (int) get_post_meta( $page_id, '_frs_page_views', true );
                            $lead_count = (int) get_post_meta( $page_id, '_frs_lead_count', true );
                            $property_address = get_post_meta( $page_id, '_frs_property_address', true );

                            $type_labels = [
                                'open_house'          => 'Open House',
                                'customer_spotlight'  => 'Spotlight',
                                'special_event'       => 'Event',
                                'mortgage_calculator' => 'Calculator',
                            ];
                            $type_label = $type_labels[ $page_type ] ?? 'Page';
                        ?>
                            <div class="frs-page-row">
                                <?php if ( $hero_image_url ) : ?>
                                    <img src="<?php echo esc_url( $hero_image_url ); ?>" alt="" class="frs-page-thumb">
                                <?php else : ?>
                                    <div class="frs-page-thumb"></div>
                                <?php endif; ?>

                                <div class="frs-page-info">
                                    <h3 class="frs-page-title"><?php echo esc_html( $property_address ?: get_the_title() ); ?></h3>
                                    <div class="frs-page-meta">
                                        <span class="frs-page-badge <?php echo esc_attr( $page_type ); ?>"><?php echo esc_html( $type_label ); ?></span>
                                        <span><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
                                    </div>
                                </div>

                                <div class="frs-page-stats">
                                    <div class="frs-page-stat">
                                        <div class="frs-page-stat-value"><?php echo number_format( $page_views ); ?></div>
                                        <div class="frs-page-stat-label">Views</div>
                                    </div>
                                    <div class="frs-page-stat">
                                        <div class="frs-page-stat-value"><?php echo number_format( $lead_count ); ?></div>
                                        <div class="frs-page-stat-label">Leads</div>
                                    </div>
                                </div>

                                <div class="frs-page-actions">
                                    <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" class="frs-icon-btn primary" target="_blank" title="View Page">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    </a>
                                    <button class="frs-icon-btn" onclick="navigator.clipboard.writeText('<?php echo esc_js( get_permalink( $page_id ) ); ?>'); this.innerHTML='<svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;><polyline points=&quot;20 6 9 17 4 12&quot;/></svg>'; setTimeout(() => this.innerHTML='<svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2&quot;><rect x=&quot;9&quot; y=&quot;9&quot; width=&quot;13&quot; height=&quot;13&quot; rx=&quot;2&quot; ry=&quot;2&quot;/><path d=&quot;M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1&quot;/></svg>', 2000);" title="Copy URL">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    </button>
                                    <button class="frs-icon-btn frs-qr-btn" data-url="<?php echo esc_attr( get_permalink( $page_id ) ); ?>" data-title="<?php echo esc_attr( $property_address ?: get_the_title() ); ?>" title="QR Code">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/><rect x="18" y="14" width="3" height="3"/><rect x="14" y="18" width="3" height="3"/><rect x="18" y="18" width="3" height="3"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                <?php else : ?>
                    <div class="frs-empty">
                        <div class="frs-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h3>No Lead Pages Yet</h3>
                        <p>Create your first lead page to start generating leads!</p>
                        <a href="<?php echo esc_url( home_url( '/generation-station/' ) ); ?>" class="frs-btn">Create Your First Page</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Leads Tab -->
            <div class="frs-tab-panel" data-panel="leads">
                <?php if ( ! empty( $leads ) ) : ?>
                    <table class="frs-leads-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Source</th>
                                <th>Property</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $leads as $lead ) : ?>
                                <tr data-lead-id="<?php echo esc_attr( $lead['id'] ); ?>">
                                    <td><span class="frs-lead-name"><?php echo esc_html( $lead['first_name'] . ' ' . $lead['last_name'] ); ?></span></td>
                                    <td class="frs-lead-contact">
                                        <a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a><br>
                                        <a href="tel:<?php echo esc_attr( $lead['phone'] ); ?>"><?php echo esc_html( $lead['phone'] ); ?></a>
                                    </td>
                                    <td><?php echo esc_html( $lead['source'] ); ?></td>
                                    <td><?php echo esc_html( $lead['property'] ?: '—' ); ?></td>
                                    <td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $lead['created_at'] ) ) ); ?></td>
                                    <td><span class="frs-lead-status <?php echo esc_attr( $lead['status'] ); ?>"><?php echo esc_html( ucfirst( $lead['status'] ?: 'new' ) ); ?></span></td>
                                    <td class="frs-lead-actions">
                                        <a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>?subject=<?php echo esc_attr( urlencode( 'Following up on your inquiry' ) ); ?>" class="frs-action-btn frs-action-reply" title="Reply">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                        </a>
                                        <button type="button" class="frs-action-btn frs-action-delete" data-lead-id="<?php echo esc_attr( $lead['id'] ); ?>" title="Delete">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <div class="frs-empty">
                        <div class="frs-empty-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <h3>No Leads Yet</h3>
                        <p>When visitors submit forms on your lead pages, they'll appear here.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- QR Code Modal -->
            <div class="frs-qr-modal" id="frs-qr-modal">
                <div class="frs-qr-modal-backdrop"></div>
                <div class="frs-qr-modal-content">
                    <button class="frs-qr-modal-close" aria-label="Close">&times;</button>
                    <div class="frs-qr-modal-header">
                        <h3 id="frs-qr-modal-title">QR Code</h3>
                        <p class="frs-qr-modal-subtitle">Scan to visit this page</p>
                    </div>
                    <div class="frs-qr-modal-body">
                        <div class="frs-qr-container" id="frs-qr-container"></div>
                    </div>
                    <div class="frs-qr-modal-footer">
                        <p class="frs-qr-url" id="frs-qr-url"></p>
                        <div class="frs-qr-actions">
                            <button class="frs-btn frs-btn-secondary" id="frs-qr-copy">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                Copy Link
                            </button>
                            <button class="frs-btn" id="frs-qr-download">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Download
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <style>
                /* QR Modal Styles */
                .frs-qr-modal { display: none; position: fixed; inset: 0; z-index: 99999; align-items: center; justify-content: center; }
                .frs-qr-modal.open { display: flex; }
                .frs-qr-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }
                .frs-qr-modal-content { position: relative; background: #fff; border-radius: 20px; padding: 32px; max-width: 400px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: frs-modal-in 0.3s ease; }
                @keyframes frs-modal-in { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
                .frs-qr-modal-close { position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border: none; background: #f3f4f6; border-radius: 50%; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #6b7280; transition: all 0.2s; }
                .frs-qr-modal-close:hover { background: #e5e7eb; color: #111827; }
                .frs-qr-modal-header { text-align: center; margin-bottom: 24px; }
                .frs-qr-modal-header h3 { margin: 0 0 4px; font-size: 20px; font-weight: 700; color: #111827; }
                .frs-qr-modal-subtitle { margin: 0; font-size: 14px; color: #6b7280; }
                .frs-qr-modal-body { display: flex; justify-content: center; margin-bottom: 24px; }
                .frs-qr-container { width: 240px; height: 240px; padding: 16px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #dbeafe 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; }
                .frs-qr-container canvas { border-radius: 8px; }
                .frs-qr-modal-footer { text-align: center; }
                .frs-qr-url { margin: 0 0 16px; font-size: 13px; color: #6b7280; word-break: break-all; padding: 8px 12px; background: #f9fafb; border-radius: 8px; }
                .frs-qr-actions { display: flex; gap: 12px; justify-content: center; }
                .frs-btn-secondary { background: #f3f4f6 !important; color: #374151 !important; }
                .frs-btn-secondary:hover { background: #e5e7eb !important; }
                .frs-btn-secondary svg { stroke: #374151 !important; }
            </style>

            <!-- Load QR Code Styling Library -->
            <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0-rc.1/lib/qr-code-styling.min.js"></script>

            <script>
            (function() {
                // Tab switching
                document.querySelectorAll('.frs-tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        document.querySelectorAll('.frs-tab').forEach(t => t.classList.remove('active'));
                        document.querySelectorAll('.frs-tab-panel').forEach(p => p.classList.remove('active'));
                        tab.classList.add('active');
                        document.querySelector('[data-panel="' + tab.dataset.tab + '"]').classList.add('active');
                    });
                });

                // QR Code Modal
                const modal = document.getElementById('frs-qr-modal');
                const container = document.getElementById('frs-qr-container');
                const titleEl = document.getElementById('frs-qr-modal-title');
                const urlEl = document.getElementById('frs-qr-url');
                const copyBtn = document.getElementById('frs-qr-copy');
                const downloadBtn = document.getElementById('frs-qr-download');
                let currentQR = null;
                let currentUrl = '';

                // Open modal
                document.querySelectorAll('.frs-qr-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const url = btn.getAttribute('data-url');
                        const title = btn.getAttribute('data-title');
                        currentUrl = url;

                        titleEl.textContent = title || 'QR Code';
                        urlEl.textContent = url;

                        // Clear previous QR
                        container.innerHTML = '';

                        // Create styled QR code
                        currentQR = new QRCodeStyling({
                            width: 208,
                            height: 208,
                            type: 'canvas',
                            data: url,
                            dotsOptions: {
                                type: 'extra-rounded',
                                gradient: {
                                    type: 'linear',
                                    rotation: 45,
                                    colorStops: [
                                        { offset: 0, color: '#0ea5e9' },
                                        { offset: 0.5, color: '#06b6d4' },
                                        { offset: 1, color: '#2563eb' }
                                    ]
                                }
                            },
                            cornersSquareOptions: {
                                type: 'extra-rounded',
                                color: '#0369a1'
                            },
                            cornersDotOptions: {
                                type: 'dot',
                                color: '#0284c7'
                            },
                            backgroundOptions: {
                                color: '#ffffff'
                            },
                            imageOptions: {
                                crossOrigin: 'anonymous',
                                margin: 4
                            }
                        });

                        currentQR.append(container);
                        modal.classList.add('open');
                        document.body.style.overflow = 'hidden';
                    });
                });

                // Close modal
                function closeModal() {
                    modal.classList.remove('open');
                    document.body.style.overflow = '';
                }

                modal.querySelector('.frs-qr-modal-backdrop').addEventListener('click', closeModal);
                modal.querySelector('.frs-qr-modal-close').addEventListener('click', closeModal);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
                });

                // Copy link
                copyBtn.addEventListener('click', () => {
                    navigator.clipboard.writeText(currentUrl).then(() => {
                        const originalHTML = copyBtn.innerHTML;
                        copyBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
                        setTimeout(() => { copyBtn.innerHTML = originalHTML; }, 2000);
                    });
                });

                // Download QR
                downloadBtn.addEventListener('click', () => {
                    if (currentQR) {
                        currentQR.download({ name: 'qr-code', extension: 'png' });
                    }
                });

                // Delete lead
                document.querySelectorAll('.frs-action-delete').forEach(btn => {
                    btn.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this lead?')) return;

                        const leadId = this.dataset.leadId;
                        const row = this.closest('tr');
                        const originalHTML = this.innerHTML;

                        // Show loading
                        this.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" class="frs-spinner"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg>';
                        this.disabled = true;

                        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=frs_delete_lead&lead_id=' + leadId + '&nonce=<?php echo wp_create_nonce( 'frs_delete_lead' ); ?>'
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                row.style.transition = 'opacity 0.3s';
                                row.style.opacity = '0';
                                setTimeout(() => row.remove(), 300);
                            } else {
                                alert(data.data || 'Failed to delete lead');
                                this.innerHTML = originalHTML;
                                this.disabled = false;
                            }
                        })
                        .catch(() => {
                            alert('Failed to delete lead');
                            this.innerHTML = originalHTML;
                            this.disabled = false;
                        });
                    });
                });
            })();
            </script>
        </div>
        <?php

        return ob_get_clean();
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
                    'property_address' => '', // FluentForms doesn't store this directly
                    'status'          => $submission['status'],
                    'created_at'      => $submission['created_at'],
                ];
            }
        }

        // Get unique page IDs for filter dropdown
        $pages_for_filter = [];
        foreach ( $page_ids as $page_id ) {
            $pages_for_filter[ $page_id ] = get_the_title( $page_id );
        }

        // Start output buffering
        ob_start();

        ?>
        <div class="frs-submissions-dashboard">
            <style>
                .frs-submissions-dashboard {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .frs-submissions-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 30px;
                    flex-wrap: wrap;
                    gap: 15px;
                }
                .frs-submissions-header h2 {
                    margin: 0;
                    font-size: 28px;
                }
                .frs-submissions-filter {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }
                .frs-submissions-filter label {
                    font-weight: 600;
                    color: #374151;
                }
                .frs-submissions-filter select {
                    padding: 8px 12px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    font-size: 14px;
                    min-width: 200px;
                }
                .frs-submissions-table-wrapper {
                    background: #fff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    overflow: hidden;
                }
                .frs-submissions-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .frs-submissions-table th {
                    background: #f9fafb;
                    padding: 12px 16px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 14px;
                    color: #374151;
                    border-bottom: 1px solid #e5e7eb;
                }
                .frs-submissions-table td {
                    padding: 16px;
                    border-bottom: 1px solid #f3f4f6;
                    color: #111827;
                }
                .frs-submissions-table tr:last-child td {
                    border-bottom: none;
                }
                .frs-submissions-table tr:hover {
                    background: #f9fafb;
                }
                .frs-submissions-mobile {
                    display: none;
                }
                .frs-submission-card {
                    background: #fff;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 16px;
                    margin-bottom: 16px;
                }
                .frs-submission-card-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }
                .frs-submission-card-label {
                    font-weight: 600;
                    color: #6b7280;
                    font-size: 14px;
                }
                .frs-submission-card-value {
                    color: #111827;
                    font-size: 14px;
                }
                .frs-no-submissions {
                    text-align: center;
                    padding: 60px 20px;
                    background: #f9fafb;
                    border-radius: 8px;
                }
                .frs-no-submissions h3 {
                    margin: 0 0 10px;
                    font-size: 20px;
                }
                .frs-no-submissions p {
                    margin: 0;
                    color: #6b7280;
                }
                @media (max-width: 768px) {
                    .frs-submissions-table-wrapper {
                        display: none;
                    }
                    .frs-submissions-mobile {
                        display: block;
                    }
                }
            </style>

            <div class="frs-submissions-header">
                <h2><?php esc_html_e( 'Lead Submissions', 'frs-lead-pages' ); ?></h2>

                <div class="frs-submissions-filter">
                    <label for="frs-page-filter"><?php esc_html_e( 'Filter by Page:', 'frs-lead-pages' ); ?></label>
                    <select id="frs-page-filter" onchange="filterSubmissions(this.value)">
                        <option value=""><?php esc_html_e( 'All Pages', 'frs-lead-pages' ); ?></option>
                        <?php foreach ( $pages_for_filter as $page_id => $page_title ) : ?>
                            <option value="<?php echo esc_attr( $page_id ); ?>">
                                <?php echo esc_html( $page_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ( ! empty( $submissions ) ) : ?>
                <!-- Desktop Table View -->
                <div class="frs-submissions-table-wrapper">
                    <table class="frs-submissions-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Lead Name', 'frs-lead-pages' ); ?></th>
                                <th><?php esc_html_e( 'Email', 'frs-lead-pages' ); ?></th>
                                <th><?php esc_html_e( 'Phone', 'frs-lead-pages' ); ?></th>
                                <th><?php esc_html_e( 'Page Title', 'frs-lead-pages' ); ?></th>
                                <th><?php esc_html_e( 'Date Submitted', 'frs-lead-pages' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $submissions as $submission ) : ?>
                                <tr data-page-id="<?php echo esc_attr( $submission['lead_page_id'] ); ?>">
                                    <td><?php echo esc_html( $submission['first_name'] . ' ' . $submission['last_name'] ); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr( $submission['email'] ); ?>"><?php echo esc_html( $submission['email'] ); ?></a></td>
                                    <td><a href="tel:<?php echo esc_attr( $submission['phone'] ); ?>"><?php echo esc_html( $submission['phone'] ); ?></a></td>
                                    <td><?php echo esc_html( $submission['lead_page_title'] ); ?></td>
                                    <td><?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $submission['created_at'] ) ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="frs-submissions-mobile">
                    <?php foreach ( $submissions as $submission ) : ?>
                        <div class="frs-submission-card" data-page-id="<?php echo esc_attr( $submission['lead_page_id'] ); ?>">
                            <div class="frs-submission-card-row">
                                <span class="frs-submission-card-label"><?php esc_html_e( 'Name:', 'frs-lead-pages' ); ?></span>
                                <span class="frs-submission-card-value"><?php echo esc_html( $submission['first_name'] . ' ' . $submission['last_name'] ); ?></span>
                            </div>
                            <div class="frs-submission-card-row">
                                <span class="frs-submission-card-label"><?php esc_html_e( 'Email:', 'frs-lead-pages' ); ?></span>
                                <span class="frs-submission-card-value"><a href="mailto:<?php echo esc_attr( $submission['email'] ); ?>"><?php echo esc_html( $submission['email'] ); ?></a></span>
                            </div>
                            <div class="frs-submission-card-row">
                                <span class="frs-submission-card-label"><?php esc_html_e( 'Phone:', 'frs-lead-pages' ); ?></span>
                                <span class="frs-submission-card-value"><a href="tel:<?php echo esc_attr( $submission['phone'] ); ?>"><?php echo esc_html( $submission['phone'] ); ?></a></span>
                            </div>
                            <div class="frs-submission-card-row">
                                <span class="frs-submission-card-label"><?php esc_html_e( 'Page:', 'frs-lead-pages' ); ?></span>
                                <span class="frs-submission-card-value"><?php echo esc_html( $submission['lead_page_title'] ); ?></span>
                            </div>
                            <div class="frs-submission-card-row">
                                <span class="frs-submission-card-label"><?php esc_html_e( 'Date:', 'frs-lead-pages' ); ?></span>
                                <span class="frs-submission-card-value"><?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $submission['created_at'] ) ) ); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="frs-no-submissions">
                    <h3><?php esc_html_e( 'No Submissions Yet', 'frs-lead-pages' ); ?></h3>
                    <p><?php esc_html_e( 'When people submit forms on your lead pages, they will appear here.', 'frs-lead-pages' ); ?></p>
                </div>
            <?php endif; ?>

            <script>
                function filterSubmissions(pageId) {
                    const rows = document.querySelectorAll('.frs-submissions-table tbody tr, .frs-submission-card');

                    rows.forEach(row => {
                        if (!pageId || row.getAttribute('data-page-id') === pageId) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
            </script>
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
