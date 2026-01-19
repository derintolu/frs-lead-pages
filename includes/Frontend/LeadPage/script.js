/**
 * Lead Page JavaScript
 *
 * Handles form field population for FluentForms integration.
 *
 * @package FRSLeadPages
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Get page data from data attributes on body or script tag
        var pageDataEl = document.querySelector('[data-lead-page-data]');
        if (!pageDataEl) return;

        var pageData;
        try {
            pageData = JSON.parse(pageDataEl.getAttribute('data-lead-page-data'));
        } catch (e) {
            console.error('Lead Page: Failed to parse page data', e);
            return;
        }

        // Populate hidden fields with page data
        var form = document.querySelector('.fluentform');
        if (!form) return;

        var hiddenFields = {
            'lead_page_id': pageData.page_id || '',
            'page_type': pageData.page_type || '',
            'loan_officer_id': pageData.lo_id || '',
            'realtor_id': pageData.realtor_id || ''
        };

        Object.keys(hiddenFields).forEach(function(name) {
            var input = form.querySelector('input[name="' + name + '"]');
            if (input) {
                input.value = hiddenFields[name];
            }
        });
    });
})();
