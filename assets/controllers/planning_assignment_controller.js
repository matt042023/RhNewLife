import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['calendar', 'usersSidebar', 'generateModal', 'monthSelector', 'scopeSelect', 'villaSelect'];
    static values = {
        currentYear: Number,
        currentMonth: Number
    };

    connect() {
        this.draggedUserId = null;
        this.saveTimeout = null;
        this.dropTargetEventId = null;

        // 🆕 Pending changes store for batch save
        this.pendingChanges = new Map();
        this.hasUnsavedChanges = false;

        this.initCalendar();
        this.initDraggableUsers();
        this.setupEventDragListeners();
        this.loadMonth();

        // 🆕 Warn before leaving page with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'Modifications non sauvegardées';
            }
        });
    }

    /**
     * Setup native drag listeners on calendar events
     */
    setupEventDragListeners() {
        // Listen for dragover on the calendar container instead of the whole document for better performance
        this.calendarTarget.addEventListener('dragover', (e) => {
            if (!this.draggedUserId) return;
            e.preventDefault();

            const eventEl = e.target.closest('.fc-event:not(.fc-event-mirror)');
            
            if (eventEl) {
                const eventId = eventEl.getAttribute('data-event-id');
                if (eventId) {
                    this.dropTargetEventId = eventId;
                    
                    // Visual feedback
                    if (!eventEl.classList.contains('drag-hover')) {
                        // Clear others
                        this.calendarTarget.querySelectorAll('.fc-event.drag-hover').forEach(el => {
                            el.classList.remove('drag-hover');
                            el.style.zIndex = '';
                        });
                        
                        eventEl.classList.add('drag-hover');
                        eventEl.style.zIndex = '100';
                    }
                }
            } else {
                this.dropTargetEventId = null;
                this.calendarTarget.querySelectorAll('.fc-event.drag-hover').forEach(el => {
                    el.classList.remove('drag-hover');
                    el.style.zIndex = '';
                });
            }
        });

        // Reset on drag end
        document.addEventListener('dragend', () => {
            this.cleanupDrag();
        });
    }

    /**
     * Initialize draggable external users
     */
    initDraggableUsers() {
        if (!window.FullCalendar || !window.FullCalendar.Draggable) {
            console.error('FullCalendar Draggable not loaded!');
            return;
        }

        const { Draggable } = window.FullCalendar;

        // Make sidebar users draggable
        const containerEl = this.usersSidebarTarget;

        new Draggable(containerEl, {
            itemSelector: '[draggable="true"]',
            // No eventData: this prevents FullCalendar from creating a new event automatically.
            // We handle the assignment manually in the drop callback.
            itemData: (eventEl) => {
                const userId = eventEl.dataset.userId;
                this.draggedUserId = userId;
                return {
                    userId: userId
                };
            }
        });

        console.log('Draggable users initialized');
    }

    /**
     * Initialize FullCalendar with monthly view
     */
    initCalendar() {
        if (!window.FullCalendar) {
            console.error('FullCalendar not loaded!');
            return;
        }

        const { Calendar, dayGridPlugin, timeGridPlugin, interactionPlugin } = window.FullCalendar;

        this.calendar = new Calendar(this.calendarTarget, {
            plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
            initialView: 'dayGridMonth',
            height: 'auto',
            editable: true,
            droppable: true,
            locale: 'fr',
            firstDay: 1,
            displayEventTime: false, // Hide time in monthly view

            // Allow events to overlap (gardes and RDVs can overlay on absences)
            slotEventOverlap: true,
            eventOverlap: true,

            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },

            // Event handlers
            eventDrop: (info) => this.handleEventDrop(info),
            eventResize: (info) => this.handleEventResize(info),
            eventDidMount: (info) => this.renderEvent(info),
            eventContent: (arg) => this.renderEventContent(arg),
            eventClick: (info) => this.handleEventClick(info),
            drop: (info) => this.handleExternalDrop(info),

            // View-specific settings
            viewDidMount: (info) => {
                // Show/hide time based on view
                if (info.view.type === 'dayGridMonth') {
                    this.calendar.setOption('displayEventTime', false);
                } else {
                    this.calendar.setOption('displayEventTime', true);
                }
            },

            // Event source
            events: (fetchInfo, successCallback, failureCallback) => {
                this.loadEvents(fetchInfo, successCallback, failureCallback);
            }
        });

        this.calendar.render();
        console.log('Calendar initialized successfully');
    }

    /**
     * Load events from API for the current view
     */
    async loadEvents(fetchInfo, successCallback, failureCallback) {
        const year = this.currentYearValue;
        const month = this.currentMonthValue;

        console.log(`Loading events for ${year}/${month}`);

        try {
            // Add timestamp to avoid caching
            const timestamp = new Date().getTime();

            // Fetch plannings, absences, and RDVs in parallel
            const [planningsResponse, absencesResponse, rdvsResponse] = await Promise.all([
                fetch(`/api/planning-assignment/${year}/${month}?t=${timestamp}`, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }),
                fetch(`/api/planning-assignment/absences/${year}/${month}?t=${timestamp}`, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                }),
                fetch(`/api/planning-assignment/rendezvous/${year}/${month}?t=${timestamp}`, {
                    cache: 'no-store',
                    credentials: 'same-origin'
                })
            ]);

            if (!planningsResponse.ok || !absencesResponse.ok || !rdvsResponse.ok) {
                throw new Error(`HTTP error`);
            }

            const planningsData = await planningsResponse.json();
            const absencesData = await absencesResponse.json();
            const rdvsData = await rdvsResponse.json();

            console.log('API Response:', { planningsData, absencesData, rdvsData });

            // Convert plannings to FullCalendar events
            const events = [];

            // 1. Add affectations (shifts) as regular events
            for (const planning of planningsData.plannings) {
                for (const affectation of planning.affectations) {
                    const villaName = affectation.villa?.nom || 'Villa inconnue';
                    const userName = affectation.user?.fullName || 'À affecter';

                    // Récupérer jours travaillés depuis l'API (avec fallback si null)
                    const workingDays = affectation.joursTravailes ??
                        this.calculateWorkingDays(affectation.startAt, affectation.endAt);

                    // Détecter le type de vue actuel
                    const currentView = this.calendar.view.type;
                    const isMonthView = currentView === 'dayGridMonth';

                    // Calculer la date de début/fin d'affichage selon la vue
                    let eventStart, eventEnd;

                    if (isMonthView) {
                        // Vue mensuelle : utiliser format date uniquement (pas d'heure)
                        // pour éviter les problèmes de timezone
                        const startDate = new Date(affectation.startAt);
                        const displayEnd = new Date(startDate);
                        displayEnd.setDate(displayEnd.getDate() + workingDays);

                        // Format YYYY-MM-DD pour vue mensuelle (all-day events)
                        eventStart = startDate.toISOString().split('T')[0];
                        eventEnd = displayEnd.toISOString().split('T')[0];
                    } else {
                        // Vue hebdomadaire/jour : utiliser l'heure de fin réelle avec heures
                        eventStart = affectation.startAt;
                        eventEnd = affectation.endAt;
                    }

                    events.push({
                        id: affectation.id,
                        start: eventStart, // Conditionnel selon la vue
                        end: eventEnd, // Conditionnel selon la vue
                        title: `${villaName} - ${userName}`,
                        order: 1, // Affectations en 2ème position (après absences)
                        extendedProps: {
                            affectation,
                            user: affectation.user,
                            villa: affectation.villa,
                            type: affectation.type,
                            statut: affectation.statut,
                            workingDays: workingDays,
                            realStart: affectation.startAt,
                            realEnd: affectation.endAt
                        }
                    });
                }
            }

            // 2. Add absences as regular events with special rendering (visible with striped pattern)
            for (const absence of absencesData) {
                // Les absences sont maintenant des dates (jours complets) - pas d'heures
                // Il faut ajouter 1 jour à la date de fin pour FullCalendar (end est exclusif)
                const endDate = new Date(absence.endAt);
                endDate.setDate(endDate.getDate() + 1);

                events.push({
                    id: `absence-${absence.id}`,
                    title: `🚫 ${absence.user.fullName} - ${absence.absenceType.label}`,
                    start: absence.startAt,
                    end: endDate.toISOString().split('T')[0],
                    allDay: true,
                    order: 2, // Absences en 1ère position (en haut)
                    editable: false,
                    classNames: ['fc-event-absence', this.getAbsenceClass(absence.absenceType.code)],
                    extendedProps: {
                        type: 'absence',
                        absenceTypeCode: absence.absenceType.code,
                        absenceTypeLabel: absence.absenceType.label,
                        user: absence.user,
                        absenceData: absence
                    }
                });
            }

            // 3. Add RDVs as regular events (same z-index as gardes/affectations)
            for (const rdv of rdvsData) {
                events.push({
                    id: `rdv-${rdv.id}`,
                    title: rdv.title,
                    start: rdv.startAt,
                    end: rdv.endAt,
                    order: 3, // RDVs en 3ème position (en bas)
                    editable: false,  // RDVs are not editable in planning view
                    classNames: ['fc-event-rdv'],
                    extendedProps: {
                        type: 'rdv',
                        participants: rdv.participants,
                        rdvTitle: rdv.title,
                        rdvData: rdv
                    }
                });
            }

            const affectationsCount = planningsData.plannings.reduce((acc, p) => acc + p.affectations.length, 0);
            console.log(`Loaded ${events.length} events (${affectationsCount} affectations, ${absencesData.length} absences, ${rdvsData.length} RDVs)`);

            // Debug: Log absences specifically
            if (absencesData.length > 0) {
                console.log('Absences loaded:', absencesData);
                console.log('Sample absence event:', events.find(e => e.extendedProps?.type === 'absence'));
            } else {
                console.warn('⚠️ No absences found for this month');
            }

            successCallback(events);

        } catch (error) {
            console.error('Failed to load events:', error);
            this.showError('Échec du chargement des événements');
            failureCallback(error);
        }
    }

    /**
     * Calculate working days (24h + 3h tolerance rule)
     * Rule: < 7h = 0 days, else ceil((hours - 3) / 24)
     * Examples: 7h=1d, 27h=1d, 27h01=2d, 50h=2d, 51h=2d, 51h01=3d, 52h=3d
     */
    calculateWorkingDays(startAt, endAt) {
        const start = new Date(startAt);
        const end = new Date(endAt);

        const hoursDiff = (end - start) / (1000 * 60 * 60);

        // If less than 7 hours, doesn't count
        if (hoursDiff < 7) {
            return 0;
        }

        // New rule: 24h + 3h tolerance
        // Each 24h block with >3h additional = 1 more day
        // Formula: ceil((hours - 3) / 24)
        return Math.ceil((hoursDiff - 3) / 24);
    }

    /**
     * Get absence CSS class based on absence type code
     */
    getAbsenceClass(absenceTypeCode) {
        const mapping = {
            'CP': 'conge-overlay',     // Congés payés
            'RTT': 'conge-overlay',    // RTT
            'MAL': 'absence-overlay',  // Maladie
            'AT': 'absence-overlay',   // Accident travail
            'CPSS': 'conge-overlay'    // Congé sans solde
        };
        return mapping[absenceTypeCode] || 'absence-overlay';
    }


    /**
     * Render event with color system (educator background + villa/type border)
     */
    renderEvent(info) {
        const { user, villa, type } = info.event.extendedProps;

        // Add event ID as data-attribute for drag & drop detection
        info.el.setAttribute('data-event-id', info.event.id);

        const isRenfort = (type === 'renfort' || type === 'TYPE_RENFORT');
        const isAssigned = !!user;

        // Drag-hover feedback for ALL events (assigned or not)
        info.el.addEventListener('dragenter', (e) => {
            if (!this.draggedUserId) return;
            info.el.classList.add('drag-hover');
        });

        info.el.addEventListener('dragleave', (e) => {
            info.el.classList.remove('drag-hover');
        });

        info.el.addEventListener('drop', (e) => {
            info.el.classList.remove('drag-hover');
        });

        if (isAssigned) {
            // ASSIGNED: Educator color background
            info.el.style.backgroundColor = user.color || '#3B82F6';

            if (isRenfort) {
                // Renfort: gray border (identifies type)
                info.el.style.border = '3px solid #6B7280';
            } else if (villa) {
                // Villa garde: villa color border
                info.el.style.border = `3px solid ${villa.color || '#10B981'}`;
            } else {
                info.el.style.border = '3px solid #10B981';
            }

            // Auto-adjust text color for contrast
            info.el.style.color = this.getContrastColor(user.color || '#3B82F6');

        } else {
            // NOT ASSIGNED: white background, dashed border
            info.el.style.backgroundColor = '#FFFFFF';
            info.el.style.color = '#6B7280';

            if (isRenfort) {
                info.el.style.border = '2px dashed #6B7280';
            } else if (villa) {
                info.el.style.border = `2px dashed ${villa.color || '#10B981'}`;
            } else {
                info.el.style.border = '2px dashed #10B981';
            }
        }

        // Note: Absences/RDV overlays are now rendered as independent background events
        // No need to call renderEventOverlay() here
    }

    /**
     * Render RDV event content with participant list
     */
    renderRdvContent(arg, participants, title) {
        const isMonthView = arg.view.type === 'dayGridMonth';
        const start = new Date(arg.event.start);
        const end = arg.event.end ? new Date(arg.event.end) : start;

        const startTime = start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const endTime = end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        const container = document.createElement('div');
        container.className = 'fc-event-rdv-custom p-1';
        container.style.cssText = 'height: 100%; display: flex; flex-direction: column; font-size: 0.75rem; line-height: 1.2; overflow: hidden;';

        // Build participants list - limit to 3 names max for readability
        const participantCount = participants.length;
        let participantText;

        if (participantCount <= 2) {
            participantText = participants.map(p => p.fullName).join(', ');
        } else {
            // Show first 2 names + count
            const firstTwo = participants.slice(0, 2).map(p => p.fullName).join(', ');
            participantText = `${firstTwo} +${participantCount - 2}`;
        }

        container.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; overflow: hidden;">
                <div style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0;">
                    📅 ${title}
                </div>
                <span style="font-size: 0.65rem; padding: 1px 4px; border-radius: 3px; background: #D97706; color: white; white-space: nowrap; flex-shrink: 0;">
                    ${participantCount} pers.
                </span>
            </div>
            <div style="opacity: 0.9; font-size: 0.7rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                ${participantText}
            </div>
            <div style="opacity: 0.8; font-size: 0.65rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                ${startTime} - ${endTime}
            </div>
        `;

        return { domNodes: [container] };
    }

    /**
     * Custom event content rendering
     */
    renderEventContent(arg) {
        const { user, villa, type, realStart, realEnd, statut, participants, rdvTitle } = arg.event.extendedProps;
        const isAssigned = !!user;
        const isMonthView = arg.view.type === 'dayGridMonth';
        const isRdv = type === 'rdv';

        // Note: Absence events are now background events (display: 'background')
        // FullCalendar doesn't call eventContent for background events - they only show the title

        // Special rendering for RDV events
        if (isRdv && participants) {
            return this.renderRdvContent(arg, participants, rdvTitle);
        }

        // Parse dates
        const start = new Date(realStart);
        const end = new Date(realEnd);

        // Format time
        const startTime = start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const endTime = end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        // Calculate duration
        const durationHours = Math.round((end - start) / (1000 * 60 * 60));

        // Type labels
        const typeLabels = {
            'garde_48h': '48h',
            'garde_24h': '24h',
            'renfort': 'Renfort',
            'TYPE_RENFORT': 'Renfort',
            'autre': 'Autre'
        };
        const typeLabel = typeLabels[type] || type;

        // Status labels and styles
        const statusConfig = {
            'draft': { label: 'Brouillon', icon: '📝', color: '#9CA3AF' },
            'validated': { label: 'Validé', icon: '✓', color: '#10B981' },
            'to_replace_absence': { label: 'À remplacer', icon: '⚠', color: '#F59E0B' },
            'to_replace_rdv': { label: 'RDV', icon: '📅', color: '#F59E0B' }
        };
        const statusInfo = statusConfig[statut] || statusConfig['draft'];

        // Create custom HTML content
        const container = document.createElement('div');
        container.className = 'fc-event-main-custom p-1';
        container.style.cssText = 'height: 100%; display: flex; flex-direction: column; font-size: 0.75rem; line-height: 1.2;';

        if (isAssigned) {
            // GARDE AFFECTÉE: Nom éducateur + infos
            // Get text color for contrast with educator background color
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
                ${isMonthView ? `
                    <div style="opacity: 0.9; font-size: 0.7rem; color: ${textColor};">
                        ${villa?.nom || 'Villa'} · ${typeLabel}
                    </div>
                    <div style="opacity: 0.8; font-size: 0.65rem; color: ${textColor};">
                        ${startTime} - ${endTime} (${durationHours}h)
                    </div>
                ` : `
                    <div style="opacity: 0.9; color: ${textColor};">
                        ${villa?.nom || 'Villa'} · ${typeLabel} · ${durationHours}h
                    </div>
                `}
            `;
        } else {
            // GARDE NON AFFECTÉE: Infos de la garde
            container.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                    <div style="font-weight: 600; color: #6B7280; flex: 1;">
                        À affecter
                    </div>
                    <span style="font-size: 0.65rem; padding: 1px 4px; border-radius: 3px; background: ${statusInfo.color}; color: white; white-space: nowrap; flex-shrink: 0;" title="${statusInfo.label}">
                        ${statusInfo.icon}
                    </span>
                </div>
                ${isMonthView ? `
                    <div style="color: #6B7280; font-size: 0.7rem;">
                        ${villa?.nom || 'Villa'} · ${typeLabel}
                    </div>
                    <div style="color: #6B7280; font-size: 0.65rem;">
                        ${startTime} - ${endTime} (${durationHours}h)
                    </div>
                ` : `
                    <div style="color: #6B7280;">
                        ${villa?.nom || 'Villa'} · ${typeLabel} · ${durationHours}h
                    </div>
                `}
            `;
        }

        return { domNodes: [container] };
    }

    /**
     * DEPRECATED: This method is no longer used.
     * Absences and RDVs are now rendered as independent background events,
     * not as overlays on shift events.
     */
    // async renderEventOverlay(info) { ... }
    // getOverlayPattern(type) { ... }

    /**
     * Calculate contrasting text color (black/white) based on background
     */
    getContrastColor(hexColor) {
        if (!hexColor || hexColor.length !== 7) return '#000000';

        const r = parseInt(hexColor.substr(1, 2), 16);
        const g = parseInt(hexColor.substr(3, 2), 16);
        const b = parseInt(hexColor.substr(5, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128 ? '#000000' : '#FFFFFF';
    }

    /**
     * Handle event drop (moved within calendar)
     */
    async handleEventDrop(info) {
        const affectationId = info.event.id;
        const newStart = info.event.start;
        const newEnd = info.event.end;

        // 🆕 Add to pending store instead of debounced PUT
        this.addPendingChange(affectationId, 'update', {
            startAt: newStart.toISOString(),
            endAt: newEnd.toISOString()
        });

        // No refetch, update is optimistic
        this.showInfo('Horaires modifiés localement');
    }

    /**
     * Handle event resize
     */
    handleEventResize(info) {
        this.handleEventDrop(info); // Same logic as drop
    }

    /**
     * Handle external drop (user from sidebar)
     */
    async handleExternalDrop(info) {
        // userId should be on the dragged element
        const userId = info.draggedEl?.dataset.userId || this.draggedUserId;
        const jsEvent = info.jsEvent;

        if (!userId) {
            console.error('No user ID found for drop');
            return;
        }

        console.log('Drop detected:', { userId, dropTargetEventId: this.dropTargetEventId });

        // Identify the exact event targeted
        let affectationId = this.dropTargetEventId;

        // Force detection via point if not already found (more reliable at the exact moment of drop)
        if (!affectationId && jsEvent) {
            const el = document.elementFromPoint(jsEvent.clientX, jsEvent.clientY);
            const eventEl = el ? el.closest('.fc-event:not(.fc-event-mirror)') : null;
            if (eventEl) {
                affectationId = eventEl.getAttribute('data-event-id');
                console.log('Detected event via coordinates on drop:', affectationId);
            }
        }

        if (!affectationId) {
            // Fallback: search by date if no specific event was detected
            const dropDate = info.date;
            const currentView = this.calendar.view.type;
            const isMonthView = currentView === 'dayGridMonth';

            const events = this.calendar.getEvents().filter(event => {
                const eventStart = event.start;
                const eventEnd = event.end;

                if (isMonthView) {
                    const dropDay = dropDate.toDateString();
                    const eventStartDay = eventStart.toDateString();
                    return dropDay === eventStartDay ||
                           (dropDate >= eventStart && dropDate < eventEnd);
                } else {
                    return dropDate >= eventStart && dropDate < eventEnd;
                }
            });

            console.log('Events found at drop position:', events.length, events.map(e => e.id));

            if (events.length === 0) {
                this.showError('Déposez l\'éducateur sur une garde (encadré pointillé)');
                this.cleanupDrag();
                return;
            }

            if (events.length > 1) {
                this.showEventSelectionModal(events, userId);
                this.cleanupDrag();
                return;
            }

            affectationId = events[0].id;
        }

        console.log('Assigning user to affectation:', { userId, affectationId });

        // 🆕 Add to pending store instead of immediate POST
        this.addPendingChange(affectationId, 'assign', { userId });

        // 🆕 Update UI optimistically (without refetch API)
        this.updateEventOptimistic(affectationId, { userId });

        this.showInfo('Modification enregistrée localement');
        this.cleanupDrag();
    }

    /**
     * Cleanup drag state and visual feedback
     */
    cleanupDrag() {
        this.draggedUserId = null;
        this.dropTargetEventId = null;

        document.querySelectorAll('.fc-event').forEach(el => {
            el.classList.remove('drag-hover');
            el.style.opacity = '';
            el.style.transform = '';
            el.style.zIndex = '';
        });
    }

    /**
     * Show modal to select which event to assign when multiple events on same day
     */
    showEventSelectionModal(events, userId) {
        const eventsList = events.map(event => {
            const props = event.extendedProps;
            const villa = props.villa?.nom || 'Villa inconnue';
            const startTime = event.start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            const endTime = event.end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

            return `
                <button onclick="window.planningController.assignUserToEvent('${event.id}', '${userId}')"
                        class="w-full text-left p-3 border rounded hover:bg-blue-50 mb-2">
                    <div class="font-semibold">${villa}</div>
                    <div class="text-sm text-gray-600">${startTime} - ${endTime}</div>
                </button>
            `;
        }).join('');

        const modalHtml = `
            <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center" id="eventSelectionModal">
                <div class="bg-white rounded-lg p-6 w-full mx-4" style="max-width: 500px;">
                    <h2 class="text-xl font-bold mb-4">Choisir la garde</h2>
                    <p class="text-gray-600 mb-4">Plusieurs gardes trouvées ce jour. Laquelle voulez-vous affecter ?</p>
                    <div class="space-y-2 mb-4">
                        ${eventsList}
                    </div>
                    <div class="flex justify-end">
                        <button onclick="document.getElementById('eventSelectionModal').remove()"
                                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    /**
     * Assign user to a specific event (called from selection modal)
     */
    async assignUserToEvent(affectationId, userId) {
        // Close modal
        const modal = document.getElementById('eventSelectionModal');
        if (modal) modal.remove();

        console.log('Assigning user to affectation:', { userId, affectationId });

        try {
            const response = await this.fetchAPI('/api/planning-assignment/assign', {
                method: 'POST',
                body: JSON.stringify({
                    affectationId,
                    userId
                })
            });

            if (response.warnings && response.warnings.length > 0) {
                this.showWarningsToast(response.warnings);
            }

            // Reload calendar
            this.calendar.refetchEvents();
            this.showSuccess('Éducateur affecté avec succès');

        } catch (error) {
            console.error('Failed to assign user:', error);
            this.showError('Échec de l\'affectation');
        }
    }

    /**
     * Handle drag start from sidebar
     */
    handleDragStart(event) {
        this.draggedUserId = event.currentTarget.dataset.userId;
        event.currentTarget.classList.add('dragging');
        console.log('Drag start:', this.draggedUserId);
    }

    handleDragEnd(event) {
        if (event.currentTarget) {
            event.currentTarget.classList.remove('dragging');
        }
        this.cleanupDrag();
    }

    /**
     * Load month data
     */
    loadMonth(event = null) {
        if (event) {
            const [year, month] = event.target.value.split('-');
            this.currentYearValue = parseInt(year);
            this.currentMonthValue = parseInt(month);
        }

        // Reload calendar
        if (this.calendar) {
            this.calendar.refetchEvents();
        }
    }

    /**
     * Open generate modal
     */
    openGenerateModal() {
        console.log('Opening generate modal');
        if (!this.hasGenerateModalTarget) {
            console.error('Generate modal target not found');
            return;
        }
        this.generateModalTarget.classList.remove('hidden');
        console.log('Modal opened');
    }

    /**
     * Close modal
     */
    closeModal() {
        this.generateModalTarget.classList.add('hidden');
    }

    /**
     * 🆕 Open bulk delete modal for draft shifts cleanup
     */
    openBulkDeleteModal() {
        // Get villas from generate modal template (already rendered)
        const villaOptions = Array.from(document.querySelectorAll('[data-planning-assignment-target="villaSelect"] option'))
            .map(opt => ({ id: opt.value, nom: opt.textContent.trim() }))
            .filter(v => v.id); // Filter out empty values

        const modalHtml = `
            <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center" id="bulkDeleteModal">
                <div class="bg-white rounded-lg p-6 w-full mx-4" style="max-width: 600px;">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Supprimer les gardes brouillon</h2>

                    <div class="mb-6">
                        <p class="text-gray-700 mb-4">
                            Cette action supprimera <strong>toutes les gardes non validées</strong>
                            (statut brouillon) sur la période sélectionnée.
                        </p>

                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <p class="text-sm text-red-800 flex items-start">
                                <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span>
                                    <strong>Attention :</strong> Cette action est irréversible.
                                    Les gardes déjà validées ne seront pas affectées.
                                </span>
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Période</label>
                                <select id="bulkDeletePeriod" class="w-full border-gray-300 rounded-lg">
                                    <option value="current-month">Mois en cours uniquement</option>
                                    <option value="all">Tous les mois (année complète)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold mb-2">Villas concernées</label>
                                <select id="bulkDeleteVilla" class="w-full border-gray-300 rounded-lg">
                                    <option value="all">Toutes les villas</option>
                                    ${villaOptions.map(v => `<option value="${v.id}">${v.nom}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button"
                                onclick="document.getElementById('bulkDeleteModal').remove()"
                                class="px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-lg font-medium">
                            Annuler
                        </button>
                        <button type="button"
                                id="confirmBulkDeleteButton"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium">
                            Supprimer
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Attach confirm handler
        document.getElementById('confirmBulkDeleteButton').addEventListener('click', () => {
            this.confirmBulkDelete();
        });
    }

    /**
     * 🆕 Confirm and execute bulk delete
     */
    async confirmBulkDelete() {
        const period = document.getElementById('bulkDeletePeriod').value;
        const villaId = document.getElementById('bulkDeleteVilla').value;

        const year = this.currentYearValue;
        const month = this.currentMonthValue;

        try {
            this.showInfo('Suppression en cours...');

            const response = await this.fetchAPI('/api/planning-assignment/bulk-delete', {
                method: 'POST',
                body: JSON.stringify({
                    year,
                    month: period === 'current-month' ? month : null,
                    villaId: villaId === 'all' ? null : parseInt(villaId)
                })
            });

            // Close modal
            const modal = document.getElementById('bulkDeleteModal');
            if (modal) modal.remove();

            // Reload calendar
            this.calendar.refetchEvents();

            this.showSuccess(`${response.deleted} garde${response.deleted > 1 ? 's' : ''} supprimée${response.deleted > 1 ? 's' : ''}`);

        } catch (error) {
            console.error('Failed to bulk delete:', error);
            this.showError('Échec de la suppression en masse');
        }
    }

    /**
     * Toggle villa select based on scope
     */
    toggleVillaSelect(event) {
        const scope = event.target.value;
        if (scope === 'villa') {
            this.villaSelectTarget.classList.remove('hidden');
        } else {
            this.villaSelectTarget.classList.add('hidden');
        }
    }

    /**
     * Submit generate form
     */
    async submitGenerate(event) {
        event.preventDefault();

        const formData = new FormData(event.target);
        const data = {
            templateId: parseInt(formData.get('templateId')),
            startDate: formData.get('startDate'),
            endDate: formData.get('endDate'),
            scope: formData.get('scope'),
            villaId: formData.get('villaId') ? parseInt(formData.get('villaId')) : null
        };

        try {
            const response = await this.fetchAPI('/api/planning-assignment/generate', {
                method: 'POST',
                body: JSON.stringify(data)
            });

            this.closeModal();
            this.calendar.refetchEvents();
            this.showSuccess(`${response.created} affectations créées avec succès`);

        } catch (error) {
            console.error('Failed to generate skeleton:', error);
            this.showError('Échec de la génération du squelette');
        }
    }

    /**
     * Validate planning for current month
     */
    async validatePlanning() {
        const year = this.currentYearValue;
        const month = this.currentMonthValue;

        // Confirm with user
        if (!confirm(`Êtes-vous sûr de vouloir valider toutes les affectations du mois ${month}/${year} ?\n\nToutes les gardes en brouillon seront marquées comme validées et apparaîtront dans le planning des éducateurs.`)) {
            return;
        }

        this.showInfo('Validation du planning en cours...');

        try {
            const response = await fetch('/api/planning-assignment/validate-month', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ year, month })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.error || 'Erreur lors de la validation');
            }

            // Show success message
            this.showSuccess(data.message || `${data.validated} affectation(s) validée(s)`);

            // Show warnings if any
            if (data.warnings && data.warnings.length > 0) {
                this.showWarningsToast(data.warnings);
            }

            // Reload calendar to show updated statuses
            this.calendar.refetchEvents();

        } catch (error) {
            console.error('Failed to validate planning:', error);
            this.showError('Échec de la validation du planning');
        }
    }

    /**
     * Handle event click (monthly view) - opens edit modal
     */
    handleEventClick(info) {
        const { type } = info.event.extendedProps;

        // Handle absence click
        if (type === 'absence') {
            this.showAbsenceDetails(info.event.extendedProps);
            return;
        }

        // Handle RDV click
        if (type === 'rdv') {
            this.showRdvDetails(info.event.extendedProps);
            return;
        }

        // Handle regular affectation click - open modal in ALL views
        const { affectation, user, villa, realStart, realEnd, workingDays } = info.event.extendedProps;
        const eventId = info.event.id;

        // Render rich edit modal (works in monthly, weekly, and daily views)
        this.renderEditModal(eventId, {
            affectation,
            user,
            villa,
            startAt: new Date(realStart),
            endAt: new Date(realEnd),
            workingDays
        });
    }

    /**
     * 🆕 Render rich edit modal with all fields
     */
    renderEditModal(eventId, data) {
        const { affectation, user, villa, startAt, endAt, workingDays } = data;

        // Get all users from sidebar
        const userCards = Array.from(document.querySelectorAll('[data-user-id]'));
        const users = userCards.map(card => ({
            id: card.dataset.userId,
            fullName: card.querySelector('.font-medium')?.textContent?.trim() || 'Utilisateur',
            remainingDays: card.querySelector('.text-xs')?.textContent?.match(/\d+/)?.[0] || null,
            color: card.dataset.userColor || '#3B82F6'
        }));

        const hoursDiff = Math.round((endAt - startAt) / (1000 * 60 * 60));

        const modalHtml = `
            <div class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center" id="editAffectationModal">
                <div class="bg-white rounded-lg p-6 w-full mx-4" style="max-width: 700px;">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-900">Édition de la garde</h2>
                        <button onclick="document.getElementById('editAffectationModal').remove()"
                                class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form id="editAffectationForm" class="space-y-6">
                        <!-- Villa (read-only) -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Villa</label>
                            <input type="text" value="${villa?.nom || 'N/A'}" disabled
                                   class="w-full border-gray-300 bg-gray-100 rounded-lg px-3 py-2">
                            <p class="text-xs text-gray-500 mt-1">La villa ne peut pas être modifiée après création</p>
                        </div>

                        <!-- Type de garde -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Type de garde</label>
                            <select id="editType" class="w-full border-gray-300 rounded-lg">
                                <option value="garde_48h" ${affectation.type === 'garde_48h' ? 'selected' : ''}>Garde 48h</option>
                                <option value="garde_24h" ${affectation.type === 'garde_24h' ? 'selected' : ''}>Garde 24h</option>
                                <option value="renfort" ${affectation.type === 'renfort' ? 'selected' : ''}>Renfort</option>
                                <option value="autre" ${affectation.type === 'autre' ? 'selected' : ''}>Autre</option>
                            </select>
                        </div>

                        <!-- Éducateur assigné -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Éducateur assigné</label>
                            <select id="editUser" class="w-full border-gray-300 rounded-lg">
                                <option value="">-- Non assigné --</option>
                                ${users.map(u => `
                                    <option value="${u.id}" ${u.id == user?.id ? 'selected' : ''}>
                                        ${u.fullName} ${u.remainingDays ? `(${u.remainingDays}j restants)` : ''}
                                    </option>
                                `).join('')}
                            </select>
                        </div>

                        <!-- Horaires -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Début</label>
                                <input type="datetime-local" id="editStartAt"
                                       value="${this.formatDateForInput(startAt)}"
                                       class="w-full border-gray-300 rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Fin</label>
                                <input type="datetime-local" id="editEndAt"
                                       value="${this.formatDateForInput(endAt)}"
                                       class="w-full border-gray-300 rounded-lg">
                            </div>
                        </div>

                        <!-- Durée calculée -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p class="text-sm text-blue-800">
                                <span class="font-semibold">Durée calculée :</span>
                                <span id="calculatedDuration">${hoursDiff}h (${workingDays} jour${workingDays > 1 ? 's' : ''} travaillé${workingDays > 1 ? 's' : ''})</span>
                            </p>
                        </div>

                        <!-- Commentaire -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Commentaire (optionnel)</label>
                            <textarea id="editCommentaire" rows="3"
                                      class="w-full border-gray-300 rounded-lg"
                                      placeholder="Notes ou observations...">${affectation.commentaire || ''}</textarea>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-between items-center pt-4 border-t">
                            <button type="button"
                                    onclick="window.planningController.deleteAffectation('${eventId}')"
                                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Supprimer
                            </button>

                            <div class="flex space-x-2">
                                <button type="button"
                                        onclick="document.getElementById('editAffectationModal').remove()"
                                        class="px-4 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-lg font-medium">
                                    Annuler
                                </button>
                                <button type="submit"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium">
                                    Enregistrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Attach form submit handler
        document.getElementById('editAffectationForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveAffectationFromModal(eventId);
        });

        // Real-time duration calculation with new formula
        const startInput = document.getElementById('editStartAt');
        const endInput = document.getElementById('editEndAt');
        const updateDuration = () => {
            const start = new Date(startInput.value);
            const end = new Date(endInput.value);
            const hours = Math.round((end - start) / (1000 * 60 * 60));

            // Apply new working days formula: ceil((hours - 3) / 24)
            let days;
            if (hours < 7) {
                days = 0;
            } else {
                days = Math.ceil((hours - 3) / 24);
            }

            document.getElementById('calculatedDuration').textContent =
                `${hours}h (${days} jour${days > 1 ? 's' : ''} travaillé${days > 1 ? 's' : ''})`;
        };
        startInput.addEventListener('change', updateDuration);
        endInput.addEventListener('change', updateDuration);

        // Store controller reference
        window.planningController = this;
    }

    /**
     * 🆕 Save affectation from modal
     */
    async saveAffectationFromModal(eventId) {
        const formData = {
            type: document.getElementById('editType').value,
            userId: document.getElementById('editUser').value || null,
            startAt: new Date(document.getElementById('editStartAt').value).toISOString(),
            endAt: new Date(document.getElementById('editEndAt').value).toISOString(),
            commentaire: document.getElementById('editCommentaire').value
        };

        // Add to pending store
        this.addPendingChange(eventId, 'update', formData);

        // Update optimistic
        this.updateEventOptimistic(eventId, formData);

        // Close modal
        document.getElementById('editAffectationModal').remove();

        this.showInfo('Modifications enregistrées localement');
    }

    /**
     * 🆕 Delete affectation
     */
    async deleteAffectation(eventId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette garde ?')) {
            return;
        }

        // Add to pending store
        this.addPendingChange(eventId, 'delete', {});

        // Remove from calendar (optimistic)
        const event = this.calendar.getEventById(eventId);
        if (event) event.remove();

        // Close modal
        const modal = document.getElementById('editAffectationModal');
        if (modal) modal.remove();

        this.showInfo('Garde supprimée localement');
    }

    /**
     * Format date for datetime-local input
     */
    formatDateForInput(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');

        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }

    /**
     * 🆕 PENDING CHANGES STORE MANAGEMENT
     */

    /**
     * Add a modification to the pending store
     */
    addPendingChange(affectationId, type, data) {
        this.pendingChanges.set(affectationId, { type, data, timestamp: Date.now() });
        this.hasUnsavedChanges = true;
        this.updateSaveIndicator();
    }

    /**
     * Update event optimistically (without refetch API)
     */
    updateEventOptimistic(affectationId, updates) {
        const event = this.calendar.getEventById(affectationId);
        if (!event) return;

        // If userId changed, update user info
        if (updates.userId) {
            // Find user from data passed to template
            const userCard = document.querySelector(`[data-user-id="${updates.userId}"]`);
            if (userCard) {
                const userName = userCard.querySelector('.font-medium')?.textContent?.trim() || 'Utilisateur';
                const userColor = userCard.dataset.userColor || '#3B82F6';

                // Update event extended props
                event.setExtendedProp('user', {
                    id: updates.userId,
                    fullName: userName,
                    color: userColor
                });

                // Update title
                const villaName = event.extendedProps.villa?.nom || 'Villa';
                event.setProp('title', `${villaName} - ${userName}`);

                // Update background color (educator color)
                event.setProp('backgroundColor', userColor);

                // Keep border color as villa color (not educator color)
                const villaColor = event.extendedProps.villa?.color || '#10B981';
                const isRenfort = event.extendedProps.type === 'renfort' || event.extendedProps.type === 'TYPE_RENFORT';
                const borderColor = isRenfort ? '#6B7280' : villaColor;
                event.setProp('borderColor', borderColor);
            }
        }

        // If dates changed, recalculate working days and update calendar display
        if (updates.startAt || updates.endAt) {
            // Update real times
            const newStartAt = updates.startAt || event.extendedProps.realStart;
            const newEndAt = updates.endAt || event.extendedProps.realEnd;

            event.setExtendedProp('realStart', newStartAt);
            event.setExtendedProp('realEnd', newEndAt);

            // Recalculate working days with new formula
            const workingDays = this.calculateWorkingDays(newStartAt, newEndAt);
            event.setExtendedProp('workingDays', workingDays);

            // Update calendar display based on current view
            const currentView = this.calendar.view.type;
            const isMonthView = currentView === 'dayGridMonth';

            if (isMonthView) {
                // Monthly view: display as all-day spanning working days
                const startDate = new Date(newStartAt);
                const displayEnd = new Date(startDate);
                displayEnd.setDate(displayEnd.getDate() + workingDays);

                event.setStart(startDate.toISOString().split('T')[0]);
                event.setEnd(displayEnd.toISOString().split('T')[0]);
                event.setAllDay(true);
            } else {
                // Weekly/daily view: display with actual times
                event.setStart(newStartAt);
                event.setEnd(newEndAt);
                event.setAllDay(false);
            }
        }
    }

    /**
     * Update visual indicator of unsaved changes
     */
    updateSaveIndicator() {
        const count = this.pendingChanges.size;
        const indicator = document.getElementById('unsaved-indicator');

        if (indicator) {
            if (count > 0) {
                indicator.classList.remove('hidden');
                indicator.querySelector('span').textContent =
                    `${count} modification${count > 1 ? 's' : ''} non sauvegardée${count > 1 ? 's' : ''}`;
            } else {
                indicator.classList.add('hidden');
            }
        }
    }

    /**
     * Save all pending changes in batch
     */
    async savePendingChanges() {
        if (this.pendingChanges.size === 0) return;

        const changes = Array.from(this.pendingChanges.entries()).map(([id, change]) => ({
            affectationId: id,
            ...change
        }));

        try {
            this.showInfo('Sauvegarde en cours...');

            const response = await this.fetchAPI('/api/planning-assignment/batch-update', {
                method: 'POST',
                body: JSON.stringify({ changes })
            });

            // Clear pending changes
            this.pendingChanges.clear();
            this.hasUnsavedChanges = false;
            this.updateSaveIndicator();

            // Refresh calendar from server
            this.calendar.refetchEvents();

            this.showSuccess(`${changes.length} modification${changes.length > 1 ? 's' : ''} sauvegardée${changes.length > 1 ? 's' : ''}`);

            if (response.warnings && response.warnings.length > 0) {
                this.showWarningsToast(response.warnings);
            }

        } catch (error) {
            console.error('Failed to save pending changes:', error);
            this.showError('Échec de la sauvegarde');
        }
    }

    /**
     * Manual save (triggered by "Sauvegarder" button)
     */
    async manualSave() {
        await this.savePendingChanges();
    }

    /**
     * Helper: fetch with JSON and CSRF
     */
    async fetchAPI(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    /**
     * Show warnings toast (using Flowbite or custom)
     */
    showWarningsToast(warnings) {
        warnings.forEach(warning => {
            const severity = warning.severity || 'warning';
            const color = severity === 'error' ? 'red' : severity === 'warning' ? 'yellow' : 'blue';

            // Simple toast implementation (can be enhanced with Flowbite)
            this.showToast(warning.message, color);
        });
    }

    /**
     * Simple toast notification
     */
    showToast(message, color = 'blue') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 bg-${color}-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    showSuccess(message) {
        this.showToast(message, 'green');
    }

    showError(message) {
        this.showToast(message, 'red');
    }

    showInfo(message) {
        this.showToast(message, 'blue');
    }

    /**
     * Show absence details modal
     */
    showAbsenceDetails(extendedProps) {
        const { user, absenceTypeLabel, absenceData } = extendedProps;

        const startDate = new Date(absenceData.startAt);
        const endDate = new Date(absenceData.endAt);

        const formatDate = (date) => {
            return date.toLocaleDateString('fr-FR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        const formatTime = (date) => {
            return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        };

        // Calculate duration in days
        const durationMs = endDate - startDate;
        const durationDays = Math.ceil(durationMs / (1000 * 60 * 60 * 24));

        const modal = document.getElementById('details-modal');
        const title = document.getElementById('details-modal-title');
        const content = document.getElementById('details-modal-content');

        title.textContent = '🚫 Détails de l\'absence';

        content.innerHTML = `
            <div class="space-y-4">
                <div class="flex items-start p-4 bg-gray-50 rounded-lg">
                    <div class="flex-shrink-0 mr-4">
                        <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-xl font-bold text-gray-700">
                            ${user.fullName.charAt(0)}
                        </div>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-semibold text-lg text-gray-900">${user.fullName}</h4>
                        <p class="text-sm text-gray-600">Éducateur</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <p class="text-xs text-blue-600 font-semibold mb-1">TYPE D'ABSENCE</p>
                        <p class="text-sm font-medium text-gray-900">${absenceTypeLabel}</p>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-lg">
                        <p class="text-xs text-purple-600 font-semibold mb-1">DURÉE</p>
                        <p class="text-sm font-medium text-gray-900">${durationDays} jour${durationDays > 1 ? 's' : ''}</p>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h5 class="font-semibold text-gray-900 mb-3">📅 Période</h5>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 w-20">Début :</span>
                            <span class="text-sm font-medium text-gray-900">${formatDate(startDate)} à ${formatTime(startDate)}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 w-20">Fin :</span>
                            <span class="text-sm font-medium text-gray-900">${formatDate(endDate)} à ${formatTime(endDate)}</span>
                        </div>
                    </div>
                </div>

                <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                    <p class="text-xs text-green-700 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold">Absence approuvée</span>
                    </p>
                </div>
            </div>
        `;

        modal.classList.remove('hidden');
    }

    /**
     * Show RDV details modal
     */
    showRdvDetails(extendedProps) {
        const { rdvData, participants } = extendedProps;

        const startDate = new Date(rdvData.startAt);
        const endDate = new Date(rdvData.endAt);

        const formatDate = (date) => {
            return date.toLocaleDateString('fr-FR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        const formatTime = (date) => {
            return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        };

        // Calculate duration
        const durationMs = endDate - startDate;
        const durationMinutes = Math.round(durationMs / (1000 * 60));
        const hours = Math.floor(durationMinutes / 60);
        const minutes = durationMinutes % 60;

        const durationText = hours > 0
            ? `${hours}h${minutes > 0 ? minutes.toString().padStart(2, '0') : ''}`
            : `${minutes} min`;

        const modal = document.getElementById('details-modal');
        const title = document.getElementById('details-modal-title');
        const content = document.getElementById('details-modal-content');

        title.textContent = '📅 Détails du rendez-vous';

        content.innerHTML = `
            <div class="space-y-4">
                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <h4 class="font-semibold text-lg text-gray-900 mb-1">${rdvData.title}</h4>
                    <p class="text-sm text-gray-600">Rendez-vous avec impact sur le planning</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <p class="text-xs text-blue-600 font-semibold mb-1">PARTICIPANTS</p>
                        <p class="text-sm font-medium text-gray-900">${participants.length} personne${participants.length > 1 ? 's' : ''}</p>
                    </div>
                    <div class="p-3 bg-purple-50 rounded-lg">
                        <p class="text-xs text-purple-600 font-semibold mb-1">DURÉE</p>
                        <p class="text-sm font-medium text-gray-900">${durationText}</p>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h5 class="font-semibold text-gray-900 mb-3">📅 Horaires</h5>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 w-20">Date :</span>
                            <span class="text-sm font-medium text-gray-900">${formatDate(startDate)}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 w-20">Début :</span>
                            <span class="text-sm font-medium text-gray-900">${formatTime(startDate)}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-gray-600 w-20">Fin :</span>
                            <span class="text-sm font-medium text-gray-900">${formatTime(endDate)}</span>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <h5 class="font-semibold text-gray-900 mb-3">👥 Participants</h5>
                    <div class="space-y-2">
                        ${participants.map(p => `
                            <div class="flex items-center p-2 bg-gray-50 rounded-lg">
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center text-sm font-bold text-gray-700 mr-3">
                                    ${p.fullName.charAt(0)}
                                </div>
                                <span class="text-sm font-medium text-gray-900">${p.fullName}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <p class="text-xs text-yellow-700 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold">Ce rendez-vous impacte le planning des participants</span>
                    </p>
                </div>
            </div>
        `;

        modal.classList.remove('hidden');
    }

    /**
     * Close details modal
     */
    closeDetailsModal() {
        const modal = document.getElementById('details-modal');
        modal.classList.add('hidden');
    }
}
