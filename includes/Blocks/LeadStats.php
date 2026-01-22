<?php
/**
 * Lead Stats Block
 *
 * Displays summary analytics for the current user's lead pages.
 * Shows total views, QR scans, submissions, and conversion rate.
 *
 * @package FRSLeadPages
 */

namespace FRSLeadPages\Blocks;

class LeadStats {

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
        register_block_type( 'frs/lead-stats', [
            'render_callback' => [ __CLASS__, 'render' ],
            'attributes'      => [
                'period' => [
                    'type'    => 'string',
                    'default' => '30days',
                ],
                'showViews' => [
                    'type'    => 'boolean',
                    'default' => true,
                ],
                'showQRScans' => [
                    'type'    => 'boolean',
                    'default' => true,
                ],
                'showSubmissions' => [
                    'type'    => 'boolean',
                    'default' => true,
                ],
                'showConversion' => [
                    'type'    => 'boolean',
                    'default' => true,
                ],
                'layout' => [
                    'type'    => 'string',
                    'default' => 'grid', // grid, row
                ],
            ],
        ] );
    }

    /**
     * Render the block
     */
    public static function render( $attributes, $content ): string {
        if ( ! is_user_logged_in() ) {
            return '<p class="frs-lead-stats-login">Please log in to view your analytics.</p>';
        }

        $user_id = get_current_user_id();
        $period = $attributes['period'] ?? '30days';
        $stats = \FRSLeadPages\Core\Analytics::get_user_stats( $user_id, $period );

        $period_labels = [
            '30days' => 'Last 30 Days',
            'week'   => 'This Week',
            'all'    => 'All Time',
        ];
        $period_label = $period_labels[ $period ] ?? 'Last 30 Days';

        $layout = $attributes['layout'] ?? 'grid';
        $wrapper_class = 'frs-lead-stats frs-lead-stats--' . $layout;

        ob_start();
        ?>
        <div class="<?php echo esc_attr( $wrapper_class ); ?>">
            <?php if ( $attributes['showViews'] ) : ?>
                <div class="frs-lead-stats__card frs-lead-stats__card--views">
                    <div class="frs-lead-stats__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </div>
                    <div class="frs-lead-stats__content">
                        <div class="frs-lead-stats__value"><?php echo number_format( $stats['views'] ); ?></div>
                        <div class="frs-lead-stats__label">Page Views</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $attributes['showQRScans'] ) : ?>
                <div class="frs-lead-stats__card frs-lead-stats__card--qr">
                    <div class="frs-lead-stats__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/><rect x="18" y="14" width="3" height="3"/><rect x="14" y="18" width="3" height="3"/><rect x="18" y="18" width="3" height="3"/></svg>
                    </div>
                    <div class="frs-lead-stats__content">
                        <div class="frs-lead-stats__value"><?php echo number_format( $stats['qr_scans'] ); ?></div>
                        <div class="frs-lead-stats__label">QR Scans</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $attributes['showSubmissions'] ) : ?>
                <div class="frs-lead-stats__card frs-lead-stats__card--leads">
                    <div class="frs-lead-stats__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="frs-lead-stats__content">
                        <div class="frs-lead-stats__value"><?php echo number_format( $stats['submissions'] ); ?></div>
                        <div class="frs-lead-stats__label">Leads</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $attributes['showConversion'] ) : ?>
                <div class="frs-lead-stats__card frs-lead-stats__card--conversion">
                    <div class="frs-lead-stats__icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                    </div>
                    <div class="frs-lead-stats__content">
                        <div class="frs-lead-stats__value"><?php echo esc_html( $stats['conversion_rate'] ); ?>%</div>
                        <div class="frs-lead-stats__label">Conversion</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .frs-lead-stats {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            .frs-lead-stats--row {
                display: flex;
                flex-wrap: wrap;
                gap: 16px;
            }
            .frs-lead-stats--row .frs-lead-stats__card {
                flex: 1;
                min-width: 150px;
            }
            .frs-lead-stats__card {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 20px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
            }
            .frs-lead-stats__icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 48px;
                height: 48px;
                border-radius: 12px;
                flex-shrink: 0;
            }
            .frs-lead-stats__card--views .frs-lead-stats__icon {
                background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
                color: #2563eb;
            }
            .frs-lead-stats__card--qr .frs-lead-stats__icon {
                background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);
                color: #9333ea;
            }
            .frs-lead-stats__card--leads .frs-lead-stats__icon {
                background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
                color: #16a34a;
            }
            .frs-lead-stats__card--conversion .frs-lead-stats__icon {
                background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
                color: #d97706;
            }
            .frs-lead-stats__value {
                font-size: 28px;
                font-weight: 700;
                color: #0f172a;
                line-height: 1.2;
            }
            .frs-lead-stats__label {
                font-size: 13px;
                font-weight: 500;
                color: #64748b;
            }
            .frs-lead-stats-login {
                padding: 24px;
                background: #f8fafc;
                border-radius: 8px;
                text-align: center;
                color: #64748b;
            }
            @media (max-width: 1024px) {
                .frs-lead-stats {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            @media (max-width: 640px) {
                .frs-lead-stats {
                    grid-template-columns: 1fr;
                }
                .frs-lead-stats__value {
                    font-size: 24px;
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }
}
