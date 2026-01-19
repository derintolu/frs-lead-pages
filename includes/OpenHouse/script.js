/**
 * Open House Wizard Modal Script
 *
 * Requires frsOpenHouseWizard object from wp_localize_script().
 *
 * @package FRSLeadPages
 */

(function() {
    'use strict';

    var config = window.frsOpenHouseWizard || {};
    var modal = document.getElementById('oh-wizard-modal');
    if (!modal) return;

    var backdrop = modal.querySelector('.oh-modal__backdrop');
    var closeBtn = modal.querySelector('.oh-modal__close');
    var triggerClass = config.triggerClass || 'oh-wizard-trigger';
    var triggerHash = config.triggerHash || 'open-house-wizard';

    function openModal() {
        modal.classList.add('oh-modal--open');
        document.body.classList.add('oh-modal-open');
    }

    function closeModal() {
        modal.classList.remove('oh-modal--open');
        document.body.classList.remove('oh-modal-open');
        if (window.location.hash === '#' + triggerHash) {
            history.replaceState(null, null, window.location.pathname + window.location.search);
        }
    }

    // Close button click
    closeBtn.addEventListener('click', closeModal);

    // Backdrop click
    backdrop.addEventListener('click', closeModal);

    // ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('oh-modal--open')) {
            closeModal();
        }
    });

    // Trigger class click handlers
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains(triggerClass) || e.target.closest('.' + triggerClass)) {
            e.preventDefault();
            openModal();
        }
    });

    // Hash trigger - check on load and on hash change
    function checkHash() {
        if (window.location.hash === '#' + triggerHash) {
            openModal();
        }
    }

    checkHash();
    window.addEventListener('hashchange', checkHash);
})();
