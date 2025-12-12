import { Controller } from '@hotwired/stimulus';

/**
 * Controller Stimulus pour la gestion des formulaires d'appointments
 * - Détection de conflits en temps réel
 * - Validation des dates
 * - Feedback visuel
 */
export default class extends Controller {
    static targets = ['startDate', 'duration', 'participants'];

    static values = {
        checkConflictsUrl: String
    };

    connect() {
        console.log('Appointment form controller connected');

        // Bind event listeners
        if (this.hasStartDateTarget) {
            this.startDateTarget.addEventListener('change', this.checkConflicts.bind(this));
        }

        if (this.hasDurationTarget) {
            this.durationTarget.addEventListener('change', this.checkConflicts.bind(this));
        }

        if (this.hasParticipantsTarget) {
            this.participantsTarget.addEventListener('change', this.checkConflicts.bind(this));
        }
    }

    disconnect() {
        // Cleanup event listeners
        if (this.hasStartDateTarget) {
            this.startDateTarget.removeEventListener('change', this.checkConflicts.bind(this));
        }

        if (this.hasDurationTarget) {
            this.durationTarget.removeEventListener('change', this.checkConflicts.bind(this));
        }

        if (this.hasParticipantsTarget) {
            this.participantsTarget.removeEventListener('change', this.checkConflicts.bind(this));
        }
    }

    /**
     * Vérifie les conflits horaires
     */
    async checkConflicts() {
        // Récupérer les valeurs du formulaire
        const startDate = this.hasStartDateTarget ? this.startDateTarget.value : null;
        const duration = this.hasDurationTarget ? this.durationTarget.value : 60;
        const participants = this.hasParticipantsTarget ?
            Array.from(this.participantsTarget.selectedOptions).map(option => option.value) : [];

        // Validation basique
        if (!startDate || participants.length === 0) {
            this.hideConflictWarning();
            return;
        }

        // Vérifier que la date est dans le futur
        const selectedDate = new Date(startDate);
        const now = new Date();

        if (selectedDate <= now) {
            this.showError('La date du rendez-vous doit être dans le futur.');
            return;
        }

        // Si une URL de vérification des conflits est fournie
        if (this.hasCheckConflictsUrlValue) {
            try {
                const response = await fetch(this.checkConflictsUrlValue, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        startDate,
                        duration,
                        participants
                    })
                });

                if (response.ok) {
                    const data = await response.json();

                    if (data.conflicts && data.conflicts.length > 0) {
                        this.showConflictWarning(data.conflicts);
                    } else {
                        this.hideConflictWarning();
                    }
                }
            } catch (error) {
                console.error('Erreur lors de la vérification des conflits:', error);
            }
        }
    }

    /**
     * Affiche un avertissement de conflit
     */
    showConflictWarning(conflicts) {
        const warningElement = document.getElementById('conflict-warning');
        const detailsElement = document.getElementById('conflict-details');

        if (!warningElement || !detailsElement) {
            return;
        }

        // Construire le message de conflit
        let conflictHtml = '<ul class="list-disc list-inside">';

        conflicts.forEach(conflict => {
            conflictHtml += `<li><strong>${conflict.user}</strong>: ${conflict.reasons.join(', ')}</li>`;
        });

        conflictHtml += '</ul>';

        detailsElement.innerHTML = conflictHtml;
        warningElement.classList.remove('hidden');
    }

    /**
     * Cache l'avertissement de conflit
     */
    hideConflictWarning() {
        const warningElement = document.getElementById('conflict-warning');

        if (warningElement) {
            warningElement.classList.add('hidden');
        }
    }

    /**
     * Affiche un message d'erreur
     */
    showError(message) {
        // Utiliser le système de flash messages ou une notification toast
        console.error(message);

        // Optionnel: afficher une notification visuelle
        const errorDiv = document.createElement('div');
        errorDiv.className = 'fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50';
        errorDiv.innerHTML = `
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span>${message}</span>
            </div>
        `;

        document.body.appendChild(errorDiv);

        // Supprimer après 5 secondes
        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }

    /**
     * Valide le formulaire avant soumission
     */
    validateForm(event) {
        const startDate = this.hasStartDateTarget ? this.startDateTarget.value : null;
        const participants = this.hasParticipantsTarget ?
            Array.from(this.participantsTarget.selectedOptions).map(option => option.value) : [];

        if (!startDate) {
            event.preventDefault();
            this.showError('La date du rendez-vous est obligatoire.');
            return false;
        }

        if (participants.length === 0) {
            event.preventDefault();
            this.showError('Au moins un participant est requis.');
            return false;
        }

        // Vérifier que la date est dans le futur
        const selectedDate = new Date(startDate);
        const now = new Date();

        if (selectedDate <= now) {
            event.preventDefault();
            this.showError('La date du rendez-vous doit être dans le futur.');
            return false;
        }

        return true;
    }
}
