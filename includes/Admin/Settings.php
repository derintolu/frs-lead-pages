<?php
/**
 * Admin Settings Page for Generation Station
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Admin;

class Settings {

    /**
     * Option group name
     */
    const OPTION_GROUP = 'frs_lead_pages_settings';

    /**
     * Initialize admin settings
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'wp_ajax_frs_lead_pages_refresh_los', [ __CLASS__, 'ajax_refresh_los' ] );
        add_action( 'wp_ajax_frs_lead_pages_retry_webhooks', [ __CLASS__, 'ajax_retry_webhooks' ] );
    }

    /**
     * Add admin menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=frs_lead_page',
            __( 'Generation Station Settings', 'frs-lead-pages' ),
            __( 'Settings', 'frs-lead-pages' ),
            'manage_options',
            'frs-lead-pages-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /**
     * Register settings
     */
    public static function register_settings() {
        // Webhook Settings
        register_setting( self::OPTION_GROUP, 'frs_lead_pages_webhook_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ] );

        register_setting( self::OPTION_GROUP, 'frs_lead_pages_webhook_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        // frs-wp-users API Settings
        register_setting( self::OPTION_GROUP, 'frs_lead_pages_users_api_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ] );

        register_setting( self::OPTION_GROUP, 'frs_lead_pages_users_api_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );

        register_setting( self::OPTION_GROUP, 'frs_lead_pages_lo_cache_duration', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 3600,
        ] );

        // Webhook Section
        add_settings_section(
            'frs_lead_pages_webhook_section',
            __( 'n8n Webhook Settings', 'frs-lead-pages' ),
            [ __CLASS__, 'render_webhook_section' ],
            'frs-lead-pages-settings'
        );

        add_settings_field(
            'frs_lead_pages_webhook_url',
            __( 'Webhook URL', 'frs-lead-pages' ),
            [ __CLASS__, 'render_webhook_url_field' ],
            'frs-lead-pages-settings',
            'frs_lead_pages_webhook_section'
        );

        add_settings_field(
            'frs_lead_pages_webhook_secret',
            __( 'Webhook Secret', 'frs-lead-pages' ),
            [ __CLASS__, 'render_webhook_secret_field' ],
            'frs-lead-pages-settings',
            'frs_lead_pages_webhook_section'
        );

        // API Section
        add_settings_section(
            'frs_lead_pages_api_section',
            __( 'Loan Officer API Settings', 'frs-lead-pages' ),
            [ __CLASS__, 'render_api_section' ],
            'frs-lead-pages-settings'
        );

        add_settings_field(
            'frs_lead_pages_users_api_url',
            __( 'frs-wp-users API URL', 'frs-lead-pages' ),
            [ __CLASS__, 'render_api_url_field' ],
            'frs-lead-pages-settings',
            'frs_lead_pages_api_section'
        );

        add_settings_field(
            'frs_lead_pages_users_api_key',
            __( 'API Key', 'frs-lead-pages' ),
            [ __CLASS__, 'render_api_key_field' ],
            'frs-lead-pages-settings',
            'frs_lead_pages_api_section'
        );

        add_settings_field(
            'frs_lead_pages_lo_cache_duration',
            __( 'Cache Duration', 'frs-lead-pages' ),
            [ __CLASS__, 'render_cache_duration_field' ],
            'frs-lead-pages-settings',
            'frs_lead_pages_api_section'
        );
    }

    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $failed_webhooks = get_option( 'frs_lead_pages_failed_webhooks', [] );
        $failed_count = count( $failed_webhooks );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors( 'frs_lead_pages_messages' ); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( 'frs-lead-pages-settings' );
                submit_button( __( 'Save Settings', 'frs-lead-pages' ) );
                ?>
            </form>

            <hr>

            <!-- Failed Webhooks Section -->
            <h2><?php _e( 'Failed Webhooks', 'frs-lead-pages' ); ?></h2>
            <?php if ( $failed_count > 0 ) : ?>
                <p class="description" style="color: #d63638;">
                    <?php printf( __( '%d webhook(s) failed to send.', 'frs-lead-pages' ), $failed_count ); ?>
                </p>
                <button type="button" class="button" id="frs-retry-webhooks">
                    <?php _e( 'Retry Failed Webhooks', 'frs-lead-pages' ); ?>
                </button>
                <span id="frs-retry-result" style="margin-left: 10px;"></span>
            <?php else : ?>
                <p class="description" style="color: #00a32a;">
                    <?php _e( 'No failed webhooks.', 'frs-lead-pages' ); ?>
                </p>
            <?php endif; ?>

            <hr>

            <!-- LO Cache Section -->
            <h2><?php _e( 'Loan Officer Cache', 'frs-lead-pages' ); ?></h2>
            <?php
            $cached_los = get_transient( 'frs_lead_pages_loan_officers' );
            $lo_count = is_array( $cached_los ) ? count( $cached_los ) : 0;
            ?>
            <p class="description">
                <?php printf( __( 'Currently cached: %d loan officers', 'frs-lead-pages' ), $lo_count ); ?>
            </p>
            <button type="button" class="button" id="frs-refresh-los">
                <?php _e( 'Refresh Loan Officers', 'frs-lead-pages' ); ?>
            </button>
            <span id="frs-refresh-result" style="margin-left: 10px;"></span>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#frs-retry-webhooks').on('click', function() {
                var $btn = $(this);
                var $result = $('#frs-retry-result');
                $btn.prop('disabled', true);
                $result.text('Retrying...');

                $.post(ajaxurl, {
                    action: 'frs_lead_pages_retry_webhooks',
                    _wpnonce: '<?php echo wp_create_nonce( 'frs_lead_pages_retry' ); ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.text('Retried ' + response.data.retried + ', succeeded ' + response.data.success);
                        if (response.data.remaining === 0) {
                            location.reload();
                        }
                    } else {
                        $result.text('Error: ' + response.data);
                    }
                });
            });

            $('#frs-refresh-los').on('click', function() {
                var $btn = $(this);
                var $result = $('#frs-refresh-result');
                $btn.prop('disabled', true);
                $result.text('Refreshing...');

                $.post(ajaxurl, {
                    action: 'frs_lead_pages_refresh_los',
                    _wpnonce: '<?php echo wp_create_nonce( 'frs_lead_pages_refresh' ); ?>'
                }, function(response) {
                    $btn.prop('disabled', false);
                    if (response.success) {
                        $result.text('Loaded ' + response.data.count + ' loan officers');
                    } else {
                        $result.text('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Section callbacks
     */
    public static function render_webhook_section() {
        echo '<p>' . __( 'Configure the n8n webhook for lead routing and automation.', 'frs-lead-pages' ) . '</p>';
    }

    public static function render_api_section() {
        echo '<p>' . __( 'Configure the connection to frs-wp-users for loan officer data.', 'frs-lead-pages' ) . '</p>';
    }

    /**
     * Field callbacks
     */
    public static function render_webhook_url_field() {
        $value = get_option( 'frs_lead_pages_webhook_url', '' );
        ?>
        <input type="url" name="frs_lead_pages_webhook_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="https://n8n.example.com/webhook/..." />
        <p class="description"><?php _e( 'The n8n webhook URL for lead notifications.', 'frs-lead-pages' ); ?></p>
        <?php
    }

    public static function render_webhook_secret_field() {
        $value = get_option( 'frs_lead_pages_webhook_secret', '' );
        ?>
        <input type="password" name="frs_lead_pages_webhook_secret" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php _e( 'Optional secret for webhook verification (sent as X-Webhook-Secret header).', 'frs-lead-pages' ); ?></p>
        <?php
    }

    public static function render_api_url_field() {
        $value = get_option( 'frs_lead_pages_users_api_url', '' );
        ?>
        <input type="url" name="frs_lead_pages_users_api_url" value="<?php echo esc_attr( $value ); ?>" class="regular-text" placeholder="https://example.com" />
        <p class="description"><?php _e( 'Base URL for frs-wp-users API (e.g., https://hub21.local). Leave empty to use local users.', 'frs-lead-pages' ); ?></p>
        <?php
    }

    public static function render_api_key_field() {
        $value = get_option( 'frs_lead_pages_users_api_key', '' );
        ?>
        <input type="password" name="frs_lead_pages_users_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <p class="description"><?php _e( 'API key for frs-wp-users authentication (if required).', 'frs-lead-pages' ); ?></p>
        <?php
    }

    public static function render_cache_duration_field() {
        $value = get_option( 'frs_lead_pages_lo_cache_duration', 3600 );
        ?>
        <select name="frs_lead_pages_lo_cache_duration">
            <option value="1800" <?php selected( $value, 1800 ); ?>><?php _e( '30 minutes', 'frs-lead-pages' ); ?></option>
            <option value="3600" <?php selected( $value, 3600 ); ?>><?php _e( '1 hour', 'frs-lead-pages' ); ?></option>
            <option value="7200" <?php selected( $value, 7200 ); ?>><?php _e( '2 hours', 'frs-lead-pages' ); ?></option>
            <option value="86400" <?php selected( $value, 86400 ); ?>><?php _e( '24 hours', 'frs-lead-pages' ); ?></option>
        </select>
        <p class="description"><?php _e( 'How long to cache loan officer data.', 'frs-lead-pages' ); ?></p>
        <?php
    }

    /**
     * AJAX: Refresh loan officers
     */
    public static function ajax_refresh_los() {
        check_ajax_referer( 'frs_lead_pages_refresh', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        // Clear cache and refresh
        delete_transient( 'frs_lead_pages_loan_officers' );
        $los = \FRSLeadPages\Core\LoanOfficers::get_loan_officers( true );

        wp_send_json_success( [ 'count' => count( $los ) ] );
    }

    /**
     * AJAX: Retry failed webhooks
     */
    public static function ajax_retry_webhooks() {
        check_ajax_referer( 'frs_lead_pages_retry', '_wpnonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $result = \FRSLeadPages\Integrations\FluentForms::retry_failed_webhooks();
        $remaining = count( get_option( 'frs_lead_pages_failed_webhooks', [] ) );

        wp_send_json_success( array_merge( $result, [ 'remaining' => $remaining ] ) );
    }
}
