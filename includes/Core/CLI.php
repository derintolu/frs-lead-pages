<?php
/**
 * WP-CLI Commands
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Core;

use WP_CLI;

class CLI {

    /**
     * Register CLI commands.
     */
    public static function init(): void {
        WP_CLI::add_command( 'frs-lead-pages', self::class );
    }

    /**
     * Seed the submissions table with dummy data.
     *
     * ## OPTIONS
     *
     * [--count=<count>]
     * : Number of submissions to create.
     * ---
     * default: 20
     * ---
     *
     * [--page-id=<page-id>]
     * : Assign all submissions to a specific lead page ID.
     *
     * [--lo-id=<lo-id>]
     * : Loan officer user ID.
     *
     * [--realtor-id=<realtor-id>]
     * : Realtor user ID.
     *
     * ## EXAMPLES
     *
     *     wp frs-lead-pages seed-submissions
     *     wp frs-lead-pages seed-submissions --count=50
     *     wp frs-lead-pages seed-submissions --page-id=123 --lo-id=2
     *
     * @subcommand seed-submissions
     */
    public function seed_submissions( $args, $assoc_args ): void {
        $count      = (int) ( $assoc_args['count'] ?? 20 );
        $page_id    = (int) ( $assoc_args['page-id'] ?? 0 );
        $lo_id      = (int) ( $assoc_args['lo-id'] ?? 0 );
        $realtor_id = (int) ( $assoc_args['realtor-id'] ?? 0 );

        // Ensure table exists.
        Submissions::create_table();

        // If no page ID specified, find existing lead pages.
        $page_ids = [];
        if ( $page_id ) {
            $page_ids = [ $page_id ];
        } else {
            $pages = get_posts([
                'post_type'      => 'frs_lead_page',
                'post_status'    => 'publish',
                'posts_per_page' => 20,
                'fields'         => 'ids',
            ]);
            if ( ! empty( $pages ) ) {
                $page_ids = $pages;
            } else {
                WP_CLI::warning( 'No published lead pages found. Creating submissions with page_id=0.' );
                $page_ids = [ 0 ];
            }
        }

        $first_names = [ 'James', 'Maria', 'Robert', 'Jennifer', 'Michael', 'Linda', 'David', 'Sarah', 'William', 'Jessica', 'Daniel', 'Emily', 'Matthew', 'Ashley', 'Christopher', 'Amanda', 'Andrew', 'Stephanie', 'Joshua', 'Nicole' ];
        $last_names  = [ 'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin' ];
        $timeframes  = [ 'immediately', '1-3 months', '3-6 months', '6-12 months', 'just looking' ];
        $statuses    = [ 'unread', 'unread', 'unread', 'read', 'read', 'contacted', 'converted' ];
        $page_types  = [ 'open_house', 'rate_quote', 'apply_now', 'customer_spotlight', 'mortgage_calculator', 'special_event' ];
        $browsers    = [ 'Chrome', 'Safari', 'Firefox', 'Edge', 'Samsung Internet' ];
        $devices     = [ 'desktop', 'mobile', 'mobile', 'tablet' ];
        $yes_no      = [ 'yes', 'no', '' ];

        $inserted = 0;
        $progress = \WP_CLI\Utils\make_progress_bar( 'Seeding submissions', $count );

        for ( $i = 0; $i < $count; $i++ ) {
            $first = $first_names[ array_rand( $first_names ) ];
            $last  = $last_names[ array_rand( $last_names ) ];
            $pid   = $page_ids[ array_rand( $page_ids ) ];

            // Get page type from meta if we have a real page.
            $p_type = '';
            if ( $pid ) {
                $p_type = get_post_meta( $pid, '_frs_page_type', true );
            }
            if ( ! $p_type ) {
                $p_type = $page_types[ array_rand( $page_types ) ];
            }

            // Get LO/realtor from page meta if not specified.
            $row_lo = $lo_id;
            $row_re = $realtor_id;
            if ( $pid && ! $row_lo ) {
                $row_lo = (int) get_post_meta( $pid, '_frs_loan_officer_id', true );
            }
            if ( $pid && ! $row_re ) {
                $row_re = (int) get_post_meta( $pid, '_frs_realtor_id', true );
            }

            // Random created_at within the last 90 days.
            $days_ago   = wp_rand( 0, 90 );
            $hours_ago  = wp_rand( 0, 23 );
            $created_at = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days -{$hours_ago} hours" ) );

            $data = [
                'page_id'                   => $pid,
                'page_type'                 => $p_type,
                'loan_officer_id'           => $row_lo,
                'realtor_id'                => $row_re,
                'first_name'                => $first,
                'last_name'                 => $last,
                'email'                     => strtolower( $first . '.' . $last . wp_rand( 10, 999 ) . '@example.com' ),
                'phone'                     => sprintf( '(%03d) %03d-%04d', wp_rand( 200, 999 ), wp_rand( 200, 999 ), wp_rand( 1000, 9999 ) ),
                'working_with_agent'        => $yes_no[ array_rand( $yes_no ) ],
                'pre_approved'              => $yes_no[ array_rand( $yes_no ) ],
                'interested_in_preapproval' => $yes_no[ array_rand( $yes_no ) ],
                'timeframe'                 => $timeframes[ array_rand( $timeframes ) ],
                'comments'                  => $i % 3 === 0 ? 'I am interested in learning more about this property.' : '',
                'responses'                 => wp_json_encode([
                    'first_name' => $first,
                    'last_name'  => $last,
                ]),
                'source_url'                => $pid ? get_permalink( $pid ) : home_url( '/lead/' ),
                'browser'                   => $browsers[ array_rand( $browsers ) ],
                'device'                    => $devices[ array_rand( $devices ) ],
                'ip'                        => long2ip( wp_rand( 167772160, 184549375 ) ),
                'status'                    => $statuses[ array_rand( $statuses ) ],
                'created_at'                => $created_at,
                'updated_at'                => $created_at,
            ];

            $result = Submissions::insert( $data );
            if ( $result ) {
                $inserted++;
            }

            $progress->tick();
        }

        $progress->finish();
        WP_CLI::success( "Inserted {$inserted} dummy submissions across " . count( $page_ids ) . ' page(s).' );
    }

    /**
     * Ensure the submissions table exists.
     *
     * ## EXAMPLES
     *
     *     wp frs-lead-pages create-table
     *
     * @subcommand create-table
     */
    public function create_table( $args, $assoc_args ): void {
        Submissions::create_table();
        Analytics::create_table();
        WP_CLI::success( 'Tables created/updated.' );
    }

    /**
     * Show submission counts.
     *
     * ## EXAMPLES
     *
     *     wp frs-lead-pages submission-stats
     *
     * @subcommand submission-stats
     */
    public function submission_stats( $args, $assoc_args ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'frs_lead_submissions';

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $by_status = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status ORDER BY cnt DESC" );

        WP_CLI::log( "Total submissions: {$total}" );
        if ( $by_status ) {
            WP_CLI::log( '' );
            $rows = [];
            foreach ( $by_status as $row ) {
                $rows[] = [ 'Status' => $row->status, 'Count' => $row->cnt ];
            }
            \WP_CLI\Utils\format_items( 'table', $rows, [ 'Status', 'Count' ] );
        }
    }

    /**
     * Migrate submissions from FluentForms table.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview without inserting.
     *
     * ## EXAMPLES
     *
     *     wp frs-lead-pages migrate-submissions --dry-run
     *     wp frs-lead-pages migrate-submissions
     *
     * @subcommand migrate-submissions
     */
    public function migrate_submissions( $args, $assoc_args ): void {
        $dry_run = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

        if ( $dry_run ) {
            WP_CLI::log( '🔍 Dry run mode — no data will be written.' );
        }

        $result = Submissions::migrate_from_fluentforms( $dry_run );

        if ( isset( $result['error'] ) ) {
            WP_CLI::error( $result['error'] );
        }

        WP_CLI::success( sprintf(
            'Migration complete: %d migrated, %d skipped (duplicates).',
            $result['migrated'] ?? 0,
            $result['skipped'] ?? 0
        ) );
    }
}
