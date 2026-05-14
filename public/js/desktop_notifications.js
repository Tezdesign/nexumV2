document.addEventListener('DOMContentLoaded', function() {
    const notifBadge = document.getElementById('desktop-notif-badge');
    const notifDropdownBtn = document.getElementById('desktop-notif-dropdown-btn');
    const notifList = document.getElementById('desktop-notification-list');
    const notifIcon = notifDropdownBtn ? notifDropdownBtn.querySelector('i') : null;

    let pollInterval = null;

    // Helper: Format UNIX timestamp to "Time Ago"
    function timeAgo(timestamp) {
        const seconds = Math.floor(Date.now() / 1000) - timestamp;
        if (seconds < 60) return seconds + 's ago';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        const days = Math.floor(hours / 24);
        return days + 'd ago';
    }

    // Function to update the unread badge
    function pollUnreadCount() {
        fetch('/api/desktop/notifications/unread', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(response => {
            if (!response.ok) throw new Error('Polling failed');
            return response.json();
        })
        .then(data => {
            if (notifBadge && typeof data.unreadCount !== 'undefined') {
                if (data.unreadCount > 0) {
                    notifBadge.textContent = data.unreadCount;
                    notifBadge.style.display = 'block';
                    if (notifIcon) notifIcon.classList.add('animate-ring');
                } else {
                    notifBadge.style.display = 'none';
                    if (notifIcon) notifIcon.classList.remove('animate-ring');
                }
            }
        })
        .catch(err => {
            // Silently ignore polling errors to prevent console spam
        });
    }

    // Initialize Polling
    if (notifBadge && notifDropdownBtn && notifList) {
        pollUnreadCount();
        pollInterval = setInterval(pollUnreadCount, 15000); // Every 15 seconds

        // Listen for the dropdown to open
        notifDropdownBtn.addEventListener('shown.bs.dropdown', function () {
            // Show loading state briefly
            notifList.innerHTML = '<div class="text-center p-3"><span class="spinner-border spinner-border-sm text-primary" role="status"></span></div>';

            fetch('/api/desktop/notifications/list', {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                // Inject the pre-rendered Twig HTML directly
                notifList.innerHTML = data.html || '<div class="p-3 text-center text-danger">Failed to load HTML.</div>';
                
                // Hide the badge since they are now marked as read
                notifBadge.style.display = 'none';
                if (notifIcon) notifIcon.classList.remove('animate-ring');
            })
            .catch(error => {
                notifList.innerHTML = '<div class="p-3 text-center text-danger">Failed to load notifications.</div>';
                console.error('Error fetching notifications:', error);
            });
        });

        // View-based actions
        const btnMarkRead = document.getElementById('btn-mark-read');
        const btnClearAll = document.getElementById('btn-clear-all');

        if (btnMarkRead) {
            btnMarkRead.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // View-based only: Remove active class from items and hide badge
                const items = notifList.querySelectorAll('.notification-item.active');
                items.forEach(item => item.classList.remove('active'));
                notifBadge.style.display = 'none';
                if (notifIcon) notifIcon.classList.remove('animate-ring');
            });
        }

        if (btnClearAll) {
            btnClearAll.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                // View-based only: Empty the list and hide badge
                notifList.innerHTML = '<div class="p-3 text-center text-muted">No new notifications</div>';
                notifBadge.style.display = 'none';
                if (notifIcon) notifIcon.classList.remove('animate-ring');
            });
        }
    }
});