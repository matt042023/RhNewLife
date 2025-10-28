import { Controller } from '@hotwired/stimulus';

/**
 * Controller for tab navigation
 * Handles tab switching, URL hash updates, lazy loading, and keyboard navigation
 */
export default class extends Controller {
    static targets = ['tab', 'panel'];

    static values = {
        defaultTab: String,
        rememberTab: Boolean,
        lazyLoad: Boolean
    };

    connect() {
        console.log('Tabs controller connected');

        // Restore from URL hash or localStorage
        const activeTab = this.getActiveTabFromUrl() || this.getStoredTab() || this.defaultTabValue || this.getFirstTabId();

        if (activeTab) {
            this.showTab(activeTab);
        }

        // Add keyboard navigation
        this.element.addEventListener('keydown', this.handleKeyboard.bind(this));
    }

    /**
     * Handle tab click
     */
    selectTab(event) {
        event.preventDefault();
        const tabId = event.currentTarget.dataset.tab || event.currentTarget.dataset.tabsTabParam;

        this.showTab(tabId);
    }

    /**
     * Show specific tab by ID
     */
    showTab(tabId) {
        // Hide all panels
        this.panelTargets.forEach(panel => {
            panel.classList.add('hidden');
            panel.setAttribute('aria-hidden', 'true');
        });

        // Deactivate all tabs
        this.tabTargets.forEach(tab => {
            tab.classList.remove('active', 'border-b-2', 'border-blue-600', 'text-blue-600');
            tab.classList.add('text-gray-600', 'hover:text-gray-800');
            tab.setAttribute('aria-selected', 'false');
            tab.setAttribute('tabindex', '-1');
        });

        // Activate selected tab
        const selectedTab = this.tabTargets.find(tab =>
            (tab.dataset.tab || tab.dataset.tabsTabParam) === tabId
        );

        if (selectedTab) {
            selectedTab.classList.add('active', 'border-b-2', 'border-blue-600', 'text-blue-600');
            selectedTab.classList.remove('text-gray-600', 'hover:text-gray-800');
            selectedTab.setAttribute('aria-selected', 'true');
            selectedTab.setAttribute('tabindex', '0');
            selectedTab.focus();
        }

        // Show selected panel
        const selectedPanel = this.panelTargets.find(panel => panel.id === `tab-${tabId}`);

        if (selectedPanel) {
            selectedPanel.classList.remove('hidden');
            selectedPanel.setAttribute('aria-hidden', 'false');

            // Lazy load content if enabled
            if (this.lazyLoadValue && selectedPanel.dataset.lazyLoad === 'true' && !selectedPanel.dataset.loaded) {
                this.loadTabContent(selectedPanel);
            }

            // Trigger custom event for analytics or other listeners
            this.dispatch('tabChanged', { detail: { tabId } });
        }

        // Update URL hash
        this.updateUrlHash(tabId);

        // Store in localStorage if remember is enabled
        if (this.rememberTabValue) {
            this.storeTab(tabId);
        }
    }

    /**
     * Get active tab from URL hash
     */
    getActiveTabFromUrl() {
        const hash = window.location.hash.substring(1);
        return hash || null;
    }

    /**
     * Update URL hash without triggering page scroll
     */
    updateUrlHash(tabId) {
        if (history.pushState) {
            history.pushState(null, null, `#${tabId}`);
        } else {
            window.location.hash = tabId;
        }
    }

    /**
     * Get stored tab from localStorage
     */
    getStoredTab() {
        if (!this.rememberTabValue) return null;

        const storageKey = `tabs_${this.element.id || 'default'}`;
        return localStorage.getItem(storageKey);
    }

    /**
     * Store active tab in localStorage
     */
    storeTab(tabId) {
        if (!this.rememberTabValue) return;

        const storageKey = `tabs_${this.element.id || 'default'}`;
        localStorage.setItem(storageKey, tabId);
    }

    /**
     * Get first tab ID
     */
    getFirstTabId() {
        return this.tabTargets.length > 0 ? (this.tabTargets[0].dataset.tab || this.tabTargets[0].dataset.tabsTabParam) : null;
    }

    /**
     * Load tab content via AJAX (for lazy loading)
     */
    async loadTabContent(panel) {
        const url = panel.dataset.lazyUrl;

        if (!url) {
            panel.dataset.loaded = 'true';
            return;
        }

        // Show loading indicator
        const originalContent = panel.innerHTML;
        panel.innerHTML = `
            <div class="flex items-center justify-center py-12">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
                <span class="ml-3 text-gray-600">Chargement...</span>
            </div>
        `;

        try {
            const response = await fetch(url);

            if (!response.ok) {
                throw new Error('Failed to load tab content');
            }

            const html = await response.text();
            panel.innerHTML = html;
            panel.dataset.loaded = 'true';

            // Dispatch event for loaded content
            this.dispatch('tabLoaded', { detail: { panel, url } });

        } catch (error) {
            console.error('Error loading tab content:', error);
            panel.innerHTML = `
                <div class="alert alert-error">
                    <p>Erreur lors du chargement du contenu.</p>
                    <button onclick="this.closest('.alert').remove()">Fermer</button>
                </div>
                ${originalContent}
            `;
        }
    }

    /**
     * Handle keyboard navigation (arrow keys)
     */
    handleKeyboard(event) {
        const currentTab = document.activeElement;

        if (!this.tabTargets.includes(currentTab)) {
            return;
        }

        const currentIndex = this.tabTargets.indexOf(currentTab);
        let nextIndex = currentIndex;

        switch (event.key) {
            case 'ArrowLeft':
            case 'ArrowUp':
                event.preventDefault();
                nextIndex = currentIndex > 0 ? currentIndex - 1 : this.tabTargets.length - 1;
                break;

            case 'ArrowRight':
            case 'ArrowDown':
                event.preventDefault();
                nextIndex = currentIndex < this.tabTargets.length - 1 ? currentIndex + 1 : 0;
                break;

            case 'Home':
                event.preventDefault();
                nextIndex = 0;
                break;

            case 'End':
                event.preventDefault();
                nextIndex = this.tabTargets.length - 1;
                break;

            default:
                return;
        }

        const nextTab = this.tabTargets[nextIndex];
        const nextTabId = nextTab.dataset.tab || nextTab.dataset.tabsTabParam;

        this.showTab(nextTabId);
    }

    /**
     * Navigate to next tab
     */
    next() {
        const currentTab = this.tabTargets.find(tab => tab.classList.contains('active'));
        const currentIndex = this.tabTargets.indexOf(currentTab);
        const nextIndex = currentIndex < this.tabTargets.length - 1 ? currentIndex + 1 : 0;
        const nextTab = this.tabTargets[nextIndex];
        const nextTabId = nextTab.dataset.tab || nextTab.dataset.tabsTabParam;

        this.showTab(nextTabId);
    }

    /**
     * Navigate to previous tab
     */
    previous() {
        const currentTab = this.tabTargets.find(tab => tab.classList.contains('active'));
        const currentIndex = this.tabTargets.indexOf(currentTab);
        const prevIndex = currentIndex > 0 ? currentIndex - 1 : this.tabTargets.length - 1;
        const prevTab = this.tabTargets[prevIndex];
        const prevTabId = prevTab.dataset.tab || prevTab.dataset.tabsTabParam;

        this.showTab(prevTabId);
    }

    /**
     * Disconnect - cleanup
     */
    disconnect() {
        this.element.removeEventListener('keydown', this.handleKeyboard);
    }
}
