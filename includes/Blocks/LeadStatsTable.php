<?php
/**
 * Lead Stats Table Block
 *
 * Displays a table of all lead pages with their analytics.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Blocks;

class LeadStatsTable {

    /**
     * Initialize the block
     */
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_block' ] );
    }

    /**
     * Register the block
     */
    public static function register_block() {
        register_block_type( 'frs/lead-stats-table', [
            'render_callback' => [ __CLASS__, 'render' ],
            'attributes'      => [
                'period' => [
                    'type'    => 'string',
                    'default' => '30days',
                ],
                'limit' => [
                    'type'    => 'number',
                    'default' => 10,
                ],
                'showType' => [
                    'type'    => 'boolean',
                    'default' => true,
                ],
            ],
        ] );
    }

    /**
     * Render the block
     */
    public static function render( $attributes, $content ): string {
        if ( ! is_user_logged_in() ) {
            return '<p class="frs-lead-stats-table-login">Please log in to view your analytics.</p>';
        }

        $user_id = get_current_user_id();
        $period = $attributes['period'] ?? '30days';
        $limit = $attributes['limit'] ?? 10;
        $pages = \FRSLeadPages\Core\Analytics::get_user_pages_stats( $user_id, $period );

        // Limit results
        if ( $limit > 0 && count( $pages ) > $limit ) {
            $pages = array_slice( $pages, 0, $limit );
        }

        $type_labels = [
            'open_house'          => 'Open House',
            'customer_spotlight'  => 'Spotlight',
            'special_event'       => 'Event',
            'mortgage_calculator' => 'Calculator',
            'rate_quote'          => 'Rate Quote',
            'apply_now'           => 'Apply Now',
        ];

        ob_start();
        ?>
        <div class="frs-lead-stats-table-wrapper">
            <?php if ( ! empty( $pages ) ) : ?>
                <table class="frs-lead-stats-table">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <?php if ( $attributes['showType'] ) : ?>
                                <th>Type</th>
                            <?php endif; ?>
                            <th>Views</th>
                            <th>QR Scans</th>
                            <th>Leads</th>
                            <th>Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $pages as $page ) : 
                            $type_label = $type_labels[ $page['page_type'] ] ?? 'Page';
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( $page['url'] ); ?>" target="_blank" class="frs-lead-stats-table__link">
                                        <?php echo esc_html( $page['title'] ); ?>
                                    </a>
                                </td>
                                <?php if ( $attributes['showType'] ) : ?>
                                    <td><span class="frs-lead-stats-table__badge frs-lead-stats-table__badge--<?php echo esc_attr( $page['page_type'] ); ?>"><?php echo esc_html( $type_label ); ?></span></td>
                                <?php endif; ?>
                                <td><?php echo number_format( $page['views'] ); ?></td>
                                <td><?php echo number_format( $page['qr_scans'] ); ?></td>
                                <td><?php echo number_format( $page['submissions'] ); ?></td>
                                <td><?php echo esc_html( $page['conversion_rate'] ); ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="frs-lead-stats-table__empty">
                    <p>No lead pages found. Create your first lead page to start tracking analytics!</p>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .frs-lead-stats-table-wrapper {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .frs-lead-stats-table {
                width: 100%;
                border-collapse: collapse;
            }
            .frs-lead-stats-table th {
                padding: 12px 16px;
                text-align: left;
                font-size: 11px;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
            }
            .frs-lead-stats-table td {
                padding: 14px 16px;
                font-size: 14px;
                color: #334155;
                border-bottom: 1px solid #f1f5f9;
            }
            .frs-lead-stats-table tr:last-child td {
                border-bottom: none;
            }
            .frs-lead-stats-table tr:hover td {
                background: #f8fafc;
            }
            .frs-lead-stats-table__link {
                color: #0ea5e9;
                text-decoration: none;
                font-weight: 500;
            }
            .frs-lead-stats-table__link:hover {
                text-decoration: underline;
            }
            .frs-lead-stats-table__badge {
                display: inline-block;
                padding: 4px 10px;
                font-size: 11px;
                font-weight: 600;
                border-radius: 6px;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .frs-lead-stats-table__badge--open_house {
                background: #dbeafe;
                color: #1d4ed8;
            }
            .frs-lead-stats-table__badge--customer_spotlight {
                background: #fef3c7;
                color: #b45309;
            }
            .frs-lead-stats-table__badge--special_event {
                background: #f3e8ff;
                color: #7c3aed;
            }
            .frs-lead-stats-table__badge--mortgage_calculator {
                background: #dcfce7;
                color: #15803d;
            }
            .frs-lead-stats-table__badge--rate_quote {
                background: #fee2e2;
                color: #dc2626;
            }
            .frs-lead-stats-table__badge--apply_now {
                background: #e0f2fe;
                color: #0369a1;
            }
            .frs-lead-stats-table__empty {
                padding: 32px;
                text-align: center;
                color: #64748b;
            }
            .frs-lead-stats-table-login {
                padding: 24px;
                background: #f8fafc;
                border-radius: 8px;
                text-align: center;
                color: #64748b;
            }
            @media (max-width: 768px) {
                .frs-lead-stats-table {
                    display: block;
                    overflow-x: auto;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }
}
