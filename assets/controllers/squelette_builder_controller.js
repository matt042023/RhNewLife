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

    addGarde24h(event) {
        event.preventDefault();
        this.configuration.creneaux_garde.push({
            jour_debut: 1,
            heure_debut: 7,
            duree_heures: 24,
            type: 'garde_24h',
            jours_garde: [1],
            use_adaptive_hours: true
        });
        this.renderCreneaux();
    }

    addGarde48h(event) {
        event.preventDefault();
        this.configuration.creneaux_garde.push({
            jour_debut: 1,
            heure_debut: 7,
            duree_heures: 48,
            type: 'garde_48h',
            jours_garde: [1, 2],
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
        const html = (this.configuration.creneaux_garde || []).map((creneau, index) => `
            <div class="border border-blue-200 bg-blue-50 p-2 rounded">
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <div class="text-xs font-medium text-blue-900">${this.getDayName(creneau.jour_debut)} ${creneau.heure_debut}h</div>
                        <div class="text-xs text-blue-700">${creneau.duree_heures}h - ${creneau.type}</div>
                    </div>
                    <button type="button" data-action="click->squelette-builder#removeGarde" data-index="${index}" class="text-red-600 hover:text-red-800 text-xs ml-2">✕</button>
                </div>
            </div>
        `).join('');
        this.gardeListTarget.innerHTML = html || '<p class="text-xs text-gray-500">Aucun créneau de garde</p>';
    }

    updateRenfortList() {
        const html = (this.configuration.creneaux_renfort || []).map((creneau, index) => `
            <div class="border border-green-200 bg-green-50 p-2 rounded">
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <div class="text-xs font-medium text-green-900">${creneau.label}</div>
                        <div class="text-xs text-green-700">${this.getDayName(creneau.jour)} ${creneau.heure_debut}h-${creneau.heure_fin}h</div>
                    </div>
                    <button type="button" data-action="click->squelette-builder#removeRenfort" data-index="${index}" class="text-red-600 hover:text-red-800 text-xs ml-2">✕</button>
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
