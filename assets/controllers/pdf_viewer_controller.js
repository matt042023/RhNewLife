import { Controller } from '@hotwired/stimulus';
import * as pdfjsLib from 'pdfjs-dist';

/**
 * PDF Viewer Controller using PDF.js
 * Displays PDF documents with navigation, zoom, and download controls
 *
 * Usage:
 * <div data-controller="pdf-viewer"
 *      data-pdf-viewer-url-value="/path/to/document.pdf"
 *      data-pdf-viewer-worker-src-value="/build/pdfjs/pdf.worker.mjs">
 *   ...
 * </div>
 */
export default class extends Controller {
    static targets = [
        'canvas',           // Canvas element for PDF rendering
        'pageNum',          // Current page number display
        'pageCount',        // Total page count display
        'scaleDisplay',     // Zoom percentage display
        'prevButton',       // Previous page button
        'nextButton',       // Next page button
        'zoomInButton',     // Zoom in button
        'zoomOutButton',    // Zoom out button
        'errorMessage',     // Error message container
        'loading',          // Loading spinner
        'fallback'          // Fallback download section
    ];

    static values = {
        url: String,                        // URL of PDF to load
        page: { type: Number, default: 1 }, // Current page number
        scale: { type: Number, default: 1.5 }, // Current zoom scale
        workerSrc: String,                  // Path to PDF.js worker
        minScale: { type: Number, default: 0.5 },
        maxScale: { type: Number, default: 3.0 },
        scaleStep: { type: Number, default: 0.25 }
    };

    /**
     * Initialize the controller
     */
    connect() {
        console.log('PDF Viewer controller connected');

        // Configure PDF.js worker
        if (this.hasWorkerSrcValue && this.workerSrcValue) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = this.workerSrcValue;
        } else {
            // Fallback to default path
            pdfjsLib.GlobalWorkerOptions.workerSrc = '/build/pdfjs/pdf.worker.mjs';
        }

        // Initialize state
        this.pdfDoc = null;
        this.pageRendering = false;
        this.pageNumPending = null;
        this.resizeTimeout = null;

        // Load the PDF
        if (this.hasUrlValue && this.urlValue) {
            this.loadPdf();
        } else {
            this.showError('Aucun document PDF spécifié');
        }

        // Handle window resize with debounce
        this.boundHandleResize = this.handleResize.bind(this);
        window.addEventListener('resize', this.boundHandleResize);
    }

    /**
     * Load PDF document
     */
    async loadPdf() {
        this.showLoading();

        try {
            const loadingTask = pdfjsLib.getDocument({
                url: this.urlValue,
                cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.0.0/cmaps/',
                cMapPacked: true
            });

            this.pdfDoc = await loadingTask.promise;

            console.log(`PDF loaded: ${this.pdfDoc.numPages} pages`);

            // Update page count
            if (this.hasPageCountTarget) {
                this.pageCountTarget.textContent = this.pdfDoc.numPages;
            }

            // Render first page
            await this.renderPage(this.pageValue);

            this.hideLoading();
            this.hideFallback();
            this.updateControls();

        } catch (error) {
            console.error('Error loading PDF:', error);
            this.handleError('Impossible de charger le document PDF', error);
        }
    }

    /**
     * Render a specific page
     */
    async renderPage(num) {
        if (!this.pdfDoc) {
            console.warn('PDF document not loaded yet');
            return;
        }

        // Prevent multiple simultaneous renders
        if (this.pageRendering) {
            this.pageNumPending = num;
            return;
        }

        this.pageRendering = true;
        this.pageValue = num;

        try {
            const page = await this.pdfDoc.getPage(num);

            // Calculate scale to fit canvas container
            const viewport = page.getViewport({ scale: this.scaleValue });

            // Set canvas dimensions
            const canvas = this.canvasTarget;
            const context = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            // Render page
            const renderContext = {
                canvasContext: context,
                viewport: viewport
            };

            await page.render(renderContext).promise;

            this.pageRendering = false;

            // If there was a pending page render request, render it now
            if (this.pageNumPending !== null) {
                const pending = this.pageNumPending;
                this.pageNumPending = null;
                await this.renderPage(pending);
            }

            // Update display
            this.updateDisplay();

        } catch (error) {
            console.error('Error rendering page:', error);
            this.pageRendering = false;
            this.handleError('Erreur lors de l\'affichage de la page', error);
        }
    }

    /**
     * Go to previous page
     */
    prevPage(event) {
        event?.preventDefault();

        if (this.pageValue <= 1) {
            return;
        }

        this.renderPage(this.pageValue - 1);
    }

    /**
     * Go to next page
     */
    nextPage(event) {
        event?.preventDefault();

        if (!this.pdfDoc || this.pageValue >= this.pdfDoc.numPages) {
            return;
        }

        this.renderPage(this.pageValue + 1);
    }

    /**
     * Zoom in
     */
    zoomIn(event) {
        event?.preventDefault();

        if (this.scaleValue >= this.maxScaleValue) {
            return;
        }

        this.scaleValue = Math.min(
            this.scaleValue + this.scaleStepValue,
            this.maxScaleValue
        );

        this.renderPage(this.pageValue);
    }

    /**
     * Zoom out
     */
    zoomOut(event) {
        event?.preventDefault();

        if (this.scaleValue <= this.minScaleValue) {
            return;
        }

        this.scaleValue = Math.max(
            this.scaleValue - this.scaleStepValue,
            this.minScaleValue
        );

        this.renderPage(this.pageValue);
    }

    /**
     * Reset zoom to 100%
     */
    resetZoom(event) {
        event?.preventDefault();
        this.scaleValue = 1.0;
        this.renderPage(this.pageValue);
    }

    /**
     * Fit to width
     */
    fitToWidth(event) {
        event?.preventDefault();

        if (!this.pdfDoc || !this.hasCanvasTarget) {
            return;
        }

        // Calculate scale to fit container width
        const containerWidth = this.canvasTarget.parentElement.clientWidth - 40; // padding

        this.pdfDoc.getPage(this.pageValue).then(page => {
            const viewport = page.getViewport({ scale: 1.0 });
            const scale = containerWidth / viewport.width;
            this.scaleValue = Math.min(scale, this.maxScaleValue);
            this.renderPage(this.pageValue);
        });
    }

    /**
     * Download PDF
     */
    download(event) {
        event?.preventDefault();

        if (this.hasUrlValue) {
            window.location.href = this.urlValue;
        }
    }

    /**
     * Update control buttons state
     */
    updateControls() {
        if (!this.pdfDoc) {
            return;
        }

        // Previous button
        if (this.hasPrevButtonTarget) {
            this.prevButtonTarget.disabled = (this.pageValue <= 1);
            if (this.pageValue <= 1) {
                this.prevButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                this.prevButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // Next button
        if (this.hasNextButtonTarget) {
            this.nextButtonTarget.disabled = (this.pageValue >= this.pdfDoc.numPages);
            if (this.pageValue >= this.pdfDoc.numPages) {
                this.nextButtonTarget.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                this.nextButtonTarget.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }

        // Zoom buttons
        if (this.hasZoomOutButtonTarget) {
            this.zoomOutButtonTarget.disabled = (this.scaleValue <= this.minScaleValue);
        }

        if (this.hasZoomInButtonTarget) {
            this.zoomInButtonTarget.disabled = (this.scaleValue >= this.maxScaleValue);
        }
    }

    /**
     * Update display elements
     */
    updateDisplay() {
        // Update page number
        if (this.hasPageNumTarget) {
            this.pageNumTarget.textContent = this.pageValue;
        }

        // Update scale display
        if (this.hasScaleDisplayTarget) {
            this.scaleDisplayTarget.textContent = `${Math.round(this.scaleValue * 100)}%`;
        }

        this.updateControls();
    }

    /**
     * Show loading spinner
     */
    showLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.remove('hidden');
        }

        if (this.hasCanvasTarget) {
            this.canvasTarget.classList.add('hidden');
        }
    }

    /**
     * Hide loading spinner
     */
    hideLoading() {
        if (this.hasLoadingTarget) {
            this.loadingTarget.classList.add('hidden');
        }

        if (this.hasCanvasTarget) {
            this.canvasTarget.classList.remove('hidden');
        }
    }

    /**
     * Show fallback download section
     */
    showFallback() {
        if (this.hasFallbackTarget) {
            this.fallbackTarget.classList.remove('hidden');
        }

        if (this.hasCanvasTarget) {
            this.canvasTarget.classList.add('hidden');
        }
    }

    /**
     * Hide fallback section
     */
    hideFallback() {
        if (this.hasFallbackTarget) {
            this.fallbackTarget.classList.add('hidden');
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.textContent = message;
            this.errorMessageTarget.classList.remove('hidden');
        }
    }

    /**
     * Handle error with fallback
     */
    handleError(message, error) {
        console.error(message, error);

        this.hideLoading();
        this.showError(message);
        this.showFallback();

        // Disable all controls
        [
            this.prevButtonTarget,
            this.nextButtonTarget,
            this.zoomInButtonTarget,
            this.zoomOutButtonTarget
        ].forEach(button => {
            if (button) {
                button.disabled = true;
                button.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });

        // Dispatch error event
        this.dispatch('error', {
            detail: { message, error }
        });
    }

    /**
     * Handle window resize with debounce
     */
    handleResize() {
        clearTimeout(this.resizeTimeout);

        this.resizeTimeout = setTimeout(() => {
            if (this.pdfDoc && this.pageValue) {
                this.renderPage(this.pageValue);
            }
        }, 300);
    }

    /**
     * Cleanup on disconnect
     */
    disconnect() {
        // Remove event listeners
        window.removeEventListener('resize', this.boundHandleResize);

        // Clear timers
        if (this.resizeTimeout) {
            clearTimeout(this.resizeTimeout);
        }

        // Cleanup PDF.js resources
        if (this.pdfDoc) {
            this.pdfDoc.destroy();
            this.pdfDoc = null;
        }

        console.log('PDF Viewer controller disconnected');
    }
}
