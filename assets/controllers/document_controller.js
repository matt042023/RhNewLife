import { Controller } from '@hotwired/stimulus';

/**
 * Unified Document Controller
 *
 * Gestion complète des documents : modales, actions CRUD, navigation
 * Remplace document_actions_controller.js et document_modal_controller.js
 *
 * Usage:
 * <div data-controller="document">
 *   <button data-action="click->document#openModal" data-document-modal-id-param="validateModal">Valider</button>
 *   <button data-action="click->document#viewDocument" data-document-id-param="123">Voir</button>
 * </div>
 */
export default class extends Controller {
    static values = {
        validateUrl: String,
        rejectUrl: String,
        archiveUrl: String,
        restoreUrl: String,
        deleteUrl: String,
        replaceUrl: String
    };

    connect() {
        console.log('Unified document controller connected');

        // Setup escape key handler for modals
        this.escapeHandler = this.handleEscape.bind(this);
        document.addEventListener('keydown', this.escapeHandler);
    }

    disconnect() {
        document.removeEventListener('keydown', this.escapeHandler);
    }

    /**
     * MODAL MANAGEMENT
     */

    /**
     * Open modal by ID
     */
    openModal(event) {
        const modalId = event.params?.modalId || event.currentTarget?.dataset.documentModalModalIdParam;

        if (!modalId) {
            console.error('No modal ID provided');
            return;
        }

        const modal = document.getElementById(modalId);
        if (!modal) {
            console.error(`Modal not found: ${modalId}`);
            return;
        }

        // Pre-fill form data if provided
        this.prefillModalForm(modal, event);

        // Show modal
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Dispatch event
        this.dispatch('modalOpened', { detail: { modalId } });
    }

    /**
     * Close modal
     */
    closeModal(event) {
        if (event) {
            event.preventDefault();
        }

        // Find modal to close
        const modal = event?.target?.closest('[id$="Modal"]') ||
                     document.querySelector('[id$="Modal"]:not(.hidden)');

        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';

            // Reset form if present
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }

            this.dispatch('modalClosed', { detail: { modalId: modal.id } });
        }
    }

    /**
     * Handle ESC key to close modals
     */
    handleEscape(event) {
        if (event.key === 'Escape') {
            const openModal = document.querySelector('[id$="Modal"][style*="display: flex"]');
            if (openModal) {
                this.closeModal({ target: openModal });
            }
        }
    }

    /**
     * Pre-fill form in modal with data from trigger button
     */
    prefillModalForm(modal, event) {
        const formType = event.currentTarget?.dataset.formType;
        const formLabel = event.currentTarget?.dataset.formLabel;

        if (formType) {
            const typeSelect = modal.querySelector('select[name="type"]');
            if (typeSelect) {
                typeSelect.value = formType;
            }
        }

        // Store label for display purposes
        if (formLabel) {
            modal.dataset.documentLabel = formLabel;
        }
    }

    /**
     * DOCUMENT ACTIONS
     */

    /**
     * View document - redirect to detail page
     */
    viewDocument(event) {
        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        // Redirect to document detail page
        window.location.href = `/admin/documents/${documentId}`;
    }

    /**
     * Download document
     */
    download(event) {
        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        const downloadUrl = event.currentTarget?.dataset.downloadUrl ||
            `/documents/${documentId}/download`;

        window.location.href = downloadUrl;

        this.dispatch('documentDownloaded', { detail: { documentId } });
    }

    /**
     * Replace document - open replace modal
     */
    replaceDocument(event) {
        const documentId = this.getDocumentId(event);
        const label = event.params?.label || event.currentTarget?.dataset.documentActionsLabelParam || 'ce document';

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        // Open replace modal
        const replaceModal = document.getElementById('replaceModal');
        if (replaceModal) {
            replaceModal.dataset.documentId = documentId;
            replaceModal.dataset.documentLabel = label;
            this.openModal({ params: { modalId: 'replaceModal' } });
        } else {
            console.error('Replace modal not found');
        }
    }

    /**
     * Delete document with confirmation
     */
    async delete(event) {
        event.preventDefault();

        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        // Open delete modal instead of inline confirm
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.dataset.documentId = documentId;
            this.openModal({ params: { modalId: 'deleteModal' } });
        } else {
            // Fallback to confirm dialog
            const confirmText = 'SUPPRIMER';
            const userInput = prompt(
                `ATTENTION : Cette action est IRRÉVERSIBLE.\n\n` +
                `Pour confirmer la suppression définitive, tapez "${confirmText}" :`
            );

            if (userInput !== confirmText) {
                alert('Suppression annulée');
                return;
            }

            await this.performAction('delete', documentId);
        }
    }

    /**
     * Perform CRUD action via AJAX
     */
    async performAction(action, documentId, data = {}) {
        const urlMap = {
            validate: this.validateUrlValue || `/admin/documents/${documentId}/validate`,
            reject: this.rejectUrlValue || `/admin/documents/${documentId}/reject`,
            archive: this.archiveUrlValue || `/admin/documents/${documentId}/archive`,
            restore: this.restoreUrlValue || `/admin/documents/${documentId}/restore`,
            delete: this.deleteUrlValue || `/admin/documents/${documentId}`,
            replace: this.replaceUrlValue || `/admin/documents/${documentId}/replace`
        };

        const url = urlMap[action];

        if (!url) {
            console.error(`No URL configured for action: ${action}`);
            return;
        }

        const method = action === 'delete' ? 'DELETE' : 'POST';

        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ ...data, document_id: documentId })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }

            const responseData = await response.json();

            // Success feedback
            this.showFeedback('success', responseData.message || `Action "${action}" effectuée avec succès`);

            // Dispatch success event
            this.dispatch('actionCompleted', {
                detail: { action, documentId, data, response: responseData }
            });

            // Redirect or reload
            if (responseData.redirect) {
                window.location.href = responseData.redirect;
            } else {
                setTimeout(() => window.location.reload(), 1500);
            }

        } catch (error) {
            console.error(`Error performing ${action}:`, error);
            this.showFeedback('error', `Erreur lors de l'action "${action}": ${error.message}`);

            this.dispatch('actionError', {
                detail: { action, documentId, error: error.message }
            });
        }
    }

    /**
     * Get document ID from event parameters or data attributes
     */
    getDocumentId(event) {
        // Try params first
        const paramId = event.params?.id ||
                       event.params?.documentId ||
                       event.currentTarget?.dataset.documentActionsIdParam;

        if (paramId) {
            return parseInt(paramId);
        }

        // Try data attributes
        const dataId = event.currentTarget?.dataset.documentId ||
                      event.currentTarget?.dataset.id;

        if (dataId) {
            return parseInt(dataId);
        }

        return null;
    }

    /**
     * Show feedback message (toast or alert)
     */
    showFeedback(type, message) {
        // Try custom toast system
        if (window.showToast && typeof window.showToast === 'function') {
            window.showToast(type, message);
            return;
        }

        // Try custom event
        const event = new CustomEvent('toast:show', {
            detail: { type, message }
        });
        document.dispatchEvent(event);

        // Fallback to simple alert after delay
        setTimeout(() => {
            if (type === 'error') {
                alert(`❌ ${message}`);
            } else {
                alert(`✅ ${message}`);
            }
        }, 100);
    }
}
