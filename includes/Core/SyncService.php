<?php
/**
 * Sync Service for Cross-Site Page Synchronization
 *
 * Handles syncing lead pages between partner portals and lender portal.
 * Partner portals push pages to lender portal when created/updated.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class SyncService {

    /**
     * Maximum log entries to keep
     */
    const MAX_LOG_ENTRIES = 100;

    /**
     * Initialize the sync service
     */
    public static function init(): void {
        // Auto-sync on page publish/update (partner portals only)
        add_action( 'save_post_frs_lead_page', [ __CLASS__, 'maybe_sync_on_save' ], 20, 2 );

        // Auto-sync on page delete
        add_action( 'before_delete_post', [ __CLASS__, 'maybe_sync_on_delete' ] );
    }

    /**
     * Check if sync is enabled and this is a partner portal
     */
    public static function is_sync_enabled(): bool {
        $portal_type = get_option( 'frs_portal_type', '' );
        $sync_enabled = get_option( 'frs_sync_enabled', false );

        return $portal_type === 'partner' && $sync_enabled;
    }

    /**
     * Check if this is a lender portal
     */
    public static function is_lender_portal(): bool {
        return get_option( 'frs_portal_type', '' ) === 'lender';
    }

    /**
     * Maybe sync page on save
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public static function maybe_sync_on_save( int $post_id, \WP_Post $post ): void {
        // Skip autosaves, revisions, and non-published posts
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        if ( $post->post_status !== 'publish' ) {
            return;
        }

        // Only sync from partner portals
        if ( ! self::is_sync_enabled() ) {
            return;
        }

        // Queue the sync (don't block the save)
        wp_schedule_single_event( time(), 'frs_sync_page_to_lender', [ $post_id ] );
    }

    /**
     * Maybe sync page deletion
     *
     * @param int $post_id Post ID.
     */
    public static function maybe_sync_on_delete( int $post_id ): void {
        $post = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return;
        }

        if ( ! self::is_sync_enabled() ) {
            return;
        }

        // Get the synced page ID on lender portal
        $synced_id = get_post_meta( $post_id, '_frs_synced_to_id', true );

        if ( $synced_id ) {
            self::sync_delete_to_lender( $post_id, $synced_id );
        }
    }

    /**
     * Push a page to the lender portal
     *
     * @param int $page_id Local page ID to sync.
     * @return array Result with success/error.
     */
    public static function push_to_lender( int $page_id ): array {
        $post = get_post( $page_id );

        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return [
                'success' => false,
                'message' => 'Page not found',
            ];
        }

        $lender_url = get_option( 'frs_lender_site_url', '' );
        $api_key = get_option( 'frs_lender_api_key', '' );

        if ( empty( $lender_url ) || empty( $api_key ) ) {
            return [
                'success' => false,
                'message' => 'Lender portal not configured',
            ];
        }

        // Prepare page data for sync
        $page_data = self::prepare_page_data( $page_id );

        // Send to lender portal
        $response = wp_remote_post(
            trailingslashit( $lender_url ) . 'wp-json/frs-lead-pages/v1/sync/push',
            [
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'X-FRS-API-Key'  => $api_key,
                    'X-FRS-Partner-URL' => home_url(),
                ],
                'body'    => wp_json_encode( $page_data ),
                'timeout' => 30,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $error = $response->get_error_message();
            update_post_meta( $page_id, '_frs_sync_status', 'error' );
            update_post_meta( $page_id, '_frs_sync_error', $error );

            self::log( 'push', $page_id, 'error', $error );

            return [
                'success' => false,
                'message' => $error,
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['success'] ) ) {
            // Update sync status
            update_post_meta( $page_id, '_frs_sync_status', 'synced' );
            update_post_meta( $page_id, '_frs_synced_to_url', $lender_url );
            update_post_meta( $page_id, '_frs_synced_to_id', $body['synced_id'] ?? '' );
            update_post_meta( $page_id, '_frs_last_sync', current_time( 'mysql' ) );
            delete_post_meta( $page_id, '_frs_sync_error' );

            self::log( 'push', $page_id, 'success', 'Synced to lender portal' );

            return [
                'success'   => true,
                'synced_id' => $body['synced_id'] ?? null,
            ];
        }

        $error = $body['message'] ?? 'Unknown error (HTTP ' . $code . ')';
        update_post_meta( $page_id, '_frs_sync_status', 'error' );
        update_post_meta( $page_id, '_frs_sync_error', $error );

        self::log( 'push', $page_id, 'error', $error );

        return [
            'success' => false,
            'message' => $error,
        ];
    }

    /**
     * Sync deletion to lender portal
     *
     * @param int $local_page_id  Local page ID.
     * @param int $remote_page_id Remote page ID on lender portal.
     */
    public static function sync_delete_to_lender( int $local_page_id, int $remote_page_id ): void {
        $lender_url = get_option( 'frs_lender_site_url', '' );
        $api_key = get_option( 'frs_lender_api_key', '' );

        if ( empty( $lender_url ) || empty( $api_key ) ) {
            return;
        }

        // Note: We're sending a sync push with delete flag
        wp_remote_post(
            trailingslashit( $lender_url ) . 'wp-json/frs-lead-pages/v1/sync/push',
            [
                'headers' => [
                    'Content-Type'   => 'application/json',
                    'X-FRS-API-Key'  => $api_key,
                ],
                'body'    => wp_json_encode([
                    'action'     => 'delete',
                    'source_id'  => $local_page_id,
                    'source_url' => home_url(),
                    'remote_id'  => $remote_page_id,
                ]),
                'timeout' => 15,
            ]
        );

        self::log( 'delete', $local_page_id, 'success', 'Deletion synced to lender portal' );
    }

    /**
     * Receive a page from partner portal (lender portal side)
     *
     * @param array $data Page data from partner portal.
     * @return array Result with success/error.
     */
    public static function receive_page( array $data ): array {
        // Handle delete action
        if ( ( $data['action'] ?? '' ) === 'delete' ) {
            return self::handle_delete( $data );
        }

        $source_id = absint( $data['source_id'] );
        $source_url = esc_url_raw( $data['source_url'] );

        // Check if we already have this page synced
        $existing = self::find_synced_page( $source_id, $source_url );

        if ( $existing ) {
            // Update existing page
            return self::update_synced_page( $existing->ID, $data );
        }

        // Create new synced page
        return self::create_synced_page( $data );
    }

    /**
     * Find existing synced page
     *
     * @param int    $source_id  Source page ID.
     * @param string $source_url Source site URL.
     * @return WP_Post|null
     */
    private static function find_synced_page( int $source_id, string $source_url ): ?\WP_Post {
        $query = new \WP_Query([
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_frs_synced_from_id',
                    'value' => $source_id,
                ],
                [
                    'key'   => '_frs_synced_from_url',
                    'value' => $source_url,
                ],
            ],
        ]);

        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Create a new synced page (lender portal side)
     *
     * @param array $data Page data.
     * @return array Result.
     */
    private static function create_synced_page( array $data ): array {
        $post_data = [
            'post_title'   => sanitize_text_field( $data['title'] ?? 'Synced Lead Page' ),
            'post_type'    => 'frs_lead_page',
            'post_status'  => 'publish',
            'post_content' => '',
        ];

        $page_id = wp_insert_post( $post_data );

        if ( is_wp_error( $page_id ) ) {
            self::log( 'receive', null, 'error', $page_id->get_error_message() );

            return [
                'success' => false,
                'message' => $page_id->get_error_message(),
            ];
        }

        // Save sync source info
        update_post_meta( $page_id, '_frs_synced_from_id', absint( $data['source_id'] ) );
        update_post_meta( $page_id, '_frs_synced_from_url', esc_url_raw( $data['source_url'] ) );
        update_post_meta( $page_id, '_frs_is_synced_copy', true );
        update_post_meta( $page_id, '_frs_last_sync_received', current_time( 'mysql' ) );

        // Save all the page meta
        self::save_synced_meta( $page_id, $data );

        self::log( 'receive', $page_id, 'success', 'Page created from partner portal' );

        return [
            'success'   => true,
            'synced_id' => $page_id,
            'url'       => get_permalink( $page_id ),
        ];
    }

    /**
     * Update an existing synced page
     *
     * @param int   $page_id Page ID.
     * @param array $data    Page data.
     * @return array Result.
     */
    private static function update_synced_page( int $page_id, array $data ): array {
        wp_update_post([
            'ID'         => $page_id,
            'post_title' => sanitize_text_field( $data['title'] ?? '' ),
        ]);

        // Update sync timestamp
        update_post_meta( $page_id, '_frs_last_sync_received', current_time( 'mysql' ) );

        // Update all the page meta
        self::save_synced_meta( $page_id, $data );

        self::log( 'receive', $page_id, 'success', 'Page updated from partner portal' );

        return [
            'success'   => true,
            'synced_id' => $page_id,
            'url'       => get_permalink( $page_id ),
        ];
    }

    /**
     * Handle delete sync
     *
     * @param array $data Delete data.
     * @return array Result.
     */
    private static function handle_delete( array $data ): array {
        $remote_id = absint( $data['remote_id'] ?? 0 );

        if ( ! $remote_id ) {
            return [
                'success' => false,
                'message' => 'Remote page ID required for delete',
            ];
        }

        $post = get_post( $remote_id );

        if ( ! $post || $post->post_type !== 'frs_lead_page' ) {
            return [
                'success' => true,
                'message' => 'Page already deleted or not found',
            ];
        }

        // Verify this is a synced page from the source
        $synced_from_id = get_post_meta( $remote_id, '_frs_synced_from_id', true );
        $synced_from_url = get_post_meta( $remote_id, '_frs_synced_from_url', true );

        if ( absint( $synced_from_id ) !== absint( $data['source_id'] ) ) {
            return [
                'success' => false,
                'message' => 'Source ID mismatch',
            ];
        }

        wp_delete_post( $remote_id, true );

        self::log( 'delete-receive', $remote_id, 'success', 'Page deleted per partner portal request' );

        return [
            'success' => true,
            'message' => 'Page deleted',
        ];
    }

    /**
     * Save synced page meta
     *
     * @param int   $page_id Page ID.
     * @param array $data    Page data.
     */
    private static function save_synced_meta( int $page_id, array $data ): void {
        $meta_fields = [
            'page_type'        => '_frs_page_type',
            'loan_officer_id'  => '_frs_loan_officer_id',
            'realtor_id'       => '_frs_realtor_id',
            'realtor_name'     => '_frs_realtor_name',
            'realtor_email'    => '_frs_realtor_email',
            'realtor_phone'    => '_frs_realtor_phone',
            'realtor_company'  => '_frs_realtor_company',
            'property_address' => '_frs_property_address',
            'property_price'   => '_frs_property_price',
            'property_beds'    => '_frs_property_beds',
            'property_baths'   => '_frs_property_baths',
            'property_sqft'    => '_frs_property_sqft',
            'headline'         => '_frs_headline',
            'subheadline'      => '_frs_subheadline',
            'event_name'       => '_frs_event_name',
            'event_date'       => '_frs_event_date',
            'event_time'       => '_frs_event_time',
            'event_venue'      => '_frs_event_venue',
            'customer_name'    => '_frs_customer_name',
            'customer_story'   => '_frs_customer_story',
            'creator_mode'     => '_frs_creator_mode',
        ];

        foreach ( $meta_fields as $data_key => $meta_key ) {
            if ( isset( $data['meta'][ $data_key ] ) ) {
                update_post_meta( $page_id, $meta_key, sanitize_text_field( $data['meta'][ $data_key ] ) );
            }
        }

        // Handle image URLs (download to local media library)
        if ( ! empty( $data['meta']['hero_image_url'] ) ) {
            $local_image = self::sideload_image( $data['meta']['hero_image_url'], $page_id );
            if ( $local_image ) {
                update_post_meta( $page_id, '_frs_hero_image_id', $local_image );
                update_post_meta( $page_id, '_frs_hero_image_url', \FRSLeadPages\frs_get_attachment_url( (int) $local_image ) );
            }
        }
    }

    /**
     * Sideload an image from URL
     *
     * @param string $url     Image URL.
     * @param int    $post_id Post to attach to.
     * @return int|false Attachment ID or false.
     */
    private static function sideload_image( string $url, int $post_id ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );

        return is_wp_error( $attachment_id ) ? false : $attachment_id;
    }

    /**
     * Prepare page data for syncing
     *
     * @param int $page_id Page ID.
     * @return array Page data.
     */
    public static function prepare_page_data( int $page_id ): array {
        $post = get_post( $page_id );
        $meta = get_post_meta( $page_id );

        return [
            'source_id'  => $page_id,
            'source_url' => home_url(),
            'title'      => $post->post_title,
            'slug'       => $post->post_name,
            'url'        => get_permalink( $page_id ),
            'meta'       => [
                'page_type'        => $meta['_frs_page_type'][0] ?? '',
                'loan_officer_id'  => $meta['_frs_loan_officer_id'][0] ?? '',
                'realtor_id'       => $meta['_frs_realtor_id'][0] ?? '',
                'realtor_name'     => $meta['_frs_realtor_name'][0] ?? '',
                'realtor_email'    => $meta['_frs_realtor_email'][0] ?? '',
                'realtor_phone'    => $meta['_frs_realtor_phone'][0] ?? '',
                'realtor_company'  => $meta['_frs_realtor_company'][0] ?? '',
                'property_address' => $meta['_frs_property_address'][0] ?? '',
                'property_price'   => $meta['_frs_property_price'][0] ?? '',
                'property_beds'    => $meta['_frs_property_beds'][0] ?? '',
                'property_baths'   => $meta['_frs_property_baths'][0] ?? '',
                'property_sqft'    => $meta['_frs_property_sqft'][0] ?? '',
                'headline'         => $meta['_frs_headline'][0] ?? '',
                'subheadline'      => $meta['_frs_subheadline'][0] ?? '',
                'event_name'       => $meta['_frs_event_name'][0] ?? '',
                'event_date'       => $meta['_frs_event_date'][0] ?? '',
                'event_time'       => $meta['_frs_event_time'][0] ?? '',
                'event_venue'      => $meta['_frs_event_venue'][0] ?? '',
                'customer_name'    => $meta['_frs_customer_name'][0] ?? '',
                'customer_story'   => $meta['_frs_customer_story'][0] ?? '',
                'hero_image_url'   => $meta['_frs_hero_image_url'][0] ?? '',
                'creator_mode'     => $meta['_frs_creator_mode'][0] ?? '',
            ],
        ];
    }

    /**
     * Log sync activity
     *
     * @param string   $direction   Direction (push, receive, delete, register).
     * @param int|null $page_id     Page ID or null.
     * @param string   $status      Status (success, error).
     * @param string   $message     Message.
     */
    public static function log( string $direction, ?int $page_id, string $status, string $message ): void {
        $log = get_option( 'frs_sync_log', [] );

        $page_title = '';
        if ( $page_id ) {
            $post = get_post( $page_id );
            $page_title = $post ? $post->post_title : 'Page #' . $page_id;
        }

        $log[] = [
            'time'       => current_time( 'mysql' ),
            'direction'  => $direction,
            'page_id'    => $page_id,
            'page_title' => $page_title,
            'status'     => $status,
            'message'    => $message,
        ];

        // Trim to max entries
        if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
            $log = array_slice( $log, -self::MAX_LOG_ENTRIES );
        }

        update_option( 'frs_sync_log', $log );
    }

    /**
     * Register with lender portal
     *
     * @return array Result.
     */
    public static function register_with_lender(): array {
        $lender_url = get_option( 'frs_lender_site_url', '' );
        $api_key = get_option( 'frs_lender_api_key', '' );

        if ( empty( $lender_url ) || empty( $api_key ) ) {
            return [
                'success' => false,
                'message' => 'Lender portal not configured',
            ];
        }

        $response = wp_remote_post(
            trailingslashit( $lender_url ) . 'wp-json/frs-lead-pages/v1/sync/register',
            [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'X-FRS-API-Key' => $api_key,
                ],
                'body'    => wp_json_encode([
                    'site_url'  => home_url(),
                    'site_name' => get_bloginfo( 'name' ),
                ]),
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['success'] ) ) {
            return [
                'success' => true,
                'message' => 'Registered successfully',
            ];
        }

        return [
            'success' => false,
            'message' => $body['message'] ?? 'Registration failed',
        ];
    }

    /**
     * Fetch loan officers from lender portal
     *
     * @return array Loan officers or empty array.
     */
    public static function fetch_loan_officers_from_lender(): array {
        $lender_url = get_option( 'frs_lender_site_url', '' );
        $api_key = get_option( 'frs_lender_api_key', '' );

        if ( empty( $lender_url ) || empty( $api_key ) ) {
            return [];
        }

        // Cache for 1 hour
        $cache_key = 'frs_lender_loan_officers';
        $cached = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        $response = wp_remote_get(
            trailingslashit( $lender_url ) . 'wp-json/frs-lead-pages/v1/sync/loan-officers',
            [
                'headers' => [
                    'X-FRS-API-Key'     => $api_key,
                    'X-FRS-Partner-URL' => home_url(),
                ],
                'timeout' => 15,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 && ! empty( $body['data'] ) ) {
            set_transient( $cache_key, $body['data'], HOUR_IN_SECONDS );
            return $body['data'];
        }

        return [];
    }
}
