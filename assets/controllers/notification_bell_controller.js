import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge', 'dropdown', 'list'];
    static values = {
        url: String,
        refreshInterval: { type: Number, default: 60000 }
    };

    connect() {
        this.loadNotifications();
        // Refresh every 60 seconds
        this.refreshTimer = setInterval(() => this.loadNotifications(), this.refreshIntervalValue);

        // Close dropdown when clicking outside
        document.addEventListener('click', this.handleOutsideClick.bind(this));
    }

    disconnect() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        document.removeEventListener('click', this.handleOutsideClick.bind(this));
    }

    handleOutsideClick(event) {
        if (!this.element.contains(event.target) && !this.dropdownTarget.classList.contains('hidden')) {
            this.dropdownTarget.classList.add('hidden');
        }
    }

    async loadNotifications() {
        try {
            const response = await fetch(this.urlValue);
            if (!response.ok) throw new Error('Failed to fetch notifications');

            const data = await response.json();
            this.updateBadge(data.count);
            this.renderNotifications(data.notifications);
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    updateBadge(count) {
        if (count > 0) {
            this.badgeTarget.textContent = count > 99 ? '99+' : count;
            this.badgeTarget.classList.remove('hidden');
        } else {
            this.badgeTarget.classList.add('hidden');
        }
    }

    renderNotifications(notifications) {
        if (notifications.length === 0) {
            this.listTarget.innerHTML = `
                <div class="p-6 text-center">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Aucune notification</p>
                </div>
            `;
            return;
        }

        this.listTarget.innerHTML = notifications.map(n => this.renderNotificationItem(n)).join('');
    }

    renderNotificationItem(notification) {
        const typeIcon = this.getTypeIcon(notification.type);
        const timeAgo = this.timeAgo(new Date(notification.dateEnvoi));
        const unreadClass = notification.lu ? '' : 'bg-emerald-50/50 dark:bg-emerald-900/20';
        const linkAttr = notification.lien
            ? `href="${notification.lien}" data-action="click->notification-bell#markAsRead"`
            : `href="#" data-action="click->notification-bell#markAsRead"`;

        return `
            <a ${linkAttr}
               class="block px-4 py-3 hover:bg-white/30 dark:hover:bg-white/5 transition-colors border-b border-white/10 last:border-0 ${unreadClass}"
               data-notification-id="${notification.id}">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        ${typeIcon}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            ${this.escapeHtml(notification.titre)}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 mt-0.5">
                            ${this.escapeHtml(notification.message)}
                        </p>
                        <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                            ${timeAgo}
                        </p>
                    </div>
                    ${!notification.lu ? '<div class="w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0 mt-2"></div>' : ''}
                </div>
            </a>
        `;
    }

    getTypeIcon(type) {
        const icons = {
            'alerte': `<div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
            </div>`,
            'action': `<div class="w-8 h-8 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                </svg>
            </div>`,
            'info': `<div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>`,
        };
        return icons[type] || icons['info'];
    }

    timeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        const intervals = {
            'annee': 31536000,
            'mois': 2592000,
            'semaine': 604800,
            'jour': 86400,
            'heure': 3600,
            'minute': 60
        };

        for (const [name, value] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / value);
            if (interval >= 1) {
                const plural = interval > 1 && name !== 'mois' ? 's' : '';
                return `Il y a ${interval} ${name}${plural}`;
            }
        }
        return 'A l\'instant';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    toggle(event) {
        event.stopPropagation();
        this.dropdownTarget.classList.toggle('hidden');

        if (!this.dropdownTarget.classList.contains('hidden')) {
            // Refresh when opening
            this.loadNotifications();
        }
    }

    async markAsRead(event) {
        const notificationId = event.currentTarget.dataset.notificationId;
        if (notificationId) {
            try {
                await fetch(`/api/notifications/${notificationId}/read`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                // Reload to update badge
                this.loadNotifications();
            } catch (error) {
                console.error('Failed to mark notification as read:', error);
            }
        }
    }

    async markAllRead(event) {
        event.preventDefault();
        try {
            await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            this.loadNotifications();
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
        }
    }
}
