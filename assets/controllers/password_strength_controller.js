import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'bar', 'label'];

    check() {
        const password = this.inputTarget.value;
        const strength = this.calculateStrength(password);

        // Met à jour la barre de progression
        this.barTarget.style.width = `${strength}%`;

        // Change la couleur selon la force
        if (strength < 40) {
            this.barTarget.className = 'h-full bg-gradient-to-r from-red-500 to-red-600 transition-all duration-500';
            this.labelTarget.textContent = 'Faible';
            this.labelTarget.className = 'text-red-600 font-semibold';
        } else if (strength < 70) {
            this.barTarget.className = 'h-full bg-gradient-to-r from-yellow-500 to-yellow-600 transition-all duration-500';
            this.labelTarget.textContent = 'Moyen';
            this.labelTarget.className = 'text-yellow-600 font-semibold';
        } else {
            this.barTarget.className = 'h-full bg-gradient-to-r from-green-500 to-green-600 transition-all duration-500';
            this.labelTarget.textContent = 'Fort';
            this.labelTarget.className = 'text-green-600 font-semibold';
        }
    }

    calculateStrength(password) {
        let strength = 0;

        if (password.length === 0) {
            return 0;
        }

        // Longueur
        if (password.length >= 8) strength += 20;
        if (password.length >= 12) strength += 10;
        if (password.length >= 16) strength += 10;

        // Complexité
        if (/[a-z]/.test(password)) strength += 15;
        if (/[A-Z]/.test(password)) strength += 15;
        if (/[0-9]/.test(password)) strength += 15;
        if (/[^A-Za-z0-9]/.test(password)) strength += 15;

        return Math.min(100, strength);
    }
}
