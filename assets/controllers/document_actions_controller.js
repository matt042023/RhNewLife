import { Controller } from '@hotwired/stimulus';

/**
 * Document Actions Controller
 *
 * Handles inline document actions (validate, reject, archive, delete, etc.)
 *
 * Usage:
 * <div data-controller="document-actions">
 *   <button data-action="click->document-actions#validate" data-document-actions-id-param="123">Validate</button>
 *   <button data-action="click->document-actions#reject" data-document-actions-id-param="123">Reject</button>
 * </div>
 */
export default class extends Controller {
    static values = {
        validateUrl: String,
        rejectUrl: String,
        archiveUrl: String,
        restoreUrl: String,
        deleteUrl: String,
        id: Number
    };

    connect() {
        console.log('Document actions controller connected');
    }

    /**
     * Validate document
     */
    async validate(event) {
        event.preventDefault();

        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        // Get optional comment
        const comment = event.params?.comment || '';

        if (!confirm('Valider ce document ?')) {
            return;
        }

        await this.performAction('validate', documentId, { comment });
    }

    /**
     * Reject document with reason
     */
    async reject(event) {
        event.preventDefault();

        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        // Prompt for rejection reason
        const reason = prompt('Motif du rejet :');

        if (!reason || reason.trim() === '') {
            alert('Le motif du rejet est obligatoire');
            return;
        }

        await this.performAction('reject', documentId, { reason: reason.trim() });
    }

    /**
     * Archive document
     */
    async archive(event) {
        event.preventDefault();

        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        // Get archive parameters
        const reason = prompt('Raison de l\'archivage (optionnel):') || '';
        const retentionYears = prompt('Durée de rétention (années):', '5');

        if (!retentionYears || isNaN(retentionYears)) {
            alert('Durée de rétention invalide');
            return;
        }

        if (!confirm(`Archiver ce document pour ${retentionYears} ans ?`)) {
            return;
        }

        await this.performAction('archive', documentId, {
            reason,
            retention_years: parseInt(retentionYears)
        });
    }

    /**
     * Restore archived document
     */
    async restore(event) {
        event.preventDefault();

        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

        if (!confirm('Restaurer ce document archivé ?')) {
            return;
        }

        await this.performAction('restore', documentId);
    }

    /**
     * Delete document permanently
     */
    async delete(event) {
        event.preventDefault();

        const documentId = this.getDocumentId(event);

        if (!documentId) {
            console.error('No document ID provided');
            return;
        }

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

        // Dispatch event
        this.dispatch('documentDownloaded', { detail: { documentId } });
    }

    /**
     * Replace document
     */
    replaceDocument(event) {
        const documentId = this.getDocumentId(event);
        const label = event.params?.label || 'ce document';

        // Open replace modal via document-modal controller
        const modalController = this.application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller*="document-modal"]'),
            'document-modal'
        );

        if (modalController) {
            modalController.openReplaceModal({
                params: { documentId, label }
            });
        } else {
            console.error('Document modal controller not found');
        }
    }

    /**
     * View document in modal
     */
    viewDocument(event) {
        const documentId = this.getDocumentId(event);

        // Open view modal via document-modal controller
        const modalController = this.application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller*="document-modal"]'),
            'document-modal'
        );

        if (modalController) {
            modalController.openViewModal({
                params: { documentId }
            });
        } else {
            console.error('Document modal controller not found');
        }
    }

    /**
     * Get document ID from event or value
     */
    getDocumentId(event) {
        // Try to get from params first
        const paramId = event.params?.id || event.params?.documentId;

        if (paramId) {
            return paramId;
        }

        // Try to get from data attribute
        const dataId = event.currentTarget?.dataset.documentId ||
            event.currentTarget?.dataset.id;

        if (dataId) {
            return parseInt(dataId);
        }

        // Try to get from value
        if (this.hasIdValue) {
            return this.idValue;
        }

        return null;
    }

    /**
     * Perform action via AJAX
     */
    async performAction(action, documentId, data = {}) {
        const urlMap = {
            validate: this.validateUrlValue || `/admin/documents/${documentId}/validate`,
            reject: this.rejectUrlValue || `/admin/documents/${documentId}/reject`,
            archive: this.archiveUrlValue || `/admin/documents/${documentId}/archive`,
            restore: this.restoreUrlValue || `/admin/documents/${documentId}/restore`,
            delete: this.deleteUrlValue || `/admin/documents/${documentId}`
        };

        const url = urlMap[action];

        if (!url) {
            console.error(`No URL configured for action: ${action}`);
            return;
        }

        // Determine HTTP method
        const method = action === 'delete' ? 'DELETE' : 'POST';

        // Show loading state
        const trigger = event?.currentTarget;
        if (trigger) {
            this.setButtonLoading(trigger, true);
        }

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

            // Update UI or reload
            if (responseData.redirect) {
                window.location.href = responseData.redirect;
            } else {
                // Reload after short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }

        } catch (error) {
            console.error(`Error performing ${action}:`, error);
            this.showFeedback('error', `Erreur lors de l'action "${action}": ${error.message}`);

            // Dispatch error event
            this.dispatch('actionError', {
                detail: { action, documentId, error: error.message }
            });
        } finally {
            if (trigger) {
                this.setButtonLoading(trigger, false);
            }
        }
    }

    /**
     * Set button loading state
     */
    setButtonLoading(button, loading) {
        if (loading) {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;

            button.innerHTML = `
                <svg class="animate-spin h-4 w-4 inline-block mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Chargement...
            `;
        } else {
            button.disabled = false;

            if (button.dataset.originalText) {
                button.innerHTML = button.dataset.originalText;
                delete button.dataset.originalText;
            }
        }
    }

    /**
     * Show feedback message
     */
    showFeedback(type, message) {
        // Try to use toast notification system if available
        if (window.showToast && typeof window.showToast === 'function') {
            window.showToast(type, message);
            return;
        }

        // Try to dispatch custom event for toast system
        const event = new CustomEvent('toast:show', {
            detail: { type, message }
        });
        document.dispatchEvent(event);

        // Fallback to alert after short delay (allow custom event handlers to process)
        setTimeout(() => {
            if (type === 'error') {
                alert(`❌ ${message}`);
            } else {
                alert(`✅ ${message}`);
            }
        }, 100);
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        console.log('Document actions controller disconnected');
    }
}
