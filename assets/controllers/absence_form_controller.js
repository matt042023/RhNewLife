import { Controller } from '@hotwired/stimulus';

/**
 * Absence Form Controller
 *
 * Handles real-time validation for absence request form:
 * - Check counter balance when type changes
 * - Calculate working days between dates
 * - Check for overlapping absences
 * - Show/hide justification field based on type
 *
 * Usage:
 * <form data-controller="absence-form">
 *   <select data-absence-form-target="absenceType" data-action="change->absence-form#onTypeChange">
 *   <input data-absence-form-target="startDate" data-action="change->absence-form#calculateWorkingDays">
 *   <input data-absence-form-target="endDate" data-action="change->absence-form#calculateWorkingDays">
 * </form>
 */
export default class extends Controller {
    static targets = [
        'absenceType',
        'startDate',
        'endDate',
        'justificationField',
        'counterInfo',
        'counterValue',
        'workingDaysDisplay',
        'workingDaysValue',
        'validationAlerts',
        'submitButton'
    ];

    static values = {
        checkOverlapUrl: { type: String, default: '/api/absences/check-overlap' },
        checkBalanceUrl: { type: String, default: '/api/absences/check-balance' },
        calculateDaysUrl: { type: String, default: '/api/absences/calculate-working-days' }
    };

    connect() {
        console.log('Absence form controller connected');
        this.debounceTimer = null;
    }

    /**
     * Handle absence type change
     */
    onTypeChange(event) {
        const typeId = event.target.value;

        if (!typeId) {
            this.hideCounterInfo();
            return;
        }

        // Get selected option to extract data attributes
        const selectedOption = event.target.options[event.target.selectedIndex];
        const requiresJustification = selectedOption.dataset.requiresJustification === '1';
        const deductFromCounter = selectedOption.dataset.deductFromCounter === '1';

        // Show/hide justification field
        this.toggleJustificationField(requiresJustification);

        // Fetch and display counter balance if needed
        if (deductFromCounter) {
            this.fetchCounterBalance(typeId);
        } else {
            this.hideCounterInfo();
        }

        // Recalculate working days with new type
        this.calculateWorkingDays();
    }

    /**
     * Toggle justification field visibility
     */
    toggleJustificationField(show) {
        if (!this.hasJustificationFieldTarget) {
            return;
        }

        if (show) {
            this.justificationFieldTarget.classList.remove('hidden');
        } else {
            this.justificationFieldTarget.classList.add('hidden');
        }
    }

    /**
     * Fetch counter balance for selected type
     */
    async fetchCounterBalance(typeId) {
        if (!this.hasCheckBalanceUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.checkBalanceUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    absenceTypeId: typeId
                })
            });

            if (!response.ok) {
                throw new Error('Failed to fetch counter balance');
            }

            const data = await response.json();

            if (data.hasCounter) {
                this.showCounterInfo(data.remaining, data.earned);
            } else {
                this.hideCounterInfo();
            }
        } catch (error) {
            console.error('Error fetching counter balance:', error);
            this.hideCounterInfo();
        }
    }

    /**
     * Show counter information
     */
    showCounterInfo(remaining, earned) {
        if (!this.hasCounterInfoTarget || !this.hasCounterValueTarget) {
            return;
        }

        this.counterValueTarget.textContent = `${remaining.toFixed(1)} / ${earned.toFixed(1)}`;
        this.counterInfoTarget.style.display = 'block';

        // Add warning if balance is low
        if (remaining < 5) {
            this.counterInfoTarget.classList.remove('bg-blue-50');
            this.counterInfoTarget.classList.add('bg-yellow-50');
            this.counterValueTarget.classList.add('text-yellow-800');
        } else {
            this.counterInfoTarget.classList.add('bg-blue-50');
            this.counterInfoTarget.classList.remove('bg-yellow-50');
            this.counterValueTarget.classList.remove('text-yellow-800');
        }
    }

    /**
     * Hide counter information
     */
    hideCounterInfo() {
        if (!this.hasCounterInfoTarget) {
            return;
        }

        this.counterInfoTarget.style.display = 'none';
    }

    /**
     * Calculate working days between dates
     */
    calculateWorkingDays() {
        // Clear existing timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Debounce calculation
        this.debounceTimer = setTimeout(() => {
            this.performCalculation();
        }, 500);
    }

    /**
     * Perform the actual calculation
     */
    async performCalculation() {
        if (!this.hasStartDateTarget || !this.hasEndDateTarget) {
            return;
        }

        const startDate = this.startDateTarget.value;
        const endDate = this.endDateTarget.value;
        const typeId = this.hasAbsenceTypeTarget ? this.absenceTypeTarget.value : null;

        if (!startDate || !endDate) {
            this.hideWorkingDaysDisplay();
            return;
        }

        // Basic validation: end date must be after start date
        if (new Date(endDate) < new Date(startDate)) {
            this.showValidationAlert('error', 'La date de fin doit être après la date de début');
            this.hideWorkingDaysDisplay();
            return;
        }

        // Calculate working days via API
        try {
            const response = await fetch('/api/absences/calculate-working-days', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    startDate: startDate,
                    endDate: endDate
                })
            });

            if (!response.ok) {
                throw new Error('Failed to calculate working days');
            }

            const data = await response.json();
            this.showWorkingDaysDisplay(data.workingDays);

            // Check for overlaps
            this.checkOverlap(startDate, endDate);

            // Check balance if type is selected
            if (typeId && data.workingDays > 0) {
                this.checkBalance(typeId, data.workingDays);
            }
        } catch (error) {
            console.error('Error calculating working days:', error);
            this.showValidationAlert('error', 'Erreur lors du calcul de la durée');
        }
    }

    /**
     * Show working days display
     */
    showWorkingDaysDisplay(days) {
        if (!this.hasWorkingDaysDisplayTarget || !this.hasWorkingDaysValueTarget) {
            return;
        }

        const daysText = days === 1 ? 'jour ouvré' : 'jours ouvrés';
        this.workingDaysValueTarget.textContent = `${days.toFixed(1)} ${daysText}`;
        this.workingDaysDisplayTarget.style.display = 'block';
    }

    /**
     * Hide working days display
     */
    hideWorkingDaysDisplay() {
        if (!this.hasWorkingDaysDisplayTarget) {
            return;
        }

        this.workingDaysDisplayTarget.style.display = 'none';
    }

    /**
     * Check for overlapping absences
     */
    async checkOverlap(startDate, endDate) {
        if (!this.hasCheckOverlapUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.checkOverlapUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    startDate: startDate,
                    endDate: endDate
                })
            });

            if (!response.ok) {
                throw new Error('Failed to check overlap');
            }

            const data = await response.json();

            if (data.hasOverlap) {
                this.showValidationAlert(
                    'warning',
                    `Attention : Cette période chevauche une autre absence (${data.overlappingAbsence.type})`
                );
                this.disableSubmit();
            } else {
                this.clearValidationAlerts();
                this.enableSubmit();
            }
        } catch (error) {
            console.error('Error checking overlap:', error);
        }
    }

    /**
     * Check if user has sufficient balance
     */
    async checkBalance(typeId, workingDays) {
        if (!this.hasCheckBalanceUrlValue) {
            return;
        }

        try {
            const response = await fetch(this.checkBalanceUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    absenceTypeId: typeId,
                    workingDays: workingDays
                })
            });

            if (!response.ok) {
                throw new Error('Failed to check balance');
            }

            const data = await response.json();

            if (data.hasCounter && !data.hasSufficientBalance) {
                this.showValidationAlert(
                    'error',
                    `Solde insuffisant : vous demandez ${workingDays.toFixed(1)} jours mais il ne vous reste que ${data.remaining.toFixed(1)} jours`
                );
                this.disableSubmit();
            } else if (data.hasCounter && data.remaining - workingDays < 2) {
                this.showValidationAlert(
                    'warning',
                    `Attention : après cette absence, il ne vous restera que ${(data.remaining - workingDays).toFixed(1)} jours`
                );
                this.enableSubmit();
            } else {
                this.clearValidationAlerts();
                this.enableSubmit();
            }
        } catch (error) {
            console.error('Error checking balance:', error);
        }
    }

    /**
     * Show validation alert
     */
    showValidationAlert(type, message) {
        if (!this.hasValidationAlertsTarget) {
            return;
        }

        const bgColor = type === 'error' ? 'bg-red-50 border-red-200 text-red-800' :
                       type === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-800' :
                       'bg-blue-50 border-blue-200 text-blue-800';

        const icon = type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️';

        this.validationAlertsTarget.innerHTML = `
            <div class="p-4 border rounded-md ${bgColor}">
                <div class="flex items-center">
                    <span class="text-lg mr-2">${icon}</span>
                    <span class="text-sm">${message}</span>
                </div>
            </div>
        `;
    }

    /**
     * Clear validation alerts
     */
    clearValidationAlerts() {
        if (!this.hasValidationAlertsTarget) {
            return;
        }

        this.validationAlertsTarget.innerHTML = '';
    }

    /**
     * Disable submit button
     */
    disableSubmit() {
        if (!this.hasSubmitButtonTarget) {
            return;
        }

        this.submitButtonTarget.disabled = true;
        this.submitButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
    }

    /**
     * Enable submit button
     */
    enableSubmit() {
        if (!this.hasSubmitButtonTarget) {
            return;
        }

        this.submitButtonTarget.disabled = false;
        this.submitButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        console.log('Absence form controller disconnected');
    }
}
