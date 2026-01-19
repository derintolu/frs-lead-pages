/**
 * Special Event Wizard Modal Script
 *
 * Requires frsSpecialEventWizard object from wp_localize_script().
 *
 * @package FRSLeadPages
 */

(function() {
    'use strict';

    var config = window.frsSpecialEventWizard || {};
    var modal = document.getElementById('se-wizard-modal');
    if (!modal) return;

    var closeBtn = modal.querySelector('.se-modal__close');
    var triggerClass = config.triggerClass || 'se-wizard-trigger';
    var triggerHash = config.triggerHash || 'special-event-wizard';

    function openModal() {
        modal.classList.add('se-modal--open');
        document.body.classList.add('se-modal-open');
    }

    function closeModal() {
        modal.classList.remove('se-modal--open');
        document.body.classList.remove('se-modal-open');
    }

    closeBtn.addEventListener('click', closeModal);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains(triggerClass) || e.target.closest('.' + triggerClass)) {
            e.preventDefault();
            openModal();
        }
    });

    if (window.location.hash === '#' + triggerHash) {
        openModal();
    }
})();
