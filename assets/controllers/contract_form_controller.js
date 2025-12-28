import { Controller } from '@hotwired/stimulus';

/**
 * Controller for contract creation and edit forms
 * Handles date validation, salary calculations, working days, and real-time updates
 */
export default class extends Controller {
    static targets = [
        'type',
        'startDate',
        'endDate',
        'endDateField',
        'essaiEndDate',
        'essaiField',
        'baseSalary',
        'activityRate',
        'weeklyHours',
        'villa',
        'workingDay',
        'salaryPreview',
        'annualSalary',
        'monthlySalary',
        'hourlySalary',
        'submitButton'
    ];

    static values = {
        isEdit: Boolean
    };

    connect() {
        console.log('Contract form controller connected');
        this.updateFormBasedOnType();
        this.calculateSalaries();
        this.updateWorkingDaysCount();
    }

    /**
     * Update form fields visibility based on contract type
     */
    updateFormBasedOnType() {
        const contractType = this.typeTarget.value;

        // Show/hide end date for CDD, Stage, Alternance
        if (this.hasEndDateFieldTarget) {
            if (['CDD', 'Stage', 'Alternance'].includes(contractType)) {
                this.endDateFieldTarget.classList.remove('hidden');
                this.endDateTarget.required = true;
            } else {
                this.endDateFieldTarget.classList.add('hidden');
                this.endDateTarget.required = false;
                this.endDateTarget.value = '';
            }
        }

        // Show/hide trial period for CDI and CDD
        if (this.hasEssaiFieldTarget) {
            if (['CDI', 'CDD'].includes(contractType)) {
                this.essaiFieldTarget.classList.remove('hidden');
            } else {
                this.essaiFieldTarget.classList.add('hidden');
                if (this.hasEssaiEndDateTarget) {
                    this.essaiEndDateTarget.value = '';
                }
            }
        }

        this.validateDates();
    }

    /**
     * Validate date coherence (start < end, start < essai < end)
     */
    validateDates() {
        const startDate = new Date(this.startDateTarget.value);
        const endDate = this.hasEndDateTarget ? new Date(this.endDateTarget.value) : null;
        const essaiEndDate = this.hasEssaiEndDateTarget ? new Date(this.essaiEndDateTarget.value) : null;

        let isValid = true;
        let errorMessage = '';

        // Check start date is not in the past (only for new contracts)
        if (!this.isEditValue) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (startDate < today) {
                isValid = false;
                errorMessage = 'La date de début ne peut pas être dans le passé';
                this.startDateTarget.classList.add('border-red-500');
            } else {
                this.startDateTarget.classList.remove('border-red-500');
            }
        }

        // Check end date is after start date
        if (endDate && !isNaN(endDate.getTime()) && !isNaN(startDate.getTime())) {
            if (endDate <= startDate) {
                isValid = false;
                errorMessage = 'La date de fin doit être après la date de début';
                this.endDateTarget.classList.add('border-red-500');
            } else {
                this.endDateTarget.classList.remove('border-red-500');
            }
        }

        // Check trial period is between start and end
        if (essaiEndDate && !isNaN(essaiEndDate.getTime()) && !isNaN(startDate.getTime())) {
            if (essaiEndDate <= startDate) {
                isValid = false;
                errorMessage = 'La fin de période d\'essai doit être après la date de début';
                this.essaiEndDateTarget.classList.add('border-red-500');
            } else if (endDate && essaiEndDate > endDate) {
                isValid = false;
                errorMessage = 'La fin de période d\'essai doit être avant la date de fin du contrat';
                this.essaiEndDateTarget.classList.add('border-red-500');
            } else {
                this.essaiEndDateTarget.classList.remove('border-red-500');
            }
        }

        // Update submit button state
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = !isValid;

            if (isValid) {
                this.submitButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                this.submitButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        return isValid;
    }

    /**
     * Calculate and display salary variations based on activity rate
     */
    calculateSalaries() {
        if (!this.hasBaseSalaryTarget) return;

        const baseSalary = parseFloat(this.baseSalaryTarget.value) || 0;
        const activityRate = this.hasActivityRateTarget ? parseFloat(this.activityRateTarget.value) || 100 : 100;
        const weeklyHours = this.hasWeeklyHoursTarget ? parseFloat(this.weeklyHoursTarget.value) || 35 : 35;

        // Calculate adjusted salary based on activity rate
        const adjustedMonthlySalary = baseSalary * (activityRate / 100);
        const annualSalary = adjustedMonthlySalary * 12;

        // Calculate hourly rate (assuming 35h = full time base)
        const monthlyHours = (weeklyHours * 52) / 12;
        const hourlySalary = monthlyHours > 0 ? adjustedMonthlySalary / monthlyHours : 0;

        // Update preview displays
        if (this.hasMonthlySalaryTarget) {
            this.monthlySalaryTarget.textContent = this.formatCurrency(adjustedMonthlySalary);
        }

        if (this.hasAnnualSalaryTarget) {
            this.annualSalaryTarget.textContent = this.formatCurrency(annualSalary);
        }

        if (this.hasHourlySalaryTarget) {
            this.hourlySalaryTarget.textContent = this.formatCurrency(hourlySalary);
        }

        // Show preview section
        if (this.hasSalaryPreviewTarget) {
            this.salaryPreviewTarget.classList.remove('hidden');
        }
    }

    /**
     * Format number as currency (EUR)
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    /**
     * Update working days count and validate minimum
     */
    updateWorkingDaysCount() {
        if (!this.hasWorkingDayTarget) return;

        const checkedDays = this.workingDayTargets.filter(checkbox => checkbox.checked);
        const count = checkedDays.length;

        // Show count indicator
        const countElement = document.getElementById('working-days-count');
        if (countElement) {
            countElement.textContent = `${count} jour${count > 1 ? 's' : ''} sélectionné${count > 1 ? 's' : ''}`;

            // Warning if less than typical work week
            if (count < 5 && count > 0) {
                countElement.classList.add('text-amber-600');
                countElement.classList.remove('text-gray-600');
            } else {
                countElement.classList.remove('text-amber-600');
                countElement.classList.add('text-gray-600');
            }
        }

        // Validate at least one day selected
        const isValid = count > 0;
        this.workingDayTargets.forEach(checkbox => {
            if (!isValid) {
                checkbox.closest('.form-check')?.classList.add('border-red-300');
            } else {
                checkbox.closest('.form-check')?.classList.remove('border-red-300');
            }
        });
    }

    /**
     * Auto-suggest trial period end date based on contract type and start date
     */
    suggestTrialPeriod() {
        if (!this.hasEssaiEndDateTarget || !this.startDateTarget.value) return;

        const startDate = new Date(this.startDateTarget.value);
        const contractType = this.typeTarget.value;

        let trialMonths = 0;

        // Typical trial periods by contract type
        switch (contractType) {
            case 'CDI':
                trialMonths = 3; // 3 months for standard CDI
                break;
            case 'CDD':
                trialMonths = 1; // 1 month for CDD
                break;
            default:
                return;
        }

        const essaiEndDate = new Date(startDate);
        essaiEndDate.setMonth(essaiEndDate.getMonth() + trialMonths);

        // Format date as YYYY-MM-DD for input
        const formattedDate = essaiEndDate.toISOString().split('T')[0];
        this.essaiEndDateTarget.value = formattedDate;

        this.validateDates();
    }

    /**
     * Toggle all working days
     */
    toggleAllWorkingDays(event) {
        const checked = event.target.checked;

        this.workingDayTargets.forEach(checkbox => {
            checkbox.checked = checked;
        });

        this.updateWorkingDaysCount();
    }

    /**
     * Set standard work week (Monday to Friday)
     */
    setStandardWorkWeek() {
        const standardDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

        this.workingDayTargets.forEach(checkbox => {
            const dayValue = checkbox.value;
            checkbox.checked = standardDays.includes(dayValue);
        });

        this.updateWorkingDaysCount();
    }

    /**
     * Validate form before submission
     */
    validateForm(event) {
        const isValid = this.validateDates();

        if (!isValid) {
            event.preventDefault();
            alert('Veuillez corriger les erreurs dans le formulaire avant de continuer.');
            return false;
        }

        // Check at least one working day selected
        const checkedDays = this.workingDayTargets.filter(checkbox => checkbox.checked);
        if (checkedDays.length === 0) {
            event.preventDefault();
            alert('Veuillez sélectionner au moins un jour travaillé.');
            return false;
        }

        return true;
    }
}
