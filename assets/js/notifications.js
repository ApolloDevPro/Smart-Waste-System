class NotificationManager {
    constructor() {
        this.updateInterval = null;
        this.pollingInterval = 30000; // 30 seconds
        this.init();
    }

    init() {
        this.checkBrowserNotificationSupport();
        this.loadUnreadCount();
        this.startPolling();
        this.attachEventListeners();
    }

    checkBrowserNotificationSupport() {
        if (!("Notification" in window)) {
            console.log("This browser does not support notifications");
            return;
        }

        if (Notification.permission === "default") {
            this.showPermissionBanner();
        }
    }

    showPermissionBanner() {
        const banner = document.querySelector('.notification-permission-banner');
        if (banner) {
            banner.classList.add('show');
        }
    }

    requestNotificationPermission() {
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission().then(permission => {
                if (permission === "granted") {
                    this.showToast("Notifications enabled!", "You'll now receive browser notifications", "success");
                    const banner = document.querySelector('.notification-permission-banner');
                    if (banner) banner.classList.remove('show');
                }
            });
        }
    }

    showBrowserNotification(title, message, icon = null) {
        if ("Notification" in window && Notification.permission === "granted") {
            const notification = new Notification(title, {
                body: message,
                icon: icon || '../assets/images/logo.png',
                badge: '../assets/images/badge.png',
                tag: 'waste-management',
                requireInteraction: false
            });

            notification.onclick = function() {
                window.focus();
                notification.close();
            };

            setTimeout(() => notification.close(), 5000);
        }
    }

    loadUnreadCount() {
        fetch('../includes/notification_handler.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.updateBadge(data.count);
                }
            })
            .catch(error => console.error('Error loading unread count:', error));
    }

    loadNotifications() {
        fetch('../includes/notification_handler.php?action=get_recent&limit=20')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.renderNotifications(data.notifications);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    renderNotifications(notifications) {
        const container = document.querySelector('.notification-list');
        if (!container) return;

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }

        container.innerHTML = notifications.map(notif => `
            <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}" 
                 data-id="${notif.notification_id}"
                 onclick="notificationManager.handleNotificationClick(${notif.notification_id}, '${notif.action_url || ''}')">
                <div class="notification-title">
                    ${this.escapeHtml(notif.title)}
                    <span class="notification-type ${notif.notification_type}">${notif.notification_type}</span>
                </div>
                <div class="notification-message">${this.escapeHtml(notif.message)}</div>
                <div class="notification-time">
                    <i class="far fa-clock"></i> ${this.formatTime(notif.created_at)}
                </div>
                <div class="notification-actions" onclick="event.stopPropagation()">
                    ${notif.is_read == 0 ? `<button class="notif-action-btn mark-read" onclick="notificationManager.markAsRead(${notif.notification_id})">
                        <i class="fas fa-check"></i> Mark Read
                    </button>` : ''}
                    <button class="notif-action-btn delete" onclick="notificationManager.deleteNotification(${notif.notification_id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `).join('');
    }

    handleNotificationClick(notificationId, actionUrl) {
        this.markAsRead(notificationId);
        if (actionUrl) {
            window.location.href = actionUrl;
        }
    }

    markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);

        fetch('../includes/notification_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadUnreadCount();
                this.loadNotifications();
            }
        })
        .catch(error => console.error('Error marking as read:', error));
    }

    markAllAsRead() {
        const formData = new FormData();
        formData.append('action', 'mark_all_read');

        fetch('../includes/notification_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadUnreadCount();
                this.loadNotifications();
                this.showToast("Success", "All notifications marked as read", "success");
            }
        })
        .catch(error => console.error('Error marking all as read:', error));
    }

    deleteNotification(notificationId) {
        if (!confirm('Delete this notification?')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('notification_id', notificationId);

        fetch('../includes/notification_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.loadUnreadCount();
                this.loadNotifications();
                this.showToast("Deleted", "Notification removed", "info");
            }
        })
        .catch(error => console.error('Error deleting notification:', error));
    }

    updateBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    toggleDropdown() {
        const dropdown = document.querySelector('.notification-dropdown');
        if (dropdown) {
            const isVisible = dropdown.classList.toggle('show');
            if (isVisible) {
                this.loadNotifications();
            }
        }
    }

    startPolling() {
        this.updateInterval = setInterval(() => {
            this.loadUnreadCount();
        }, this.pollingInterval);
    }

    stopPolling() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
    }

    attachEventListeners() {
        document.addEventListener('click', (e) => {
            const dropdown = document.querySelector('.notification-dropdown');
            const bell = document.querySelector('.notification-bell');
            
            if (dropdown && bell) {
                if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                    dropdown.classList.remove('show');
                }
            }
        });

        const enableBtn = document.querySelector('.enable-notifications-btn');
        if (enableBtn) {
            enableBtn.addEventListener('click', () => this.requestNotificationPermission());
        }

        const markAllBtn = document.querySelector('.mark-all-read');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', () => this.markAllAsRead());
        }

        const bellIcon = document.querySelector('.notification-bell');
        if (bellIcon) {
            bellIcon.addEventListener('click', () => this.toggleDropdown());
        }
    }

    showToast(title, message, type = 'info') {
        let toast = document.querySelector('.toast-notification');
        
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'toast-notification';
            document.body.appendChild(toast);
        }

        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-times-circle',
            info: 'fas fa-info-circle',
            warning: 'fas fa-exclamation-triangle'
        };

        toast.innerHTML = `
            <i class="toast-icon ${type} ${icons[type]}"></i>
            <div class="toast-content">
                <div class="toast-title">${this.escapeHtml(title)}</div>
                <div class="toast-message">${this.escapeHtml(message)}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.classList.remove('show')">&times;</button>
        `;

        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 5000);
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        
        return date.toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

let notificationManager;
document.addEventListener('DOMContentLoaded', function() {
    notificationManager = new NotificationManager();
});
