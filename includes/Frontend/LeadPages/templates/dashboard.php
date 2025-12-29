<?php
/**
 * Lead Pages Dashboard Template
 *
 * Variables available:
 * - $query: WP_Query with user's lead pages
 * - $leads: Array of user's leads
 *
 * @package FRSLeadPages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="frs-dashboard">
    <div class="frs-dashboard-header">
        <h2>Lead Pages</h2>
        <details class="frs-dropdown">
            <summary class="frs-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Create New Page
                <svg class="frs-dropdown-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M6 9l6 6 6-6"/></svg>
            </summary>
            <div class="frs-dropdown-menu">
                <button type="button" class="frs-dropdown-item oh-wizard-trigger">
                    <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                    <span class="frs-dropdown-text">
                        <strong>Open House</strong>
                        <small>Property showing landing page</small>
                    </span>
                </button>
                <button type="button" class="frs-dropdown-item cs-wizard-trigger">
                    <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span>
                    <span class="frs-dropdown-text">
                        <strong>Customer Spotlight</strong>
                        <small>Target specific buyer types</small>
                    </span>
                </button>
                <button type="button" class="frs-dropdown-item se-wizard-trigger">
                    <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                    <span class="frs-dropdown-text">
                        <strong>Special Event</strong>
                        <small>Seminars, workshops, webinars</small>
                    </span>
                </button>
                <button type="button" class="frs-dropdown-item mc-wizard-trigger">
                    <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/><line x1="8" y1="18" x2="12" y2="18"/></svg></span>
                    <span class="frs-dropdown-text">
                        <strong>Mortgage Calculator</strong>
                        <small>Interactive calculator with leads</small>
                    </span>
                </button>
                <button type="button" class="frs-dropdown-item rq-wizard-trigger">
                    <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                    <span class="frs-dropdown-text">
                        <strong>Rate Quote</strong>
                        <small>Get personalized rate quotes</small>
                    </span>
                </button>
                <button type="button" class="frs-dropdown-item an-wizard-trigger">
                    <span class="frs-dropdown-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M9 14l2 2 4-4"/></svg></span>
                    <span class="frs-dropdown-text">
                        <strong>Apply Now</strong>
                        <small>Start your application</small>
                    </span>
                </button>
            </div>
        </details>
    </div>

    <div class="frs-tabs">
        <button class="frs-tab active" data-tab="pages">
            My Pages <span class="frs-tab-count"><?php echo esc_html( $query->found_posts ); ?></span>
        </button>
        <button class="frs-tab" data-tab="leads">
            My Leads <span class="frs-tab-count"><?php echo count( $leads ); ?></span>
        </button>
        <button class="frs-tab" data-tab="settings">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16" style="margin-right:4px;vertical-align:-3px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Settings
        </button>
    </div>

    <!-- Pages Tab -->
    <div class="frs-tab-panel active" data-panel="pages">
        <?php if ( $query->have_posts() ) : ?>
            <div class="frs-pages-list">
                <?php while ( $query->have_posts() ) : $query->the_post();
                    $page_id = get_the_ID();
                    $page_type = get_post_meta( $page_id, '_frs_page_type', true );
                    $hero_image_url = get_post_meta( $page_id, '_frs_hero_image_url', true );
                    $page_views = (int) get_post_meta( $page_id, '_frs_page_views', true );
                    $lead_count = (int) get_post_meta( $page_id, '_frs_lead_count', true );
                    $property_address = get_post_meta( $page_id, '_frs_property_address', true );

                    $type_labels = [
                        'open_house'          => 'Open House',
                        'customer_spotlight'  => 'Spotlight',
                        'special_event'       => 'Event',
                        'mortgage_calculator' => 'Calculator',
                    ];
                    $type_label = $type_labels[ $page_type ] ?? 'Page';
                ?>
                    <div class="frs-page-row">
                        <?php if ( $hero_image_url ) : ?>
                            <img src="<?php echo esc_url( $hero_image_url ); ?>" alt="" class="frs-page-thumb">
                        <?php else : ?>
                            <div class="frs-page-thumb"></div>
                        <?php endif; ?>

                        <div class="frs-page-info">
                            <h3 class="frs-page-title"><?php echo esc_html( $property_address ?: get_the_title() ); ?></h3>
                            <div class="frs-page-meta">
                                <span class="frs-page-badge <?php echo esc_attr( $page_type ); ?>"><?php echo esc_html( $type_label ); ?></span>
                                <span><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
                            </div>
                        </div>

                        <div class="frs-page-stats">
                            <div class="frs-page-stat">
                                <div class="frs-page-stat-value"><?php echo number_format( $page_views ); ?></div>
                                <div class="frs-page-stat-label">Views</div>
                            </div>
                            <div class="frs-page-stat">
                                <div class="frs-page-stat-value"><?php echo number_format( $lead_count ); ?></div>
                                <div class="frs-page-stat-label">Leads</div>
                            </div>
                        </div>

                        <div class="frs-page-actions">
                            <a href="<?php echo esc_url( get_permalink( $page_id ) ); ?>" class="frs-icon-btn primary" target="_blank" title="View Page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            </a>
                            <button class="frs-icon-btn frs-copy-btn" data-url="<?php echo esc_attr( get_permalink( $page_id ) ); ?>" title="Copy URL">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            </button>
                            <button class="frs-icon-btn frs-qr-btn" data-url="<?php echo esc_attr( get_permalink( $page_id ) ); ?>" data-title="<?php echo esc_attr( $property_address ?: get_the_title() ); ?>" title="QR Code">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/><rect x="18" y="14" width="3" height="3"/><rect x="14" y="18" width="3" height="3"/><rect x="18" y="18" width="3" height="3"/></svg>
                            </button>
                            <button class="frs-icon-btn frs-delete-page-btn" data-page-id="<?php echo esc_attr( $page_id ); ?>" title="Delete Page">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <div class="frs-empty">
                <div class="frs-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <h3>No Lead Pages Yet</h3>
                <p>Create your first lead page to start generating leads!</p>
                <button type="button" class="frs-btn frs-trigger-dropdown">Create Your First Page</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Leads Tab -->
    <div class="frs-tab-panel" data-panel="leads">
        <?php if ( ! empty( $leads ) ) : ?>
            <table class="frs-leads-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Source</th>
                        <th>Property</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $leads as $lead ) : ?>
                        <tr data-lead-id="<?php echo esc_attr( $lead['id'] ); ?>">
                            <td><span class="frs-lead-name"><?php echo esc_html( $lead['first_name'] . ' ' . $lead['last_name'] ); ?></span></td>
                            <td class="frs-lead-contact">
                                <a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>"><?php echo esc_html( $lead['email'] ); ?></a><br>
                                <a href="tel:<?php echo esc_attr( $lead['phone'] ); ?>"><?php echo esc_html( $lead['phone'] ); ?></a>
                            </td>
                            <td><?php echo esc_html( $lead['source'] ); ?></td>
                            <td><?php echo esc_html( $lead['property'] ?: '—' ); ?></td>
                            <td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $lead['created_at'] ) ) ); ?></td>
                            <td><span class="frs-lead-status <?php echo esc_attr( $lead['status'] ); ?>"><?php echo esc_html( ucfirst( $lead['status'] ?: 'new' ) ); ?></span></td>
                            <td class="frs-lead-actions">
                                <a href="mailto:<?php echo esc_attr( $lead['email'] ); ?>?subject=<?php echo esc_attr( urlencode( 'Following up on your inquiry' ) ); ?>" class="frs-action-btn frs-action-reply" title="Reply">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                                </a>
                                <button type="button" class="frs-action-btn frs-action-delete" data-lead-id="<?php echo esc_attr( $lead['id'] ); ?>" title="Delete">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="frs-empty">
                <div class="frs-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h3>No Leads Yet</h3>
                <p>When visitors submit forms on your lead pages, they'll appear here.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Settings Tab -->
    <?php
    $current_user_id = get_current_user_id();
    $fub_connected = \FRSLeadPages\Integrations\FollowUpBoss::is_connected( $current_user_id );
    $fub_status = \FRSLeadPages\Integrations\FollowUpBoss::get_status( $current_user_id );
    $fub_stats = \FRSLeadPages\Integrations\FollowUpBoss::get_stats( $current_user_id );
    $fub_nonce = wp_create_nonce( 'frs_fub_nonce' );
    ?>
    <div class="frs-tab-panel" data-panel="settings">
        <div class="frs-settings-section">
            <div class="frs-settings-header">
                <div class="frs-settings-icon fub-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="frs-settings-title">
                    <h3>Follow Up Boss</h3>
                    <p>Connect your CRM to automatically sync leads</p>
                </div>
                <?php if ( $fub_connected ) : ?>
                    <span class="frs-connection-badge connected">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Connected
                    </span>
                <?php else : ?>
                    <span class="frs-connection-badge disconnected">Not Connected</span>
                <?php endif; ?>
            </div>

            <div class="frs-settings-body">
                <?php if ( $fub_connected ) : ?>
                    <!-- Connected State -->
                    <div class="frs-fub-connected">
                        <div class="frs-fub-account">
                            <div class="frs-fub-account-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div class="frs-fub-account-info">
                                <strong><?php echo esc_html( $fub_status['account_name'] ?? 'Follow Up Boss Account' ); ?></strong>
                                <span>Connected <?php echo esc_html( human_time_diff( strtotime( $fub_status['connected_at'] ?? 'now' ) ) ); ?> ago</span>
                            </div>
                        </div>

                        <div class="frs-fub-stats">
                            <div class="frs-fub-stat">
                                <div class="frs-fub-stat-value"><?php echo number_format( $fub_stats['total_synced'] ?? 0 ); ?></div>
                                <div class="frs-fub-stat-label">Leads Synced</div>
                            </div>
                            <?php if ( ! empty( $fub_stats['last_sync'] ) ) : ?>
                                <div class="frs-fub-stat">
                                    <div class="frs-fub-stat-value"><?php echo esc_html( human_time_diff( strtotime( $fub_stats['last_sync'] ) ) ); ?></div>
                                    <div class="frs-fub-stat-label">Since Last Sync</div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="frs-fub-actions">
                            <button type="button" class="frs-btn frs-btn-secondary" id="frs-fub-test">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Test Connection
                            </button>
                            <button type="button" class="frs-btn frs-btn-danger" id="frs-fub-disconnect">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                                Disconnect
                            </button>
                        </div>
                    </div>
                <?php else : ?>
                    <!-- Disconnected State -->
                    <div class="frs-fub-connect">
                        <p class="frs-fub-description">
                            Connect your Follow Up Boss account to automatically send leads from your landing pages directly to your CRM.
                            New leads will appear in Follow Up Boss with all their details and be ready for follow-up.
                        </p>

                        <div class="frs-fub-form">
                            <div class="frs-field">
                                <label class="frs-label" for="frs-fub-api-key">API Key</label>
                                <div class="frs-input-group">
                                    <input type="password" id="frs-fub-api-key" class="frs-input" placeholder="Enter your Follow Up Boss API key">
                                    <button type="button" class="frs-input-toggle" id="frs-fub-toggle-key" title="Show/Hide">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                <p class="frs-helper">
                                    Find your API key in Follow Up Boss: <strong>Admin > API</strong>
                                    <a href="https://app.followupboss.com/2/api" target="_blank" rel="noopener">Open API Settings</a>
                                </p>
                            </div>

                            <button type="button" class="frs-btn" id="frs-fub-connect">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                                Connect to Follow Up Boss
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <div id="frs-fub-message" class="frs-message" style="display:none;"></div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var nonce = '<?php echo esc_js( $fub_nonce ); ?>';
        var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

        // Toggle API key visibility
        var toggleBtn = document.getElementById('frs-fub-toggle-key');
        var apiKeyInput = document.getElementById('frs-fub-api-key');
        if (toggleBtn && apiKeyInput) {
            toggleBtn.addEventListener('click', function() {
                apiKeyInput.type = apiKeyInput.type === 'password' ? 'text' : 'password';
            });
        }

        // Connect button
        var connectBtn = document.getElementById('frs-fub-connect');
        if (connectBtn) {
            connectBtn.addEventListener('click', function() {
                var apiKey = document.getElementById('frs-fub-api-key').value.trim();
                if (!apiKey) {
                    showMessage('Please enter your API key', 'error');
                    return;
                }

                connectBtn.disabled = true;
                connectBtn.innerHTML = '<span class="frs-spinner"></span> Connecting...';

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'frs_fub_save_api_key',
                        nonce: nonce,
                        api_key: apiKey
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        showMessage(response.data.message, 'error');
                        connectBtn.disabled = false;
                        connectBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> Connect to Follow Up Boss';
                    }
                })
                .catch(function() {
                    showMessage('Connection error. Please try again.', 'error');
                    connectBtn.disabled = false;
                    connectBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> Connect to Follow Up Boss';
                });
            });
        }

        // Test connection button
        var testBtn = document.getElementById('frs-fub-test');
        if (testBtn) {
            testBtn.addEventListener('click', function() {
                testBtn.disabled = true;
                testBtn.innerHTML = '<span class="frs-spinner"></span> Testing...';

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'frs_fub_test_connection',
                        nonce: nonce
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    showMessage(response.data.message, response.success ? 'success' : 'error');
                    testBtn.disabled = false;
                    testBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Test Connection';
                });
            });
        }

        // Disconnect button
        var disconnectBtn = document.getElementById('frs-fub-disconnect');
        if (disconnectBtn) {
            disconnectBtn.addEventListener('click', function() {
                if (!confirm('Are you sure you want to disconnect Follow Up Boss? New leads will no longer sync to your CRM.')) {
                    return;
                }

                disconnectBtn.disabled = true;
                disconnectBtn.innerHTML = '<span class="frs-spinner"></span> Disconnecting...';

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'frs_fub_disconnect',
                        nonce: nonce
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(response) {
                    if (response.success) {
                        showMessage(response.data.message, 'success');
                        setTimeout(function() { location.reload(); }, 1000);
                    }
                });
            });
        }

        function showMessage(text, type) {
            var msg = document.getElementById('frs-fub-message');
            msg.textContent = text;
            msg.className = 'frs-message frs-message--' + type;
            msg.style.display = 'block';
            setTimeout(function() { msg.style.display = 'none'; }, 5000);
        }
    })();
    </script>

    <!-- QR Code Modal -->
    <div class="frs-qr-modal" id="frs-qr-modal">
        <div class="frs-qr-modal-backdrop"></div>
        <div class="frs-qr-modal-content">
            <button class="frs-qr-modal-close" aria-label="Close">&times;</button>
            <div class="frs-qr-modal-header">
                <h3 id="frs-qr-modal-title">QR Code</h3>
                <p class="frs-qr-modal-subtitle">Scan to visit this page</p>
            </div>
            <div class="frs-qr-modal-body">
                <div class="frs-qr-container" id="frs-qr-container"></div>
            </div>
            <div class="frs-qr-modal-footer">
                <p class="frs-qr-url" id="frs-qr-url"></p>
                <div class="frs-qr-actions">
                    <button class="frs-btn frs-btn-secondary" id="frs-qr-copy">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        Copy Link
                    </button>
                    <button class="frs-btn" id="frs-qr-download">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="frs-confirm-modal" id="frs-confirm-modal">
        <div class="frs-confirm-backdrop"></div>
        <div class="frs-confirm-content">
            <div class="frs-confirm-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h3 class="frs-confirm-title" id="frs-confirm-title">Are you sure?</h3>
            <p class="frs-confirm-message" id="frs-confirm-message">This action cannot be undone.</p>
            <div class="frs-confirm-actions">
                <button class="frs-confirm-btn frs-confirm-cancel" id="frs-confirm-cancel">Cancel</button>
                <button class="frs-confirm-btn frs-confirm-delete" id="frs-confirm-ok">Delete</button>
            </div>
        </div>
    </div>
</div>
