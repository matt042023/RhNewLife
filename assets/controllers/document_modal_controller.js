import { Controller } from '@hotwired/stimulus';

/**
 * Document Modal Controller
 *
 * Manages modal dialogs for document operations (upload, replace, delete, view)
 *
 * Usage:
 * <div data-controller="document-modal">
 *   <button data-action="click->document-modal#open" data-document-modal-modal-id-param="uploadModal">Open</button>
 *   <div data-document-modal-target="modal" id="uploadModal" class="hidden">
 *     <button data-action="click->document-modal#close">Close</button>
 *   </div>
 * </div>
 */
export default class extends Controller {
    static targets = ['modal', 'backdrop', 'content', 'closeButton'];

    static values = {
        closeOnBackdrop: { type: Boolean, default: true },
        closeOnEscape: { type: Boolean, default: true },
        loadUrl: String
    };

    connect() {
        console.log('Document modal controller connected');

        // Setup escape key handler
        if (this.closeOnEscapeValue) {
            this.escapeHandler = this.handleEscape.bind(this);
            document.addEventListener('keydown', this.escapeHandler);
        }
    }

    /**
     * Open modal
     * Can be called with modalId parameter to open specific modal
     */
    open(event) {
        // Get modal ID from parameter or data attribute
        const modalId = event.params?.modalId || event.currentTarget?.dataset.modalId;

        let modal;

        if (modalId) {
            // Find modal by ID
            modal = document.getElementById(modalId);
        } else if (this.hasModalTarget) {
            // Use first modal target
            modal = this.modalTarget;
        }

        if (!modal) {
            console.error('No modal found to open');
            return;
        }

        // Store currently open modal
        this.currentModal = modal;

        // Load content via AJAX if URL specified
        const loadUrl = event.currentTarget?.dataset.loadUrl || this.loadUrlValue;

        if (loadUrl) {
            this.loadModalContent(modal, loadUrl);
        }

        // Pre-fill form data if specified
        this.prefillFormData(modal, event);

        // Show modal
        this.showModal(modal);

        // Dispatch event
        this.dispatch('modalOpened', { detail: { modal, modalId } });
    }

    /**
     * Close modal
     */
    close(event) {
        if (event) {
            event.preventDefault();
        }

        // Try to find modal from current context or button clicked
        let modalToClose = this.currentModal;

        // If no currentModal, try to find it from the event target
        if (!modalToClose && event?.target) {
            // Find closest modal element
            modalToClose = event.target.closest('[data-document-modal-target="modal"]');

            // Or try to find by id if we're in a specific modal
            if (!modalToClose) {
                const modalIds = ['uploadModal', 'replaceModal', 'deleteModal', 'viewModal'];
                for (const id of modalIds) {
                    const modal = document.getElementById(id);
                    if (modal && !modal.classList.contains('hidden')) {
                        modalToClose = modal;
                        break;
                    }
                }
            }
        }

        if (!modalToClose) {
            console.warn('No modal to close');
            return;
        }

        this.hideModal(modalToClose);

        // Dispatch event
        this.dispatch('modalClosed', { detail: { modal: modalToClose } });

        this.currentModal = null;
    }

    /**
     * Show modal with animation
     */
    showModal(modal) {
        // Remove hidden class
        modal.classList.remove('hidden');

        // Force reflow for animation
        modal.offsetHeight;

        // Add visible class for animation
        modal.classList.add('modal-visible');

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        // Focus first focusable element
        setTimeout(() => {
            const firstFocusable = modal.querySelector('input, textarea, select, button');
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }, 100);
    }

    /**
     * Hide modal with animation
     */
    hideModal(modal) {
        // Remove visible class
        modal.classList.remove('modal-visible');

        // Wait for animation to complete before hiding
        setTimeout(() => {
            modal.classList.add('hidden');

            // Clear any loaded content
            const contentContainer = modal.querySelector('[data-modal-content]');
            if (contentContainer && contentContainer.dataset.clearOnClose) {
                contentContainer.innerHTML = '';
            }

            // Restore body scroll
            document.body.style.overflow = '';
        }, 300);
    }

    /**
     * Close modal when clicking backdrop
     */
    closeOnBackdropClick(event) {
        if (!this.closeOnBackdropValue) {
            return;
        }

        // Only close if clicking the backdrop itself, not its children
        if (event.target === event.currentTarget) {
            // Store reference to the modal before closing
            if (!this.currentModal) {
                this.currentModal = event.currentTarget;
            }
            this.close(event);
        }
    }

    /**
     * Handle escape key press
     */
    handleEscape(event) {
        if (event.key === 'Escape') {
            // If no currentModal, try to find any open modal
            if (!this.currentModal) {
                const modalIds = ['viewModal', 'uploadModal', 'replaceModal', 'deleteModal'];
                for (const id of modalIds) {
                    const modal = document.getElementById(id);
                    if (modal && !modal.classList.contains('hidden')) {
                        this.currentModal = modal;
                        break;
                    }
                }
            }

            if (this.currentModal) {
                this.close(event);
            }
        }
    }

    /**
     * Load modal content via AJAX
     */
    async loadModalContent(modal, url) {
        const contentContainer = modal.querySelector('[data-modal-content]');

        if (!contentContainer) {
            console.warn('No content container found in modal');
            return;
        }

        // Show loading state
        contentContainer.innerHTML = `
            <div class="flex items-center justify-center p-8">
                <svg class="animate-spin h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-3 text-gray-600">Chargement...</span>
            </div>
        `;

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const html = await response.text();
            contentContainer.innerHTML = html;

            // Re-bind event listeners to dynamically loaded content
            this.bindDynamicEventListeners(contentContainer);

            // Dispatch event
            this.dispatch('contentLoaded', { detail: { modal, url } });

        } catch (error) {
            console.error('Error loading modal content:', error);

            contentContainer.innerHTML = `
                <div class="p-8 text-center">
                    <svg class="h-12 w-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-red-600">Erreur de chargement</p>
                    <button onclick="location.reload()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg">
                        Réessayer
                    </button>
                </div>
            `;

            this.dispatch('loadError', { detail: { modal, error: error.message } });
        }
    }

    /**
     * Pre-fill form data from trigger element
     */
    prefillFormData(modal, event) {
        const trigger = event.currentTarget;

        if (!trigger) {
            return;
        }

        // Get data attributes from trigger
        const data = {};

        Object.keys(trigger.dataset).forEach(key => {
            if (key.startsWith('form')) {
                const fieldName = key.replace('form', '').replace(/^./, str => str.toLowerCase());
                data[fieldName] = trigger.dataset[key];
            }
        });

        // Fill form fields
        Object.keys(data).forEach(fieldName => {
            const field = modal.querySelector(`[name="${fieldName}"]`);

            if (field) {
                field.value = data[fieldName];

                // If this is the type field, also update the label display
                if (fieldName === 'type') {
                    const labelField = modal.querySelector('#uploadTypeLabel');
                    if (labelField && data['label']) {
                        labelField.value = data['label'];
                    }
                }
            }
        });

        // Special handling for documented type and document ID
        if (trigger.dataset.documentType) {
            const typeField = modal.querySelector('[name="type"], #documentType');
            if (typeField) {
                typeField.value = trigger.dataset.documentType;
            }
        }

        if (trigger.dataset.documentId) {
            const idField = modal.querySelector('[name="document_id"], #documentId');
            if (idField) {
                idField.value = trigger.dataset.documentId;
            }
        }

        if (trigger.dataset.documentLabel) {
            const labelElement = modal.querySelector('[data-document-label]');
            if (labelElement) {
                labelElement.textContent = trigger.dataset.documentLabel;
            }

            // Also update uploadTypeLabel if it exists (for upload modal)
            const uploadTypeLabelField = modal.querySelector('#uploadTypeLabel');
            if (uploadTypeLabelField) {
                uploadTypeLabelField.value = trigger.dataset.documentLabel;
            }
        }
    }

    /**
     * Open upload modal with optional pre-filled type
     */
    openUploadModal(event) {
        const type = event.params?.type || '';
        const label = event.params?.label || '';

        const modal = document.getElementById('uploadModal');

        if (modal) {
            // Pre-fill type if specified
            if (type) {
                const typeField = modal.querySelector('#documentType, [name="type"]');
                if (typeField) {
                    typeField.value = type;
                }
            }

            this.currentModal = modal;
            this.showModal(modal);
        }
    }

    /**
     * Open replace modal
     */
    openReplaceModal(event) {
        const documentId = event.params?.documentId;
        const label = event.params?.label || '';

        const modal = document.getElementById('replaceModal');

        if (modal) {
            // Set document ID in hidden field
            const idField = modal.querySelector('[name="document_id"]');
            if (idField) {
                idField.value = documentId;
            }

            // Set form action dynamically
            const form = modal.querySelector('#replaceForm');
            if (form && documentId) {
                form.action = `/profile/documents/${documentId}/replace`;
            }

            // Set document label
            const labelElement = modal.querySelector('[data-document-label]');
            if (labelElement) {
                labelElement.textContent = label;
            }

            this.currentModal = modal;
            this.showModal(modal);
        }
    }

    /**
     * Open delete modal
     */
    openDeleteModal(event) {
        const documentId = event.params?.documentId;
        const label = event.params?.label || '';

        const modal = document.getElementById('deleteModal');

        if (modal) {
            // Set document ID in hidden field
            const idField = modal.querySelector('[name="document_id"]');
            if (idField) {
                idField.value = documentId;
            }

            // Set form action dynamically
            const form = modal.querySelector('#deleteForm');
            if (form && documentId) {
                form.action = `/profile/documents/${documentId}`;
            }

            // Set document label
            const labelElement = modal.querySelector('[data-document-label]');
            if (labelElement) {
                labelElement.textContent = label;
            }

            this.currentModal = modal;
            this.showModal(modal);
        }
    }

    /**
     * Open view modal
     */
    openViewModal(event) {
        const documentId = event.params?.documentId;
        const url = `/profile/documents/${documentId}/view`;

        const modal = document.getElementById('viewModal');

        if (modal) {
            this.currentModal = modal;
            this.loadModalContent(modal, url);
            this.showModal(modal);
        }
    }

    /**
     * Handle successful upload
     */
    handleUploadSuccess(event) {
        console.log('Upload success:', event.detail);

        // Close the modal
        this.close();

        // Show success message
        const message = event.detail.response?.message || 'Document téléversé avec succès';
        this.showNotification('success', message);

        // Reload the page to update the document list
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }

    /**
     * Handle upload error
     */
    handleUploadError(event) {
        console.log('Upload error:', event.detail);

        const message = event.detail.error || event.detail.message || 'Erreur lors du téléversement';
        this.showNotification('error', message);
    }

    /**
     * Show notification message
     */
    showNotification(type, message) {
        // Try to use existing notification system if available
        if (window.showNotification) {
            window.showNotification(type, message);
            return;
        }

        // Fallback to creating a simple toast notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg shadow-lg text-white transform transition-all duration-300 ${
            type === 'success' ? 'bg-green-600' : 'bg-red-600'
        }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
            notification.style.opacity = '1';
        }, 10);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Bind event listeners to dynamically loaded content
     */
    bindDynamicEventListeners(container) {
        // Find all elements with data-action that should close the modal
        const closeButtons = container.querySelectorAll('[data-action*="document-modal#close"]');

        closeButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.close(e);
            });
        });

        // Also handle any other dynamic actions if needed
        console.log(`Bound ${closeButtons.length} close button(s) in dynamic content`);
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        if (this.escapeHandler) {
            document.removeEventListener('keydown', this.escapeHandler);
        }

        // Restore body scroll if modal was open
        if (this.currentModal) {
            document.body.style.overflow = '';
        }

        console.log('Document modal controller disconnected');
    }
}
