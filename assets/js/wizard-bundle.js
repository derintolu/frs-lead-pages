/**
 * Wizard Bundle JS
 *
 * Common wizard functionality. Individual wizard scripts are loaded separately.
 * Receives frsWizardConfig from wp_localize_script().
 *
 * @package FRSLeadPages
 */

(function() {
    'use strict';

    // Store config globally for individual wizard scripts
    window.frsWizardConfig = window.frsWizardConfig || {};

    // Common wizard utilities
    window.frsWizardUtils = {
        /**
         * Show loading state on button
         */
        showLoading: function(btn, text) {
            btn.disabled = true;
            btn.dataset.originalText = btn.innerHTML;
            btn.innerHTML = '<span class="frs-spinner"></span> ' + (text || 'Loading...');
        },

        /**
         * Hide loading state on button
         */
        hideLoading: function(btn) {
            btn.disabled = false;
            if (btn.dataset.originalText) {
                btn.innerHTML = btn.dataset.originalText;
            }
        },

        /**
         * Make AJAX request
         */
        ajax: function(action, data) {
            var config = window.frsWizardConfig;
            var formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', config.nonce);

            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                }
            }

            return fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData
            }).then(function(response) {
                return response.json();
            });
        }
    };
})();
