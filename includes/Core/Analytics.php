<?php
/**
 * Analytics tracking for FRS Lead Pages
 *
 * Tracks page views, QR scans, and submissions per user with deduplication.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

class Analytics {

    /**
     * Database table name (without prefix)
     */
    const TABLE_NAME = 'frs_lead_page_events';

    /**
     * Event types
     */
    const EVENT_VIEW = 'view';
    const EVENT_QR_SCAN = 'qr_scan';
    const EVENT_SUBMISSION = 'submission';

    /**
     * Deduplication window in seconds (24 hours)
     */
    const DEDUP_WINDOW = 86400;

    /**
     * Initialize hooks
     */
    public static function init() {
        // Track views on page load
        add_action( 'template_redirect', [ __CLASS__, 'track_page_view' ], 5 );

        // Track submissions
        add_action( 'frs_lead_pages/submission_inserted', [ __CLASS__, 'track_submission' ], 20, 3 );
    }

    /**
     * Get the full table name with prefix
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the events table
     */
    public static function create_table() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            event_type varchar(20) NOT NULL,
            visitor_hash varchar(32) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY user_id (user_id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY visitor_dedup (page_id, event_type, visitor_hash, created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store the DB version
        update_option( 'frs_lead_pages_analytics_db_version', '1.0' );
    }

    /**
     * Check if table exists
     */
    public static function table_exists(): bool {
        global $wpdb;
        $table_name = self::get_table_name();
        return $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
    }

    /**
     * Generate visitor hash for deduplication
     */
    public static function get_visitor_hash(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return md5( $ip . $ua );
    }

    /**
     * Check if this visitor has already been tracked for this event within the dedup window
     */
    public static function is_duplicate( int $page_id, string $event_type, string $visitor_hash ): bool {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return false;
        }

        $table_name = self::get_table_name();
        $cutoff = gmdate( 'Y-m-d H:i:s', time() - self::DEDUP_WINDOW );

        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE page_id = %d 
             AND event_type = %s 
             AND visitor_hash = %s 
             AND created_at > %s",
            $page_id,
            $event_type,
            $visitor_hash,
            $cutoff
        ) );

        return (int) $count > 0;
    }

    /**
     * Record an event
     */
    public static function record_event( int $page_id, string $event_type, bool $deduplicate = true ): bool {
        global $wpdb;

        if ( ! self::table_exists() ) {
            self::create_table();
        }

        // Get page owner (user_id)
        $user_id = 0;
        $lo_id = get_post_meta( $page_id, '_frs_loan_officer_id', true );
        $realtor_id = get_post_meta( $page_id, '_frs_realtor_id', true );
        
        // Use realtor as primary owner, fallback to LO, then post author
        if ( $realtor_id ) {
            $user_id = (int) $realtor_id;
        } elseif ( $lo_id ) {
            $user_id = (int) $lo_id;
        } else {
            $post = get_post( $page_id );
            if ( $post ) {
                $user_id = (int) $post->post_author;
            }
        }

        $visitor_hash = self::get_visitor_hash();

        // Check for duplicate if deduplication is enabled
        if ( $deduplicate && self::is_duplicate( $page_id, $event_type, $visitor_hash ) ) {
            return false;
        }

        $table_name = self::get_table_name();

        $result = $wpdb->insert(
            $table_name,
            [
                'page_id'      => $page_id,
                'user_id'      => $user_id,
                'event_type'   => $event_type,
                'visitor_hash' => $visitor_hash,
                'created_at'   => current_time( 'mysql', true ),
            ],
            [ '%d', '%d', '%s', '%s', '%s' ]
        );

        return $result !== false;
    }

    /**
     * Track page view (called on template_redirect)
     */
    public static function track_page_view() {
        if ( ! is_singular( 'frs_lead_page' ) ) {
            return;
        }

        $page_id = get_the_ID();
        if ( ! $page_id ) {
            return;
        }

        // Check for QR scan parameter
        $is_qr_scan = isset( $_GET['scan'] ) && $_GET['scan'] === '1';

        if ( $is_qr_scan ) {
            self::record_event( $page_id, self::EVENT_QR_SCAN );
        } else {
            self::record_event( $page_id, self::EVENT_VIEW );
        }
    }

    /**
     * Track form submission
     *
     * @param int   $submission_id Submission row ID.
     * @param array $form_data     Form data array.
     * @param int   $page_id       Lead page post ID.
     */
    public static function track_submission( $submission_id, $form_data, $page_id ) {
        $page_id = absint( $page_id );

        // Fallback: try form_data
        if ( ! $page_id && isset( $form_data['lead_page_id'] ) ) {
            $page_id = absint( $form_data['lead_page_id'] );
        }

        if ( $page_id && get_post_type( $page_id ) === 'frs_lead_page' ) {
            // Submissions should not be deduplicated
            self::record_event( $page_id, self::EVENT_SUBMISSION, false );
        }
    }

    /**
     * Get stats for a specific user
     *
     * @param int    $user_id  User ID
     * @param string $period   'all', 'week', 'month', '30days'
     * @return array Stats array
     */
    public static function get_user_stats( int $user_id, string $period = 'all' ): array {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return self::empty_stats();
        }

        $table_name = self::get_table_name();
        $date_condition = self::get_date_condition( $period );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$table_name} 
             WHERE user_id = %d {$date_condition}
             GROUP BY event_type",
            $user_id
        ), ARRAY_A );

        $stats = [
            'views'       => 0,
            'qr_scans'    => 0,
            'submissions' => 0,
        ];

        foreach ( $results as $row ) {
            switch ( $row['event_type'] ) {
                case self::EVENT_VIEW:
                    $stats['views'] = (int) $row['count'];
                    break;
                case self::EVENT_QR_SCAN:
                    $stats['qr_scans'] = (int) $row['count'];
                    break;
                case self::EVENT_SUBMISSION:
                    $stats['submissions'] = (int) $row['count'];
                    break;
            }
        }

        // Calculate conversion rate
        $total_traffic = $stats['views'] + $stats['qr_scans'];
        $stats['conversion_rate'] = $total_traffic > 0 
            ? round( ( $stats['submissions'] / $total_traffic ) * 100, 1 ) 
            : 0;

        return $stats;
    }

    /**
     * Get stats for a specific page
     *
     * @param int    $page_id  Page ID
     * @param string $period   'all', 'week', 'month', '30days'
     * @return array Stats array
     */
    public static function get_page_stats( int $page_id, string $period = 'all' ): array {
        global $wpdb;

        if ( ! self::table_exists() ) {
            return self::empty_stats();
        }

        $table_name = self::get_table_name();
        $date_condition = self::get_date_condition( $period );

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT event_type, COUNT(*) as count 
             FROM {$table_name} 
             WHERE page_id = %d {$date_condition}
             GROUP BY event_type",
            $page_id
        ), ARRAY_A );

        $stats = [
            'views'       => 0,
            'qr_scans'    => 0,
            'submissions' => 0,
        ];

        foreach ( $results as $row ) {
            switch ( $row['event_type'] ) {
                case self::EVENT_VIEW:
                    $stats['views'] = (int) $row['count'];
                    break;
                case self::EVENT_QR_SCAN:
                    $stats['qr_scans'] = (int) $row['count'];
                    break;
                case self::EVENT_SUBMISSION:
                    $stats['submissions'] = (int) $row['count'];
                    break;
            }
        }

        // Calculate conversion rate
        $total_traffic = $stats['views'] + $stats['qr_scans'];
        $stats['conversion_rate'] = $total_traffic > 0 
            ? round( ( $stats['submissions'] / $total_traffic ) * 100, 1 ) 
            : 0;

        return $stats;
    }

    /**
     * Get stats for all pages belonging to a user
     *
     * @param int    $user_id  User ID
     * @param string $period   'all', 'week', 'month', '30days'
     * @return array Array of page stats
     */
    public static function get_user_pages_stats( int $user_id, string $period = 'all' ): array {
        global $wpdb;

        // Get all pages for this user
        $pages = get_posts( [
            'post_type'      => 'frs_lead_page',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'   => '_frs_realtor_id',
                    'value' => $user_id,
                ],
                [
                    'key'   => '_frs_loan_officer_id',
                    'value' => $user_id,
                ],
            ],
            'author' => $user_id,
        ] );

        // Remove duplicates (a page might match both author and meta)
        $page_ids = [];
        $unique_pages = [];
        foreach ( $pages as $page ) {
            if ( ! in_array( $page->ID, $page_ids, true ) ) {
                $page_ids[] = $page->ID;
                $unique_pages[] = $page;
            }
        }

        $results = [];

        foreach ( $unique_pages as $page ) {
            $stats = self::get_page_stats( $page->ID, $period );
            $results[] = [
                'id'              => $page->ID,
                'title'           => $page->post_title,
                'url'             => get_permalink( $page->ID ),
                'status'          => $page->post_status,
                'page_type'       => get_post_meta( $page->ID, '_frs_page_type', true ),
                'created'         => $page->post_date,
                'views'           => $stats['views'],
                'qr_scans'        => $stats['qr_scans'],
                'submissions'     => $stats['submissions'],
                'conversion_rate' => $stats['conversion_rate'],
            ];
        }

        // Sort by views descending
        usort( $results, function( $a, $b ) {
            return ( $b['views'] + $b['qr_scans'] ) - ( $a['views'] + $a['qr_scans'] );
        } );

        return $results;
    }

    /**
     * Get SQL date condition for period filtering
     */
    private static function get_date_condition( string $period ): string {
        switch ( $period ) {
            case 'week':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case 'month':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case '30days':
                return "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case 'all':
            default:
                return '';
        }
    }

    /**
     * Return empty stats array
     */
    private static function empty_stats(): array {
        return [
            'views'           => 0,
            'qr_scans'        => 0,
            'submissions'     => 0,
            'conversion_rate' => 0,
        ];
    }

    /**
     * Migrate existing view counts from post meta to events table
     * Creates synthetic events for historical data preservation
     */
    public static function migrate_existing_counts() {
        global $wpdb;

        if ( ! self::table_exists() ) {
            self::create_table();
        }

        // Check if migration already done
        if ( get_option( 'frs_lead_pages_analytics_migrated' ) ) {
            return;
        }

        // Get all pages with view counts
        $pages = $wpdb->get_results(
            "SELECT post_id, meta_value 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_frs_page_views' 
             AND meta_value > 0"
        );

        $table_name = self::get_table_name();

        foreach ( $pages as $page ) {
            $page_id = (int) $page->post_id;
            $view_count = (int) $page->meta_value;

            // Get page owner
            $user_id = 0;
            $lo_id = get_post_meta( $page_id, '_frs_loan_officer_id', true );
            $realtor_id = get_post_meta( $page_id, '_frs_realtor_id', true );
            
            if ( $realtor_id ) {
                $user_id = (int) $realtor_id;
            } elseif ( $lo_id ) {
                $user_id = (int) $lo_id;
            } else {
                $post = get_post( $page_id );
                if ( $post ) {
                    $user_id = (int) $post->post_author;
                }
            }

            // Insert synthetic view events (with unique hashes to avoid dedup)
            for ( $i = 0; $i < $view_count; $i++ ) {
                $wpdb->insert(
                    $table_name,
                    [
                        'page_id'      => $page_id,
                        'user_id'      => $user_id,
                        'event_type'   => self::EVENT_VIEW,
                        'visitor_hash' => md5( 'migrated_' . $page_id . '_' . $i ),
                        'created_at'   => get_post_field( 'post_date', $page_id ), // Use page creation date
                    ],
                    [ '%d', '%d', '%s', '%s', '%s' ]
                );
            }
        }

        // Also migrate submission counts
        $submissions = $wpdb->get_results(
            "SELECT post_id, meta_value 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_frs_page_submissions' 
             AND meta_value > 0"
        );

        foreach ( $submissions as $sub ) {
            $page_id = (int) $sub->post_id;
            $sub_count = (int) $sub->meta_value;

            $user_id = 0;
            $lo_id = get_post_meta( $page_id, '_frs_loan_officer_id', true );
            $realtor_id = get_post_meta( $page_id, '_frs_realtor_id', true );
            
            if ( $realtor_id ) {
                $user_id = (int) $realtor_id;
            } elseif ( $lo_id ) {
                $user_id = (int) $lo_id;
            } else {
                $post = get_post( $page_id );
                if ( $post ) {
                    $user_id = (int) $post->post_author;
                }
            }

            for ( $i = 0; $i < $sub_count; $i++ ) {
                $wpdb->insert(
                    $table_name,
                    [
                        'page_id'      => $page_id,
                        'user_id'      => $user_id,
                        'event_type'   => self::EVENT_SUBMISSION,
                        'visitor_hash' => md5( 'migrated_sub_' . $page_id . '_' . $i ),
                        'created_at'   => get_post_field( 'post_date', $page_id ),
                    ],
                    [ '%d', '%d', '%s', '%s', '%s' ]
                );
            }
        }

        update_option( 'frs_lead_pages_analytics_migrated', true );
    }
}
