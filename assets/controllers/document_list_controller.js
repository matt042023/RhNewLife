import { Controller } from '@hotwired/stimulus';

/**
 * Document List Controller
 *
 * Handles bulk selection, actions, and list management
 *
 * Usage:
 * <div data-controller="document-list">
 *   <input type="checkbox" data-document-list-target="selectAll" data-action="change->document-list#toggleAll">
 *   <input type="checkbox" class="document-checkbox" data-document-list-target="checkbox" data-action="change->document-list#updateSelection">
 *   <div data-document-list-target="bulkActions"></div>
 * </div>
 */
export default class extends Controller {
    static targets = [
        'selectAll',
        'checkbox',
        'bulkActions',
        'selectionCount',
        'validateButton',
        'archiveButton',
        'downloadButton',
        'deleteButton'
    ];

    static values = {
        validateUrl: String,
        archiveUrl: String,
        downloadUrl: String,
        deleteUrl: String
    };

    connect() {
        console.log('Document list controller connected');
        this.updateBulkActionsVisibility();
    }

    /**
     * Toggle all checkboxes
     */
    toggleAll(event) {
        const checked = event.target.checked;

        this.checkboxTargets.forEach(checkbox => {
            checkbox.checked = checked;
        });

        this.updateBulkActionsVisibility();
    }

    /**
     * Update selection when individual checkbox changes
     */
    updateSelection() {
        // Update select-all checkbox state
        if (this.hasSelectAllTarget) {
            const allChecked = this.checkboxTargets.every(cb => cb.checked);
            const someChecked = this.checkboxTargets.some(cb => cb.checked);

            this.selectAllTarget.checked = allChecked;
            this.selectAllTarget.indeterminate = someChecked && !allChecked;
        }

        this.updateBulkActionsVisibility();
    }

    /**
     * Update bulk actions panel visibility and count
     */
    updateBulkActionsVisibility() {
        const selectedCount = this.getSelectedCount();

        // Update selection count display
        if (this.hasSelectionCountTarget) {
            this.selectionCountTarget.textContent = `${selectedCount} document(s) sélectionné(s)`;
        }

        // Show/hide bulk actions panel
        if (this.hasBulkActionsTarget) {
            if (selectedCount > 0) {
                this.bulkActionsTarget.classList.remove('hidden');
            } else {
                this.bulkActionsTarget.classList.add('hidden');
            }
        }

        // Enable/disable bulk action buttons
        const hasSelection = selectedCount > 0;

        [
            this.validateButtonTarget,
            this.archiveButtonTarget,
            this.downloadButtonTarget,
            this.deleteButtonTarget
        ].forEach(target => {
            if (target) {
                target.disabled = !hasSelection;

                if (hasSelection) {
                    target.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    target.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
        });

        // Dispatch event
        this.dispatch('selectionChanged', {
            detail: {
                count: selectedCount,
                ids: this.getSelectedIds()
            }
        });
    }

    /**
     * Get count of selected checkboxes
     */
    getSelectedCount() {
        return this.checkboxTargets.filter(cb => cb.checked).length;
    }

    /**
     * Get IDs of selected documents
     */
    getSelectedIds() {
        return this.checkboxTargets
            .filter(cb => cb.checked)
            .map(cb => cb.value);
    }

    /**
     * Validate selected documents
     */
    async validateSelection(event) {
        event.preventDefault();

        const ids = this.getSelectedIds();

        if (ids.length === 0) {
            alert('Aucun document sélectionné');
            return;
        }

        if (!confirm(`Valider ${ids.length} document(s) sélectionné(s) ?`)) {
            return;
        }

        await this.performBulkAction('validate', ids);
    }

    /**
     * Archive selected documents
     */
    async archiveSelection(event) {
        event.preventDefault();

        const ids = this.getSelectedIds();

        if (ids.length === 0) {
            alert('Aucun document sélectionné');
            return;
        }

        if (!confirm(`Archiver ${ids.length} document(s) sélectionné(s) ?`)) {
            return;
        }

        await this.performBulkAction('archive', ids);
    }

    /**
     * Download selected documents as ZIP
     */
    async downloadSelection(event) {
        event.preventDefault();

        const ids = this.getSelectedIds();

        if (ids.length === 0) {
            alert('Aucun document sélectionné');
            return;
        }

        // Create download link with IDs as query params
        const url = new URL(this.downloadUrlValue, window.location.origin);
        ids.forEach(id => url.searchParams.append('ids[]', id));

        // Trigger download
        window.location.href = url.toString();

        // Dispatch event
        this.dispatch('bulkDownloadStarted', { detail: { ids } });
    }

    /**
     * Delete selected documents
     */
    async deleteSelection(event) {
        event.preventDefault();

        const ids = this.getSelectedIds();

        if (ids.length === 0) {
            alert('Aucun document sélectionné');
            return;
        }

        if (!confirm(`ATTENTION : Supprimer définitivement ${ids.length} document(s) ? Cette action est irréversible.`)) {
            return;
        }

        await this.performBulkAction('delete', ids);
    }

    /**
     * Perform bulk action via AJAX
     */
    async performBulkAction(action, ids) {
        const urlMap = {
            validate: this.validateUrlValue,
            archive: this.archiveUrlValue,
            delete: this.deleteUrlValue
        };

        const url = urlMap[action];

        if (!url) {
            console.error(`No URL configured for action: ${action}`);
            return;
        }

        // Show loading state
        this.setLoadingState(true);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ ids })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Success feedback
            this.showFeedback('success', data.message || `Action "${action}" effectuée avec succès`);

            // Refresh page or update UI
            this.dispatch('bulkActionCompleted', {
                detail: { action, ids, response: data }
            });

            // Reset selection
            this.clearSelection();

            // Reload page after short delay (allow user to see feedback)
            setTimeout(() => {
                window.location.reload();
            }, 1500);

        } catch (error) {
            console.error('Bulk action error:', error);
            this.showFeedback('error', `Erreur lors de l'action "${action}": ${error.message}`);

            this.dispatch('bulkActionError', {
                detail: { action, ids, error: error.message }
            });
        } finally {
            this.setLoadingState(false);
        }
    }

    /**
     * Clear all selections
     */
    clearSelection() {
        if (this.hasSelectAllTarget) {
            this.selectAllTarget.checked = false;
            this.selectAllTarget.indeterminate = false;
        }

        this.checkboxTargets.forEach(cb => {
            cb.checked = false;
        });

        this.updateBulkActionsVisibility();
    }

    /**
     * Set loading state
     */
    setLoadingState(loading) {
        const buttons = [
            this.validateButtonTarget,
            this.archiveButtonTarget,
            this.downloadButtonTarget,
            this.deleteButtonTarget
        ].filter(btn => btn);

        buttons.forEach(button => {
            button.disabled = loading;

            if (loading) {
                button.classList.add('opacity-50', 'cursor-wait');
                // Store original text
                button.dataset.originalText = button.textContent;
                button.textContent = 'Chargement...';
            } else {
                button.classList.remove('opacity-50', 'cursor-wait');
                // Restore original text
                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                }
            }
        });
    }

    /**
     * Show feedback message (toast or alert)
     */
    showFeedback(type, message) {
        // Try to use a toast notification system if available
        if (window.showToast && typeof window.showToast === 'function') {
            window.showToast(type, message);
            return;
        }

        // Fallback to alert
        if (type === 'error') {
            alert(`❌ ${message}`);
        } else {
            alert(`✅ ${message}`);
        }
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        console.log('Document list controller disconnected');
    }
}
