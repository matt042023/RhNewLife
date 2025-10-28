import { Controller } from '@hotwired/stimulus';

/**
 * Controller for profile/document completion tracking
 * Displays real-time completion progress, missing fields, and completion status
 */
export default class extends Controller {
    static targets = [
        'progressBar',
        'progressText',
        'percentage',
        'missingList',
        'completionBadge',
        'completeButton'
    ];

    static values = {
        requiredFields: Array,
        userId: Number,
        autoRefresh: Boolean,
        refreshInterval: { type: Number, default: 30000 } // 30 seconds
    };

    connect() {
        console.log('Document completion controller connected');

        // Initial calculation
        this.calculateCompletion();

        // Auto-refresh if enabled
        if (this.autoRefreshValue) {
            this.startAutoRefresh();
        }

        // Listen for field updates
        this.element.addEventListener('input', this.handleFieldUpdate.bind(this));
        this.element.addEventListener('change', this.handleFieldUpdate.bind(this));
    }

    /**
     * Calculate completion percentage based on filled fields
     */
    calculateCompletion() {
        const requiredFields = this.getRequiredFields();
        const totalFields = requiredFields.length;

        if (totalFields === 0) {
            this.updateDisplay(100, []);
            return;
        }

        const completedFields = requiredFields.filter(field => this.isFieldCompleted(field));
        const completedCount = completedFields.length;
        const percentage = Math.round((completedCount / totalFields) * 100);

        const missingFields = requiredFields.filter(field => !this.isFieldCompleted(field));

        this.updateDisplay(percentage, missingFields);
    }

    /**
     * Get list of required fields to check
     */
    getRequiredFields() {
        // Use value from controller or find all required fields in form
        if (this.hasRequiredFieldsValue && this.requiredFieldsValue.length > 0) {
            return this.requiredFieldsValue;
        }

        // Auto-detect required fields
        const requiredInputs = this.element.querySelectorAll('[required], [data-required="true"]');
        return Array.from(requiredInputs).map(input => ({
            name: input.name,
            label: input.dataset.label || this.getLabelForField(input),
            element: input
        }));
    }

    /**
     * Check if a field is completed
     */
    isFieldCompleted(field) {
        let element = field.element;

        if (!element && field.name) {
            element = this.element.querySelector(`[name="${field.name}"]`);
        }

        if (!element) {
            return false;
        }

        // Check based on field type
        switch (element.type) {
            case 'checkbox':
                return element.checked;

            case 'radio':
                const radioGroup = this.element.querySelectorAll(`[name="${element.name}"]`);
                return Array.from(radioGroup).some(radio => radio.checked);

            case 'select-one':
            case 'select-multiple':
                return element.value !== '' && element.value !== null;

            case 'file':
                return element.files && element.files.length > 0;

            default:
                return element.value && element.value.trim() !== '';
        }
    }

    /**
     * Get label text for a field
     */
    getLabelForField(element) {
        const id = element.id;

        if (id) {
            const label = this.element.querySelector(`label[for="${id}"]`);
            if (label) {
                return label.textContent.trim().replace(/\*$/, ''); // Remove asterisk
            }
        }

        // Fallback to placeholder or name
        return element.placeholder || element.name || 'Champ requis';
    }

    /**
     * Update display elements with completion data
     */
    updateDisplay(percentage, missingFields) {
        // Update progress bar
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = `${percentage}%`;

            // Change color based on completion
            this.progressBarTarget.className = this.getProgressBarClass(percentage);
        }

        // Update percentage text
        if (this.hasPercentageTarget) {
            this.percentageTarget.textContent = `${percentage}%`;
        }

        // Update progress text
        if (this.hasProgressTextTarget) {
            const status = percentage === 100 ? 'Profil complet' : `${missingFields.length} champ${missingFields.length > 1 ? 's' : ''} manquant${missingFields.length > 1 ? 's' : ''}`;
            this.progressTextTarget.textContent = status;
        }

        // Update missing fields list
        if (this.hasMissingListTarget) {
            if (missingFields.length > 0) {
                this.missingListTarget.innerHTML = missingFields
                    .map(field => `<li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        ${field.label}
                    </li>`)
                    .join('');
                this.missingListTarget.closest('.alert, .info-box')?.classList.remove('hidden');
            } else {
                this.missingListTarget.innerHTML = '';
                this.missingListTarget.closest('.alert, .info-box')?.classList.add('hidden');
            }
        }

        // Update completion badge
        if (this.hasCompletionBadgeTarget) {
            this.completionBadgeTarget.className = this.getCompletionBadgeClass(percentage);
            this.completionBadgeTarget.textContent = percentage === 100 ? '✓ Complet' : `${percentage}%`;
        }

        // Update complete button
        if (this.hasCompleteButtonTarget) {
            if (percentage === 100) {
                this.completeButtonTarget.disabled = false;
                this.completeButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                this.completeButtonTarget.disabled = true;
                this.completeButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        // Dispatch event
        this.dispatch('completionUpdated', {
            detail: {
                percentage,
                missingCount: missingFields.length,
                isComplete: percentage === 100
            }
        });
    }

    /**
     * Get progress bar class based on percentage
     */
    getProgressBarClass(percentage) {
        const baseClass = 'h-full transition-all duration-500';

        if (percentage === 100) {
            return `${baseClass} bg-gradient-to-r from-green-500 to-green-600`;
        } else if (percentage >= 75) {
            return `${baseClass} bg-gradient-to-r from-blue-500 to-blue-600`;
        } else if (percentage >= 50) {
            return `${baseClass} bg-gradient-to-r from-yellow-500 to-yellow-600`;
        } else {
            return `${baseClass} bg-gradient-to-r from-red-500 to-red-600`;
        }
    }

    /**
     * Get completion badge class based on percentage
     */
    getCompletionBadgeClass(percentage) {
        const baseClass = 'badge';

        if (percentage === 100) {
            return `${baseClass} badge-success`;
        } else if (percentage >= 75) {
            return `${baseClass} badge-info`;
        } else if (percentage >= 50) {
            return `${baseClass} badge-warning`;
        } else {
            return `${baseClass} badge-error`;
        }
    }

    /**
     * Handle field update
     */
    handleFieldUpdate(event) {
        // Debounce rapid updates
        clearTimeout(this.updateTimeout);

        this.updateTimeout = setTimeout(() => {
            this.calculateCompletion();
        }, 300);
    }

    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        this.refreshTimer = setInterval(() => {
            this.fetchCompletionStatus();
        }, this.refreshIntervalValue);
    }

    /**
     * Fetch completion status from server
     */
    async fetchCompletionStatus() {
        if (!this.hasUserIdValue) {
            return;
        }

        try {
            const response = await fetch(`/api/users/${this.userIdValue}/completion-status`);

            if (!response.ok) {
                throw new Error('Failed to fetch completion status');
            }

            const data = await response.json();

            this.updateDisplay(data.percentage, data.missingFields);

        } catch (error) {
            console.error('Error fetching completion status:', error);
        }
    }

    /**
     * Manually refresh completion
     */
    refresh() {
        this.calculateCompletion();
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }

        if (this.updateTimeout) {
            clearTimeout(this.updateTimeout);
        }

        this.element.removeEventListener('input', this.handleFieldUpdate);
        this.element.removeEventListener('change', this.handleFieldUpdate);
    }
}
