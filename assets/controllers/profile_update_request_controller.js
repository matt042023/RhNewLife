import { Controller } from '@hotwired/stimulus';

/**
 * Controller for profile update request forms
 * Handles field comparison (old vs new), change tracking, and validation
 */
export default class extends Controller {
    static targets = [
        'field',
        'changePreview',
        'changeCount',
        'submitButton',
        'reason',
        'comparison'
    ];

    static values = {
        originalData: Object
    };

    connect() {
        console.log('Profile update request controller connected');

        // Track initial state
        this.changes = new Map();

        // Initial validation
        this.updateChanges();
    }

    /**
     * Handle field change and update preview
     */
    handleFieldChange(event) {
        const field = event.target;
        const fieldName = field.name;
        const newValue = this.getFieldValue(field);
        const originalValue = this.getOriginalValue(fieldName);

        // Check if value has changed
        if (this.hasChanged(originalValue, newValue)) {
            this.changes.set(fieldName, {
                field: fieldName,
                label: this.getFieldLabel(field),
                oldValue: originalValue,
                newValue: newValue
            });
        } else {
            this.changes.delete(fieldName);
        }

        this.updateChanges();
    }

    /**
     * Get field value based on type
     */
    getFieldValue(field) {
        switch (field.type) {
            case 'checkbox':
                return field.checked;

            case 'radio':
                const radioGroup = this.element.querySelectorAll(`[name="${field.name}"]`);
                const checked = Array.from(radioGroup).find(radio => radio.checked);
                return checked ? checked.value : null;

            case 'select-one':
            case 'select-multiple':
                return field.value;

            default:
                return field.value.trim();
        }
    }

    /**
     * Get original value from originalData
     */
    getOriginalValue(fieldName) {
        if (!this.hasOriginalDataValue) {
            // Try to get from data attribute on field
            const field = this.element.querySelector(`[name="${fieldName}"]`);
            return field?.dataset.originalValue || '';
        }

        return this.originalDataValue[fieldName] || '';
    }

    /**
     * Check if value has changed
     */
    hasChanged(originalValue, newValue) {
        // Normalize for comparison
        const original = String(originalValue || '').trim();
        const current = String(newValue || '').trim();

        return original !== current;
    }

    /**
     * Get field label
     */
    getFieldLabel(field) {
        // Try to find associated label
        if (field.id) {
            const label = this.element.querySelector(`label[for="${field.id}"]`);
            if (label) {
                return label.textContent.trim().replace(/\*$/, '');
            }
        }

        // Try data-label attribute
        if (field.dataset.label) {
            return field.dataset.label;
        }

        // Fallback to name
        return this.formatFieldName(field.name);
    }

    /**
     * Format field name to readable label
     */
    formatFieldName(name) {
        const labels = {
            'phone': 'Téléphone',
            'address': 'Adresse',
            'familyStatus': 'Situation familiale',
            'children': 'Nombre d\'enfants',
            'iban': 'IBAN',
            'bic': 'BIC'
        };

        return labels[name] || name.charAt(0).toUpperCase() + name.slice(1);
    }

    /**
     * Update changes display and validation
     */
    updateChanges() {
        const changeCount = this.changes.size;

        // Update change count
        if (this.hasChangeCountTarget) {
            this.changeCountTarget.textContent = changeCount;

            if (changeCount > 0) {
                this.changeCountTarget.classList.add('badge', 'badge-primary');
            } else {
                this.changeCountTarget.classList.remove('badge', 'badge-primary');
            }
        }

        // Update change preview
        if (this.hasChangePreviewTarget) {
            if (changeCount > 0) {
                this.renderChangePreview();
                this.changePreviewTarget.classList.remove('hidden');
            } else {
                this.changePreviewTarget.classList.add('hidden');
            }
        }

        // Update submit button
        this.validateForm();
    }

    /**
     * Render change preview
     */
    renderChangePreview() {
        if (!this.hasChangePreviewTarget) return;

        const changesArray = Array.from(this.changes.values());

        const html = `
            <div class="bg-blue-50 border-l-4 border-blue-500 rounded-lg p-4">
                <h4 class="font-bold text-gray-900 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Modifications à valider (${changesArray.length})
                </h4>
                <div class="space-y-3">
                    ${changesArray.map(change => this.renderChangeItem(change)).join('')}
                </div>
            </div>
        `;

        this.changePreviewTarget.innerHTML = html;
    }

    /**
     * Render individual change item
     */
    renderChangeItem(change) {
        const oldValue = change.oldValue || '<em class="text-gray-400">non renseigné</em>';
        const newValue = change.newValue || '<em class="text-gray-400">vide</em>';

        return `
            <div class="bg-white rounded-lg p-3 border border-gray-200">
                <div class="font-semibold text-gray-700 mb-2">${change.label}</div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-red-600 line-through">${oldValue}</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                    <span class="text-green-600 font-semibold">${newValue}</span>
                </div>
            </div>
        `;
    }

    /**
     * Validate form
     */
    validateForm() {
        const hasChanges = this.changes.size > 0;
        const hasReason = this.hasReasonTarget ? this.reasonTarget.value.trim().length >= 10 : true;

        const isValid = hasChanges && hasReason;

        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = !isValid;

            if (isValid) {
                this.submitButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                this.submitButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        // Update reason field validation
        if (this.hasReasonTarget) {
            if (hasChanges && !hasReason) {
                this.showReasonError();
            } else {
                this.hideReasonError();
            }
        }
    }

    /**
     * Show reason validation error
     */
    showReasonError() {
        const errorId = 'reason-error';
        let errorElement = this.element.querySelector(`#${errorId}`);

        if (!errorElement) {
            errorElement = document.createElement('p');
            errorElement.id = errorId;
            errorElement.className = 'text-red-600 text-sm mt-1';
            errorElement.textContent = 'Veuillez expliquer le motif de votre demande (minimum 10 caractères)';
            this.reasonTarget.parentNode.appendChild(errorElement);
        }

        this.reasonTarget.classList.add('border-red-500');
    }

    /**
     * Hide reason validation error
     */
    hideReasonError() {
        const errorElement = this.element.querySelector('#reason-error');

        if (errorElement) {
            errorElement.remove();
        }

        this.reasonTarget?.classList.remove('border-red-500');
    }

    /**
     * Toggle comparison view
     */
    toggleComparison(event) {
        event.preventDefault();

        if (this.hasComparisonTarget) {
            this.comparisonTarget.classList.toggle('hidden');
        }
    }

    /**
     * Reset form to original values
     */
    resetForm(event) {
        event.preventDefault();

        const confirmed = confirm('Êtes-vous sûr de vouloir annuler toutes les modifications ?');

        if (!confirmed) {
            return;
        }

        // Reset all fields to original values
        this.fieldTargets.forEach(field => {
            const fieldName = field.name;
            const originalValue = this.getOriginalValue(fieldName);

            switch (field.type) {
                case 'checkbox':
                    field.checked = originalValue === 'true' || originalValue === true;
                    break;

                case 'radio':
                    if (field.value === originalValue) {
                        field.checked = true;
                    }
                    break;

                default:
                    field.value = originalValue || '';
            }
        });

        // Clear changes
        this.changes.clear();
        this.updateChanges();
    }

    /**
     * Confirm submission
     */
    confirmSubmit(event) {
        const changeCount = this.changes.size;

        if (changeCount === 0) {
            event.preventDefault();
            alert('Aucune modification détectée. Veuillez modifier au moins un champ.');
            return false;
        }

        const confirmed = confirm(
            `Vous allez soumettre ${changeCount} modification${changeCount > 1 ? 's' : ''} pour validation.\n\n` +
            'Votre demande sera examinée par le service RH.\n\n' +
            'Continuer ?'
        );

        if (!confirmed) {
            event.preventDefault();
            return false;
        }

        return true;
    }
}
