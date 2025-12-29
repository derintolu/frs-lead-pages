/**
 * Customer Spotlight Wizard Modal Script
 *
 * Requires frsCustomerSpotlightWizard object from wp_localize_script().
 *
 * @package FRSLeadPages
 */

(function() {
    'use strict';

    var config = window.frsCustomerSpotlightWizard || {};
    var modal = document.getElementById('cs-wizard-modal');
    if (!modal) return;

    var backdrop = modal.querySelector('.cs-modal__backdrop');
    var closeBtn = modal.querySelector('.cs-modal__close');
    var triggerClass = config.triggerClass || 'cs-wizard-trigger';
    var triggerHash = config.triggerHash || 'customer-spotlight-wizard';

    function openModal() {
        modal.classList.add('cs-modal--open');
        document.body.classList.add('cs-modal-open');
    }

    function closeModal() {
        modal.classList.remove('cs-modal--open');
        document.body.classList.remove('cs-modal-open');
        if (window.location.hash === '#' + triggerHash) {
            history.replaceState(null, null, window.location.pathname + window.location.search);
        }
    }

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('cs-modal--open')) {
            closeModal();
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains(triggerClass) || e.target.closest('.' + triggerClass)) {
            e.preventDefault();
            openModal();
        }
    });

    function checkHash() {
        if (window.location.hash === '#' + triggerHash) {
            openModal();
        }
    }

    checkHash();
    window.addEventListener('hashchange', checkHash);
})();
