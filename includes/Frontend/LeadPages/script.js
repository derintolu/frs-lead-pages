/**
 * Lead Pages Dashboard Scripts
 *
 * @package FRSLeadPages
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        initTabs();
        initQRModal();
        initConfirmModal();
        initCopyButtons();
        initDeleteHandlers();
        initTriggerDropdown();
    }

    /**
     * Tab switching
     */
    function initTabs() {
        document.querySelectorAll('.frs-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.frs-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.frs-tab-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                document.querySelector('[data-panel="' + tab.dataset.tab + '"]').classList.add('active');
            });
        });
    }

    /**
     * QR Code Modal
     */
    function initQRModal() {
        const modal = document.getElementById('frs-qr-modal');
        if (!modal) return;

        const container = document.getElementById('frs-qr-container');
        const titleEl = document.getElementById('frs-qr-modal-title');
        const urlEl = document.getElementById('frs-qr-url');
        const copyBtn = document.getElementById('frs-qr-copy');
        const downloadBtn = document.getElementById('frs-qr-download');
        let currentQR = null;
        let currentUrl = '';

        // Open modal
        document.querySelectorAll('.frs-qr-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const url = btn.getAttribute('data-url');
                const title = btn.getAttribute('data-title');
                currentUrl = url;

                titleEl.textContent = title || 'QR Code';
                urlEl.textContent = url;

                // Clear previous QR
                container.innerHTML = '';

                // Create styled QR code (requires qr-code-styling library)
                if (typeof QRCodeStyling !== 'undefined') {
                    currentQR = new QRCodeStyling({
                        width: 208,
                        height: 208,
                        type: 'canvas',
                        data: url,
                        dotsOptions: {
                            type: 'extra-rounded',
                            gradient: {
                                type: 'linear',
                                rotation: 45,
                                colorStops: [
                                    { offset: 0, color: '#0ea5e9' },
                                    { offset: 0.5, color: '#06b6d4' },
                                    { offset: 1, color: '#2563eb' }
                                ]
                            }
                        },
                        cornersSquareOptions: {
                            type: 'extra-rounded',
                            color: '#0369a1'
                        },
                        cornersDotOptions: {
                            type: 'dot',
                            color: '#0284c7'
                        },
                        backgroundOptions: {
                            color: '#ffffff'
                        },
                        imageOptions: {
                            crossOrigin: 'anonymous',
                            margin: 4
                        }
                    });

                    currentQR.append(container);
                }

                modal.classList.add('open');
                document.body.style.overflow = 'hidden';
            });
        });

        // Close modal functions
        function closeModal() {
            modal.classList.remove('open');
            document.body.style.overflow = '';
        }

        modal.querySelector('.frs-qr-modal-backdrop').addEventListener('click', closeModal);
        modal.querySelector('.frs-qr-modal-close').addEventListener('click', closeModal);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
        });

        // Copy link
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                navigator.clipboard.writeText(currentUrl).then(() => {
                    const originalHTML = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg> Copied!';
                    setTimeout(() => { copyBtn.innerHTML = originalHTML; }, 2000);
                });
            });
        }

        // Download QR
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                if (currentQR) {
                    currentQR.download({ name: 'qr-code', extension: 'png' });
                }
            });
        }
    }

    /**
     * Confirm Modal
     */
    function initConfirmModal() {
        const confirmModal = document.getElementById('frs-confirm-modal');
        if (!confirmModal) return;

        // Store the showConfirm function globally for use by delete handlers
        window.frsShowConfirm = function(title, message) {
            return new Promise((resolve) => {
                const confirmTitle = document.getElementById('frs-confirm-title');
                const confirmMessage = document.getElementById('frs-confirm-message');
                const confirmOkBtn = document.getElementById('frs-confirm-ok');
                const confirmCancelBtn = document.getElementById('frs-confirm-cancel');
                const confirmBackdrop = confirmModal.querySelector('.frs-confirm-backdrop');

                confirmTitle.textContent = title;
                confirmMessage.textContent = message;
                confirmModal.classList.add('open');
                document.body.style.overflow = 'hidden';

                function cleanup() {
                    confirmModal.classList.remove('open');
                    document.body.style.overflow = '';
                    confirmOkBtn.removeEventListener('click', onOk);
                    confirmCancelBtn.removeEventListener('click', onCancel);
                    confirmBackdrop.removeEventListener('click', onCancel);
                }

                function onOk() {
                    cleanup();
                    resolve(true);
                }

                function onCancel() {
                    cleanup();
                    resolve(false);
                }

                confirmOkBtn.addEventListener('click', onOk);
                confirmCancelBtn.addEventListener('click', onCancel);
                confirmBackdrop.addEventListener('click', onCancel);
            });
        };
    }

    /**
     * Copy URL buttons
     */
    function initCopyButtons() {
        document.querySelectorAll('.frs-copy-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const url = this.getAttribute('data-url');
                navigator.clipboard.writeText(url).then(() => {
                    this.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
                    setTimeout(() => {
                        this.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
                    }, 2000);
                });
            });
        });
    }

    /**
     * Delete handlers
     */
    function initDeleteHandlers() {
        // Get config from localized script data
        const config = window.frsLeadPages || {};
        const ajaxUrl = config.ajaxUrl || '/wp-admin/admin-ajax.php';
        const deleteLeadNonce = config.deleteLeadNonce || '';
        const deletePageNonce = config.deletePageNonce || '';

        // Delete lead
        document.querySelectorAll('.frs-action-delete').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (typeof window.frsShowConfirm !== 'function') return;

                const confirmed = await window.frsShowConfirm('Delete Lead?', 'This will permanently remove this lead from your list.');
                if (!confirmed) return;

                const leadId = this.dataset.leadId;
                const row = this.closest('tr');
                const originalHTML = this.innerHTML;

                // Show loading
                this.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" class="frs-spinner"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg>';
                this.disabled = true;

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=frs_delete_lead&lead_id=' + leadId + '&nonce=' + deleteLeadNonce
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(() => row.remove(), 300);
                    } else {
                        alert(data.data || 'Failed to delete lead');
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }
                })
                .catch(() => {
                    alert('Failed to delete lead');
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                });
            });
        });

        // Delete lead page
        document.querySelectorAll('.frs-delete-page-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (typeof window.frsShowConfirm !== 'function') return;

                const confirmed = await window.frsShowConfirm('Delete Lead Page?', 'This will permanently delete this page and cannot be undone.');
                if (!confirmed) return;

                const pageId = this.dataset.pageId;
                const row = this.closest('.frs-page-row');
                const originalHTML = this.innerHTML;

                // Show loading
                this.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg>';
                this.disabled = true;

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=frs_delete_lead_page&page_id=' + pageId + '&nonce=' + deletePageNonce
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        row.style.transition = 'opacity 0.3s, transform 0.3s';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        setTimeout(() => row.remove(), 300);
                    } else {
                        alert(data.data || 'Failed to delete page');
                        this.innerHTML = originalHTML;
                        this.disabled = false;
                    }
                })
                .catch(() => {
                    alert('Failed to delete page');
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                });
            });
        });
    }

    /**
     * Trigger dropdown from empty state button
     */
    function initTriggerDropdown() {
        document.querySelectorAll('.frs-trigger-dropdown').forEach(btn => {
            btn.addEventListener('click', () => {
                const dropdown = document.querySelector('.frs-dropdown summary');
                if (dropdown) {
                    dropdown.click();
                }
            });
        });
    }

    /**
     * Analytics Period Filter
     */
    function initAnalyticsFilter() {
        document.querySelectorAll('.frs-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const period = btn.dataset.period;
                if (!period) return;

                // Update URL and reload
                const url = new URL(window.location.href);
                url.searchParams.set('analytics_period', period);
                url.searchParams.set('tab', 'analytics');
                
                window.location.href = url.toString();
            });
        });

        // Check if we should switch to analytics tab on page load
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'analytics') {
            const analyticsTab = document.querySelector('[data-tab="analytics"]');
            if (analyticsTab) {
                analyticsTab.click();
            }
        }
    }

    // Initialize analytics filter
    initAnalyticsFilter();
})();
