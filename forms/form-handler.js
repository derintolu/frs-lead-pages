/**
 * FRS Lead Pages - Form Handler
 * Handles multi-step navigation and submission for all lead capture forms
 */
(function() {
    'use strict';

    const API_BASE = '/wp-json/frs-lead-pages/v1';

    // Initialize all forms
    document.querySelectorAll('.frs-lead-form').forEach(initForm);

    function initForm(form) {
        // Handle multi-step navigation
        if (form.classList.contains('frs-multistep-form')) {
            initMultiStep(form);
        }

        // Handle form submission
        form.addEventListener('submit', handleSubmit);
    }

    function initMultiStep(form) {
        const totalSteps = parseInt(form.dataset.steps, 10);
        let currentStep = 1;

        // Next button handlers
        form.querySelectorAll('.frs-btn-next').forEach(btn => {
            btn.addEventListener('click', () => {
                if (validateStep(form, currentStep)) {
                    currentStep++;
                    showStep(form, currentStep, totalSteps);
                }
            });
        });

        // Previous button handlers
        form.querySelectorAll('.frs-btn-prev').forEach(btn => {
            btn.addEventListener('click', () => {
                currentStep--;
                showStep(form, currentStep, totalSteps);
            });
        });

        // Auto-advance on button option selection (for radio/checkbox steps)
        form.querySelectorAll('.frs-button-option input[type="radio"]').forEach(input => {
            input.addEventListener('change', () => {
                // Add selected state
                const group = input.closest('.frs-button-group');
                group.querySelectorAll('.frs-button-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                input.closest('.frs-button-option').classList.add('selected');

                // Auto-advance after short delay
                setTimeout(() => {
                    const step = input.closest('.frs-form-step');
                    const nextBtn = step.querySelector('.frs-btn-next');
                    if (nextBtn) {
                        currentStep++;
                        showStep(form, currentStep, totalSteps);
                    }
                }, 300);
            });
        });
    }

    function showStep(form, step, totalSteps) {
        // Hide all steps
        form.querySelectorAll('.frs-form-step').forEach(s => {
            s.style.display = 'none';
        });

        // Show current step
        const currentStepEl = form.querySelector(`[data-step="${step}"]`);
        if (currentStepEl) {
            currentStepEl.style.display = 'block';
        }

        // Update progress bar
        const progressBar = form.querySelector('.frs-form-progress-bar');
        const progressText = form.querySelector('.frs-form-progress-text');

        if (progressBar) {
            const progress = (step / totalSteps) * 100;
            progressBar.style.width = `${progress}%`;
        }

        if (progressText) {
            progressText.textContent = `Step ${step} of ${totalSteps}`;
        }

        // Scroll to top of form
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function validateStep(form, step) {
        const stepEl = form.querySelector(`[data-step="${step}"]`);
        const requiredInputs = stepEl.querySelectorAll('[required]');
        let valid = true;

        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('frs-input-error');
                valid = false;
            } else {
                input.classList.remove('frs-input-error');
            }
        });

        if (!valid) {
            // Show validation message
            const firstInvalid = stepEl.querySelector('.frs-input-error');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }

        return valid;
    }

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

        // Get page_id from hidden field or data attribute
        const pageIdInput = form.querySelector('input[name="page_id"]');
        const pageId = pageIdInput ? pageIdInput.value : (form.dataset.pageId || '');

        // Build full name from first/last if separate
        if (data.first_name && data.last_name && !data.fullName) {
            data.fullName = data.first_name + ' ' + data.last_name;
        }

        // Add metadata
        data.page_url = window.location.href;
        data.timestamp = new Date().toISOString();

        // Build API endpoint with page_id
        const endpoint = pageId
            ? `${API_BASE}/pages/${pageId}/submit`
            : `${API_BASE}/submit`;

        try {
            const response = await fetch(endpoint, {
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
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
