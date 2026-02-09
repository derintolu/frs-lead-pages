<?php
/**
 * Lead Submissions Storage
 *
 * Custom table storage for lead page submissions.
 * Replaces FluentForms integration with direct database storage.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class Submissions {

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'frs_lead_submissions';

    /**
     * Initialize
     */
    public static function init(): void {
        // Nothing to gate on — always available.
    }

    /**
     * Get the full table name with prefix
     */
    private static function table(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    // ──────────────────────────────────────────────
    //  Table management
    // ──────────────────────────────────────────────

    /**
     * Create the submissions table via dbDelta
     */
    public static function create_table(): void {
        global $wpdb;

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id bigint(20) unsigned NOT NULL DEFAULT 0,
            page_type varchar(30) NOT NULL DEFAULT '',
            loan_officer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            realtor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            first_name varchar(100) NOT NULL DEFAULT '',
            last_name varchar(100) NOT NULL DEFAULT '',
            email varchar(200) NOT NULL DEFAULT '',
            phone varchar(50) NOT NULL DEFAULT '',
            working_with_agent varchar(20) NOT NULL DEFAULT '',
            pre_approved varchar(20) NOT NULL DEFAULT '',
            interested_in_preapproval varchar(30) NOT NULL DEFAULT '',
            timeframe varchar(50) NOT NULL DEFAULT '',
            comments text NOT NULL,
            responses longtext NOT NULL,
            source_url varchar(500) NOT NULL DEFAULT '',
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            browser varchar(50) NOT NULL DEFAULT '',
            device varchar(20) NOT NULL DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'unread',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY page_id (page_id),
            KEY loan_officer_id (loan_officer_id),
            KEY realtor_id (realtor_id),
            KEY status (status),
            KEY created_at (created_at),
            KEY user_lookup (loan_officer_id, realtor_id, created_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // ──────────────────────────────────────────────
    //  CRUD
    // ──────────────────────────────────────────────

    /**
     * Insert a submission row
     *
     * @param array $data Column => value pairs.
     * @return int|false Inserted row ID or false on failure.
     */
    public static function insert( array $data ) {
        global $wpdb;

        $defaults = [
            'page_id'                   => 0,
            'page_type'                 => '',
            'loan_officer_id'           => 0,
            'realtor_id'                => 0,
            'first_name'                => '',
            'last_name'                 => '',
            'email'                     => '',
            'phone'                     => '',
            'working_with_agent'        => '',
            'pre_approved'              => '',
            'interested_in_preapproval' => '',
            'timeframe'                 => '',
            'comments'                  => '',
            'responses'                 => '',
            'source_url'                => '',
            'user_id'                   => 0,
            'browser'                   => '',
            'device'                    => '',
            'ip'                        => '',
            'status'                    => 'unread',
            'created_at'                => current_time( 'mysql' ),
            'updated_at'                => current_time( 'mysql' ),
        ];

        $data = wp_parse_args( $data, $defaults );

        $result = $wpdb->insert( self::table(), $data );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a single submission by ID
     *
     * @param int $id Submission ID.
     * @return object|null Row object or null.
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", self::table(), $id )
        );
    }

    /**
     * Update a submission
     *
     * @param int   $id   Submission ID.
     * @param array $data Column => value pairs to update.
     * @return bool
     */
    public static function update( int $id, array $data ): bool {
        global $wpdb;

        $data['updated_at'] = current_time( 'mysql' );

        return (bool) $wpdb->update( self::table(), $data, [ 'id' => $id ] );
    }

    /**
     * Delete a submission
     *
     * @param int $id Submission ID.
     * @return bool
     */
    public static function delete( int $id ): bool {
        global $wpdb;

        return (bool) $wpdb->delete( self::table(), [ 'id' => $id ] );
    }

    // ──────────────────────────────────────────────
    //  Full submission flow
    // ──────────────────────────────────────────────

    /**
     * Submit a lead (insert + webhook + FluentCRM tagging)
     *
     * @param array $lead_data Lead data from the REST API.
     * @param int   $page_id   Lead page post ID.
     * @return array Result with success flag and submission_id.
     */
    public static function submit_lead( array $lead_data, int $page_id ): array {
        // Get page meta
        $page_type  = get_post_meta( $page_id, '_frs_page_type', true );
        $lo_id      = (int) get_post_meta( $page_id, '_frs_loan_officer_id', true );
        $realtor_id = (int) get_post_meta( $page_id, '_frs_realtor_id', true );

        // Parse full name
        $name_parts = explode( ' ', $lead_data['fullName'] ?? '', 2 );
        $first_name = sanitize_text_field( $name_parts[0] ?? '' );
        $last_name  = sanitize_text_field( $name_parts[1] ?? '' );

        $email = sanitize_email( $lead_data['email'] ?? '' );
        $phone = sanitize_text_field( $lead_data['phone'] ?? '' );

        $working_with_agent        = $lead_data['workingWithAgent'] === true ? 'yes' : ( $lead_data['workingWithAgent'] === false ? 'no' : '' );
        $pre_approved              = $lead_data['preApproved'] === true ? 'yes' : ( $lead_data['preApproved'] === false ? 'no' : '' );
        $interested_in_preapproval = $lead_data['interestedInPreApproval'] === true ? 'yes' : ( $lead_data['interestedInPreApproval'] === false ? 'no' : '' );
        $timeframe                 = sanitize_text_field( $lead_data['timeframe'] ?? '' );
        $comments                  = sanitize_textarea_field( $lead_data['comments'] ?? '' );

        // Build the form_data array for hooks/webhook (matches old FluentForms structure)
        $form_data = [
            'names' => [
                'first_name' => $first_name,
                'last_name'  => $last_name,
            ],
            'email'                     => $email,
            'phone'                     => $phone,
            'working_with_agent'        => $working_with_agent,
            'pre_approved'              => $pre_approved,
            'interested_in_preapproval' => $interested_in_preapproval,
            'timeframe'                 => $timeframe,
            'comments'                  => $comments,
            'lead_page_id'              => $page_id,
            'page_type'                 => $page_type,
            'loan_officer_id'           => $lo_id,
            'realtor_id'                => $realtor_id,
        ];

        // Insert row
        $submission_id = self::insert( [
            'page_id'                   => $page_id,
            'page_type'                 => $page_type ?: '',
            'loan_officer_id'           => $lo_id,
            'realtor_id'                => $realtor_id,
            'first_name'                => $first_name,
            'last_name'                 => $last_name,
            'email'                     => $email,
            'phone'                     => $phone,
            'working_with_agent'        => $working_with_agent,
            'pre_approved'              => $pre_approved,
            'interested_in_preapproval' => $interested_in_preapproval,
            'timeframe'                 => $timeframe,
            'comments'                  => $comments,
            'responses'                 => wp_json_encode( $form_data, JSON_UNESCAPED_UNICODE ),
            'source_url'                => get_permalink( $page_id ) ?: '',
            'user_id'                   => get_current_user_id(),
            'browser'                   => self::get_browser(),
            'device'                    => self::get_device(),
            'ip'                        => self::get_ip(),
        ] );

        if ( ! $submission_id ) {
            return [
                'success' => false,
                'message' => 'Failed to create submission',
            ];
        }

        // Store reference in post meta
        add_post_meta( $page_id, '_frs_submission_ids', $submission_id );

        // Update lead count
        $lead_count = (int) get_post_meta( $page_id, '_frs_lead_count', true );
        update_post_meta( $page_id, '_frs_lead_count', $lead_count + 1 );

        // Fire custom hook (replaces fluentform/submission_inserted)
        do_action( 'frs_lead_pages/submission_inserted', $submission_id, $form_data, $page_id );

        // Send to n8n webhook
        self::send_to_webhook( $submission_id, $form_data, $page_id );

        // FluentCRM tagging
        self::tag_fluentcrm_contact( $form_data, $page_type );

        return [
            'success'       => true,
            'submission_id' => $submission_id,
        ];
    }

    // ──────────────────────────────────────────────
    //  Query helpers
    // ──────────────────────────────────────────────

    /**
     * Get submissions for a lead page
     *
     * @param int   $page_id Lead page post ID.
     * @param array $args    { per_page, page, status }.
     * @return array { total: int, submissions: array }
     */
    public static function get_submissions_for_page( int $page_id, array $args = [] ): array {
        global $wpdb;

        $table = self::table();

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => 'all',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where  = $wpdb->prepare( "WHERE page_id = %d", $page_id );
        if ( $args['status'] !== 'all' ) {
            $where .= $wpdb->prepare( " AND status = %s", $args['status'] );
        }

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );

        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        $rows   = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
                $args['per_page'],
                $offset
            )
        );

        $submissions = array_map( function( $row ) {
            return [
                'id'         => (int) $row->id,
                'first_name' => $row->first_name,
                'last_name'  => $row->last_name,
                'name'       => trim( $row->first_name . ' ' . $row->last_name ),
                'email'      => $row->email,
                'phone'      => $row->phone,
                'status'     => $row->status,
                'created_at' => $row->created_at,
                'response'   => json_decode( $row->responses, true ) ?: [],
            ];
        }, $rows ?: [] );

        return [
            'total'       => $total,
            'submissions' => $submissions,
        ];
    }

    /**
     * Get submissions for a user (loan officer or realtor)
     *
     * @param int $user_id User ID.
     * @return array Formatted submission rows.
     */
    public static function get_submissions_for_user( int $user_id ): array {
        global $wpdb;

        $table = self::table();

        $cache_key = 'frs_user_submissions_' . $user_id;
        $cached    = wp_cache_get( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE loan_officer_id = %d OR realtor_id = %d
                 ORDER BY id DESC
                 LIMIT 200",
                $user_id,
                $user_id
            )
        );

        if ( ! $rows ) {
            wp_cache_set( $cache_key, [], '', 120 );
            return [];
        }

        // Cache page titles
        $page_title_cache = [];

        $result = array_map( function( $row ) use ( &$page_title_cache ) {
            $pid = (int) $row->page_id;
            if ( $pid && ! isset( $page_title_cache[ $pid ] ) ) {
                $page_title_cache[ $pid ] = get_the_title( $pid );
            }

            return [
                'id'              => (int) $row->id,
                'lead_page_id'    => $pid,
                'lead_page_title' => $page_title_cache[ $pid ] ?? 'Unknown',
                'page_type'       => $row->page_type ?: 'general',
                'first_name'      => $row->first_name,
                'last_name'       => $row->last_name,
                'email'           => $row->email,
                'phone'           => $row->phone,
                'message'         => $row->comments,
                'status'          => $row->status === 'unread' ? 'new' : $row->status,
                'loan_officer_id' => (int) $row->loan_officer_id ?: null,
                'realtor_id'      => (int) $row->realtor_id ?: null,
                'created_at'      => $row->created_at,
                'submission_id'   => (int) $row->id,
            ];
        }, $rows );

        wp_cache_set( $cache_key, $result, '', 120 );

        return $result;
    }

    /**
     * Get submissions with optional filters
     *
     * @param array $filters { loan_officer_id, realtor_id, page_id, status }
     * @return array Formatted submission rows.
     */
    public static function get_submissions( array $filters = [] ): array {
        global $wpdb;

        $table = self::table();
        $where = '1=1';
        $values = [];

        if ( ! empty( $filters['loan_officer_id'] ) ) {
            $where .= ' AND loan_officer_id = %d';
            $values[] = (int) $filters['loan_officer_id'];
        }

        if ( ! empty( $filters['realtor_id'] ) ) {
            $where .= ' AND realtor_id = %d';
            $values[] = (int) $filters['realtor_id'];
        }

        if ( ! empty( $filters['page_id'] ) ) {
            $where .= ' AND page_id = %d';
            $values[] = (int) $filters['page_id'];
        }

        if ( ! empty( $filters['status'] ) ) {
            $where .= ' AND status = %s';
            $values[] = $filters['status'];
        }

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT 100";

        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, ...$values );
        }

        $rows = $wpdb->get_results( $sql );

        if ( ! $rows ) {
            return [];
        }

        return array_map( function( $row ) {
            $pid = (int) $row->page_id;

            return [
                'id'              => (int) $row->id,
                'lead_page_id'    => $pid,
                'lead_page_title' => $pid ? get_the_title( $pid ) : 'Unknown',
                'page_type'       => $row->page_type ?: 'general',
                'first_name'      => $row->first_name,
                'last_name'       => $row->last_name,
                'email'           => $row->email,
                'phone'           => $row->phone,
                'message'         => $row->comments,
                'status'          => $row->status === 'unread' ? 'new' : $row->status,
                'loan_officer_id' => (int) $row->loan_officer_id ?: null,
                'realtor_id'      => (int) $row->realtor_id ?: null,
                'created_at'      => $row->created_at,
                'submission_id'   => (int) $row->id,
            ];
        }, $rows );
    }

    /**
     * Count submissions for a page
     *
     * @param int $page_id Lead page post ID.
     * @return int
     */
    public static function count_for_page( int $page_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE page_id = %d",
                self::table(),
                $page_id
            )
        );
    }

    // ──────────────────────────────────────────────
    //  Webhook
    // ──────────────────────────────────────────────

    /**
     * Send lead data to n8n webhook
     *
     * @param int   $submission_id Submission ID.
     * @param array $form_data     Form data array.
     * @param int   $page_id       Lead page post ID.
     */
    private static function send_to_webhook( int $submission_id, array $form_data, int $page_id ): void {
        $webhook_url = get_option( 'frs_lead_pages_webhook_url', '' );

        if ( empty( $webhook_url ) ) {
            return;
        }

        $webhook_secret = get_option( 'frs_lead_pages_webhook_secret', '' );

        // Get page data
        $page_type  = get_post_meta( $page_id, '_frs_page_type', true );
        $lo_id      = (int) get_post_meta( $page_id, '_frs_loan_officer_id', true );
        $realtor_id = (int) get_post_meta( $page_id, '_frs_realtor_id', true );

        $lo_data      = self::get_loan_officer_data( $lo_id );
        $realtor_data = self::get_realtor_data( $realtor_id );

        $property_data = [
            'address'   => get_post_meta( $page_id, '_frs_property_address', true ),
            'price'     => get_post_meta( $page_id, '_frs_property_price', true ),
            'bedrooms'  => get_post_meta( $page_id, '_frs_property_beds', true ),
            'bathrooms' => get_post_meta( $page_id, '_frs_property_baths', true ),
            'sqft'      => get_post_meta( $page_id, '_frs_property_sqft', true ),
        ];

        $payload = [
            'event'     => 'new_lead',
            'timestamp' => current_time( 'c' ),
            'source'    => 'generation_station',

            'lead' => [
                'first_name' => $form_data['names']['first_name'] ?? '',
                'last_name'  => $form_data['names']['last_name'] ?? '',
                'email'      => $form_data['email'] ?? '',
                'phone'      => $form_data['phone'] ?? '',
                'responses'  => [
                    'working_with_agent'        => $form_data['working_with_agent'] ?? '',
                    'pre_approved'              => $form_data['pre_approved'] ?? '',
                    'interested_in_preapproval' => $form_data['interested_in_preapproval'] ?? '',
                    'timeframe'                 => $form_data['timeframe'] ?? '',
                    'comments'                  => $form_data['comments'] ?? '',
                ],
            ],

            'page' => [
                'id'    => $page_id,
                'type'  => $page_type,
                'url'   => get_permalink( $page_id ),
                'title' => get_the_title( $page_id ),
            ],

            'property'     => $property_data,
            'realtor'      => $realtor_data,
            'loan_officer' => $lo_data,

            'submission' => [
                'id' => $submission_id,
            ],
        ];

        $headers = [ 'Content-Type' => 'application/json' ];
        if ( ! empty( $webhook_secret ) ) {
            $headers['X-Webhook-Secret'] = $webhook_secret;
        }

        $response = wp_remote_post( $webhook_url, [
            'headers' => $headers,
            'body'    => wp_json_encode( $payload ),
            'timeout' => 15,
        ] );

        $log_data = [
            'success'     => ! is_wp_error( $response ),
            'status_code' => is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response ),
            'response'    => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ),
            'timestamp'   => current_time( 'mysql' ),
        ];

        add_post_meta( $page_id, '_frs_webhook_log', $log_data );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
            self::store_failed_webhook( $payload, $submission_id, $log_data );
        }
    }

    /**
     * Store failed webhook for retry
     */
    private static function store_failed_webhook( array $payload, int $submission_id, array $log_data ): void {
        $failed = get_option( 'frs_lead_pages_failed_webhooks', [] );

        $failed[] = [
            'payload'       => $payload,
            'submission_id' => $submission_id,
            'error'         => $log_data,
            'failed_at'     => current_time( 'mysql' ),
            'attempts'      => 1,
        ];

        update_option( 'frs_lead_pages_failed_webhooks', $failed );
    }

    /**
     * Retry failed webhooks
     *
     * @return array { retried: int, success: int }
     */
    public static function retry_failed_webhooks(): array {
        $failed         = get_option( 'frs_lead_pages_failed_webhooks', [] );
        $webhook_url    = get_option( 'frs_lead_pages_webhook_url', '' );
        $webhook_secret = get_option( 'frs_lead_pages_webhook_secret', '' );

        if ( empty( $failed ) || empty( $webhook_url ) ) {
            return [ 'retried' => 0, 'success' => 0 ];
        }

        $results      = [ 'retried' => 0, 'success' => 0 ];
        $still_failed = [];

        foreach ( $failed as $item ) {
            if ( $item['attempts'] >= 5 ) {
                $still_failed[] = $item;
                continue;
            }

            $results['retried']++;

            $headers = [ 'Content-Type' => 'application/json' ];
            if ( ! empty( $webhook_secret ) ) {
                $headers['X-Webhook-Secret'] = $webhook_secret;
            }

            $response = wp_remote_post( $webhook_url, [
                'headers' => $headers,
                'body'    => wp_json_encode( $item['payload'] ),
                'timeout' => 15,
            ] );

            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400 ) {
                $results['success']++;
            } else {
                $item['attempts']++;
                $item['last_attempt'] = current_time( 'mysql' );
                $still_failed[] = $item;
            }
        }

        update_option( 'frs_lead_pages_failed_webhooks', $still_failed );

        return $results;
    }

    // ──────────────────────────────────────────────
    //  FluentCRM tagging
    // ──────────────────────────────────────────────

    /**
     * Tag a FluentCRM contact based on lead responses
     */
    private static function tag_fluentcrm_contact( array $form_data, string $page_type ): void {
        if ( ! function_exists( 'FluentCrmApi' ) ) {
            return;
        }

        $email = $form_data['email'] ?? '';
        if ( empty( $email ) ) {
            return;
        }

        $tags = [];

        // Page type tag
        if ( $page_type ) {
            $tags[] = 'lead-page-' . $page_type;
        }

        // Pre-approval status
        $pre_approved = $form_data['pre_approved'] ?? '';
        $interested   = $form_data['interested_in_preapproval'] ?? '';

        if ( $pre_approved === 'yes' ) {
            $tags[] = 'pre-approved';
        } elseif ( $pre_approved === 'no' && $interested === 'yes' ) {
            $tags[] = 'needs-preapproval';
            $tags[] = 'hot-lead';
        }

        if ( ! empty( $tags ) ) {
            $contact_api = FluentCrmApi( 'contacts' );
            $contact     = $contact_api->getContact( $email );

            if ( $contact ) {
                $contact->attachTags( $tags );
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Helpers (ported from FluentForms.php)
    // ──────────────────────────────────────────────

    /**
     * Get loan officer data for webhook
     */
    private static function get_loan_officer_data( int $lo_id ): array {
        if ( ! $lo_id ) {
            return [];
        }

        $user = get_user_by( 'ID', $lo_id );
        if ( ! $user ) {
            return [];
        }

        return [
            'id'    => $lo_id,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta( $lo_id, 'phone', true ) ?: get_user_meta( $lo_id, 'billing_phone', true ),
            'nmls'  => \FRSLeadPages\frs_get_user_nmls( $lo_id ),
            'photo' => get_avatar_url( $lo_id, [ 'size' => 200 ] ),
        ];
    }

    /**
     * Get realtor data for webhook
     */
    private static function get_realtor_data( int $realtor_id ): array {
        if ( ! $realtor_id ) {
            return [];
        }

        $user = get_user_by( 'ID', $realtor_id );
        if ( ! $user ) {
            return [];
        }

        return [
            'id'    => $realtor_id,
            'name'  => $user->display_name,
            'email' => $user->user_email,
            'phone' => get_user_meta( $realtor_id, 'phone', true ) ?: get_user_meta( $realtor_id, 'billing_phone', true ),
        ];
    }

    /**
     * Get browser from user agent
     */
    private static function get_browser(): string {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ( strpos( $user_agent, 'Chrome' ) !== false ) {
            return 'Chrome';
        } elseif ( strpos( $user_agent, 'Firefox' ) !== false ) {
            return 'Firefox';
        } elseif ( strpos( $user_agent, 'Safari' ) !== false ) {
            return 'Safari';
        } elseif ( strpos( $user_agent, 'Edge' ) !== false ) {
            return 'Edge';
        }

        return 'Other';
    }

    /**
     * Get device type from user agent
     */
    private static function get_device(): string {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if ( preg_match( '/Mobile|Android|iPhone|iPad/i', $user_agent ) ) {
            return 'Mobile';
        }

        return 'Desktop';
    }

    /**
     * Get client IP address
     */
    private static function get_ip(): string {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field( $ip );
    }

    // ──────────────────────────────────────────────
    //  Data migration
    // ──────────────────────────────────────────────

    /**
     * Migrate submissions from FluentForms tables
     *
     * Callable via WP-CLI: wp frs-lead-pages migrate-submissions
     *
     * @param bool $dry_run If true, only report what would be migrated.
     * @return array Migration results.
     */
    public static function migrate_from_fluentforms( bool $dry_run = false ): array {
        global $wpdb;

        $ff_table = $wpdb->prefix . 'fluentform_submissions';

        // Check if FluentForms table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$ff_table}'" ) !== $ff_table ) {
            return [ 'error' => 'FluentForms submissions table not found.' ];
        }

        // Get lead page form IDs from mapping
        $form_mapping = get_option( 'frs_lead_pages_form_mapping', [] );
        $tracked_ids  = get_option( 'frs_lead_pages_form_ids', [] );
        $form_ids     = array_unique( array_merge( array_values( $form_mapping ), $tracked_ids ) );

        if ( empty( $form_ids ) ) {
            // Fallback: find forms with "Lead Page" in title
            $form_ids = $wpdb->get_col(
                "SELECT id FROM {$wpdb->prefix}fluentform_forms WHERE title LIKE '%Lead Page%'"
            );
        }

        if ( empty( $form_ids ) ) {
            return [ 'error' => 'No lead page form IDs found to migrate.' ];
        }

        $placeholders = implode( ',', array_fill( 0, count( $form_ids ), '%d' ) );
        $submissions  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$ff_table} WHERE form_id IN ({$placeholders}) ORDER BY id ASC",
                ...$form_ids
            )
        );

        $migrated  = 0;
        $skipped   = 0;
        $errors    = 0;
        $dest_table = self::table();

        foreach ( $submissions as $sub ) {
            $response = json_decode( $sub->response, true );
            if ( ! $response ) {
                $errors++;
                continue;
            }

            // Parse name (handles both nested and flat)
            $first_name = $response['names']['first_name'] ?? $response['first_name'] ?? '';
            $last_name  = $response['names']['last_name'] ?? $response['last_name'] ?? '';
            $email      = $response['email'] ?? '';
            $page_id    = (int) ( $response['lead_page_id'] ?? 0 );

            // Deduplicate by email + created_at + page_id
            if ( ! $dry_run ) {
                $exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM {$dest_table} WHERE email = %s AND created_at = %s AND page_id = %d LIMIT 1",
                        $email,
                        $sub->created_at,
                        $page_id
                    )
                );

                if ( $exists ) {
                    $skipped++;
                    continue;
                }
            }

            if ( $dry_run ) {
                $migrated++;
                continue;
            }

            $result = self::insert( [
                'page_id'                   => $page_id,
                'page_type'                 => $response['page_type'] ?? '',
                'loan_officer_id'           => (int) ( $response['loan_officer_id'] ?? 0 ),
                'realtor_id'                => (int) ( $response['realtor_id'] ?? 0 ),
                'first_name'                => $first_name,
                'last_name'                 => $last_name,
                'email'                     => $email,
                'phone'                     => $response['phone'] ?? '',
                'working_with_agent'        => $response['working_with_agent'] ?? '',
                'pre_approved'              => $response['pre_approved'] ?? '',
                'interested_in_preapproval' => $response['interested_in_preapproval'] ?? '',
                'timeframe'                 => $response['timeframe'] ?? '',
                'comments'                  => $response['comments'] ?? '',
                'responses'                 => $sub->response,
                'source_url'                => $sub->source_url ?? '',
                'user_id'                   => (int) ( $sub->user_id ?? 0 ),
                'browser'                   => $sub->browser ?? '',
                'device'                    => $sub->device ?? '',
                'ip'                        => $sub->ip ?? '',
                'status'                    => $sub->status ?? 'unread',
                'created_at'                => $sub->created_at,
                'updated_at'                => $sub->updated_at ?? $sub->created_at,
            ] );

            if ( $result ) {
                $migrated++;
            } else {
                $errors++;
            }
        }

        return [
            'total_found' => count( $submissions ),
            'migrated'    => $migrated,
            'skipped'     => $skipped,
            'errors'      => $errors,
            'dry_run'     => $dry_run,
        ];
    }
}
