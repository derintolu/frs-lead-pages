<?php
/**
 * Follow Up Boss CRM Integration
 *
 * Sends leads to Follow Up Boss CRM via their Events API.
 * Each loan officer can connect their own FUB account.
 *
 * NOTE: This class now delegates to FRSUsers\Integrations\FollowUpBoss when available.
 * The centralized class stores credentials in wp_frs_profiles for multisite support.
 * Falls back to user_meta for backwards compatibility.
 *
 * @package FRSLeadPages
 * @see https://docs.followupboss.com/reference/events-post
 */

namespace FRSLeadPages\Integrations;

class FollowUpBoss {

    /**
     * API base URL
     */
    const API_URL = 'https://api.followupboss.com/v1';

    /**
     * User meta key for storing API key (legacy - for backwards compatibility)
     */
    const API_KEY_META = '_frs_followupboss_api_key';

    /**
     * User meta key for storing connection status (legacy)
     */
    const STATUS_META = '_frs_followupboss_status';

    /**
     * Source name for FUB campaigns
     */
    const SOURCE_NAME = '21st Century Lending Lead Pages';

    /**
     * Check if centralized FUB class is available
     *
     * @return bool
     */
    private static function has_centralized_class(): bool {
        return class_exists( '\FRSUsers\Integrations\FollowUpBoss' );
    }

    /**
     * Initialize the integration
     */
    public static function init(): void {
        // Hook into lead submission to send leads
        add_action( 'frs_lead_pages/submission_inserted', [ __CLASS__, 'on_form_submission' ], 20, 3 );

        // AJAX handlers for frontend settings (legacy - kept for backwards compatibility)
        add_action( 'wp_ajax_frs_fub_save_api_key', [ __CLASS__, 'ajax_save_api_key' ] );
        add_action( 'wp_ajax_frs_fub_test_connection', [ __CLASS__, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_frs_fub_disconnect', [ __CLASS__, 'ajax_disconnect' ] );
    }

    /**
     * Check if a user has FUB connected
     *
     * @param int $user_id User ID
     * @return bool
     */
    public static function is_connected( int $user_id ): bool {
        // Try centralized class first
        if ( self::has_centralized_class() ) {
            return \FRSUsers\Integrations\FollowUpBoss::is_connected( $user_id );
        }

        // Fallback to legacy user_meta
        $api_key = self::get_api_key( $user_id );
        return ! empty( $api_key );
    }

    /**
     * Get user's API key
     *
     * @param int $user_id User ID
     * @return string
     */
    public static function get_api_key( int $user_id ): string {
        // Try centralized class first
        if ( self::has_centralized_class() ) {
            return \FRSUsers\Integrations\FollowUpBoss::get_api_key( $user_id );
        }

        // Fallback to legacy user_meta
        return get_user_meta( $user_id, self::API_KEY_META, true ) ?: '';
    }

    /**
     * Save user's API key
     *
     * @param int    $user_id User ID
     * @param string $api_key API key
     * @return bool
     */
    public static function save_api_key( int $user_id, string $api_key ): bool {
        // Try centralized class first
        if ( self::has_centralized_class() ) {
            $result = \FRSUsers\Integrations\FollowUpBoss::save_api_key( $user_id, $api_key );
            return $result['success'] ?? false;
        }

        // Fallback to legacy user_meta
        if ( empty( $api_key ) ) {
            delete_user_meta( $user_id, self::API_KEY_META );
            delete_user_meta( $user_id, self::STATUS_META );
            return true;
        }

        // Validate the API key by testing connection
        $test = self::test_connection( $api_key );

        if ( $test['success'] ) {
            update_user_meta( $user_id, self::API_KEY_META, sanitize_text_field( $api_key ) );
            update_user_meta( $user_id, self::STATUS_META, [
                'connected'    => true,
                'account_name' => $test['account_name'] ?? '',
                'connected_at' => current_time( 'mysql' ),
            ] );
            return true;
        }

        return false;
    }

    /**
     * Get connection status
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_status( int $user_id ): array {
        // Try centralized class first
        if ( self::has_centralized_class() ) {
            return \FRSUsers\Integrations\FollowUpBoss::get_status( $user_id );
        }

        // Fallback to legacy user_meta
        $status = get_user_meta( $user_id, self::STATUS_META, true );

        if ( empty( $status ) ) {
            return [
                'connected'    => false,
                'account_name' => '',
                'connected_at' => null,
            ];
        }

        return $status;
    }

    /**
     * Test API connection
     *
     * @param string $api_key API key to test
     * @return array
     */
    public static function test_connection( string $api_key ): array {
        // Try centralized class first
        if ( self::has_centralized_class() ) {
            return \FRSUsers\Integrations\FollowUpBoss::test_connection( $api_key );
        }

        // Fallback to direct API call
        if ( empty( $api_key ) ) {
            return [
                'success' => false,
                'message' => 'API key is required',
            ];
        }

        // Call the /me endpoint to verify credentials
        $response = wp_remote_get( self::API_URL . '/me', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $api_key . ':' ),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 ) {
            return [
                'success'      => true,
                'message'      => 'Connected successfully',
                'account_name' => $body['name'] ?? $body['email'] ?? 'Follow Up Boss Account',
            ];
        }

        if ( $code === 401 ) {
            return [
                'success' => false,
                'message' => 'Invalid API key. Please check your credentials.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Connection failed with status ' . $code,
        ];
    }

    /**
     * Send lead to Follow Up Boss
     *
     * @param array $lead_data Lead data
     * @param int   $lo_user_id Loan officer user ID (who owns the FUB account)
     * @return array Result
     */
    public static function send_lead( array $lead_data, int $lo_user_id ): array {
        // Try centralized class first
        if ( self::has_centralized_class() ) {
            // Add Lead Pages specific source info
            $lead_data['source'] = $lead_data['source'] ?? self::SOURCE_NAME;
            $lead_data['system'] = 'FRS Lead Pages';
            return \FRSUsers\Integrations\FollowUpBoss::send_lead( $lead_data, $lo_user_id );
        }

        // Fallback to legacy implementation
        $api_key = self::get_api_key( $lo_user_id );

        if ( empty( $api_key ) ) {
            return [
                'success' => false,
                'message' => 'Loan officer has no Follow Up Boss connection',
            ];
        }

        // Determine event type based on page type
        $event_type = self::get_event_type( $lead_data['page_type'] ?? '' );

        // Build the event payload
        $payload = [
            'source'  => self::SOURCE_NAME,
            'system'  => 'FRS Lead Pages',
            'type'    => $event_type,
            'message' => self::build_message( $lead_data ),
            'person'  => [
                'firstName' => $lead_data['first_name'] ?? '',
                'lastName'  => $lead_data['last_name'] ?? '',
                'emails'    => [ [ 'value' => $lead_data['email'] ?? '' ] ],
                'phones'    => ! empty( $lead_data['phone'] ) ? [ [ 'value' => $lead_data['phone'] ] ] : [],
                'tags'      => self::get_tags( $lead_data ),
            ],
        ];

        // Add property data if available (for Property Inquiry type)
        if ( ! empty( $lead_data['property_address'] ) ) {
            $payload['property'] = [
                'street' => $lead_data['property_address'],
                'price'  => $lead_data['property_price'] ?? null,
            ];
        }

        // Add source URL
        if ( ! empty( $lead_data['source_url'] ) ) {
            $payload['sourceUrl'] = $lead_data['source_url'];
        }

        // Send to FUB API
        $response = wp_remote_post( self::API_URL . '/events', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $api_key . ':' ),
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        if ( is_wp_error( $response ) ) {
            self::log_error( 'API Error: ' . $response->get_error_message(), $lo_user_id );
            return [
                'success' => false,
                'message' => 'API Error: ' . $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( in_array( $code, [ 200, 201 ], true ) ) {
            self::log_success( $lead_data['email'], $lo_user_id );
            return [
                'success'   => true,
                'message'   => 'Lead sent to Follow Up Boss',
                'person_id' => $body['id'] ?? null,
            ];
        }

        if ( $code === 204 ) {
            // Lead flow archived/ignored
            return [
                'success' => true,
                'message' => 'Lead received but filtered by Follow Up Boss lead flow',
            ];
        }

        $error_msg = $body['message'] ?? 'Unknown error';
        self::log_error( "API returned {$code}: {$error_msg}", $lo_user_id );

        return [
            'success' => false,
            'message' => "Failed to send lead: {$error_msg}",
        ];
    }

    /**
     * Get FUB event type based on page type
     *
     * @param string $page_type Lead page type
     * @return string FUB event type
     */
    private static function get_event_type( string $page_type ): string {
        $type_map = [
            'open_house'          => 'Visited Open House',
            'customer_spotlight'  => 'Property Inquiry',
            'special_event'       => 'Registration',
            'mortgage_calculator' => 'General Inquiry',
            'rate_quote'          => 'General Inquiry',
            'apply_now'           => 'Registration',
        ];

        return $type_map[ $page_type ] ?? 'General Inquiry';
    }

    /**
     * Build message for FUB event
     *
     * @param array $lead_data Lead data
     * @return string
     */
    private static function build_message( array $lead_data ): string {
        $parts = [];

        $page_type = $lead_data['page_type'] ?? 'lead_page';
        $type_labels = [
            'open_house'          => 'Open House',
            'customer_spotlight'  => 'Customer Spotlight',
            'special_event'       => 'Special Event',
            'mortgage_calculator' => 'Mortgage Calculator',
            'rate_quote'          => 'Rate Quote',
            'apply_now'           => 'Apply Now',
        ];

        $parts[] = 'Lead from: ' . ( $type_labels[ $page_type ] ?? 'Lead Page' );

        if ( ! empty( $lead_data['property_address'] ) ) {
            $parts[] = 'Property: ' . $lead_data['property_address'];
        }

        if ( ! empty( $lead_data['comments'] ) ) {
            $parts[] = 'Comments: ' . $lead_data['comments'];
        }

        // Add qualifying info
        if ( ! empty( $lead_data['pre_approved'] ) ) {
            $parts[] = 'Pre-approved: ' . $lead_data['pre_approved'];
        }

        if ( ! empty( $lead_data['timeframe'] ) ) {
            $parts[] = 'Timeframe: ' . $lead_data['timeframe'];
        }

        if ( ! empty( $lead_data['working_with_agent'] ) ) {
            $parts[] = 'Working with agent: ' . $lead_data['working_with_agent'];
        }

        return implode( "\n", $parts );
    }

    /**
     * Get tags for lead
     *
     * @param array $lead_data Lead data
     * @return array
     */
    private static function get_tags( array $lead_data ): array {
        $tags = [ '21CL Lead Pages' ];

        // Add page type tag
        $page_type = $lead_data['page_type'] ?? '';
        if ( $page_type ) {
            $tags[] = str_replace( '_', ' ', ucfirst( $page_type ) );
        }

        // Add qualifying tags
        if ( ( $lead_data['pre_approved'] ?? '' ) === 'no' && ( $lead_data['interested_in_preapproval'] ?? '' ) === 'yes' ) {
            $tags[] = 'Needs Preapproval';
            $tags[] = 'Hot Lead';
        }

        if ( ( $lead_data['timeframe'] ?? '' ) === 'As soon as possible' ) {
            $tags[] = 'Hot Lead';
        }

        return array_unique( $tags );
    }

    /**
     * Hook into lead page submission
     *
     * @param int   $submission_id Submission ID.
     * @param array $form_data     Form data array.
     * @param int   $page_id       Lead page post ID.
     */
    public static function on_form_submission( int $submission_id, array $form_data, $page_id ): void {
        // Only process lead page forms
        if ( empty( $form_data['lead_page_id'] ) && empty( $form_data['loan_officer_id'] ) ) {
            return;
        }

        $page_id = (int) ( $form_data['lead_page_id'] ?? 0 );
        $lo_id = (int) ( $form_data['loan_officer_id'] ?? 0 );

        // Get LO ID from page if not in form data
        if ( ! $lo_id && $page_id ) {
            $lo_id = (int) get_post_meta( $page_id, '_frs_loan_officer_id', true );
        }

        if ( ! $lo_id ) {
            return;
        }

        // Check if LO has FUB connected
        if ( ! self::is_connected( $lo_id ) ) {
            return;
        }

        // Get page data
        $page_type = $page_id ? get_post_meta( $page_id, '_frs_page_type', true ) : '';
        $property_address = $page_id ? get_post_meta( $page_id, '_frs_property_address', true ) : '';
        $property_price = $page_id ? get_post_meta( $page_id, '_frs_property_price', true ) : '';

        // Handle both nested (names.first_name) and flat (first_name) structures
        $first_name = $form_data['names']['first_name'] ?? $form_data['first_name'] ?? '';
        $last_name = $form_data['names']['last_name'] ?? $form_data['last_name'] ?? '';

        // Build lead data
        $lead_data = [
            'first_name'                => $first_name,
            'last_name'                 => $last_name,
            'email'                     => $form_data['email'] ?? '',
            'phone'                     => $form_data['phone'] ?? '',
            'page_type'                 => $page_type,
            'property_address'          => $property_address,
            'property_price'            => $property_price,
            'source_url'                => $page_id ? get_permalink( $page_id ) : '',
            'comments'                  => $form_data['comments'] ?? $form_data['message'] ?? '',
            'pre_approved'              => $form_data['pre_approved'] ?? '',
            'interested_in_preapproval' => $form_data['interested_in_preapproval'] ?? '',
            'working_with_agent'        => $form_data['working_with_agent'] ?? '',
            'timeframe'                 => $form_data['timeframe'] ?? '',
        ];

        // Send to FUB
        $result = self::send_lead( $lead_data, $lo_id );

        // Log result
        if ( $page_id ) {
            add_post_meta( $page_id, '_frs_fub_sync_log', [
                'submission_id' => $submission_id,
                'success'       => $result['success'],
                'message'       => $result['message'],
                'timestamp'     => current_time( 'mysql' ),
            ] );
        }
    }

    /**
     * AJAX: Save API key
     */
    public static function ajax_save_api_key(): void {
        check_ajax_referer( 'frs_fub_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Not authenticated' ] );
        }

        $user_id = get_current_user_id();
        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'API key is required' ] );
        }

        // Test and save
        $test = self::test_connection( $api_key );

        if ( ! $test['success'] ) {
            wp_send_json_error( [ 'message' => $test['message'] ] );
        }

        self::save_api_key( $user_id, $api_key );

        wp_send_json_success( [
            'message'      => 'Connected to Follow Up Boss successfully!',
            'account_name' => $test['account_name'],
        ] );
    }

    /**
     * AJAX: Test connection
     */
    public static function ajax_test_connection(): void {
        check_ajax_referer( 'frs_fub_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Not authenticated' ] );
        }

        $user_id = get_current_user_id();
        $api_key = self::get_api_key( $user_id );

        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'No API key configured' ] );
        }

        $result = self::test_connection( $api_key );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * AJAX: Disconnect
     */
    public static function ajax_disconnect(): void {
        check_ajax_referer( 'frs_fub_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Not authenticated' ] );
        }

        $user_id = get_current_user_id();

        // Try centralized class first (saves empty key to disconnect)
        if ( self::has_centralized_class() ) {
            \FRSUsers\Integrations\FollowUpBoss::save_api_key( $user_id, '' );
        }

        // Also clear legacy user_meta for backwards compatibility
        delete_user_meta( $user_id, self::API_KEY_META );
        delete_user_meta( $user_id, self::STATUS_META );

        wp_send_json_success( [ 'message' => 'Disconnected from Follow Up Boss' ] );
    }

    /**
     * Log successful sync
     *
     * @param string $email Lead email
     * @param int    $user_id LO user ID
     */
    private static function log_success( string $email, int $user_id ): void {
        $log = get_user_meta( $user_id, '_frs_fub_sync_count', true ) ?: 0;
        update_user_meta( $user_id, '_frs_fub_sync_count', $log + 1 );
        update_user_meta( $user_id, '_frs_fub_last_sync', current_time( 'mysql' ) );
    }

    /**
     * Log error
     *
     * @param string $message Error message
     * @param int    $user_id LO user ID
     */
    private static function log_error( string $message, int $user_id ): void {
        $errors = get_user_meta( $user_id, '_frs_fub_errors', true ) ?: [];
        $errors[] = [
            'message'   => $message,
            'timestamp' => current_time( 'mysql' ),
        ];

        // Keep last 20 errors
        $errors = array_slice( $errors, -20 );
        update_user_meta( $user_id, '_frs_fub_errors', $errors );
    }

    /**
     * Get sync stats for user
     *
     * @param int $user_id User ID
     * @return array
     */
    public static function get_stats( int $user_id ): array {
        // Try centralized class first
        if ( self::has_centralized_class() ) {
            return \FRSUsers\Integrations\FollowUpBoss::get_stats( $user_id );
        }

        // Fallback to legacy user_meta
        return [
            'total_synced' => (int) get_user_meta( $user_id, '_frs_fub_sync_count', true ),
            'last_sync'    => get_user_meta( $user_id, '_frs_fub_last_sync', true ) ?: null,
            'errors'       => get_user_meta( $user_id, '_frs_fub_errors', true ) ?: [],
        ];
    }

    /**
     * Get masked API key for display
     *
     * @param int $user_id User ID
     * @return string
     */
    public static function get_masked_api_key( int $user_id ): string {
        $api_key = self::get_api_key( $user_id );

        if ( empty( $api_key ) ) {
            return '';
        }

        // Show first 4 and last 4 characters
        $length = strlen( $api_key );
        if ( $length <= 8 ) {
            return str_repeat( '*', $length );
        }

        return substr( $api_key, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $api_key, -4 );
    }
}
