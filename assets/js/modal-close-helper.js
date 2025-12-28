/**
 * Global modal close helper
 * Handles Escape key for closing modals that might not be properly bound to Stimulus
 */

document.addEventListener('DOMContentLoaded', () => {
    console.log('Modal close helper initialized');

    // Global Escape key handler for all modals
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            // List of modal IDs to check
            const modalIds = ['viewModal', 'uploadModal', 'replaceModal', 'deleteModal'];

            // Find and close the first visible modal
            for (const modalId of modalIds) {
                const modal = document.getElementById(modalId);
                if (modal && !modal.classList.contains('hidden')) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                    console.log(`Closed modal: ${modalId} via Escape key`);
                    break;
                }
            }
        }
    });
});

/**
 * Global function to close a specific modal by ID
 */
window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        console.log(`Closed modal: ${modalId}`);
    }
};
