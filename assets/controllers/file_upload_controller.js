import { Controller } from '@hotwired/stimulus';

/**
 * Simple File Upload Controller
 *
 * Lightweight file upload handler with drag-and-drop support
 * Designed for simple file inputs in forms (e.g., absence justifications)
 *
 * Usage:
 * <div data-controller="file-upload">
 *   <div data-file-upload-target="dropzone">
 *     <input type="file" data-action="change->file-upload#onFileSelect">
 *   </div>
 *   <span data-file-upload-target="fileName"></span>
 * </div>
 */
export default class extends Controller {
    static targets = [
        'dropzone',
        'fileName',
        'fileSize',
        'errorMessage'
    ];

    static values = {
        maxSize: { type: Number, default: 10485760 }, // 10 MB
        allowedExtensions: {
            type: Array,
            default: ['.pdf', '.jpg', '.jpeg', '.png']
        }
    };

    connect() {
        console.log('File upload controller connected');
        this.setupDragAndDrop();
    }

    /**
     * Setup drag-and-drop
     */
    setupDragAndDrop() {
        if (!this.hasDropzoneTarget) {
            return;
        }

        // Prevent default behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.dropzoneTarget.addEventListener(eventName, this.preventDefaults.bind(this), false);
        });

        // Highlight on drag over
        ['dragenter', 'dragover'].forEach(eventName => {
            this.dropzoneTarget.addEventListener(eventName, this.highlight.bind(this), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            this.dropzoneTarget.addEventListener(eventName, this.unhighlight.bind(this), false);
        });

        // Handle drop
        this.dropzoneTarget.addEventListener('drop', this.handleDrop.bind(this), false);
    }

    /**
     * Prevent default events
     */
    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    /**
     * Highlight dropzone
     */
    highlight() {
        this.dropzoneTarget.classList.add('border-blue-400', 'bg-blue-50');
    }

    /**
     * Remove highlight
     */
    unhighlight() {
        this.dropzoneTarget.classList.remove('border-blue-400', 'bg-blue-50');
    }

    /**
     * Handle file drop
     */
    handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files.length > 0) {
            const fileInput = this.element.querySelector('input[type="file"]');
            if (fileInput) {
                fileInput.files = files;
                this.displayFileName(files[0]);
            }
        }
    }

    /**
     * Handle file selection from input
     */
    onFileSelect(event) {
        const files = event.target.files;

        if (files.length > 0) {
            const file = files[0];

            // Validate file
            const validation = this.validateFile(file);

            if (!validation.valid) {
                this.showError(validation.error);
                event.target.value = ''; // Clear selection
                this.clearFileName();
                return;
            }

            this.clearError();
            this.displayFileName(file);
        }
    }

    /**
     * Validate file
     */
    validateFile(file) {
        // Check size
        if (file.size > this.maxSizeValue) {
            const maxSizeMB = (this.maxSizeValue / 1024 / 1024).toFixed(0);
            return {
                valid: false,
                error: `Fichier trop volumineux. Taille maximale : ${maxSizeMB} MB`
            };
        }

        // Check extension
        const extension = '.' + file.name.split('.').pop().toLowerCase();
        if (!this.allowedExtensionsValue.includes(extension)) {
            return {
                valid: false,
                error: `Extension non autorisée. Formats acceptés : ${this.allowedExtensionsValue.join(', ').toUpperCase()}`
            };
        }

        return { valid: true };
    }

    /**
     * Display file name
     */
    displayFileName(file) {
        if (this.hasFileNameTarget) {
            this.fileNameTarget.textContent = `Fichier sélectionné : ${file.name}`;
            this.fileNameTarget.classList.remove('hidden');
        }

        if (this.hasFileSizeTarget) {
            this.fileSizeTarget.textContent = this.formatFileSize(file.size);
        }
    }

    /**
     * Clear file name display
     */
    clearFileName() {
        if (this.hasFileNameTarget) {
            this.fileNameTarget.textContent = '';
            this.fileNameTarget.classList.add('hidden');
        }

        if (this.hasFileSizeTarget) {
            this.fileSizeTarget.textContent = '';
        }
    }

    /**
     * Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Show error
     */
    showError(message) {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.textContent = message;
            this.errorMessageTarget.classList.remove('hidden');
        } else {
            alert(message);
        }
    }

    /**
     * Clear error
     */
    clearError() {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.textContent = '';
            this.errorMessageTarget.classList.add('hidden');
        }
    }

    /**
     * Disconnect
     */
    disconnect() {
        console.log('File upload controller disconnected');
    }
}
