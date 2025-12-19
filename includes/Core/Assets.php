<?php
/**
 * Asset Management for FRS Lead Pages
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class Assets {

    /**
     * Initialize hooks
     */
    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin' ] );
    }

    /**
     * Check if we're in development mode
     */
    private static function is_dev_mode(): bool {
        // Check if Vite dev server is running
        $dev_server_url = 'http://localhost:5183';

        // Only check in local environment
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return false;
        }

        // Use transient to cache the check
        $cached = get_transient( 'frs_lead_pages_dev_mode' );
        if ( $cached !== false ) {
            return $cached === 'yes';
        }

        $response = @file_get_contents( $dev_server_url . '/@vite/client', false, stream_context_create([
            'http' => [ 'timeout' => 0.5 ]
        ]));

        $is_dev = $response !== false;
        set_transient( 'frs_lead_pages_dev_mode', $is_dev ? 'yes' : 'no', 5 );

        return $is_dev;
    }

    /**
     * Enqueue frontend assets (hook callback)
     */
    public static function enqueue_frontend() {
        // Only load on lead pages or pages with wizard shortcode
        if ( ! self::should_load_assets() ) {
            return;
        }

        self::enqueue_frontend_assets();
    }

    /**
     * Enqueue frontend assets (can be called directly from shortcode)
     */
    public static function enqueue_frontend_assets() {
        // Prevent double-loading
        static $loaded = false;
        if ( $loaded ) {
            return;
        }
        $loaded = true;

        if ( self::is_dev_mode() ) {
            self::enqueue_dev_assets();
        } else {
            self::enqueue_prod_assets();
        }
    }

    /**
     * Check if we should load assets on this page
     */
    private static function should_load_assets(): bool {
        global $post;

        // Lead page post type
        if ( is_singular( 'frs_lead_page' ) ) {
            return true;
        }

        // Check for shortcode in content
        if ( $post && has_shortcode( $post->post_content, 'generation_station' ) ) {
            return true;
        }

        if ( $post && has_shortcode( $post->post_content, 'lead_page_wizard' ) ) {
            return true;
        }

        return false;
    }

    /**
     * Enqueue development assets (Vite HMR)
     */
    private static function enqueue_dev_assets() {
        $dev_server = 'http://localhost:5183';

        // Vite client for HMR
        wp_enqueue_script(
            'frs-lead-pages-vite-client',
            $dev_server . '/@vite/client',
            [],
            null,
            false
        );

        // React refresh runtime
        wp_enqueue_script(
            'frs-lead-pages-react-refresh',
            $dev_server . '/@react-refresh',
            [ 'frs-lead-pages-vite-client' ],
            null,
            false
        );

        // Main entry point
        wp_enqueue_script(
            'frs-lead-pages-main',
            $dev_server . '/src/main.tsx',
            [ 'frs-lead-pages-react-refresh' ],
            null,
            true
        );

        // Add type="module" to scripts
        add_filter( 'script_loader_tag', [ __CLASS__, 'add_module_type' ], 10, 3 );

        // Localize script data
        self::localize_script_data( 'frs-lead-pages-main' );
    }

    /**
     * Enqueue production assets
     */
    private static function enqueue_prod_assets() {
        $dist_path = FRS_LEAD_PAGES_PLUGIN_DIR . 'assets/dist/';
        $dist_url  = FRS_LEAD_PAGES_PLUGIN_URL . 'assets/dist/';

        // Read manifest
        $manifest_path = $dist_path . '.vite/manifest.json';
        if ( ! file_exists( $manifest_path ) ) {
            return;
        }

        $manifest = json_decode( file_get_contents( $manifest_path ), true );
        if ( ! $manifest ) {
            return;
        }

        // Find main entry
        $main_entry = $manifest['src/main.tsx'] ?? null;
        if ( ! $main_entry ) {
            return;
        }

        // Enqueue CSS
        if ( ! empty( $main_entry['css'] ) ) {
            foreach ( $main_entry['css'] as $index => $css_file ) {
                wp_enqueue_style(
                    'frs-lead-pages-style-' . $index,
                    $dist_url . $css_file,
                    [],
                    FRS_LEAD_PAGES_VERSION
                );
            }
        }

        // Enqueue JS
        wp_enqueue_script(
            'frs-lead-pages-main',
            $dist_url . $main_entry['file'],
            [],
            FRS_LEAD_PAGES_VERSION,
            true
        );

        // Add type="module" to scripts
        add_filter( 'script_loader_tag', [ __CLASS__, 'add_module_type' ], 10, 3 );

        // Localize script data
        self::localize_script_data( 'frs-lead-pages-main' );
    }

    /**
     * Localize script data
     */
    private static function localize_script_data( string $handle ) {
        global $post;

        $data = [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'restUrl'     => rest_url( 'frs-lead-pages/v1/' ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'pluginUrl'   => FRS_LEAD_PAGES_PLUGIN_URL,
            'isLoggedIn'  => is_user_logged_in(),
            'currentUser' => self::get_current_user_data(),
            'pageData'    => self::get_page_data( $post ),
        ];

        wp_localize_script( $handle, 'frsLeadPages', $data );
    }

    /**
     * Get current user data for realtors
     */
    private static function get_current_user_data(): ?array {
        if ( ! is_user_logged_in() ) {
            return null;
        }

        $user = wp_get_current_user();

        // Check if user is a realtor
        if ( ! in_array( 'realtor_partner', $user->roles, true ) && ! in_array( 'administrator', $user->roles, true ) ) {
            return null;
        }

        return [
            'id'        => $user->ID,
            'name'      => $user->display_name,
            'firstName' => get_user_meta( $user->ID, 'first_name', true ),
            'lastName'  => get_user_meta( $user->ID, 'last_name', true ),
            'email'     => $user->user_email,
            'phone'     => get_user_meta( $user->ID, 'phone', true ) ?: get_user_meta( $user->ID, 'billing_phone', true ),
            'photo'     => get_avatar_url( $user->ID, [ 'size' => 200 ] ),
            'license'   => get_user_meta( $user->ID, 'realtor_license', true ) ?: get_user_meta( $user->ID, 'dre_license', true ),
        ];
    }

    /**
     * Get lead page data if viewing a lead page
     */
    private static function get_page_data( $post ): ?array {
        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return null;
        }

        $meta = get_post_meta( $post->ID );

        return [
            'id'              => $post->ID,
            'title'           => $post->post_title,
            'url'             => get_permalink( $post->ID ),
            'pageType'        => $meta['_frs_page_type'][0] ?? 'open_house',
            'loanOfficerId'   => (int) ( $meta['_frs_loan_officer_id'][0] ?? 0 ),
            'realtorId'       => (int) ( $meta['_frs_realtor_id'][0] ?? 0 ),
            // Open House
            'propertyAddress' => $meta['_frs_property_address'][0] ?? '',
            'propertyPrice'   => $meta['_frs_property_price'][0] ?? '',
            'propertyBeds'    => $meta['_frs_property_beds'][0] ?? '',
            'propertyBaths'   => $meta['_frs_property_baths'][0] ?? '',
            'propertySqft'    => $meta['_frs_property_sqft'][0] ?? '',
            // Hero
            'heroImageUrl'    => self::get_hero_image_url( $meta ),
            // Content
            'headline'        => $meta['_frs_headline'][0] ?? 'Welcome!',
            'subheadline'     => $meta['_frs_subheadline'][0] ?? '',
            'buttonText'      => $meta['_frs_button_text'][0] ?? 'Sign In',
            'consentText'     => $meta['_frs_consent_text'][0] ?? '',
            // Event
            'eventName'       => $meta['_frs_event_name'][0] ?? '',
            'eventDate'       => $meta['_frs_event_date'][0] ?? '',
            'eventTimeStart'  => $meta['_frs_event_time_start'][0] ?? '',
            'eventTimeEnd'    => $meta['_frs_event_time_end'][0] ?? '',
            'eventVenue'      => $meta['_frs_event_venue'][0] ?? '',
            'eventAddress'    => $meta['_frs_event_address'][0] ?? '',
            // Spotlight
            'spotlightType'   => $meta['_frs_spotlight_type'][0] ?? null,
            // Form
            'formQuestions'   => json_decode( $meta['_frs_form_questions'][0] ?? '{}', true ),
            // Team
            'loanOfficer'     => self::get_loan_officer_data( (int) ( $meta['_frs_loan_officer_id'][0] ?? 0 ) ),
            'realtor'         => self::get_realtor_data( (int) ( $meta['_frs_realtor_id'][0] ?? 0 ) ),
        ];
    }

    /**
     * Get hero image URL
     */
    private static function get_hero_image_url( array $meta ): string {
        // Check for attachment ID first
        $image_id = (int) ( $meta['_frs_hero_image_id'][0] ?? 0 );
        if ( $image_id ) {
            $url = wp_get_attachment_image_url( $image_id, 'full' );
            if ( $url ) {
                return $url;
            }
        }

        // Fall back to external URL
        return $meta['_frs_hero_image_url'][0] ?? '';
    }

    /**
     * Get loan officer data
     */
    private static function get_loan_officer_data( int $user_id ): ?array {
        if ( ! $user_id ) {
            return null;
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return null;
        }

        return [
            'id'    => $user->ID,
            'name'  => $user->display_name,
            'title' => get_user_meta( $user_id, 'job_title', true ) ?: 'Loan Officer',
            'nmls'  => \FRSLeadPages\frs_get_user_nmls( $user_id ),
            'phone' => get_user_meta( $user_id, 'phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true ),
            'email' => $user->user_email,
            'photo' => get_avatar_url( $user_id, [ 'size' => 200 ] ),
        ];
    }

    /**
     * Get realtor data
     */
    private static function get_realtor_data( int $user_id ): ?array {
        if ( ! $user_id ) {
            return null;
        }

        $user = get_user_by( 'ID', $user_id );
        if ( ! $user ) {
            return null;
        }

        return [
            'id'      => $user->ID,
            'name'    => $user->display_name,
            'title'   => 'Realtor®',
            'license' => get_user_meta( $user_id, 'realtor_license', true ) ?: get_user_meta( $user_id, 'dre_license', true ),
            'phone'   => get_user_meta( $user_id, 'phone', true ) ?: get_user_meta( $user_id, 'billing_phone', true ),
            'email'   => $user->user_email,
            'photo'   => get_avatar_url( $user_id, [ 'size' => 200 ] ),
        ];
    }

    /**
     * Add type="module" to script tags
     */
    public static function add_module_type( string $tag, string $handle, string $src ): string {
        if ( strpos( $handle, 'frs-lead-pages' ) === 0 ) {
            $tag = str_replace( '<script ', '<script type="module" crossorigin ', $tag );
        }
        return $tag;
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin( string $hook ) {
        // Only load on lead pages admin
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'frs_lead_page' ) {
            return;
        }

        // Admin styles
        wp_enqueue_style(
            'frs-lead-pages-admin',
            FRS_LEAD_PAGES_PLUGIN_URL . 'assets/css/admin.css',
            [],
            FRS_LEAD_PAGES_VERSION
        );
    }
}
