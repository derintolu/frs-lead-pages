<?php
/**
 * Plugin Name: FRS Lead Pages (Generation Station)
 * Plugin URI: https://21stcenturylending.com
 * Description: Lead generation landing page builder with multi-step wizard. Create Open House, Customer Spotlight, and Event pages with LO/Realtor co-branding.
 * Version: 1.3.0
 * Author: 21st Century Lending
 * Author URI: https://21stcenturylending.com
 * Text Domain: frs-lead-pages
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'FRS_LEAD_PAGES_VERSION', '1.3.0' );
define( 'FRS_LEAD_PAGES_PLUGIN_FILE', __FILE__ );
define( 'FRS_LEAD_PAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FRS_LEAD_PAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FRS_LEAD_PAGES_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Get user NMLS from the most accurate source
 *
 * Priority:
 * 1. FRS Profiles table (frs-wp-users plugin)
 * 2. Linked person post meta
 * 3. User meta fallback
 *
 * @param int $user_id WordPress user ID.
 * @return string NMLS number or empty string.
 */
function frs_get_user_nmls( int $user_id ): string {
    if ( ! $user_id ) {
        return '';
    }

    // 1. Check FRS Profiles table first (most accurate source)
    if ( class_exists( 'FRSUsers\Models\Profile' ) ) {
        $profile = \FRSUsers\Models\Profile::where( 'user_id', $user_id )->first();
        if ( $profile ) {
            $nmls = $profile->nmls ?: $profile->nmls_number;
            if ( ! empty( $nmls ) ) {
                return (string) $nmls;
            }
        }
    }

    // 2. Check linked person post meta
    $profile_id = get_user_meta( $user_id, 'profile', true );
    if ( $profile_id ) {
        $nmls = get_post_meta( $profile_id, 'nmls', true ) ?: get_post_meta( $profile_id, 'nmls_number', true );
        if ( ! empty( $nmls ) ) {
            return (string) $nmls;
        }
    }

    // 3. Fallback to user meta
    $nmls = get_user_meta( $user_id, 'nmls_id', true ) ?: get_user_meta( $user_id, 'nmls', true );
    return (string) $nmls;
}

// Autoloader
spl_autoload_register( function ( $class ) {
    $prefix = 'FRSLeadPages\\';
    $base_dir = FRS_LEAD_PAGES_PLUGIN_DIR . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
});

/**
 * Initialize the plugin
 */
function init() {
    // FluentForms is required
    if ( ! defined( 'FLUENTFORM' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'FRS Lead Pages requires Fluent Forms to be installed and active.', 'frs-lead-pages' );
            echo '</p></div>';
        });
        return;
    }

    // Initialize capabilities (map_meta_cap filter)
    Core\Capabilities::init();

    // Check if capabilities need updating (plugin update scenario)
    if ( Core\Capabilities::needs_update() ) {
        Core\Capabilities::register();
    }

    // Load core classes
    Core\PostTypes::init();
    Core\Assets::init();
    Core\Shortcodes::init();

    // Initialize integrations
    Integrations\FluentForms::init();

    // Initialize wizards
    OpenHouse\Wizard::init();
    CustomerSpotlight\Wizard::init();
    SpecialEvent\Wizard::init();
    MortgageCalculator\Wizard::init();

    // Initialize admin
    if ( is_admin() ) {
        Admin\Settings::init();
        Admin\Dashboard::init();
        Admin\Submissions::init();
    }

    // Load REST API routes
    add_action( 'rest_api_init', function() {
        Routes\Api::register_routes();
    });

    // Generate QR code on page publish (Open House only)
    add_action( 'save_post_frs_lead_page', __NAMESPACE__ . '\\maybe_generate_qr', 10, 2 );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Generate QR code when Open House page is published
 */
function maybe_generate_qr( $post_id, $post ) {
    if ( $post->post_status !== 'publish' ) {
        return;
    }

    $page_type = get_post_meta( $post_id, '_frs_page_type', true );

    if ( $page_type === 'open_house' ) {
        $existing = Core\QRCode::get( $post_id );
        if ( ! $existing ) {
            Core\QRCode::generate( $post_id );
        }
    }
}

/**
 * Activation hook
 */
function activate() {
    // Register capabilities for roles
    Core\Capabilities::register();

    // Flush rewrite rules for custom post types
    Core\PostTypes::register();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook
 */
function deactivate() {
    // Note: We don't unregister capabilities on deactivation
    // to preserve user access if plugin is temporarily disabled.
    // Capabilities are only removed on uninstall.
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Uninstall hook - registered separately in uninstall.php
 * To fully remove capabilities, create uninstall.php with:
 * Core\Capabilities::unregister();
 */

/**
 * Handle ICS calendar file download for events
 */
function handle_ics_download() {
    if ( empty( $_GET['frs_calendar_event'] ) || empty( $_GET['format'] ) || $_GET['format'] !== 'ics' ) {
        return;
    }

    $post_id = absint( $_GET['frs_calendar_event'] );
    $post = get_post( $post_id );

    if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
        return;
    }

    $page_type = get_post_meta( $post_id, '_frs_page_type', true );
    if ( $page_type !== 'special_event' ) {
        return;
    }

    // Get event data
    $event_name = get_post_meta( $post_id, '_frs_event_name', true );
    $event_date = get_post_meta( $post_id, '_frs_event_date', true );
    $event_time = get_post_meta( $post_id, '_frs_event_time', true );
    $event_end_time = get_post_meta( $post_id, '_frs_event_end_time', true );
    $event_venue = get_post_meta( $post_id, '_frs_event_venue', true );
    $event_description = get_post_meta( $post_id, '_frs_event_description', true );
    $subheadline = get_post_meta( $post_id, '_frs_subheadline', true );

    if ( ! $event_name || ! $event_date ) {
        return;
    }

    // Build date/time strings
    if ( $event_time ) {
        $start_datetime = strtotime( $event_date . ' ' . $event_time );
        $dtstart = gmdate( 'Ymd\THis\Z', $start_datetime );

        if ( $event_end_time ) {
            $end_datetime = strtotime( $event_date . ' ' . $event_end_time );
        } else {
            $end_datetime = $start_datetime + ( 2 * 60 * 60 ); // 2 hours default
        }
        $dtend = gmdate( 'Ymd\THis\Z', $end_datetime );
    } else {
        // All-day event
        $dtstart = date( 'Ymd', strtotime( $event_date ) );
        $dtend = date( 'Ymd', strtotime( $event_date . ' +1 day' ) );
    }

    $description = $event_description ?: $subheadline ?: '';
    $location = $event_venue ?: '';
    $url = get_permalink( $post_id );
    $uid = 'frs-event-' . $post_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

    // Generate ICS content
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//FRS Lead Pages//Event Calendar//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";
    $ics .= "BEGIN:VEVENT\r\n";
    $ics .= "UID:" . esc_ics( $uid ) . "\r\n";
    $ics .= "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";

    if ( $event_time ) {
        $ics .= "DTSTART:" . $dtstart . "\r\n";
        $ics .= "DTEND:" . $dtend . "\r\n";
    } else {
        $ics .= "DTSTART;VALUE=DATE:" . $dtstart . "\r\n";
        $ics .= "DTEND;VALUE=DATE:" . $dtend . "\r\n";
    }

    $ics .= "SUMMARY:" . esc_ics( $event_name ) . "\r\n";

    if ( $description ) {
        $ics .= "DESCRIPTION:" . esc_ics( $description ) . "\r\n";
    }

    if ( $location ) {
        $ics .= "LOCATION:" . esc_ics( $location ) . "\r\n";
    }

    $ics .= "URL:" . esc_ics( $url ) . "\r\n";
    $ics .= "END:VEVENT\r\n";
    $ics .= "END:VCALENDAR\r\n";

    // Send headers and output
    $filename = sanitize_title( $event_name ) . '.ics';

    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $ics ) );
    header( 'Cache-Control: no-cache, must-revalidate' );
    header( 'Expires: 0' );

    echo $ics;
    exit;
}
add_action( 'template_redirect', __NAMESPACE__ . '\\handle_ics_download', 1 );

/**
 * Escape string for ICS format
 */
function esc_ics( $string ) {
    $string = str_replace( [ '\\', ';', ',', "\n", "\r" ], [ '\\\\', '\\;', '\\,', '\\n', '' ], $string );
    return $string;
}

/**
 * Handle vCard file download for LO and Realtor contacts
 */
function handle_vcard_download() {
    if ( empty( $_GET['frs_vcard'] ) || empty( $_GET['type'] ) ) {
        return;
    }

    $user_id = sanitize_text_field( $_GET['frs_vcard'] );
    $type = sanitize_text_field( $_GET['type'] );
    $page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;

    // Get user data based on type
    if ( $user_id === 'manual' && $page_id ) {
        // Manual realtor entry from page meta
        $contact = [
            'first_name' => '',
            'last_name'  => get_post_meta( $page_id, '_frs_realtor_name', true ),
            'name'       => get_post_meta( $page_id, '_frs_realtor_name', true ),
            'email'      => get_post_meta( $page_id, '_frs_realtor_email', true ),
            'phone'      => get_post_meta( $page_id, '_frs_realtor_phone', true ),
            'title'      => 'Sales Associate',
            'company'    => get_post_meta( $page_id, '_frs_realtor_company', true ),
            'license'    => get_post_meta( $page_id, '_frs_realtor_license', true ),
            'photo'      => get_post_meta( $page_id, '_frs_realtor_photo', true ),
        ];
    } else {
        $user_id = absint( $user_id );
        $user = get_user_by( 'ID', $user_id );

        if ( ! $user ) {
            return;
        }

        $contact = [
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'name'       => $user->display_name,
            'email'      => $user->user_email,
            'phone'      => get_user_meta( $user_id, 'phone', true ) ?: get_user_meta( $user_id, 'phone_number', true ) ?: get_user_meta( $user_id, 'mobile_phone', true ),
            'title'      => get_user_meta( $user_id, 'job_title', true ),
            'photo'      => '',
        ];

        if ( $type === 'lo' ) {
            $contact['company'] = '21st Century Lending';
            $contact['title'] = $contact['title'] ?: 'Loan Officer';
            $contact['nmls'] = frs_get_user_nmls( (int) $user_id );
        } else {
            $contact['company'] = get_user_meta( $user_id, 'company', true ) ?: get_user_meta( $user_id, 'brokerage', true );
            $contact['title'] = $contact['title'] ?: 'Sales Associate';
            $contact['license'] = get_user_meta( $user_id, 'license_number', true ) ?: get_user_meta( $user_id, 'dre_license', true );
        }

        // Get photo URL
        $contact['photo'] = get_user_photo( $user_id );
    }

    if ( empty( $contact['name'] ) ) {
        return;
    }

    // Generate vCard content
    $vcard = "BEGIN:VCARD\r\n";
    $vcard .= "VERSION:3.0\r\n";

    // Name
    $fn = $contact['first_name'] && $contact['last_name']
        ? $contact['last_name'] . ';' . $contact['first_name'] . ';;;'
        : ';' . $contact['name'] . ';;;';
    $vcard .= "N:" . esc_vcard( $fn ) . "\r\n";
    $vcard .= "FN:" . esc_vcard( $contact['name'] ) . "\r\n";

    // Organization and Title
    if ( ! empty( $contact['company'] ) ) {
        $vcard .= "ORG:" . esc_vcard( $contact['company'] ) . "\r\n";
    }
    if ( ! empty( $contact['title'] ) ) {
        $title = $contact['title'];
        if ( ! empty( $contact['nmls'] ) ) {
            $title .= ' | NMLS# ' . $contact['nmls'];
        } elseif ( ! empty( $contact['license'] ) ) {
            $title .= ' | DRE# ' . $contact['license'];
        }
        $vcard .= "TITLE:" . esc_vcard( $title ) . "\r\n";
    }

    // Email
    if ( ! empty( $contact['email'] ) ) {
        $vcard .= "EMAIL;TYPE=WORK:" . esc_vcard( $contact['email'] ) . "\r\n";
    }

    // Phone
    if ( ! empty( $contact['phone'] ) ) {
        $vcard .= "TEL;TYPE=CELL:" . esc_vcard( $contact['phone'] ) . "\r\n";
    }

    // Photo (base64 encoded if available)
    if ( ! empty( $contact['photo'] ) && filter_var( $contact['photo'], FILTER_VALIDATE_URL ) ) {
        $photo_data = @file_get_contents( $contact['photo'] );
        if ( $photo_data ) {
            $photo_base64 = base64_encode( $photo_data );
            $photo_type = 'JPEG';
            if ( strpos( $contact['photo'], '.png' ) !== false ) {
                $photo_type = 'PNG';
            }
            $vcard .= "PHOTO;ENCODING=b;TYPE=" . $photo_type . ":" . $photo_base64 . "\r\n";
        }
    }

    // Note with license/NMLS info
    $note_parts = [];
    if ( ! empty( $contact['nmls'] ) ) {
        $note_parts[] = 'NMLS# ' . $contact['nmls'];
    }
    if ( ! empty( $contact['license'] ) ) {
        $note_parts[] = 'DRE# ' . $contact['license'];
    }
    if ( ! empty( $note_parts ) ) {
        $vcard .= "NOTE:" . esc_vcard( implode( ' | ', $note_parts ) ) . "\r\n";
    }

    $vcard .= "END:VCARD\r\n";

    // Send headers and output
    $filename = sanitize_title( $contact['name'] ) . '.vcf';

    header( 'Content-Type: text/vcard; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Content-Length: ' . strlen( $vcard ) );
    header( 'Cache-Control: no-cache, must-revalidate' );
    header( 'Expires: 0' );

    echo $vcard;
    exit;
}
add_action( 'template_redirect', __NAMESPACE__ . '\\handle_vcard_download', 1 );

/**
 * Escape string for vCard format
 */
function esc_vcard( $string ) {
    $string = str_replace( [ '\\', ';', ',', "\n", "\r" ], [ '\\\\', '\\;', '\\,', '\\n', '' ], $string );
    return $string;
}

/**
 * Get user photo from multiple sources
 */
function get_user_photo( $user_id ) {
    if ( ! $user_id ) {
        return '';
    }

    // Check FRS Profiles table (headshot_id)
    if ( class_exists( 'FRSUsers\Models\Profile' ) ) {
        $profile = \FRSUsers\Models\Profile::where( 'user_id', $user_id )->first();
        if ( $profile && ! empty( $profile->headshot_id ) ) {
            $url = wp_get_attachment_url( $profile->headshot_id );
            if ( $url ) {
                return $url;
            }
        }
    }

    // Check user_profile_photo meta (SureDash)
    $suredash_photo = get_user_meta( $user_id, 'user_profile_photo', true );
    if ( ! empty( $suredash_photo ) ) {
        return $suredash_photo;
    }

    // Check Simple Local Avatars
    $simple_avatar = get_user_meta( $user_id, 'simple_local_avatar', true );
    if ( ! empty( $simple_avatar ) && ! empty( $simple_avatar['full'] ) ) {
        return $simple_avatar['full'];
    }

    // Check custom_avatar_url meta
    $custom_avatar = get_user_meta( $user_id, 'custom_avatar_url', true );
    if ( ! empty( $custom_avatar ) ) {
        return $custom_avatar;
    }

    // Check profile_photo meta
    $profile_photo = get_user_meta( $user_id, 'profile_photo', true );
    if ( ! empty( $profile_photo ) ) {
        return $profile_photo;
    }

    return '';
}
