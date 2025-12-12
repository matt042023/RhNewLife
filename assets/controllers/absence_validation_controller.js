import { Controller } from '@hotwired/stimulus';

/**
 * Absence Validation Controller
 *
 * Handles the admin validation form for absences:
 * - Show/hide rejection reason field based on action
 * - Show/hide force checkbox based on action and justification status
 * - Form validation before submission
 *
 * Usage:
 * <form data-controller="absence-validation">
 *   <input type="radio" name="action" value="validate" data-action="change->absence-validation#onActionChange">
 *   <input type="radio" name="action" value="reject" data-action="change->absence-validation#onActionChange">
 *   <textarea data-absence-validation-target="rejectionReason"></textarea>
 *   <input type="checkbox" data-absence-validation-target="forceCheckbox">
 * </form>
 */
export default class extends Controller {
    static targets = [
        'rejectionReasonField',
        'forceCheckboxField',
        'submitButton'
    ];

    connect() {
        console.log('Absence validation controller connected');

        // Initialize form state based on initial selection
        this.updateFormState();
    }

    /**
     * Handle action radio button change
     */
    onActionChange(event) {
        this.updateFormState();
    }

    /**
     * Update form state based on selected action
     */
    updateFormState() {
        const selectedAction = this.getSelectedAction();

        if (selectedAction === 'validate') {
            this.showValidateFields();
        } else if (selectedAction === 'reject') {
            this.showRejectFields();
        }
    }

    /**
     * Get currently selected action
     */
    getSelectedAction() {
        const actionRadios = this.element.querySelectorAll('input[name*="action"]');

        for (const radio of actionRadios) {
            if (radio.checked) {
                return radio.value;
            }
        }

        return null;
    }

    /**
     * Show fields for validation action
     */
    showValidateFields() {
        // Hide rejection reason
        if (this.hasRejectionReasonFieldTarget) {
            this.rejectionReasonFieldTarget.style.display = 'none';

            // Make rejection reason not required
            const textarea = this.rejectionReasonFieldTarget.querySelector('textarea');
            if (textarea) {
                textarea.removeAttribute('required');
            }
        }

        // Show force checkbox (if exists)
        if (this.hasForceCheckboxFieldTarget) {
            this.forceCheckboxFieldTarget.style.display = 'block';
        }
    }

    /**
     * Show fields for rejection action
     */
    showRejectFields() {
        // Show rejection reason
        if (this.hasRejectionReasonFieldTarget) {
            this.rejectionReasonFieldTarget.style.display = 'block';

            // Make rejection reason required
            const textarea = this.rejectionReasonFieldTarget.querySelector('textarea');
            if (textarea) {
                textarea.setAttribute('required', 'required');
            }
        }

        // Hide force checkbox
        if (this.hasForceCheckboxFieldTarget) {
            this.forceCheckboxFieldTarget.style.display = 'none';
        }
    }

    /**
     * Validate form before submission
     */
    validateForm(event) {
        const selectedAction = this.getSelectedAction();

        if (!selectedAction) {
            event.preventDefault();
            this.showError('Veuillez sélectionner une action (valider ou refuser)');
            return false;
        }

        if (selectedAction === 'reject') {
            const rejectionReason = this.getRejectionReasonValue();

            if (!rejectionReason || rejectionReason.trim() === '') {
                event.preventDefault();
                this.showError('Le motif de refus est obligatoire');
                return false;
            }
        }

        // Show loading state
        this.setLoadingState(true);

        return true;
    }

    /**
     * Get rejection reason value
     */
    getRejectionReasonValue() {
        if (!this.hasRejectionReasonFieldTarget) {
            return '';
        }

        const textarea = this.rejectionReasonFieldTarget.querySelector('textarea');
        return textarea ? textarea.value : '';
    }

    /**
     * Show error message
     */
    showError(message) {
        // Try to find an error container
        let errorContainer = this.element.querySelector('[data-absence-validation-target="errorContainer"]');

        if (!errorContainer) {
            // Create error container if it doesn't exist
            errorContainer = document.createElement('div');
            errorContainer.className = 'p-4 mb-4 bg-red-50 border border-red-200 text-red-800 rounded-md';
            errorContainer.setAttribute('data-absence-validation-target', 'errorContainer');
            this.element.insertBefore(errorContainer, this.element.firstChild);
        }

        errorContainer.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-medium">${message}</span>
            </div>
        `;
        errorContainer.style.display = 'block';

        // Scroll to error
        errorContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    /**
     * Clear error message
     */
    clearError() {
        const errorContainer = this.element.querySelector('[data-absence-validation-target="errorContainer"]');

        if (errorContainer) {
            errorContainer.style.display = 'none';
            errorContainer.innerHTML = '';
        }
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
                <svg class="animate-spin inline w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Traitement en cours...
            `;
        } else {
            this.submitButtonTarget.disabled = false;

            if (this.originalButtonHTML) {
                this.submitButtonTarget.innerHTML = this.originalButtonHTML;
            }
        }
    }

    /**
     * Confirm rejection with user
     */
    confirmRejection(event) {
        const selectedAction = this.getSelectedAction();

        if (selectedAction === 'reject') {
            const confirmed = confirm(
                'Êtes-vous sûr de vouloir refuser cette demande d\'absence ?\n\n' +
                'L\'employé recevra une notification par email avec le motif du refus.'
            );

            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        }

        return true;
    }

    /**
     * Confirm force validation without justification
     */
    confirmForceValidation(event) {
        const selectedAction = this.getSelectedAction();

        if (selectedAction === 'validate' && this.hasForceCheckboxFieldTarget) {
            const forceCheckbox = this.forceCheckboxFieldTarget.querySelector('input[type="checkbox"]');

            if (forceCheckbox && forceCheckbox.checked) {
                const confirmed = confirm(
                    'Attention : Vous allez valider cette absence sans justificatif valide.\n\n' +
                    'Cette action sera tracée dans l\'historique.\n\n' +
                    'Êtes-vous sûr de vouloir continuer ?'
                );

                if (!confirmed) {
                    event.preventDefault();
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Handle form submission with all validations
     */
    onSubmit(event) {
        // Clear previous errors
        this.clearError();

        // Validate form
        if (!this.validateForm(event)) {
            return;
        }

        // Confirm rejection if needed
        if (!this.confirmRejection(event)) {
            return;
        }

        // Confirm force validation if needed
        if (!this.confirmForceValidation(event)) {
            this.setLoadingState(false);
            return;
        }

        // Form will submit naturally
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        console.log('Absence validation controller disconnected');
    }
}
