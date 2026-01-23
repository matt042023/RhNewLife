import { Controller } from '@hotwired/stimulus';

/**
 * Controller for the direct user creation multi-step form
 * Handles navigation between steps, validation, document uploads, and summary generation
 */
export default class extends Controller {
    static targets = [
        'form',
        'stepIndicator',
        'stepLine',
        'stepContent',
        'prevButton',
        'nextButton',
        'submitButton',
        // Personal info
        'firstName',
        'lastName',
        'email',
        'position',
        'villa',
        'hiringDate',
        'color',
        // Documents
        'documentsRequired',
        'documentsProgress',
        'documentsCount',
        'documentsProgressBar',
        'documentCard',
        // Contract
        'createContract',
        'contractSection',
        'noContractInfo',
        'contractType',
        'contractStartDate',
        'contractEndDate',
        'contractEndDateWrapper',
        'contractSalary',
        'useAnnualDaySystem',
        'annualDaysWrapper',
        'contractDropArea',
        'contractFilePreview',
        'contractFilename',
        // Activation
        'activationOption',
        'passwordSection',
        'temporaryPassword',
        // Summary
        'summaryPersonal',
        'summaryDocuments',
        'summaryContract',
        'summaryActivation'
    ];

    connect() {
        this.currentStep = 1;
        this.totalSteps = 5;
        this.uploadedDocuments = {};
        this.contractFile = null;

        this.updateNavigation();
        this.updateDocumentsProgress();
    }

    // ==================== Navigation ====================

    nextStep() {
        if (!this.validateCurrentStep()) {
            return;
        }

        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.showStep(this.currentStep);
            this.updateNavigation();

            // Update summary on last step
            if (this.currentStep === this.totalSteps) {
                this.updateSummary();
            }
        }
    }

    previousStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
            this.updateNavigation();
        }
    }

    showStep(stepNumber) {
        // Hide all steps using step-hidden class (not hidden/display:none)
        // This preserves file inputs so they are submitted with the form
        this.stepContentTargets.forEach(content => {
            content.classList.add('step-hidden');
        });

        // Show current step
        const currentContent = this.stepContentTargets.find(
            content => content.dataset.step === String(stepNumber)
        );
        if (currentContent) {
            currentContent.classList.remove('step-hidden');
        }

        // Update step indicators
        this.stepIndicatorTargets.forEach(indicator => {
            const step = parseInt(indicator.dataset.step);
            const circle = indicator.querySelector('div');

            if (step < stepNumber) {
                // Completed step
                circle.classList.remove('bg-white/50', 'dark:bg-gray-700', 'text-gray-500', 'dark:text-gray-400');
                circle.classList.add('bg-green-500', 'text-white');
            } else if (step === stepNumber) {
                // Current step
                circle.classList.remove('bg-white/50', 'dark:bg-gray-700', 'text-gray-500', 'dark:text-gray-400', 'bg-green-500');
                circle.classList.add('bg-blue-600', 'text-white');
            } else {
                // Future step
                circle.classList.remove('bg-blue-600', 'bg-green-500', 'text-white');
                circle.classList.add('bg-white/50', 'dark:bg-gray-700', 'text-gray-500', 'dark:text-gray-400');
            }
        });

        // Update step lines
        this.stepLineTargets.forEach(line => {
            const step = parseInt(line.dataset.step);
            if (step < stepNumber) {
                line.classList.remove('bg-gray-200', 'dark:bg-gray-700');
                line.classList.add('bg-green-500');
            } else {
                line.classList.remove('bg-green-500');
                line.classList.add('bg-gray-200', 'dark:bg-gray-700');
            }
        });
    }

    updateNavigation() {
        // Previous button
        if (this.hasPrevButtonTarget) {
            if (this.currentStep === 1) {
                this.prevButtonTarget.classList.add('hidden');
            } else {
                this.prevButtonTarget.classList.remove('hidden');
            }
        }

        // Next/Submit buttons
        if (this.hasNextButtonTarget && this.hasSubmitButtonTarget) {
            if (this.currentStep === this.totalSteps) {
                this.nextButtonTarget.classList.add('hidden');
                this.submitButtonTarget.classList.remove('hidden');
            } else {
                this.nextButtonTarget.classList.remove('hidden');
                this.submitButtonTarget.classList.add('hidden');
            }
        }
    }

    // ==================== Validation ====================

    validateCurrentStep() {
        switch (this.currentStep) {
            case 1:
                return this.validatePersonalInfo();
            case 2:
                return this.validateDocuments();
            case 3:
                return this.validateContract();
            case 4:
                return this.validateActivation();
            default:
                return true;
        }
    }

    validatePersonalInfo() {
        const errors = [];

        if (this.hasFirstNameTarget && !this.firstNameTarget.value.trim()) {
            errors.push('Le prénom est obligatoire');
            this.firstNameTarget.classList.add('border-red-500');
        } else if (this.hasFirstNameTarget) {
            this.firstNameTarget.classList.remove('border-red-500');
        }

        if (this.hasLastNameTarget && !this.lastNameTarget.value.trim()) {
            errors.push('Le nom est obligatoire');
            this.lastNameTarget.classList.add('border-red-500');
        } else if (this.hasLastNameTarget) {
            this.lastNameTarget.classList.remove('border-red-500');
        }

        if (this.hasEmailTarget) {
            const email = this.emailTarget.value.trim();
            if (!email) {
                errors.push("L'email est obligatoire");
                this.emailTarget.classList.add('border-red-500');
            } else if (!this.isValidEmail(email)) {
                errors.push("L'email n'est pas valide");
                this.emailTarget.classList.add('border-red-500');
            } else {
                this.emailTarget.classList.remove('border-red-500');
            }
        }

        if (this.hasPositionTarget && !this.positionTarget.value.trim()) {
            errors.push('Le poste est obligatoire');
            this.positionTarget.classList.add('border-red-500');
        } else if (this.hasPositionTarget) {
            this.positionTarget.classList.remove('border-red-500');
        }

        if (errors.length > 0) {
            this.showValidationErrors(errors);
            return false;
        }

        return true;
    }

    validateDocuments() {
        if (!this.hasDocumentsRequiredTarget) return true;

        const documentsRequired = this.documentsRequiredTarget.checked;

        if (!documentsRequired) {
            return true; // Documents are optional
        }

        const requiredTypes = ['cni', 'rib', 'domicile', 'honorabilite'];
        const missingDocuments = [];

        requiredTypes.forEach(type => {
            if (!this.uploadedDocuments[type]) {
                const card = this.documentCardTargets.find(c => c.dataset.documentType === type);
                if (card) {
                    const label = card.querySelector('.font-medium')?.textContent || type;
                    missingDocuments.push(label);
                }
            }
        });

        if (missingDocuments.length > 0) {
            this.showValidationErrors([
                `Documents obligatoires manquants : ${missingDocuments.join(', ')}`
            ]);
            return false;
        }

        return true;
    }

    validateContract() {
        if (!this.hasCreateContractTarget || !this.createContractTarget.checked) {
            return true; // No contract to validate
        }

        const errors = [];

        if (this.hasContractTypeTarget && !this.contractTypeTarget.value) {
            errors.push('Le type de contrat est obligatoire');
        }

        if (this.hasContractStartDateTarget && !this.contractStartDateTarget.value) {
            errors.push('La date de début est obligatoire');
        }

        if (this.hasContractTypeTarget && this.contractTypeTarget.value === 'CDD') {
            if (this.hasContractEndDateTarget && !this.contractEndDateTarget.value) {
                errors.push('La date de fin est obligatoire pour un CDD');
            }
        }

        if (this.hasContractSalaryTarget && !this.contractSalaryTarget.value) {
            errors.push('Le salaire est obligatoire');
        }

        // Validate date coherence
        if (this.hasContractStartDateTarget && this.hasContractEndDateTarget) {
            const startDate = new Date(this.contractStartDateTarget.value);
            const endDate = new Date(this.contractEndDateTarget.value);

            if (this.contractEndDateTarget.value && endDate <= startDate) {
                errors.push('La date de fin doit être après la date de début');
            }
        }

        if (errors.length > 0) {
            this.showValidationErrors(errors);
            return false;
        }

        return true;
    }

    validateActivation() {
        const activationMode = this.getActivationMode();

        if (activationMode === 'password') {
            if (this.hasTemporaryPasswordTarget) {
                const password = this.temporaryPasswordTarget.value.trim();
                if (!password) {
                    this.showValidationErrors(['Le mot de passe temporaire est obligatoire']);
                    return false;
                }
                if (password.length < 8) {
                    this.showValidationErrors(['Le mot de passe doit contenir au moins 8 caractères']);
                    return false;
                }
            }
        }

        return true;
    }

    showValidationErrors(errors) {
        alert('Erreurs de validation :\n\n' + errors.join('\n'));
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // ==================== Documents ====================

    toggleDocumentsRequired() {
        const required = this.documentsRequiredTarget.checked;

        this.documentCardTargets.forEach(card => {
            const marker = card.querySelector('.document-required-marker');
            if (marker) {
                if (required && card.dataset.required === '1') {
                    marker.classList.remove('hidden');
                } else {
                    marker.classList.add('hidden');
                }
            }
        });
    }

    handleFileSelect(event) {
        const input = event.target;
        const file = input.files[0];

        if (!file) return;

        const card = input.closest('[data-document-type]');
        const documentType = card?.dataset.documentType;

        if (!documentType) return;

        // Validate file
        const validTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!validTypes.includes(file.type)) {
            alert('Format de fichier non accepté. Utilisez PDF, JPEG ou PNG.');
            input.value = '';
            return;
        }

        const maxSize = 10 * 1024 * 1024; // 10 MB
        if (file.size > maxSize) {
            alert('Le fichier est trop volumineux (max 10 Mo).');
            input.value = '';
            return;
        }

        // Update UI
        this.uploadedDocuments[documentType] = file;
        this.updateDocumentCard(card, file);
        this.updateDocumentsProgress();
    }

    updateDocumentCard(card, file) {
        const dropArea = card.querySelector('.document-drop-area');
        const preview = card.querySelector('.document-preview');
        const filename = preview?.querySelector('.document-filename');
        const statusEmpty = card.querySelector('.document-status-empty');
        const statusReady = card.querySelector('.document-status-ready');

        if (dropArea) dropArea.classList.add('hidden');
        if (preview) {
            preview.classList.remove('hidden');
            if (filename) filename.textContent = file.name;
        }
        if (statusEmpty) statusEmpty.classList.add('hidden');
        if (statusReady) statusReady.classList.remove('hidden');
    }

    removeFile(event) {
        event.preventDefault();

        const button = event.currentTarget;
        const documentType = button.dataset.documentType;
        const card = this.documentCardTargets.find(c => c.dataset.documentType === documentType);

        if (!card) return;

        // Clear file input
        const input = card.querySelector('input[type="file"]');
        if (input) input.value = '';

        // Reset UI
        delete this.uploadedDocuments[documentType];

        const dropArea = card.querySelector('.document-drop-area');
        const preview = card.querySelector('.document-preview');
        const statusEmpty = card.querySelector('.document-status-empty');
        const statusReady = card.querySelector('.document-status-ready');

        if (dropArea) dropArea.classList.remove('hidden');
        if (preview) preview.classList.add('hidden');
        if (statusEmpty) statusEmpty.classList.remove('hidden');
        if (statusReady) statusReady.classList.add('hidden');

        this.updateDocumentsProgress();
    }

    updateDocumentsProgress() {
        const count = Object.keys(this.uploadedDocuments).length;
        const total = 4; // 4 required document types
        const percentage = Math.min((count / total) * 100, 100);

        if (this.hasDocumentsCountTarget) {
            this.documentsCountTarget.textContent = count;
        }

        if (this.hasDocumentsProgressBarTarget) {
            this.documentsProgressBarTarget.style.width = `${percentage}%`;
        }
    }

    // ==================== Contract ====================

    toggleContractSection() {
        const createContract = this.createContractTarget.checked;

        if (this.hasContractSectionTarget) {
            if (createContract) {
                this.contractSectionTarget.classList.remove('hidden');
            } else {
                this.contractSectionTarget.classList.add('hidden');
            }
        }

        if (this.hasNoContractInfoTarget) {
            if (createContract) {
                this.noContractInfoTarget.classList.add('hidden');
            } else {
                this.noContractInfoTarget.classList.remove('hidden');
            }
        }
    }

    handleContractTypeChange() {
        const contractType = this.contractTypeTarget.value;

        // Show/hide end date requirement
        const cddMarker = document.querySelector('.cdd-required-marker');
        if (cddMarker) {
            if (contractType === 'CDD') {
                cddMarker.classList.remove('hidden');
            } else {
                cddMarker.classList.add('hidden');
            }
        }
    }

    toggleAnnualDaySystem() {
        if (!this.hasUseAnnualDaySystemTarget || !this.hasAnnualDaysWrapperTarget) return;

        const enabled = this.useAnnualDaySystemTarget.checked;

        if (enabled) {
            this.annualDaysWrapperTarget.classList.remove('hidden');
        } else {
            this.annualDaysWrapperTarget.classList.add('hidden');
        }
    }

    handleContractFileSelect(event) {
        const file = event.target.files[0];

        if (!file) return;

        if (file.type !== 'application/pdf') {
            alert('Le contrat signé doit être au format PDF.');
            event.target.value = '';
            return;
        }

        this.contractFile = file;

        if (this.hasContractDropAreaTarget) {
            this.contractDropAreaTarget.classList.add('hidden');
        }

        if (this.hasContractFilePreviewTarget) {
            this.contractFilePreviewTarget.classList.remove('hidden');
        }

        if (this.hasContractFilenameTarget) {
            this.contractFilenameTarget.textContent = file.name;
        }
    }

    removeContractFile() {
        this.contractFile = null;

        const input = document.getElementById('contract_signed_file');
        if (input) input.value = '';

        if (this.hasContractDropAreaTarget) {
            this.contractDropAreaTarget.classList.remove('hidden');
        }

        if (this.hasContractFilePreviewTarget) {
            this.contractFilePreviewTarget.classList.add('hidden');
        }
    }

    // ==================== Activation ====================

    handleActivationModeChange(event) {
        const mode = event.target.value;

        // Update option styles
        this.activationOptionTargets.forEach(option => {
            const optionMode = option.dataset.mode;
            if (optionMode === mode) {
                option.classList.remove('border-white/10');
                if (mode === 'email') {
                    option.classList.add('border-blue-500', 'bg-blue-50/50', 'dark:bg-blue-900/20');
                } else if (mode === 'password') {
                    option.classList.add('border-amber-500', 'bg-amber-50/50', 'dark:bg-amber-900/20');
                } else {
                    option.classList.add('border-gray-500', 'bg-gray-50/50', 'dark:bg-gray-800/50');
                }
            } else {
                option.classList.remove(
                    'border-blue-500', 'bg-blue-50/50', 'dark:bg-blue-900/20',
                    'border-amber-500', 'bg-amber-50/50', 'dark:bg-amber-900/20',
                    'border-gray-500', 'bg-gray-50/50', 'dark:bg-gray-800/50'
                );
                option.classList.add('border-white/10');
            }
        });

        // Show/hide password section
        if (this.hasPasswordSectionTarget) {
            if (mode === 'password') {
                this.passwordSectionTarget.classList.remove('hidden');
            } else {
                this.passwordSectionTarget.classList.add('hidden');
            }
        }
    }

    generatePassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$%';
        let password = '';

        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        if (this.hasTemporaryPasswordTarget) {
            this.temporaryPasswordTarget.value = password;
        }
    }

    getActivationMode() {
        const selected = document.querySelector('input[name="activation_mode"]:checked');
        return selected ? selected.value : 'email';
    }

    // ==================== Summary ====================

    updateSummary() {
        this.updatePersonalSummary();
        this.updateDocumentsSummary();
        this.updateContractSummary();
        this.updateActivationSummary();
    }

    updatePersonalSummary() {
        const fullName = `${this.firstNameTarget?.value || ''} ${this.lastNameTarget?.value || ''}`.trim();
        const email = this.emailTarget?.value || '-';
        const position = this.positionTarget?.value || '-';
        const villa = this.villaTarget?.options[this.villaTarget.selectedIndex]?.text || 'Aucune';
        const hiringDate = this.hiringDateTarget?.value || '-';
        const color = this.colorTarget?.value || '#3B82F6';

        const summaryEl = this.summaryPersonalTarget;
        if (!summaryEl) return;

        summaryEl.querySelector('[data-summary="fullName"]').textContent = fullName || '-';
        summaryEl.querySelector('[data-summary="email"]').textContent = email;
        summaryEl.querySelector('[data-summary="position"]').textContent = position;
        summaryEl.querySelector('[data-summary="villa"]').textContent = villa;
        summaryEl.querySelector('[data-summary="hiringDate"]').textContent = hiringDate ? this.formatDate(hiringDate) : '-';

        const colorSwatch = summaryEl.querySelector('[data-summary="colorSwatch"]');
        const colorValue = summaryEl.querySelector('[data-summary="colorValue"]');
        if (colorSwatch) colorSwatch.style.backgroundColor = color;
        if (colorValue) colorValue.textContent = color;
    }

    updateDocumentsSummary() {
        const summaryEl = this.summaryDocumentsTarget;
        if (!summaryEl) return;

        const docs = Object.entries(this.uploadedDocuments);

        if (docs.length === 0) {
            const required = this.documentsRequiredTarget?.checked;
            summaryEl.innerHTML = `<p class="text-gray-500 dark:text-gray-400 text-sm">${required ? 'Aucun document sélectionné (obligatoires)' : 'Aucun document (optionnel)'}</p>`;
            return;
        }

        const docLabels = {
            'cni': "Carte d'identité",
            'rib': 'RIB',
            'domicile': 'Justificatif de domicile',
            'honorabilite': "Attestation d'honorabilité",
            'diplome': 'Diplôme'
        };

        const html = docs.map(([type, file]) => `
            <div class="flex items-center gap-2 text-sm">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-gray-700 dark:text-gray-300">${docLabels[type] || type}</span>
                <span class="text-gray-500 text-xs">(${file.name})</span>
            </div>
        `).join('');

        summaryEl.innerHTML = html;
    }

    updateContractSummary() {
        const summaryEl = this.summaryContractTarget;
        if (!summaryEl) return;

        if (!this.createContractTarget?.checked) {
            summaryEl.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-sm">Pas de contrat</p>';
            return;
        }

        const type = this.contractTypeTarget?.value || '-';
        const startDate = this.contractStartDateTarget?.value;
        const endDate = this.contractEndDateTarget?.value;
        const salary = this.contractSalaryTarget?.value;
        const hasSignedFile = !!this.contractFile;

        let html = `
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Type</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">${type}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Date de début</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">${startDate ? this.formatDate(startDate) : '-'}</p>
                </div>
        `;

        if (endDate) {
            html += `
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Date de fin</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">${this.formatDate(endDate)}</p>
                </div>
            `;
        }

        if (salary) {
            html += `
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Salaire brut</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">${this.formatCurrency(salary)}</p>
                </div>
            `;
        }

        html += '</div>';

        if (hasSignedFile) {
            html += `
                <div class="mt-3 flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Contrat signé fourni (sera activé immédiatement)</span>
                </div>
            `;
        }

        summaryEl.innerHTML = html;
    }

    updateActivationSummary() {
        const summaryEl = this.summaryActivationTarget;
        if (!summaryEl) return;

        const mode = this.getActivationMode();

        let html = '';

        switch (mode) {
            case 'email':
                html = `
                    <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>Email d'activation (le salarié définira son mot de passe)</span>
                    </div>
                `;
                break;
            case 'password':
                const password = this.temporaryPasswordTarget?.value || '';
                html = `
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                            <span>Mot de passe temporaire (changement obligatoire à la 1ère connexion)</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 ml-7">
                            Mot de passe : <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">${password ? '********' : 'Non défini'}</code>
                        </p>
                    </div>
                `;
                break;
            case 'none':
                html = `
                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Pas d'activation (invitation à envoyer plus tard)</span>
                    </div>
                `;
                break;
        }

        summaryEl.innerHTML = html;
    }

    // ==================== Helpers ====================

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }

    formatCurrency(amount) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }
}
