/**
 * FRS Lead Pages - Form Handler
 * Handles submission for all lead capture forms
 */
(function() {
    'use strict';

    const API_ENDPOINT = '/wp-json/frs-lead-pages/v1/submit';

    document.querySelectorAll('.frs-lead-form').forEach(form => {
        form.addEventListener('submit', handleSubmit);
    });

    async function handleSubmit(e) {
        e.preventDefault();

        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;

        // Disable form
        btn.disabled = true;
        btn.textContent = 'Sending...';
        form.classList.add('frs-form--submitting');

        // Gather form data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Add metadata
        data.page_type = form.dataset.pageType || '';
        data.page_id = form.dataset.pageId || '';
        data.page_url = window.location.href;
        data.timestamp = new Date().toISOString();

        try {
            const response = await fetch(API_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                showSuccess(form);
            } else {
                const error = await response.json();
                showError(form, error.message || 'Something went wrong. Please try again.');
                resetButton(btn, originalText);
            }
        } catch (err) {
            showError(form, 'Network error. Please check your connection and try again.');
            resetButton(btn, originalText);
        }
    }

    function showSuccess(form) {
        form.innerHTML = `
            <div class="frs-form-success">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9 12l2 2 4-4"/>
                </svg>
                <h3>Thank you!</h3>
                <p>We'll be in touch shortly.</p>
            </div>
        `;
    }

    function showError(form, message) {
        // Remove existing error
        const existing = form.querySelector('.frs-form-error');
        if (existing) existing.remove();

        const errorDiv = document.createElement('div');
        errorDiv.className = 'frs-form-error';
        errorDiv.textContent = message;
        form.prepend(errorDiv);

        form.classList.remove('frs-form--submitting');
    }

    function resetButton(btn, text) {
        btn.disabled = false;
        btn.textContent = text;
    }
})();
