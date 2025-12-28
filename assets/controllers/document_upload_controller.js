import { Controller } from '@hotwired/stimulus';

/**
 * Document Upload Controller
 *
 * Handles file upload with drag-and-drop, validation, and preview
 *
 * Usage:
 * <div data-controller="document-upload"
 *      data-document-upload-max-size-value="10485760"
 *      data-document-upload-allowed-types-value='["application/pdf", "image/jpeg"]'>
 *   <input type="file" data-document-upload-target="fileInput">
 *   <div data-document-upload-target="dropZone"></div>
 * </div>
 */
export default class extends Controller {
    static targets = [
        'fileInput',
        'dropZone',
        'dropZoneContent',
        'filePreview',
        'fileName',
        'fileSize',
        'submitButton',
        'form',
        'errorMessage'
    ];

    static values = {
        maxSize: { type: Number, default: 10485760 }, // 10 MB in bytes
        allowedTypes: {
            type: Array,
            default: [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ]
        },
        allowedExtensions: {
            type: Array,
            default: ['.pdf', '.jpg', '.jpeg', '.png', '.doc', '.docx']
        },
        uploadUrl: String,
        multiple: { type: Boolean, default: false }
    };

    connect() {
        console.log('Document upload controller connected');
        this.setupDragAndDrop();
        this.setupFileInput();
    }

    /**
     * Setup drag-and-drop functionality
     */
    setupDragAndDrop() {
        if (!this.hasDropZoneTarget) {
            return;
        }

        // Prevent default drag behaviors on document
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropZoneTarget.addEventListener(eventName, this.preventDefaults.bind(this), false);
            document.body.addEventListener(eventName, this.preventDefaults.bind(this), false);
        });

        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropZoneTarget.addEventListener(eventName, this.highlight.bind(this), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropZoneTarget.addEventListener(eventName, this.unhighlight.bind(this), false);
        });

        // Handle dropped files
        this.dropZoneTarget.addEventListener('drop', this.handleDrop.bind(this), false);
    }

    /**
     * Setup file input change handler
     */
    setupFileInput() {
        if (!this.hasFileInputTarget) {
            return;
        }

        this.fileInputTarget.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFiles(e.target.files);
            }
        });
    }

    /**
     * Prevent default browser behavior
     */
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    /**
     * Highlight drop zone
     */
    highlight(e) {
        this.dropZoneTarget.classList.add('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
    }

    /**
     * Remove highlight from drop zone
     */
    unhighlight(e) {
        this.dropZoneTarget.classList.remove('border-blue-500', 'bg-blue-50', 'dark:bg-blue-900/20');
    }

    /**
     * Handle dropped files
     */
    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length > 0) {
            // Set files to input element
            this.fileInputTarget.files = files;
            this.handleFiles(files);
        }
    }

    /**
     * Handle files (from drop or input)
     */
    handleFiles(files) {
        this.clearError();

        if (!this.multipleValue && files.length > 1) {
            this.showError('Un seul fichier autorisé');
            return;
        }

        const validFiles = [];
        const errors = [];

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const validation = this.validateFile(file);

            if (validation.valid) {
                validFiles.push(file);
            } else {
                errors.push(`${file.name}: ${validation.error}`);
            }
        }

        if (errors.length > 0) {
            this.showError(errors.join('<br>'));
            this.removeFile();
            return;
        }

        if (validFiles.length > 0) {
            this.displayPreview(validFiles);
        }
    }

    /**
     * Validate a single file
     */
    validateFile(file) {
        // Check file size
        if (file.size > this.maxSizeValue) {
            const maxSizeMB = (this.maxSizeValue / 1024 / 1024).toFixed(0);
            return {
                valid: false,
                error: `Fichier trop volumineux. Taille maximale : ${maxSizeMB} MB`
            };
        }

        // Check file type (MIME)
        if (!this.allowedTypesValue.includes(file.type)) {
            return {
                valid: false,
                error: 'Type de fichier non autorisé. Formats acceptés : ' + this.getAllowedExtensionsDisplay()
            };
        }

        // Additional check for file extension (some browsers don't set correct MIME type)
        const extension = '.' + file.name.split('.').pop().toLowerCase();
        if (!this.allowedExtensionsValue.includes(extension)) {
            return {
                valid: false,
                error: 'Extension de fichier non autorisée. Extensions acceptées : ' + this.getAllowedExtensionsDisplay()
            };
        }

        return { valid: true };
    }

    /**
     * Get display string for allowed extensions
     */
    getAllowedExtensionsDisplay() {
        return this.allowedExtensionsValue.map(ext => ext.toUpperCase()).join(', ');
    }

    /**
     * Display file preview
     */
    displayPreview(files) {
        if (!this.hasDropZoneContentTarget || !this.hasFilePreviewTarget) {
            return;
        }

        // Hide drop zone content, show preview
        this.dropZoneContentTarget.classList.add('hidden');
        this.filePreviewTarget.classList.remove('hidden');

        if (files.length === 1) {
            // Single file preview
            const file = files[0];

            if (this.hasFileNameTarget) {
                this.fileNameTarget.textContent = file.name;
            }

            if (this.hasFileSizeTarget) {
                this.fileSizeTarget.textContent = this.formatFileSize(file.size);
            }
        } else {
            // Multiple files preview
            if (this.hasFileNameTarget) {
                this.fileNameTarget.textContent = `${files.length} fichiers sélectionnés`;
            }

            if (this.hasFileSizeTarget) {
                const totalSize = Array.from(files).reduce((sum, file) => sum + file.size, 0);
                this.fileSizeTarget.textContent = this.formatFileSize(totalSize);
            }
        }

        // Dispatch event
        this.dispatch('fileSelected', {
            detail: { files: Array.from(files) }
        });
    }

    /**
     * Remove selected file
     */
    removeFile() {
        if (this.hasFileInputTarget) {
            this.fileInputTarget.value = '';
        }

        if (this.hasDropZoneContentTarget && this.hasFilePreviewTarget) {
            this.dropZoneContentTarget.classList.remove('hidden');
            this.filePreviewTarget.classList.add('hidden');
        }

        this.clearError();

        // Dispatch event
        this.dispatch('fileRemoved');
    }

    /**
     * Format file size for display
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Show error message
     */
    showError(message) {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.innerHTML = message;
            this.errorMessageTarget.classList.remove('hidden');
        } else {
            alert(message);
        }

        // Dispatch error event
        this.dispatch('uploadError', { detail: { message } });
    }

    /**
     * Clear error message
     */
    clearError() {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.innerHTML = '';
            this.errorMessageTarget.classList.add('hidden');
        }
    }

    /**
     * Show notification toast
     */
    showNotification(type, message) {
        // Try to use global notification function if available
        if (window.showNotification) {
            window.showNotification(type, message);
            return;
        }

        // Fallback to simple toast notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-600' : 'bg-red-600'
        }`;
        notification.textContent = message;
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';

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
     * Handle form submission
     */
    submit(event) {
        if (!this.hasFormTarget) {
            return;
        }

        // Validate file is selected
        if (!this.hasFileInputTarget || this.fileInputTarget.files.length === 0) {
            event.preventDefault();
            this.showError('Veuillez sélectionner un fichier');
            return;
        }

        // Show loading state
        this.setLoadingState(true);

        // Dispatch event
        this.dispatch('uploadStarted');

        // Let the form submit naturally or handle with AJAX if uploadUrl is set
        if (this.hasUploadUrlValue) {
            event.preventDefault();
            this.uploadWithAjax();
        }
    }

    /**
     * Upload file via AJAX with progress
     */
    async uploadWithAjax() {
        if (!this.hasFormTarget || !this.hasFileInputTarget) {
            return;
        }

        const formData = new FormData(this.formTarget);
        const xhr = new XMLHttpRequest();

        // Track upload progress
        xhr.upload.addEventListener('progress', (e) => {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                this.dispatch('uploadProgress', { detail: { percent: percentComplete } });
            }
        });

        // Handle completion
        xhr.addEventListener('load', () => {
            this.setLoadingState(false);

            if (xhr.status >= 200 && xhr.status < 300) {
                const response = JSON.parse(xhr.responseText);
                this.dispatch('uploadSuccess', { detail: { response } });

                // Close modal
                const uploadModal = document.getElementById('uploadModal');
                if (uploadModal) {
                    uploadModal.classList.add('hidden');
                    document.body.style.overflow = '';
                }

                // Show success notification
                const message = response.message || 'Document téléversé avec succès';
                this.showNotification('success', message);

                // Reload page to show new document
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Try to parse error response as JSON
                let errorMessage = 'Erreur lors de l\'upload';
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    errorMessage = errorResponse.message || errorMessage;
                } catch (e) {
                    errorMessage = xhr.responseText || errorMessage;
                }

                this.showError(errorMessage);
                this.dispatch('uploadError', { detail: { error: errorMessage, message: errorMessage } });
            }
        });

        // Handle errors
        xhr.addEventListener('error', () => {
            this.setLoadingState(false);
            this.showError('Erreur réseau lors de l\'upload');
            this.dispatch('uploadError', { detail: { error: 'Network error' } });
        });

        // Send request
        xhr.open('POST', this.uploadUrlValue);
        xhr.send(formData);
    }

    /**
     * Set loading state on submit button
     */
    setLoadingState(loading) {
        if (!this.hasSubmitButtonTarget) {
            return;
        }

        if (loading) {
            this.submitButtonTarget.disabled = true;
            this.originalButtonHTML = this.submitButtonTarget.innerHTML;

            this.submitButtonTarget.innerHTML = `
                <svg class="animate-spin w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Téléversement en cours...
            `;
        } else {
            this.submitButtonTarget.disabled = false;

            if (this.originalButtonHTML) {
                this.submitButtonTarget.innerHTML = this.originalButtonHTML;
            }
        }
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        // Cleanup event listeners if needed
        console.log('Document upload controller disconnected');
    }
}
