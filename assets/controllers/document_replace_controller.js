import { Controller } from '@hotwired/stimulus';

/**
 * Document Replace Controller
 *
 * Handles document replacement via AJAX
 */
export default class extends Controller {
    static targets = ['form', 'submitButton'];

    /**
     * Handle form submission
     */
    submit(event) {
        event.preventDefault();

        const form = this.hasFormTarget ? this.formTarget : event.target;
        const formData = new FormData(form);

        // Validate file is selected
        const fileInput = form.querySelector('input[type="file"]');
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            this.showError('Veuillez sélectionner un fichier');
            return;
        }

        // Show loading state
        this.setLoadingState(true);

        // Send AJAX request
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            this.setLoadingState(false);

            if (data.success) {
                // Close modal
                const modal = document.getElementById('replaceModal');
                if (modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                }

                // Show success message
                this.showNotification('success', data.message || 'Document remplacé avec succès');

                // Reload page to show updated document
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                this.showError(data.message || 'Erreur lors du remplacement');
            }
        })
        .catch(error => {
            this.setLoadingState(false);
            console.error('Replace error:', error);
            this.showError('Erreur lors du remplacement du document');
        });
    }

    /**
     * Set loading state on submit button
     */
    setLoadingState(loading) {
        if (!this.hasSubmitButtonTarget) {
            return;
        }

        if (loading) {
            this.submitButtonTarget.disabled = true;
            this.originalButtonHTML = this.submitButtonTarget.innerHTML;
            this.submitButtonTarget.innerHTML = `
                <svg class="animate-spin w-5 h-5 inline-block mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Remplacement en cours...
            `;
        } else {
            this.submitButtonTarget.disabled = false;
            if (this.originalButtonHTML) {
                this.submitButtonTarget.innerHTML = this.originalButtonHTML;
            }
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        this.showNotification('error', message);
    }

    /**
     * Show notification
     */
    showNotification(type, message) {
        // Try to use existing notification system
        if (window.showNotification) {
            window.showNotification(type, message);
            return;
        }

        // Fallback to simple toast
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-600' : 'bg-red-600'
        }`;
        notification.textContent = message;
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.opacity = '1';
        }, 10);

        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
}
