import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['calendar', 'gardeList', 'renfortList', 'previewModal', 'previewContent',
                      'nomInput', 'descriptionInput', 'validationErrors', 'validationWarnings'];
    static values = {
        squeletteId: Number,
        isEdit: Boolean
    };

    connect() {
        this.configuration = {
            creneaux_garde: [],
            creneaux_renfort: [],
            options: {}
        };

        this.initCalendar();

        if (this.isEditValue && this.squeletteIdValue) {
            this.loadSquelette();
        }
    }

    initCalendar() {
        const { Calendar, timeGridPlugin, interactionPlugin } = window.FullCalendar;

        this.calendar = new Calendar(this.calendarTarget, {
            plugins: [timeGridPlugin, interactionPlugin],
            initialView: 'timeGrid',
            initialDate: '2025-01-06', // Monday
            duration: { days: 8 }, // 8 days: Monday to next Monday (inclusive)
            allDaySlot: false,
            slotMinTime: '00:00:00',
            slotMaxTime: '24:00:00',
            height: 'auto',
            editable: true,
            selectable: true,
            selectMirror: true,
            slotDuration: '01:00:00',
            snapDuration: '01:00:00',
            headerToolbar: {
                left: '',
                center: 'title',
                right: ''
            },
            titleFormat: { year: 'numeric', month: 'long', day: 'numeric' },
            locale: 'fr',
            firstDay: 1,
            dayHeaderFormat: { weekday: 'short', day: 'numeric', month: 'numeric' },

            eventDrop: (info) => this.handleEventDrop(info),
            eventResize: (info) => this.handleEventResize(info),
            eventClick: (info) => this.handleEventClick(info)
        });

        this.calendar.render();
    }

    async loadSquelette() {
        try {
            const response = await fetch(`/admin/templates-garde/api/${this.squeletteIdValue}`);
            const result = await response.json();

            if (result.success) {
                this.nomInputTarget.value = result.data.nom;
                this.descriptionInputTarget.value = result.data.description || '';
                this.configuration = result.data.configuration;
                this.renderCreneaux();
            }
        } catch (error) {
            console.error('Erreur chargement:', error);
            alert('Erreur lors du chargement du template');
        }
    }

    renderCreneaux() {
        this.calendar.removeAllEvents();

        (this.configuration.creneaux_garde || []).forEach((creneau, index) => {
            this.addEventToCalendar(creneau, 'garde', index);
        });

        (this.configuration.creneaux_renfort || []).forEach((creneau, index) => {
            this.addEventToCalendar(creneau, 'renfort', index);
        });

        this.updateGardeList();
        this.updateRenfortList();
    }

    addEventToCalendar(creneau, type, index) {
        const weekStart = new Date('2025-01-06'); // Monday
        let eventData;

        if (type === 'garde') {
            const start = new Date(weekStart);
            start.setDate(start.getDate() + (creneau.jour_debut - 1));
            start.setHours(creneau.heure_debut, 0, 0);

            const end = new Date(start);
            end.setTime(start.getTime() + (creneau.duree_heures * 60 * 60 * 1000));

            eventData = {
                id: `garde-${index}`,
                title: `Garde ${creneau.duree_heures}h`,
                start,
                end,
                backgroundColor: '#3B82F6',
                borderColor: '#2563EB',
                extendedProps: { type: 'garde', index }
            };
        } else {
            const start = new Date(weekStart);
            start.setDate(start.getDate() + (creneau.jour - 1));
            start.setHours(creneau.heure_debut, 0, 0);

            const end = new Date(start);
            end.setHours(creneau.heure_fin, 0, 0);

            eventData = {
                id: `renfort-${index}`,
                title: creneau.label || 'Renfort',
                start,
                end,
                backgroundColor: '#10B981',
                borderColor: '#059669',
                extendedProps: { type: 'renfort', index }
            };
        }

        this.calendar.addEvent(eventData);
    }

    addGarde(event) {
        event.preventDefault();

        // Déterminer le jour de début intelligent
        let jour_debut = 1; // Lundi par défaut
        let heure_debut = 8;

        // Si des gardes existent déjà, placer la nouvelle garde après la dernière
        if (this.configuration.creneaux_garde && this.configuration.creneaux_garde.length > 0) {
            const lastGarde = this.configuration.creneaux_garde[this.configuration.creneaux_garde.length - 1];

            // Calculer le jour de fin de la dernière garde
            const weekStart = new Date('2025-01-06');
            const lastStart = new Date(weekStart);
            lastStart.setDate(lastStart.getDate() + (lastGarde.jour_debut - 1));
            lastStart.setHours(lastGarde.heure_debut, 0, 0);

            const lastEnd = new Date(lastStart);
            lastEnd.setTime(lastStart.getTime() + (lastGarde.duree_heures * 60 * 60 * 1000));

            // Nouvelle garde commence où la dernière se termine
            const daysSinceMonday = Math.floor((lastEnd - weekStart) / (1000 * 60 * 60 * 24));
            jour_debut = (daysSinceMonday % 7) + 1; // Jour de la semaine (1-7)
            heure_debut = lastEnd.getHours();
        }

        this.configuration.creneaux_garde.push({
            jour_debut: jour_debut,
            heure_debut: heure_debut,
            duree_heures: 48,
            type: 'garde_48h',
            jours_garde: [jour_debut, jour_debut + 1],
            use_adaptive_hours: true
        });
        this.renderCreneaux();
    }

    addRenfort(event) {
        event.preventDefault();
        this.configuration.creneaux_renfort.push({
            jour: 3,
            heure_debut: 11,
            heure_fin: 19,
            villa_id: null,
            label: 'Nouveau renfort'
        });
        this.renderCreneaux();
    }

    handleEventDrop(info) {
        const { event } = info;
        const { type, index } = event.extendedProps;

        const newDayOfWeek = (event.start.getDay() || 7);
        const newHour = event.start.getHours();

        if (type === 'garde') {
            this.configuration.creneaux_garde[index].jour_debut = newDayOfWeek;
            this.configuration.creneaux_garde[index].heure_debut = newHour;
        } else {
            this.configuration.creneaux_renfort[index].jour = newDayOfWeek;
            this.configuration.creneaux_renfort[index].heure_debut = newHour;
        }

        this.updateGardeList();
        this.updateRenfortList();
    }

    handleEventResize(info) {
        const { event } = info;
        const { type, index } = event.extendedProps;

        if (type === 'garde') {
            const durationMs = event.end - event.start;
            const durationHours = Math.round(durationMs / (1000 * 60 * 60));
            this.configuration.creneaux_garde[index].duree_heures = durationHours;
        } else {
            this.configuration.creneaux_renfort[index].heure_fin = event.end.getHours();
        }

        this.updateGardeList();
        this.updateRenfortList();
        this.renderCreneaux();
    }

    handleEventClick(info) {
        const { type, index } = info.event.extendedProps;
        if (confirm(`Supprimer ce créneau ?`)) {
            if (type === 'garde') {
                this.configuration.creneaux_garde.splice(index, 1);
            } else {
                this.configuration.creneaux_renfort.splice(index, 1);
            }
            this.renderCreneaux();
        }
    }

    updateGardeList() {
        const html = (this.configuration.creneaux_garde || []).map((creneau, index) => {
            // Calculate working days using the formula: ceil((hours - 3) / 24) for hours >= 7
            const hours = creneau.duree_heures;
            const workingDays = hours >= 7 ? Math.ceil((hours - 3) / 24) : 0;

            // Calculate end day and hour
            const weekStart = new Date('2025-01-06'); // Monday
            const start = new Date(weekStart);
            start.setDate(start.getDate() + (creneau.jour_debut - 1));
            start.setHours(creneau.heure_debut, 0, 0);

            const end = new Date(start);
            end.setTime(start.getTime() + (creneau.duree_heures * 60 * 60 * 1000));

            const startDayName = this.getDayName(creneau.jour_debut);
            const endDay = (end.getDay() || 7); // Convert Sunday (0) to 7
            const endDayName = this.getDayName(endDay);
            const endHour = end.getHours();

            return `
            <div class="border border-blue-200 bg-blue-50 p-3 rounded">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="text-sm font-bold text-blue-900 mb-1">
                            🏠 Garde principale ${workingDays} jour${workingDays > 1 ? 's' : ''}
                        </div>
                        <div class="text-xs text-blue-700">
                            Du ${startDayName} ${creneau.heure_debut}h au ${endDayName} ${endHour}h
                        </div>
                    </div>
                    <button type="button" data-action="click->squelette-builder#removeGarde" data-index="${index}" class="text-red-600 hover:text-red-800 text-xs ml-2">✕</button>
                </div>
            </div>
        `}).join('');
        this.gardeListTarget.innerHTML = html || '<p class="text-xs text-gray-500">Aucun créneau de garde</p>';
    }

    updateRenfortList() {
        const html = (this.configuration.creneaux_renfort || []).map((creneau, index) => `
            <div class="border border-green-200 bg-green-50 p-2 rounded hover:bg-green-100 cursor-pointer transition"
                 data-action="click->squelette-builder#editRenfort"
                 data-index="${index}">
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <div class="text-xs font-medium text-green-900">${creneau.label}</div>
                        <div class="text-xs text-green-700">${this.getDayName(creneau.jour)} ${creneau.heure_debut}h-${creneau.heure_fin}h</div>
                        ${creneau.villa_id ? `<div class="text-xs text-green-600 mt-1">📍 Villa spécifique</div>` : `<div class="text-xs text-gray-500 mt-1">🌐 Centre-complet</div>`}
                    </div>
                    <button type="button" data-action="click->squelette-builder#removeRenfort" data-index="${index}" class="text-red-600 hover:text-red-800 text-xs ml-2" onclick="event.stopPropagation()">✕</button>
                </div>
            </div>
        `).join('');
        this.renfortListTarget.innerHTML = html || '<p class="text-xs text-gray-500">Aucun renfort</p>';
    }

    removeGarde(event) {
        const index = parseInt(event.currentTarget.dataset.index);
        this.configuration.creneaux_garde.splice(index, 1);
        this.renderCreneaux();
    }

    removeRenfort(event) {
        const index = parseInt(event.currentTarget.dataset.index);
        this.configuration.creneaux_renfort.splice(index, 1);
        this.renderCreneaux();
    }

    editRenfort(event) {
        const index = parseInt(event.currentTarget.dataset.index);
        const creneau = this.configuration.creneaux_renfort[index];

        // Fetch villas list (assuming it's available globally or needs to be fetched)
        // For now, we'll create a simple modal
        const modalHtml = `
            <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center" id="editRenfortModal">
                <div class="bg-white rounded-lg p-6 w-full mx-4" style="max-width: 500px;">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-900">Éditer le renfort</h2>
                        <button onclick="document.getElementById('editRenfortModal').remove()"
                                class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form id="editRenfortForm" class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Label</label>
                            <input type="text" id="renfortLabel" value="${creneau.label || 'Renfort'}"
                                   class="w-full border-gray-300 rounded-lg px-3 py-2">
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Jour</label>
                                <select id="renfortJour" class="w-full border-gray-300 rounded-lg px-3 py-2">
                                    <option value="1" ${creneau.jour === 1 ? 'selected' : ''}>Lundi</option>
                                    <option value="2" ${creneau.jour === 2 ? 'selected' : ''}>Mardi</option>
                                    <option value="3" ${creneau.jour === 3 ? 'selected' : ''}>Mercredi</option>
                                    <option value="4" ${creneau.jour === 4 ? 'selected' : ''}>Jeudi</option>
                                    <option value="5" ${creneau.jour === 5 ? 'selected' : ''}>Vendredi</option>
                                    <option value="6" ${creneau.jour === 6 ? 'selected' : ''}>Samedi</option>
                                    <option value="7" ${creneau.jour === 7 ? 'selected' : ''}>Dimanche</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Début</label>
                                <input type="number" id="renfortDebut" value="${creneau.heure_debut}" min="0" max="23"
                                       class="w-full border-gray-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Fin</label>
                                <input type="number" id="renfortFin" value="${creneau.heure_fin}" min="0" max="23"
                                       class="w-full border-gray-300 rounded-lg px-3 py-2">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">
                                Villa (optionnel)
                                <span class="text-xs text-gray-500 font-normal">- Laisser vide pour centre-complet</span>
                            </label>
                            <select id="renfortVilla" class="w-full border-gray-300 rounded-lg px-3 py-2">
                                <option value="">-- Aucune (centre-complet) --</option>
                                ${this.getVillaOptions(creneau.villa_id)}
                            </select>
                        </div>

                        <div class="flex justify-end space-x-2 pt-4 border-t">
                            <button type="button"
                                    onclick="document.getElementById('editRenfortModal').remove()"
                                    class="px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-lg font-medium">
                                Annuler
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Attach form submit handler
        document.getElementById('editRenfortForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveRenfortEdit(index);
        });
    }

    saveRenfortEdit(index) {
        this.configuration.creneaux_renfort[index] = {
            label: document.getElementById('renfortLabel').value,
            jour: parseInt(document.getElementById('renfortJour').value),
            heure_debut: parseInt(document.getElementById('renfortDebut').value),
            heure_fin: parseInt(document.getElementById('renfortFin').value),
            villa_id: document.getElementById('renfortVilla').value || null
        };

        document.getElementById('editRenfortModal').remove();
        this.renderCreneaux();
    }

    getVillaOptions(selectedVillaId) {
        // This should be populated from the villas available in the system
        // For now, we'll need to fetch this or have it passed in
        // Placeholder implementation - needs to be populated with actual villas
        const villas = window.villasList || [];
        return villas.map(v => `
            <option value="${v.id}" ${v.id == selectedVillaId ? 'selected' : ''}>
                ${v.nom}
            </option>
        `).join('');
    }

    getDayName(jour) {
        const jours = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        return jours[jour];
    }

    async save(event) {
        event.preventDefault();

        const nom = this.nomInputTarget.value;
        const description = this.descriptionInputTarget.value;

        if (!nom) {
            alert('Le nom est obligatoire');
            return;
        }

        const data = {
            nom,
            description,
            configuration: this.configuration
        };

        try {
            let response;
            if (this.isEditValue) {
                response = await fetch(`/admin/templates-garde/api/${this.squeletteIdValue}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            } else {
                response = await fetch('/admin/templates-garde/api', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            }

            const result = await response.json();

            if (result.success) {
                alert(result.message);
                window.location.href = `/admin/templates-garde`;
            } else {
                this.displayValidationErrors(result.errors || [], result.warnings || []);
            }
        } catch (error) {
            alert('Erreur: ' + error.message);
        }
    }

    displayValidationErrors(errors, warnings) {
        if (this.hasValidationErrorsTarget) {
            this.validationErrorsTarget.innerHTML = errors
                .map(e => `<div class="p-3 bg-red-100 text-red-800 rounded">${e.message}</div>`)
                .join('');
        }
        if (this.hasValidationWarningsTarget) {
            this.validationWarningsTarget.innerHTML = warnings
                .map(w => `<div class="p-3 bg-yellow-100 text-yellow-800 rounded">${w.message}</div>`)
                .join('');
        }
    }

    preview(event) {
        event.preventDefault();
        // Simplified preview for now
        alert('Prévisualisation : ' + (this.configuration.creneaux_garde.length + this.configuration.creneaux_renfort.length) + ' créneaux configurés');
    }
}
