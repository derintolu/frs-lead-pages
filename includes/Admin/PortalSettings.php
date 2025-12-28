<?php
/**
 * Portal Settings Admin Page
 *
 * Admin interface for configuring multisite portal settings:
 * - Portal type (lender vs partner)
 * - Lender site URL for sync
 * - API keys for authentication
 * - Assigned LOs (for partner portals)
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Admin;

use FRSLeadPages\Core\PartnerPortal;

class PortalSettings {

    /**
     * Menu slug
     */
    const MENU_SLUG = 'frs-portal-settings';

    /**
     * Option group
     */
    const OPTION_GROUP = 'frs_portal_settings';

    /**
     * Initialize
     */
    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'wp_ajax_frs_test_sync_connection', [ __CLASS__, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_frs_generate_api_key', [ __CLASS__, 'ajax_generate_api_key' ] );
        add_action( 'wp_ajax_frs_clear_sync_log', [ __CLASS__, 'ajax_clear_sync_log' ] );
    }

    /**
     * Add admin menu page
     */
    public static function add_menu_page(): void {
        add_submenu_page(
            'frs-lead-pages',
            'Portal Settings',
            'Portal Settings',
            'manage_options',
            self::MENU_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    /**
     * Register settings
     */
    public static function register_settings(): void {
        // Portal Type
        register_setting( self::OPTION_GROUP, 'frs_portal_type', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Lender Site URL (for partner portals to sync to)
        register_setting( self::OPTION_GROUP, 'frs_lender_site_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ]);

        // API Key for this site (used by other sites to authenticate)
        register_setting( self::OPTION_GROUP, 'frs_sync_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Lender Site API Key (for partner portals to authenticate with lender)
        register_setting( self::OPTION_GROUP, 'frs_lender_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);

        // Assigned Loan Officers (JSON array of user IDs)
        register_setting( self::OPTION_GROUP, 'frs_assigned_loan_officers', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize_assigned_los' ],
            'default'           => [],
        ]);

        // Sync enabled
        register_setting( self::OPTION_GROUP, 'frs_sync_enabled', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
    }

    /**
     * Sanitize assigned LOs array
     */
    public static function sanitize_assigned_los( $value ): array {
        if ( ! is_array( $value ) ) {
            $value = [];
        }
        return array_map( 'absint', array_filter( $value ) );
    }

    /**
     * Render the settings page
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $portal_type = get_option( 'frs_portal_type', '' );
        $lender_url = get_option( 'frs_lender_site_url', '' );
        $api_key = get_option( 'frs_sync_api_key', '' );
        $lender_api_key = get_option( 'frs_lender_api_key', '' );
        $assigned_los = get_option( 'frs_assigned_loan_officers', [] );
        $sync_enabled = get_option( 'frs_sync_enabled', false );

        // Get all loan officers for selection
        $all_los = \FRSLeadPages\Core\LoanOfficers::get_loan_officers();

        ?>
        <div class="wrap">
            <h1>Portal Settings</h1>

            <div class="frs-portal-settings">
                <form method="post" action="options.php">
                    <?php settings_fields( self::OPTION_GROUP ); ?>

                    <!-- Portal Type Section -->
                    <div class="frs-settings-section">
                        <h2>Portal Configuration</h2>
                        <p class="description">Configure this site's role in the multisite network.</p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Portal Type</th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="radio" name="frs_portal_type" value="lender" <?php checked( $portal_type, 'lender' ); ?>>
                                            <strong>Lender Portal</strong>
                                            <p class="description">Main site for loan officers. Receives synced pages from partner portals.</p>
                                        </label>
                                        <br><br>
                                        <label>
                                            <input type="radio" name="frs_portal_type" value="partner" <?php checked( $portal_type, 'partner' ); ?>>
                                            <strong>Partner Portal</strong>
                                            <p class="description">Site for realtors. Syncs pages to the lender portal.</p>
                                        </label>
                                        <br><br>
                                        <label>
                                            <input type="radio" name="frs_portal_type" value="" <?php checked( $portal_type, '' ); ?>>
                                            <strong>Not Configured</strong>
                                            <p class="description">Single site mode (no sync).</p>
                                        </label>
                                    </fieldset>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Sync Configuration -->
                    <div class="frs-settings-section" id="sync-config" style="<?php echo $portal_type ? '' : 'display:none;'; ?>">
                        <h2>Sync Configuration</h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Enable Sync</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="frs_sync_enabled" value="1" <?php checked( $sync_enabled ); ?>>
                                        Enable cross-site page synchronization
                                    </label>
                                    <p class="description">When enabled, pages will be synced between portals.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">This Site's API Key</th>
                                <td>
                                    <input type="text" name="frs_sync_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" readonly>
                                    <button type="button" class="button" id="generate-api-key">Generate New Key</button>
                                    <p class="description">Share this key with other sites that need to sync to this site.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Partner Portal Settings -->
                    <div class="frs-settings-section" id="partner-config" style="<?php echo $portal_type === 'partner' ? '' : 'display:none;'; ?>">
                        <h2>Partner Portal Settings</h2>
                        <p class="description">Configure connection to the lender portal.</p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Lender Portal URL</th>
                                <td>
                                    <input type="url" name="frs_lender_site_url" value="<?php echo esc_url( $lender_url ); ?>" class="regular-text" placeholder="https://lender-portal.example.com">
                                    <button type="button" class="button" id="test-connection">Test Connection</button>
                                    <span id="connection-status"></span>
                                    <p class="description">The URL of the main lender portal site.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Lender Portal API Key</th>
                                <td>
                                    <input type="text" name="frs_lender_api_key" value="<?php echo esc_attr( $lender_api_key ); ?>" class="regular-text" placeholder="Enter the lender portal's API key">
                                    <p class="description">Get this from the lender portal's settings page.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Assigned Loan Officers (Partner Portal Only) -->
                    <div class="frs-settings-section" id="assigned-los" style="<?php echo $portal_type === 'partner' ? '' : 'display:none;'; ?>">
                        <h2>Assigned Loan Officers</h2>
                        <p class="description">Select which loan officers are available to realtors on this partner portal.</p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Available LOs</th>
                                <td>
                                    <div class="frs-lo-selector">
                                        <?php if ( empty( $all_los ) ) : ?>
                                            <p class="description">No loan officers found. Add users with the "loan_officer" role.</p>
                                        <?php else : ?>
                                            <?php foreach ( $all_los as $lo ) : ?>
                                                <label class="frs-lo-item">
                                                    <input type="checkbox" name="frs_assigned_loan_officers[]" value="<?php echo esc_attr( $lo['id'] ); ?>" <?php checked( in_array( $lo['id'], $assigned_los ) ); ?>>
                                                    <img src="<?php echo esc_url( $lo['photo_url'] ?? get_avatar_url( $lo['id'] ) ); ?>" alt="" class="frs-lo-photo">
                                                    <span class="frs-lo-info">
                                                        <strong><?php echo esc_html( $lo['name'] ); ?></strong>
                                                        <?php if ( ! empty( $lo['nmls'] ) ) : ?>
                                                            <span class="frs-lo-nmls">NMLS# <?php echo esc_html( $lo['nmls'] ); ?></span>
                                                        <?php endif; ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description">Only selected LOs will appear in the partner selection dropdown for realtors.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Lender Portal Settings -->
                    <div class="frs-settings-section" id="lender-config" style="<?php echo $portal_type === 'lender' ? '' : 'display:none;'; ?>">
                        <h2>Lender Portal Settings</h2>
                        <p class="description">This site receives synced pages from partner portals.</p>

                        <table class="form-table">
                            <tr>
                                <th scope="row">Registered Partner Portals</th>
                                <td>
                                    <?php
                                    $partner_sites = get_option( 'frs_registered_partner_sites', [] );
                                    if ( empty( $partner_sites ) ) :
                                    ?>
                                        <p class="description">No partner portals registered yet. Partner portals will appear here after they successfully sync.</p>
                                    <?php else : ?>
                                        <table class="widefat striped">
                                            <thead>
                                                <tr>
                                                    <th>Site URL</th>
                                                    <th>Last Sync</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ( $partner_sites as $site ) : ?>
                                                    <tr>
                                                        <td><?php echo esc_url( $site['url'] ); ?></td>
                                                        <td><?php echo esc_html( $site['last_sync'] ?? 'Never' ); ?></td>
                                                        <td><span class="frs-status frs-status--<?php echo esc_attr( $site['status'] ?? 'unknown' ); ?>"><?php echo esc_html( ucfirst( $site['status'] ?? 'Unknown' ) ); ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <?php submit_button( 'Save Settings' ); ?>
                </form>

                <!-- Sync Log Section -->
                <div class="frs-settings-section">
                    <h2>Sync Log</h2>
                    <p class="description">Recent sync activity.</p>

                    <?php
                    $sync_log = get_option( 'frs_sync_log', [] );
                    $sync_log = array_slice( array_reverse( $sync_log ), 0, 20 ); // Last 20 entries
                    ?>

                    <?php if ( empty( $sync_log ) ) : ?>
                        <p>No sync activity yet.</p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Direction</th>
                                    <th>Page</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $sync_log as $entry ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
                                        <td><?php echo esc_html( $entry['direction'] ?? '' ); ?></td>
                                        <td><?php echo esc_html( $entry['page_title'] ?? 'Unknown' ); ?></td>
                                        <td><span class="frs-status frs-status--<?php echo esc_attr( $entry['status'] ?? 'unknown' ); ?>"><?php echo esc_html( ucfirst( $entry['status'] ?? 'Unknown' ) ); ?></span></td>
                                        <td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <p style="margin-top: 10px;">
                        <button type="button" class="button" id="clear-sync-log">Clear Log</button>
                    </p>
                </div>
            </div>
        </div>

        <style>
            .frs-portal-settings { max-width: 800px; }
            .frs-settings-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin: 20px 0;
            }
            .frs-settings-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .frs-lo-selector {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 10px;
                max-height: 400px;
                overflow-y: auto;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #ddd;
            }
            .frs-lo-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
            }
            .frs-lo-item:hover {
                border-color: #2271b1;
            }
            .frs-lo-item input[type="checkbox"] {
                margin: 0;
            }
            .frs-lo-photo {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                object-fit: cover;
            }
            .frs-lo-info {
                display: flex;
                flex-direction: column;
            }
            .frs-lo-nmls {
                font-size: 12px;
                color: #666;
            }
            .frs-status {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
            .frs-status--success { background: #d4edda; color: #155724; }
            .frs-status--error { background: #f8d7da; color: #721c24; }
            .frs-status--pending { background: #fff3cd; color: #856404; }
            .frs-status--unknown { background: #e2e3e5; color: #383d41; }
            .frs-status--active { background: #d4edda; color: #155724; }
            #connection-status {
                margin-left: 10px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Toggle sections based on portal type
            $('input[name="frs_portal_type"]').on('change', function() {
                var type = $(this).val();
                $('#sync-config').toggle(type !== '');
                $('#partner-config').toggle(type === 'partner');
                $('#assigned-los').toggle(type === 'partner');
                $('#lender-config').toggle(type === 'lender');
            });

            // Generate API key
            $('#generate-api-key').on('click', function() {
                $.post(ajaxurl, {
                    action: 'frs_generate_api_key',
                    nonce: '<?php echo wp_create_nonce( 'frs_portal_settings' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $('input[name="frs_sync_api_key"]').val(response.data.key);
                        alert('New API key generated. Remember to save settings!');
                    }
                });
            });

            // Test connection
            $('#test-connection').on('click', function() {
                var $btn = $(this);
                var $status = $('#connection-status');
                var url = $('input[name="frs_lender_site_url"]').val();
                var apiKey = $('input[name="frs_lender_api_key"]').val();

                if (!url) {
                    $status.html('<span style="color:red;">Enter a URL first</span>');
                    return;
                }

                $btn.prop('disabled', true);
                $status.html('<span>Testing...</span>');

                $.post(ajaxurl, {
                    action: 'frs_test_sync_connection',
                    nonce: '<?php echo wp_create_nonce( 'frs_portal_settings' ); ?>',
                    url: url,
                    api_key: apiKey
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $status.html('<span style="color:green;">✓ Connected successfully</span>');
                    } else {
                        $status.html('<span style="color:red;">✗ ' + (response.data || 'Connection failed') + '</span>');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $status.html('<span style="color:red;">✗ Request failed</span>');
                });
            });

            // Clear sync log
            $('#clear-sync-log').on('click', function() {
                if (confirm('Clear all sync log entries?')) {
                    $.post(ajaxurl, {
                        action: 'frs_clear_sync_log',
                        nonce: '<?php echo wp_create_nonce( 'frs_portal_settings' ); ?>'
                    }, function() {
                        location.reload();
                    });
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Test connection to lender portal
     */
    public static function ajax_test_connection(): void {
        check_ajax_referer( 'frs_portal_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $url = esc_url_raw( $_POST['url'] ?? '' );
        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

        if ( empty( $url ) ) {
            wp_send_json_error( 'URL is required' );
        }

        // Try to connect to the lender portal's ping endpoint
        $response = wp_remote_get( trailingslashit( $url ) . 'wp-json/frs-lead-pages/v1/sync/ping', [
            'headers' => [
                'X-FRS-API-Key' => $api_key,
            ],
            'timeout' => 10,
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['success'] ) ) {
            wp_send_json_success( $body );
        } else {
            wp_send_json_error( $body['message'] ?? 'Unknown error (HTTP ' . $code . ')' );
        }
    }

    /**
     * AJAX: Generate new API key
     */
    public static function ajax_generate_api_key(): void {
        check_ajax_referer( 'frs_portal_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $key = 'frs_' . wp_generate_password( 32, false );

        wp_send_json_success( [ 'key' => $key ] );
    }

    /**
     * AJAX: Clear sync log
     */
    public static function ajax_clear_sync_log(): void {
        check_ajax_referer( 'frs_portal_settings', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        delete_option( 'frs_sync_log' );

        wp_send_json_success( 'Sync log cleared' );
    }
}
