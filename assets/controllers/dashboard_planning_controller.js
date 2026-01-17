import { Controller } from '@hotwired/stimulus';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import frLocale from '@fullcalendar/core/locales/fr';

/**
 * Dashboard Planning Controller
 *
 * Affiche un calendrier FullCalendar en lecture seule sur les dashboards.
 * - Dashboard Admin: toutes les affectations validées/à remplacer + absences + RDV + astreintes
 * - Dashboard Éducateur: uniquement ses affectations validées + ses absences + ses RDV
 *
 * UI identique au planning principal avec badges, astreintes, etc.
 */
export default class extends Controller {
    static targets = ['calendar'];
    static values = {
        year: Number,
        month: Number,
        endpoint: String,
        statusFilter: Array,
        mode: { type: String, default: 'admin' } // 'admin' or 'employee'
    };

    connect() {
        this.astreintesData = [];
        this.isEmployeeMode = this.modeValue === 'employee';
        this.initCalendar();
    }

    disconnect() {
        if (this.calendar) {
            this.calendar.destroy();
        }
    }

    initCalendar() {
        const initialDate = new Date(this.yearValue, this.monthValue - 1, 1);

        this.calendar = new Calendar(this.calendarTarget, {
            plugins: [dayGridPlugin],
            initialView: 'dayGridMonth',
            initialDate: initialDate,
            locale: frLocale,
            firstDay: 1,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            height: 'auto',
            editable: false,
            selectable: false,
            dayMaxEvents: 4,
            moreLinkText: (num) => `+${num} autres`,
            events: (fetchInfo, success, failure) => this.loadEvents(fetchInfo, success, failure),
            eventContent: (arg) => this.renderEventContent(arg),
            eventDidMount: (info) => this.styleEvent(info),
            eventClick: (info) => this.handleEventClick(info),
            datesSet: (dateInfo) => this.handleDatesSet(dateInfo)
        });

        this.calendar.render();
    }

    /**
     * Charger les événements depuis l'API
     * Charge 3 mois (précédent, courant, suivant) pour afficher les événements
     * sur toutes les semaines visibles du calendrier
     */
    async loadEvents(fetchInfo, success, failure) {
        // Utiliser le milieu de la plage visible pour obtenir le bon mois
        const midDate = new Date((fetchInfo.start.getTime() + fetchInfo.end.getTime()) / 2);
        const year = midDate.getFullYear();
        const month = midDate.getMonth() + 1;

        // Calculer les 3 mois à charger (précédent, courant, suivant)
        const monthsToLoad = this.getAdjacentMonths(year, month);

        try {
            // Charger les 3 mois en parallèle
            const responses = await Promise.all(
                monthsToLoad.map(m =>
                    fetch(`${this.endpointValue}/${m.year}/${m.month}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                )
            );

            // Vérifier les réponses
            for (const response of responses) {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
            }

            // Parser les données JSON
            const dataArray = await Promise.all(responses.map(r => r.json()));

            // Fusionner les données des 3 mois
            const mergedData = this.mergeMonthsData(dataArray);

            // Stocker les astreintes pour les barres overlay
            this.astreintesData = (mergedData.astreintes || []).filter(a => a.educateur);

            const events = this.transformDataToEvents(mergedData);
            success(events);

            // Rendre les barres d'astreinte après le chargement
            setTimeout(() => this.renderAstreinteBars(), 100);

        } catch (error) {
            console.error('Failed to load planning data:', error);
            failure(error);
        }
    }

    /**
     * Obtenir les mois adjacents (précédent, courant, suivant)
     */
    getAdjacentMonths(year, month) {
        const months = [];

        // Mois précédent
        if (month === 1) {
            months.push({ year: year - 1, month: 12 });
        } else {
            months.push({ year: year, month: month - 1 });
        }

        // Mois courant
        months.push({ year: year, month: month });

        // Mois suivant
        if (month === 12) {
            months.push({ year: year + 1, month: 1 });
        } else {
            months.push({ year: year, month: month + 1 });
        }

        return months;
    }

    /**
     * Fusionner les données de plusieurs mois en évitant les doublons
     */
    mergeMonthsData(dataArray) {
        const merged = {
            plannings: [],
            absences: [],
            rendezvous: [],
            astreintes: [],
            joursChomes: []
        };

        const seenPlanningIds = new Set();
        const seenAbsenceIds = new Set();
        const seenRdvIds = new Set();
        const seenAstreinteIds = new Set();
        const seenJourChomeIds = new Set();

        for (const data of dataArray) {
            // Fusionner les plannings (éviter les doublons)
            if (data.plannings) {
                for (const planning of data.plannings) {
                    // Pour les plannings avec affectations
                    if (planning.affectations && Array.isArray(planning.affectations)) {
                        for (const affectation of planning.affectations) {
                            if (!seenPlanningIds.has(affectation.id)) {
                                seenPlanningIds.add(affectation.id);
                            }
                        }
                        merged.plannings.push(planning);
                    } else if (planning.id && !seenPlanningIds.has(planning.id)) {
                        // Pour les plannings directs (my-planning)
                        seenPlanningIds.add(planning.id);
                        merged.plannings.push(planning);
                    }
                }
            }

            // Fusionner les absences
            if (data.absences) {
                for (const absence of data.absences) {
                    if (!seenAbsenceIds.has(absence.id)) {
                        seenAbsenceIds.add(absence.id);
                        merged.absences.push(absence);
                    }
                }
            }

            // Fusionner les rendez-vous
            if (data.rendezvous) {
                for (const rdv of data.rendezvous) {
                    if (!seenRdvIds.has(rdv.id)) {
                        seenRdvIds.add(rdv.id);
                        merged.rendezvous.push(rdv);
                    }
                }
            }

            // Fusionner les astreintes
            if (data.astreintes) {
                for (const astreinte of data.astreintes) {
                    if (!seenAstreinteIds.has(astreinte.id)) {
                        seenAstreinteIds.add(astreinte.id);
                        merged.astreintes.push(astreinte);
                    }
                }
            }

            // Fusionner les jours chômés
            if (data.joursChomes) {
                for (const jourChome of data.joursChomes) {
                    if (!seenJourChomeIds.has(jourChome.id)) {
                        seenJourChomeIds.add(jourChome.id);
                        merged.joursChomes.push(jourChome);
                    }
                }
            }
        }

        return merged;
    }

    /**
     * Transformer les données API en événements FullCalendar
     */
    transformDataToEvents(data) {
        const events = [];
        const { plannings = [], absences = [], rendezvous = [], joursChomes = [] } = data;

        // Extraire toutes les affectations des plannings
        let allAffectations = [];
        for (const planning of plannings) {
            if (planning.affectations && Array.isArray(planning.affectations)) {
                for (const affectation of planning.affectations) {
                    allAffectations.push({
                        ...affectation,
                        villaFromPlanning: planning.villa
                    });
                }
            } else if (planning.startAt) {
                allAffectations.push(planning);
            }
        }

        // Filtrer les affectations par statut si nécessaire
        let filteredAffectations = allAffectations;
        if (this.hasStatusFilterValue && this.statusFilterValue.length > 0) {
            filteredAffectations = allAffectations.filter(a =>
                this.statusFilterValue.includes(a.statut)
            );
        }

        // Ajouter les affectations
        for (const affectation of filteredAffectations) {
            if (!affectation.startAt) {
                continue;
            }

            const startDate = new Date(affectation.startAt);
            const endDate = new Date(affectation.endAt);

            // Calculer la fin d'affichage (jours travaillés)
            const workingDays = affectation.joursTravailes || this.calculateWorkingDays(startDate, endDate);
            const displayEnd = new Date(startDate);
            displayEnd.setDate(displayEnd.getDate() + workingDays);

            const villa = affectation.villa || affectation.villaFromPlanning;
            const userColor = affectation.user?.color || '#6B7280';
            const isAssigned = !!affectation.user;

            events.push({
                id: `planning-${affectation.id}`,
                title: affectation.user?.fullName || 'À affecter',
                start: startDate.toISOString().split('T')[0],
                end: displayEnd.toISOString().split('T')[0],
                allDay: true,
                backgroundColor: isAssigned ? userColor : '#F3F4F6',
                borderColor: this.getStatusBorderColor(affectation.statut),
                textColor: isAssigned ? this.getContrastColor(userColor) : '#6B7280',
                extendedProps: {
                    type: 'planning',
                    statut: affectation.statut,
                    user: affectation.user,
                    villa: villa,
                    affectationType: affectation.type,
                    workingDays: workingDays,
                    realStart: affectation.startAt,
                    realEnd: affectation.endAt
                }
            });
        }

        // Ajouter les absences (comme événements réguliers, pas background)
        for (const absence of absences) {
            const endDate = new Date(absence.endAt);
            endDate.setDate(endDate.getDate() + 1);

            const absenceClass = this.getAbsenceClass(absence.absenceType?.code);

            events.push({
                id: `absence-${absence.id}`,
                title: `🚫 ${absence.user?.fullName || 'Utilisateur'}`,
                start: absence.startAt,
                end: endDate.toISOString().split('T')[0],
                allDay: true,
                classNames: ['fc-event-absence', absenceClass],
                extendedProps: {
                    type: 'absence',
                    absenceType: absence.absenceType?.label || 'Absence',
                    absenceCode: absence.absenceType?.code,
                    userName: absence.user?.fullName
                }
            });
        }

        // Ajouter les rendez-vous
        for (const rdv of rendezvous) {
            const participants = rdv.participants || [];

            // Déterminer la classe CSS selon le type de RDV
            const rdvTypeClass = this.getRdvTypeClass(rdv.type);
            const participationClass = this.getRdvParticipationClass(rdv.myParticipationStatus);

            events.push({
                id: `rdv-${rdv.id}`,
                title: rdv.title || 'RDV',
                start: rdv.startAt,
                end: rdv.endAt,
                allDay: false,
                classNames: ['fc-event-rdv', rdvTypeClass, participationClass],
                extendedProps: {
                    type: 'rdv',
                    rdvId: rdv.id,
                    participants: participants,
                    rdvTitle: rdv.title,
                    rdvType: rdv.type,
                    rdvTypeLabel: rdv.typeLabel,
                    rdvStatut: rdv.statut,
                    rdvDisplayStatus: rdv.displayStatus,
                    rdvLocation: rdv.location,
                    rdvOrganizer: rdv.organizer,
                    myParticipationStatus: rdv.myParticipationStatus,
                    canConfirm: rdv.canConfirm
                }
            });
        }

        // Ajouter les jours chômés (repos hebdomadaire)
        for (const jourChome of joursChomes) {
            events.push({
                id: `jour-chome-${jourChome.id}`,
                title: 'Repos hebdomadaire',
                start: jourChome.date,
                allDay: true,
                classNames: ['fc-event-jour-chome'],
                extendedProps: {
                    type: 'jour_chome',
                    notes: jourChome.notes
                }
            });
        }

        return events;
    }

    /**
     * Rendu personnalisé du contenu des événements
     */
    renderEventContent(arg) {
        const props = arg.event.extendedProps;
        const { type } = props;

        // Rendu des absences
        if (type === 'absence') {
            return this.renderAbsenceContent(arg, props.userName, props.absenceType, props.absenceCode);
        }

        // Rendu des RDV
        if (type === 'rdv') {
            return this.renderRdvContent(arg, props);
        }

        // Rendu des plannings/gardes
        if (type === 'planning') {
            return this.renderPlanningContent(arg, props.user, props.villa, props.affectationType, props.statut, props.workingDays, props.realStart, props.realEnd);
        }

        // Rendu des jours chômés
        if (type === 'jour_chome') {
            return this.renderJourChomeContent(arg, props);
        }

        return null;
    }

    /**
     * Rendu du contenu d'une absence
     */
    renderAbsenceContent(arg, userName, absenceType, absenceCode) {
        const container = document.createElement('div');
        container.className = 'fc-event-main-custom p-1';
        container.style.cssText = 'height: 100%; display: flex; flex-direction: column; font-size: 0.75rem; line-height: 1.2;';

        const codeLabel = absenceCode || 'ABS';

        container.innerHTML = `
            <div style="display: flex; align-items: center; gap: 4px;">
                <span style="font-size: 0.65rem; padding: 1px 4px; border-radius: 3px; background: rgba(255,255,255,0.9); color: #991B1B; font-weight: 600;">
                    🚫 ${codeLabel}
                </span>
                <span style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: white; text-shadow: 0 0 2px rgba(0,0,0,0.5);">
                    ${userName || 'Utilisateur'}
                </span>
            </div>
        `;

        return { domNodes: [container] };
    }

    /**
     * Rendu du contenu d'un RDV avec code couleur par type et statut de participation
     */
    renderRdvContent(arg, props) {
        const { participants, rdvTitle, rdvType, rdvTypeLabel, myParticipationStatus, canConfirm, rdvLocation } = props;

        const start = new Date(arg.event.start);
        const end = arg.event.end ? new Date(arg.event.end) : start;

        const startTime = start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const endTime = end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        // Icône selon le type de RDV
        const typeConfig = this.getRdvTypeConfig(rdvType);

        // Icône de participation
        const participationConfig = this.getParticipationConfig(myParticipationStatus);

        const container = document.createElement('div');
        container.className = 'fc-event-rdv-custom p-1';
        container.style.cssText = 'height: 100%; display: flex; flex-direction: column; font-size: 0.75rem; line-height: 1.2; overflow: hidden;';

        const participantCount = (participants || []).length;

        container.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; overflow: hidden;">
                <div style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0;">
                    ${typeConfig.icon} ${rdvTitle || 'RDV'}
                </div>
                <span style="font-size: 0.65rem; padding: 1px 4px; border-radius: 3px; background: ${participationConfig.bgColor}; color: ${participationConfig.textColor}; white-space: nowrap; flex-shrink: 0;" title="${participationConfig.label}">
                    ${participationConfig.icon}
                </span>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 3px; align-items: center; margin-top: 2px;">
                <span style="display: inline-block; background: rgba(255,255,255,0.3); font-size: 0.6rem; font-weight: 600; padding: 1px 4px; border-radius: 3px;">
                    ${rdvTypeLabel || rdvType}
                </span>
                ${participantCount > 0 ? `
                    <span style="display: inline-block; background: rgba(255,255,255,0.3); font-size: 0.6rem; font-weight: 600; padding: 1px 4px; border-radius: 3px;">
                        ${participantCount} pers.
                    </span>
                ` : ''}
            </div>
            <div style="opacity: 0.9; font-size: 0.65rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 2px;">
                ${startTime} - ${endTime}${rdvLocation ? ` • ${rdvLocation}` : ''}
            </div>
            ${canConfirm ? `
                <div style="margin-top: 3px; padding: 2px 4px; background: rgba(245, 158, 11, 0.3); border-radius: 3px; font-size: 0.6rem; font-weight: 600; text-align: center;">
                    ⏳ Cliquez pour confirmer
                </div>
            ` : ''}
        `;

        return { domNodes: [container] };
    }

    /**
     * Rendu du contenu d'un planning/garde
     * Mode admin: couleur éducateur, nom affiché
     * Mode employee: couleur villa, pas de nom (c'est le planning de l'employé connecté)
     */
    renderPlanningContent(arg, user, villa, affectationType, statut, workingDays, realStart, realEnd) {
        const isAssigned = !!user;
        const isRenfort = (affectationType === 'renfort' || affectationType === 'TYPE_RENFORT');
        const isMainShift = (affectationType === 'garde_24h' || affectationType === 'garde_48h');

        // Parse dates
        const start = new Date(realStart);
        const end = new Date(realEnd);

        // Format day names
        const dayNames = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        const startDay = dayNames[start.getDay()];
        const endDay = dayNames[end.getDay()];
        const startHour = start.getHours();
        const endHour = end.getHours();

        // Check if same day
        const isSameDay = start.toDateString() === end.toDateString();

        // Status config (only for admin mode)
        const statusConfig = {
            'draft': { label: 'Brouillon', icon: '📝', color: '#9CA3AF' },
            'validated': { label: 'Validé', icon: '✓', color: '#10B981' },
            'to_replace_absence': { label: 'À remplacer', icon: '⚠', color: '#F59E0B' },
            'to_replace_rdv': { label: 'RDV', icon: '📅', color: '#F59E0B' },
            'to_replace_schedule_conflict': { label: 'Conflit', icon: '⏰', color: '#EF4444' }
        };
        const statusInfo = statusConfig[statut] || statusConfig['draft'];

        // Déterminer si la villa est vraiment présente
        const hasVilla = villa && villa.id && villa.nom;

        const container = document.createElement('div');
        container.className = 'fc-event-main-custom p-1';
        container.style.cssText = 'height: 100%; display: flex; flex-direction: column; font-size: 0.75rem; line-height: 1.2;';

        // Mode Employee: affichage simplifié avec couleur villa
        if (this.isEmployeeMode) {
            // Couleur de fond basée sur la villa ou renfort
            const bgColor = isRenfort ? '#6B7280' : (villa?.color || '#6366F1');
            const textColor = this.getContrastColor(bgColor);

            // Titre basé sur le type
            let title = '';
            if (isRenfort) {
                title = hasVilla ? `Renfort ${villa.nom}` : 'Renfort';
            } else if (hasVilla) {
                title = villa.nom;
            } else {
                title = 'Garde';
            }

            container.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                    <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: ${textColor}; flex: 1;">
                        ${title}
                    </div>
                    <span style="font-size: 0.65rem; padding: 1px 4px; border-radius: 3px; background: rgba(255,255,255,0.3); color: ${textColor}; white-space: nowrap; flex-shrink: 0;">
                        ${statusInfo.icon}
                    </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 3px; align-items: center; margin-top: 2px;">
                    ${isRenfort ? '<span style="display: inline-block; background: rgba(255,255,255,0.25); color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">🔧 RENFORT</span>' : ''}
                    ${isMainShift ? '<span style="display: inline-block; background: rgba(255,255,255,0.25); color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">🏠 GARDE</span>' : ''}
                    ${workingDays > 0 ? `<span style="display: inline-block; background: rgba(255,255,255,0.25); color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">${workingDays}j</span>` : ''}
                </div>
                <div style="opacity: 0.9; font-size: 0.65rem; color: ${textColor}; margin-top: 2px;">
                    ${isSameDay ? `${startDay} ${startHour}h - ${endHour}h` : `${startDay} ${startHour}h - ${endDay} ${endHour}h`}
                </div>
            `;

            return { domNodes: [container] };
        }

        // Mode Admin: affichage complet avec couleur éducateur
        if (isAssigned) {
            const textColor = this.getContrastColor(user.color || '#3B82F6');

            container.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                    <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: ${textColor}; flex: 1;">
                        ${user.fullName}
                    </div>
                    <span style="font-size: 0.65rem; padding: 1px 4px; border-radius: 3px; background: ${statusInfo.color}; color: white; white-space: nowrap; flex-shrink: 0;" title="${statusInfo.label}">
                        ${statusInfo.icon}
                    </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 3px; align-items: center; margin-top: 2px;">
                    ${hasVilla ? `<span style="display: inline-block; background: ${villa.color || '#6366F1'}; color: white; font-size: 0.6rem; font-weight: 600; padding: 2px 5px; border-radius: 3px;">📍 ${villa.nom}</span>` : ''}
                    ${isRenfort ? '<span style="display: inline-block; background: #F59E0B; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">🔧 RENFORT</span>' : ''}
                    ${isMainShift ? '<span style="display: inline-block; background: #3B82F6; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">🏠 GARDE</span>' : ''}
                    ${workingDays > 0 ? `<span style="display: inline-block; background: #10B981; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">${workingDays} jour${workingDays > 1 ? 's' : ''}</span>` : ''}
                </div>
                <div style="opacity: 0.8; font-size: 0.65rem; color: ${textColor}; margin-top: 2px;">
                    ${isSameDay ? `${startDay} ${startHour}h - ${endHour}h` : `${startDay} ${startHour}h - ${endDay} ${endHour}h`}
                </div>
            `;
        } else {
            container.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                    <div style="font-weight: 600; color: #6B7280; flex: 1;">
                        À affecter
                    </div>
                    <span style="font-size: 0.65rem; padding: 1px 4px; border-radius: 3px; background: ${statusInfo.color}; color: white; white-space: nowrap; flex-shrink: 0;" title="${statusInfo.label}">
                        ${statusInfo.icon}
                    </span>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 3px; align-items: center; margin-top: 2px;">
                    ${hasVilla ? `<span style="display: inline-block; background: ${villa.color || '#6366F1'}; color: white; font-size: 0.6rem; font-weight: 600; padding: 2px 5px; border-radius: 3px;">📍 ${villa.nom}</span>` : ''}
                    ${isRenfort ? '<span style="display: inline-block; background: #F59E0B; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">🔧 RENFORT</span>' : ''}
                    ${isMainShift ? '<span style="display: inline-block; background: #3B82F6; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">🏠 GARDE</span>' : ''}
                    ${workingDays > 0 ? `<span style="display: inline-block; background: #10B981; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 3px;">${workingDays} jour${workingDays > 1 ? 's' : ''}</span>` : ''}
                </div>
                <div style="color: #6B7280; font-size: 0.65rem; margin-top: 2px;">
                    ${isSameDay ? `${startDay} ${startHour}h - ${endHour}h` : `${startDay} ${startHour}h - ${endDay} ${endHour}h`}
                </div>
            `;
        }

        return { domNodes: [container] };
    }

    /**
     * Rendu du contenu d'un jour chômé (repos hebdomadaire)
     */
    renderJourChomeContent(arg, props) {
        const container = document.createElement('div');
        container.className = 'fc-event-main-custom p-1';
        container.style.cssText = 'height: 100%; display: flex; flex-direction: column; font-size: 0.75rem; line-height: 1.2;';

        container.innerHTML = `
            <div style="display: flex; align-items: center; gap: 4px;">
                <span style="font-size: 1rem;">🏠</span>
                <span style="font-weight: 600; color: #374151;">
                    Repos hebdomadaire
                </span>
            </div>
            ${props.notes ? `<div style="font-size: 0.65rem; color: #6B7280; margin-top: 2px;">${props.notes}</div>` : ''}
        `;

        return { domNodes: [container] };
    }

    /**
     * Styliser les événements après le rendu
     * Mode admin: couleur de fond = éducateur, bordure = villa
     * Mode employee: couleur de fond = villa (ou gris pour renfort partagé), bordure = statut
     */
    styleEvent(info) {
        const { type, statut, affectationType, user, villa } = info.event.extendedProps;

        if (type === 'planning') {
            const isRenfort = (affectationType === 'renfort' || affectationType === 'TYPE_RENFORT');
            const isAssigned = !!user;

            // Mode Employee: couleur de fond = villa, bordure selon statut
            if (this.isEmployeeMode) {
                if (isRenfort) {
                    // Renfort: gris (ou couleur villa si spécifiée)
                    info.el.style.backgroundColor = villa?.color || '#6B7280';
                    info.el.style.border = `3px solid ${this.getStatusBorderColor(statut)}`;
                } else {
                    // Garde normale: couleur villa
                    info.el.style.backgroundColor = villa?.color || '#6366F1';
                    info.el.style.border = `3px solid ${this.getStatusBorderColor(statut)}`;
                }
            } else {
                // Mode Admin: couleur de fond = éducateur, bordure = villa
                if (isAssigned) {
                    info.el.style.backgroundColor = user.color || '#3B82F6';

                    if (isRenfort) {
                        if (villa) {
                            info.el.style.border = `3px solid ${villa.color || '#6B7280'}`;
                        } else {
                            info.el.style.border = '3px solid #6B7280';
                        }
                    } else if (villa) {
                        info.el.style.border = `3px solid ${villa.color || '#10B981'}`;
                    } else {
                        info.el.style.border = '3px solid #10B981';
                    }
                } else {
                    info.el.style.backgroundColor = '#F3F4F6';
                    info.el.style.border = '2px dashed #9CA3AF';
                }
            }
        }
    }

    /**
     * Gérer le clic sur un événement
     */
    handleEventClick(info) {
        const props = info.event.extendedProps;
        const { type } = props;

        if (type === 'planning') {
            this.showPlanningDetails(props);
        } else if (type === 'absence') {
            this.showAbsenceDetails(info.event.title, props.absenceType);
        } else if (type === 'rdv') {
            this.showRdvModal(props, info.event);
        }
    }

    /**
     * Afficher les détails d'un planning
     */
    showPlanningDetails(props) {
        const { statut, villa, user, workingDays, realStart, realEnd } = props;
        const statusLabel = this.getStatusLabel(statut);
        const userName = user?.fullName || 'Non assigné';
        const villaName = villa?.nom || 'Non spécifiée';
        const start = new Date(realStart);
        const end = new Date(realEnd);

        alert(`${userName}\n\nVilla: ${villaName}\nStatut: ${statusLabel}\nDurée: ${workingDays} jour(s)\nDébut: ${start.toLocaleString('fr-FR')}\nFin: ${end.toLocaleString('fr-FR')}`);
    }

    /**
     * Afficher les détails d'une absence
     */
    showAbsenceDetails(title, absenceType) {
        alert(`${title}\n\nType: ${absenceType}`);
    }

    /**
     * Afficher le modal de détails/confirmation RDV
     */
    showRdvModal(props, event) {
        const { rdvId, rdvTitle, rdvType, rdvTypeLabel, rdvLocation, rdvOrganizer, participants, myParticipationStatus, canConfirm } = props;

        // Supprimer le modal existant s'il y en a un
        const existingModal = document.getElementById('rdv-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const start = new Date(event.start);
        const end = event.end ? new Date(event.end) : start;
        const dateStr = start.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const startTime = start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const endTime = end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        const typeConfig = this.getRdvTypeConfig(rdvType);
        const participationConfig = this.getParticipationConfig(myParticipationStatus);

        // Liste des participants avec leur statut
        const participantsList = (participants || []).map(p => {
            const pConfig = this.getParticipationConfig(p.presenceStatus);
            return `<div class="flex items-center justify-between py-1">
                <span>${p.fullName}</span>
                <span class="text-xs px-2 py-0.5 rounded" style="background: ${pConfig.bgColor}; color: ${pConfig.textColor};">${pConfig.icon} ${pConfig.label}</span>
            </div>`;
        }).join('');

        // Créer le modal
        const modal = document.createElement('div');
        modal.id = 'rdv-modal';
        modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="document.getElementById('rdv-modal').remove()"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl  w-full max-h-[90vh] overflow-y-auto">
                <!-- Header avec couleur du type -->
                <div class="p-4 rounded-t-xl" style="background: ${typeConfig.color};">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 text-white">
                                <span class="text-2xl">${typeConfig.icon}</span>
                                <span class="text-sm font-medium opacity-90">${rdvTypeLabel || rdvType}</span>
                            </div>
                            <h3 class="text-xl font-bold text-white mt-1">${rdvTitle || 'Rendez-vous'}</h3>
                        </div>
                        <button onclick="document.getElementById('rdv-modal').remove()" class="text-white/80 hover:text-white p-1">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Contenu -->
                <div class="p-4 space-y-4">
                    <!-- Date et heure -->
                    <div class="flex items-center gap-3 text-gray-700 dark:text-gray-300">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <div>
                            <div class="font-medium">${dateStr}</div>
                            <div class="text-sm text-gray-500">${startTime} - ${endTime}</div>
                        </div>
                    </div>

                    ${rdvLocation ? `
                    <!-- Lieu -->
                    <div class="flex items-center gap-3 text-gray-700 dark:text-gray-300">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>${rdvLocation}</span>
                    </div>
                    ` : ''}

                    ${rdvOrganizer ? `
                    <!-- Organisateur -->
                    <div class="flex items-center gap-3 text-gray-700 dark:text-gray-300">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span>Organisé par <strong>${rdvOrganizer.fullName}</strong></span>
                    </div>
                    ` : ''}

                    <!-- Ma participation -->
                    <div class="p-3 rounded-lg" style="background: ${participationConfig.bgColor}20; border: 1px solid ${participationConfig.bgColor};">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-700 dark:text-gray-300">Ma participation</span>
                            <span class="px-2 py-1 rounded text-sm font-medium" style="background: ${participationConfig.bgColor}; color: ${participationConfig.textColor};">
                                ${participationConfig.icon} ${participationConfig.label}
                            </span>
                        </div>
                    </div>

                    <!-- Participants -->
                    ${participantsList ? `
                    <div>
                        <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Participants (${participants.length})</h4>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 space-y-1 text-sm">
                            ${participantsList}
                        </div>
                    </div>
                    ` : ''}

                    <!-- Boutons d'action -->
                    ${canConfirm ? `
                    <div class="flex gap-3 pt-2">
                        <button onclick="window.dashboardPlanningConfirmRdv(${rdvId}, 'confirm')" class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Confirmer
                        </button>
                        <button onclick="window.dashboardPlanningConfirmRdv(${rdvId}, 'decline')" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Décliner
                        </button>
                    </div>
                    ` : `
                    <div class="pt-2">
                        <button onclick="document.getElementById('rdv-modal').remove()" class="w-full px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 font-medium rounded-lg transition-colors">
                            Fermer
                        </button>
                    </div>
                    `}
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Exposer la fonction de confirmation globalement
        window.dashboardPlanningConfirmRdv = (id, action) => this.confirmRdvParticipation(id, action);
    }

    /**
     * Confirmer ou décliner la participation à un RDV
     */
    async confirmRdvParticipation(rdvId, action) {
        try {
            const response = await fetch(`/api/user-planning/rdv/${rdvId}/participation`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ action })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Erreur lors de la mise à jour');
            }

            // Fermer le modal
            const modal = document.getElementById('rdv-modal');
            if (modal) {
                modal.remove();
            }

            // Afficher le message de succès
            this.showNotification(data.message, 'success');

            // Rafraîchir le calendrier
            this.calendar.refetchEvents();

        } catch (error) {
            console.error('Error updating participation:', error);
            this.showNotification(error.message || 'Erreur lors de la mise à jour', 'error');
        }
    }

    /**
     * Afficher une notification
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white font-medium transition-all transform translate-x-0 ${
            type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600'
        }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Animation d'entrée
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
        }, 3000);

        // Supprimer après l'animation
        setTimeout(() => notification.remove(), 3500);
    }

    /**
     * Gérer le changement de mois
     */
    handleDatesSet(dateInfo) {
        const midDate = new Date((dateInfo.start.getTime() + dateInfo.end.getTime()) / 2);
        this.yearValue = midDate.getFullYear();
        this.monthValue = midDate.getMonth() + 1;

        // Re-render astreinte bars after date change
        setTimeout(() => this.renderAstreinteBars(), 100);
    }

    /**
     * Rendre les barres d'astreinte comme overlays sur les semaines
     */
    renderAstreinteBars() {
        // Supprimer les barres existantes
        const existingBars = this.calendarTarget.querySelectorAll('.astreinte-bar-overlay');
        existingBars.forEach(bar => bar.remove());

        if (!this.astreintesData || this.astreintesData.length === 0) {
            return;
        }

        // Obtenir les lignes de semaine du calendrier
        const weekRows = this.calendarTarget.querySelectorAll('.fc-scrollgrid-section-body tr[role="row"]');
        if (!weekRows || weekRows.length === 0) {
            return;
        }

        // Pour chaque astreinte, trouver la ligne de semaine correspondante
        this.astreintesData.forEach((astreinte) => {
            const astreinteStart = new Date(astreinte.startAt);

            weekRows.forEach((weekRow) => {
                const dayCells = weekRow.querySelectorAll('td.fc-daygrid-day');
                if (dayCells.length === 0) return;

                const firstCell = dayCells[0];
                const lastCell = dayCells[dayCells.length - 1];

                const firstDate = new Date(firstCell.getAttribute('data-date'));
                const lastDate = new Date(lastCell.getAttribute('data-date'));

                // Vérifier si l'astreinte commence dans cette semaine
                if (astreinteStart >= firstDate && astreinteStart <= lastDate) {
                    const bar = this.createAstreinteBar(astreinte, weekRow);
                    if (bar) {
                        weekRow.style.position = 'relative';
                        weekRow.appendChild(bar);
                    }
                }
            });
        });
    }

    /**
     * Créer une barre d'astreinte overlay
     */
    createAstreinteBar(astreinte, weekRow) {
        const educateur = astreinte.educateur;
        if (!educateur) return null;

        const color = educateur.color || '#cccccc';

        const startDate = new Date(astreinte.startAt);
        const endDate = new Date(astreinte.endAt);
        const startFormatted = startDate.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
        const endFormatted = endDate.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });

        // Convert hex to rgba
        const hexToRgba = (hex, alpha) => {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        };

        const bar = document.createElement('div');
        bar.className = 'astreinte-bar-overlay';
        bar.style.position = 'absolute';
        bar.style.top = '0';
        bar.style.left = '0';
        bar.style.right = '0';
        bar.style.bottom = '0';
        bar.style.border = `3px solid ${hexToRgba(color, 0.1)}`;
        bar.style.borderRadius = '6px';
        bar.style.pointerEvents = 'none';
        bar.style.zIndex = '0';

        // Motif hachuré
        const gradient = `repeating-linear-gradient(
            45deg,
            ${hexToRgba(color, 0.10)},
            ${hexToRgba(color, 0.10)} 10px,
            ${hexToRgba(color, 0.05)} 10px,
            ${hexToRgba(color, 0.05)} 20px
        )`;
        bar.style.background = gradient;

        // Texte en haut à gauche
        const text = document.createElement('div');
        text.style.position = 'absolute';
        text.style.top = '8px';
        text.style.left = '12px';
        text.style.color = color;
        text.style.fontWeight = '700';
        text.style.fontSize = '13px';
        text.style.lineHeight = '1.3';
        text.style.textShadow = '0 0 3px white, 0 0 5px white, 1px 1px 2px white';
        text.style.whiteSpace = 'nowrap';
        text.innerHTML = `Astreinte ${astreinte.periodLabel || ''} - ${educateur.fullName}<br><span style="font-size: 11px; font-weight: 600;">${startFormatted} → ${endFormatted}</span>`;

        bar.appendChild(text);

        return bar;
    }

    /**
     * Calculer les jours travaillés
     */
    calculateWorkingDays(startAt, endAt) {
        const start = new Date(startAt);
        const end = new Date(endAt);
        const hours = (end - start) / (1000 * 60 * 60);

        if (hours < 7) return 0;
        return Math.ceil((hours - 3) / 24);
    }

    /**
     * Obtenir la couleur de bordure selon le statut
     */
    getStatusBorderColor(statut) {
        const colors = {
            'draft': '#9CA3AF',
            'validated': '#10B981',
            'to_replace_absence': '#F59E0B',
            'to_replace_rdv': '#F59E0B',
            'to_replace_schedule_conflict': '#EF4444'
        };
        return colors[statut] || '#6B7280';
    }

    /**
     * Obtenir le label du statut
     */
    getStatusLabel(statut) {
        const labels = {
            'draft': 'Brouillon',
            'validated': 'Validé',
            'to_replace_absence': 'À remplacer (absence)',
            'to_replace_rdv': 'À remplacer (RDV)',
            'to_replace_schedule_conflict': 'Conflit horaire'
        };
        return labels[statut] || statut;
    }

    /**
     * Obtenir la classe CSS d'absence selon le type
     */
    getAbsenceClass(absenceTypeCode) {
        const mapping = {
            'CP': 'conge-overlay',
            'RTT': 'conge-overlay',
            'MAL': 'absence-overlay',
            'AT': 'absence-overlay',
            'CPSS': 'conge-overlay'
        };
        return mapping[absenceTypeCode] || 'absence-overlay';
    }

    /**
     * Obtenir une couleur de texte contrastée
     */
    getContrastColor(hexColor) {
        if (!hexColor || hexColor.length !== 7) return '#FFFFFF';

        const r = parseInt(hexColor.substr(1, 2), 16);
        const g = parseInt(hexColor.substr(3, 2), 16);
        const b = parseInt(hexColor.substr(5, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128 ? '#1F2937' : '#FFFFFF';
    }

    /**
     * Obtenir la configuration de couleur selon le type de RDV
     */
    getRdvTypeConfig(rdvType) {
        const configs = {
            'CONVOCATION': { icon: '📋', color: '#DC2626', label: 'Convocation' },      // Rouge - obligatoire
            'DEMANDE': { icon: '📝', color: '#3B82F6', label: 'Demande' },              // Bleu - demande standard
            'VISITE_MEDICALE': { icon: '🏥', color: '#059669', label: 'Visite médicale' }, // Vert - médical
            'individuel': { icon: '👤', color: '#6366F1', label: 'Individuel' },        // Indigo
            'groupe': { icon: '👥', color: '#8B5CF6', label: 'Groupe' }                  // Violet
        };
        return configs[rdvType] || { icon: '📅', color: '#6B7280', label: 'RDV' };
    }

    /**
     * Obtenir la configuration selon le statut de participation
     */
    getParticipationConfig(status) {
        const configs = {
            'PENDING': { icon: '⏳', bgColor: '#F59E0B', textColor: '#FFFFFF', label: 'En attente' },
            'CONFIRMED': { icon: '✓', bgColor: '#10B981', textColor: '#FFFFFF', label: 'Confirmé' },
            'ABSENT': { icon: '✗', bgColor: '#EF4444', textColor: '#FFFFFF', label: 'Décliné' }
        };
        return configs[status] || { icon: '?', bgColor: '#6B7280', textColor: '#FFFFFF', label: 'Inconnu' };
    }

    /**
     * Obtenir la classe CSS selon le type de RDV
     */
    getRdvTypeClass(rdvType) {
        const classes = {
            'CONVOCATION': 'rdv-type-convocation',
            'DEMANDE': 'rdv-type-demande',
            'VISITE_MEDICALE': 'rdv-type-visite-medicale',
            'individuel': 'rdv-type-individuel',
            'groupe': 'rdv-type-groupe'
        };
        return classes[rdvType] || 'rdv-type-default';
    }

    /**
     * Obtenir la classe CSS selon le statut de participation
     */
    getRdvParticipationClass(status) {
        const classes = {
            'PENDING': 'rdv-participation-pending',
            'CONFIRMED': 'rdv-participation-confirmed',
            'ABSENT': 'rdv-participation-absent'
        };
        return classes[status] || 'rdv-participation-unknown';
    }
}
