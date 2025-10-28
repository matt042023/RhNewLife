import { Controller } from '@hotwired/stimulus';

/**
 * Controller for user creation and edit forms
 * Handles real-time validation, field dependencies, and form state
 */
export default class extends Controller {
    static targets = [
        'firstName',
        'lastName',
        'email',
        'phone',
        'position',
        'structure',
        'hiringDate',
        'address',
        'familyStatus',
        'children',
        'iban',
        'bic',
        'sendEmail',
        'submitButton',
        'requiredSection',
        'optionalSection'
    ];

    static values = {
        isEdit: Boolean
    };

    connect() {
        console.log('User form controller connected');
        this.validateForm();

        // Auto-format phone on input
        if (this.hasPhoneTarget) {
            this.phoneTarget.addEventListener('input', () => this.formatPhone());
        }

        // Auto-format IBAN on input
        if (this.hasIbanTarget) {
            this.ibanTarget.addEventListener('input', () => this.formatIban());
        }

        // Auto-uppercase BIC
        if (this.hasBicTarget) {
            this.bicTarget.addEventListener('input', () => this.formatBic());
        }
    }

    /**
     * Validate required fields and enable/disable submit button
     */
    validateForm() {
        const requiredFields = [
            this.firstNameTarget,
            this.lastNameTarget,
            this.emailTarget,
            this.positionTarget,
            this.structureTarget
        ];

        const allFilled = requiredFields.every(field => field.value.trim() !== '');

        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = !allFilled;

            if (allFilled) {
                this.submitButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                this.submitButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
    }

    /**
     * Format phone number as: 06 12 34 56 78
     */
    formatPhone() {
        let value = this.phoneTarget.value.replace(/\s/g, '');

        if (value.length > 0) {
            value = value.match(/.{1,2}/g)?.join(' ') || value;
        }

        this.phoneTarget.value = value.substring(0, 14); // Max: 06 12 34 56 78
    }

    /**
     * Format IBAN with spaces every 4 characters
     */
    formatIban() {
        let value = this.ibanTarget.value.replace(/\s/g, '').toUpperCase();

        if (value.length > 0) {
            value = value.match(/.{1,4}/g)?.join(' ') || value;
        }

        this.ibanTarget.value = value.substring(0, 34); // Max IBAN length
        this.validateIban(value.replace(/\s/g, ''));
    }

    /**
     * Validate IBAN format and show feedback
     */
    validateIban(iban) {
        const ibanRegex = /^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/;
        const isValid = iban.length === 0 || (ibanRegex.test(iban) && iban.length >= 15);

        if (iban.length > 0 && !isValid) {
            this.ibanTarget.classList.add('border-red-500');
            this.ibanTarget.classList.remove('border-gray-300');
        } else {
            this.ibanTarget.classList.remove('border-red-500');
            this.ibanTarget.classList.add('border-gray-300');
        }
    }

    /**
     * Format BIC to uppercase
     */
    formatBic() {
        this.bicTarget.value = this.bicTarget.value.toUpperCase().substring(0, 11);
        this.validateBic(this.bicTarget.value);
    }

    /**
     * Validate BIC format
     */
    validateBic(bic) {
        const bicRegex = /^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/;
        const isValid = bic.length === 0 || bicRegex.test(bic);

        if (bic.length > 0 && !isValid) {
            this.bicTarget.classList.add('border-red-500');
            this.bicTarget.classList.remove('border-gray-300');
        } else {
            this.bicTarget.classList.remove('border-red-500');
            this.bicTarget.classList.add('border-gray-300');
        }
    }

    /**
     * Validate email format
     */
    validateEmail() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = emailRegex.test(this.emailTarget.value);

        if (this.emailTarget.value.length > 0 && !isValid) {
            this.emailTarget.classList.add('border-red-500');
            this.emailTarget.classList.remove('border-gray-300');
        } else {
            this.emailTarget.classList.remove('border-red-500');
            this.emailTarget.classList.add('border-gray-300');
        }

        this.validateForm();
    }

    /**
     * Toggle optional section visibility
     */
    toggleOptionalSection() {
        if (this.hasOptionalSectionTarget) {
            this.optionalSectionTarget.classList.toggle('hidden');
        }
    }

    /**
     * Auto-generate email suggestion based on first and last name
     */
    suggestEmail() {
        if (!this.isEditValue && this.emailTarget.value === '') {
            const firstName = this.firstNameTarget.value.toLowerCase().trim();
            const lastName = this.lastNameTarget.value.toLowerCase().trim();

            if (firstName && lastName) {
                const suggestion = `${firstName}.${lastName}@newlife.com`;
                this.emailTarget.value = suggestion;
                this.validateEmail();
            }
        }
    }

    /**
     * Update children count when family status changes
     */
    updateFamilyStatus() {
        if (this.hasFamilyStatusTarget && this.hasChildrenTarget) {
            const status = this.familyStatusTarget.value;

            // If célibataire, suggest 0 children
            if (status === 'Célibataire' && this.childrenTarget.value === '') {
                this.childrenTarget.value = '0';
            }
        }
    }

    /**
     * Show confirmation before submission
     */
    confirmSubmit(event) {
        if (!this.isEditValue && this.hasSendEmailTarget && this.sendEmailTarget.checked) {
            const confirmed = confirm(
                'Un email d\'activation sera envoyé à ' + this.emailTarget.value + '. Continuer ?'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        }
    }
}
