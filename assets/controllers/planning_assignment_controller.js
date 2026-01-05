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
            const response = await fetch(`/api/planning-assignment/${year}/${month}?t=${timestamp}`, {
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            console.log('API Response:', data);

            // Convert plannings to FullCalendar events
            const events = [];

            for (const planning of data.plannings) {
                // Add affectations as events
                for (const affectation of planning.affectations) {
                    const villaName = affectation.villa?.nom || 'Villa inconnue';
                    const userName = affectation.user?.fullName || 'À affecter';

                    // Calculate working days for display
                    const workingDays = this.calculateWorkingDays(affectation.startAt, affectation.endAt);

                    // For monthly view: use working days calculation
                    // Event end date should be start + working days (not actual end time)
                    const startDate = new Date(affectation.startAt);
                    const displayEnd = new Date(startDate);
                    displayEnd.setDate(displayEnd.getDate() + workingDays);

                    events.push({
                        id: affectation.id,
                        start: affectation.startAt,
                        end: affectation.endAt, // Keep real end for weekly view
                        displayEnd: displayEnd.toISOString().split('T')[0], // Display end for monthly
                        title: `${villaName} - ${userName}`,
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

            console.log(`Loaded ${events.length} events`);
            successCallback(events);

        } catch (error) {
            console.error('Failed to load events:', error);
            this.showError('Échec du chargement des événements');
            failureCallback(error);
        }
    }

    /**
     * Calculate working days (48h = 2 days, not 3)
     * Rule: < 7h = 0 day, else ceil(hours / 24)
     */
    calculateWorkingDays(startAt, endAt) {
        const start = new Date(startAt);
        const end = new Date(endAt);

        const hoursDiff = (end - start) / (1000 * 60 * 60);

        // If less than 7 hours, doesn't count
        if (hoursDiff < 7) {
            return 0;
        }

        // Otherwise, round up by 24h blocks
        return Math.ceil(hoursDiff / 24);
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

        // Render overlays for absences/RDV
        if (isAssigned && user) {
            this.renderEventOverlay(info);
        }
    }

    /**
     * Custom event content rendering
     */
    renderEventContent(arg) {
        const { user, villa, type, realStart, realEnd } = arg.event.extendedProps;
        const isAssigned = !!user;
        const isMonthView = arg.view.type === 'dayGridMonth';

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

        // Create custom HTML content
        const container = document.createElement('div');
        container.className = 'fc-event-main-custom p-1';
        container.style.cssText = 'height: 100%; display: flex; flex-direction: column; font-size: 0.75rem; line-height: 1.2;';

        if (isAssigned) {
            // GARDE AFFECTÉE: Nom éducateur + infos
            // Get text color for contrast with educator background color
            const textColor = this.getContrastColor(user.color || '#3B82F6');

            container.innerHTML = `
                <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: ${textColor};">
                    ${user.fullName}
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
                <div style="font-weight: 600; color: #6B7280;">
                    À affecter
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
     * Render overlay for absences/RDV (transparent stripes)
     */
    async renderEventOverlay(info) {
        const { user, affectation } = info.event.extendedProps;

        try {
            const startStr = encodeURIComponent(affectation.startAt);
            const endStr = encodeURIComponent(affectation.endAt);
            const response = await fetch(
                `/api/planning-assignment/availability?userId=${user.id}&startDate=${startStr}&endDate=${endStr}`
            );
            const data = await response.json();

            if (data.periods && data.periods.length > 0) {
                // Create overlay element
                const overlay = document.createElement('div');
                overlay.style.position = 'absolute';
                overlay.style.top = '0';
                overlay.style.left = '0';
                overlay.style.right = '0';
                overlay.style.bottom = '0';
                overlay.style.pointerEvents = 'none';

                // Combine all conflict types into gradient
                const patterns = data.periods.map(period => {
                    return this.getOverlayPattern(period.type);
                });

                overlay.style.background = patterns[0]; // Use first pattern (can be enhanced for multiple)
                overlay.style.opacity = '0.4';

                info.el.style.position = 'relative';
                info.el.appendChild(overlay);
            }
        } catch (error) {
            console.error('Failed to load availability:', error);
        }
    }

    /**
     * Get overlay pattern based on conflict type
     */
    getOverlayPattern(type) {
        const patterns = {
            'conges': 'repeating-linear-gradient(45deg, rgba(239,68,68,0.5), rgba(239,68,68,0.5) 10px, transparent 10px, transparent 20px)',
            'absence': 'repeating-linear-gradient(45deg, rgba(239,68,68,0.5), rgba(239,68,68,0.5) 10px, transparent 10px, transparent 20px)',
            'maladie': 'repeating-linear-gradient(45deg, rgba(251,146,60,0.5), rgba(251,146,60,0.5) 10px, transparent 10px, transparent 20px)',
            'rdv': 'repeating-linear-gradient(45deg, rgba(250,204,21,0.5), rgba(250,204,21,0.5) 10px, transparent 10px, transparent 20px)'
        };
        return patterns[type] || patterns['conges'];
    }

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
     * Validate planning
     */
    async validatePlanning() {
        // TODO: Get current planningId (for now, validate all plannings in view)
        this.showInfo('Validation du planning en cours...');

        // For complete implementation, need to select which planning to validate
        // This is a placeholder
    }

    /**
     * Handle event click (monthly view) - opens edit modal
     */
    handleEventClick(info) {
        const currentView = this.calendar.view.type;

        // Only open modal in monthly view
        if (currentView === 'dayGridMonth') {
            const { affectation, user, villa, realStart, realEnd, workingDays } = info.event.extendedProps;
            const eventId = info.event.id;

            // Render rich edit modal
            this.renderEditModal(eventId, {
                affectation,
                user,
                villa,
                startAt: new Date(realStart),
                endAt: new Date(realEnd),
                workingDays
            });
        }
        // In weekly view, the time is already visible and editable via resize/drag
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

        // Real-time duration calculation
        const startInput = document.getElementById('editStartAt');
        const endInput = document.getElementById('editEndAt');
        const updateDuration = () => {
            const start = new Date(startInput.value);
            const end = new Date(endInput.value);
            const hours = Math.round((end - start) / (1000 * 60 * 60));
            const days = Math.ceil(hours / 24);
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

        // If dates changed, they're already updated by FullCalendar drag/resize
        // Just ensure extendedProps are synced
        if (updates.startAt) {
            event.setExtendedProp('realStart', updates.startAt);
        }
        if (updates.endAt) {
            event.setExtendedProp('realEnd', updates.endAt);
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
}
