/**
 * Smart Printing Payment System - Global JavaScript
 */

window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
};

window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.modal-overlay.open')) {
        document.body.classList.remove('modal-open');
    }
};

document.addEventListener('DOMContentLoaded', function () {
    // Current year in footer
    const currentYear = document.getElementById('currentYear');
    if (currentYear) {
        currentYear.textContent = new Date().getFullYear();
    }

    // Sidebar toggle for mobile responsive layouts
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('open');
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
            sidebarToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('show', isOpen);
        });

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
                sidebarToggle.setAttribute('aria-expanded', 'false');
                sidebarToggle.setAttribute('aria-label', 'Open navigation menu');
            });
        }
    }

    document.querySelectorAll('[data-tabs]').forEach(function (tabGroup) {
        const buttons = tabGroup.querySelectorAll('.tab-btn[data-tab]');
        const panes = tabGroup.querySelectorAll('.tab-pane[id]');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                const targetId = button.dataset.tab;
                const targetPane = Array.from(panes).find(function (pane) {
                    return pane.id === targetId;
                });
                if (!targetPane) return;

                buttons.forEach(function (tabButton) {
                    const isSelected = tabButton === button;
                    tabButton.classList.toggle('active', isSelected);
                    tabButton.setAttribute('aria-selected', String(isSelected));
                });
                panes.forEach(function (pane) {
                    const isSelected = pane === targetPane;
                    pane.classList.toggle('active', isSelected);
                    pane.hidden = !isSelected;
                });
            });
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (modal) {
        modal.setAttribute('aria-hidden', String(!modal.classList.contains('open')));
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                window.closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const openModal = document.querySelector('.modal-overlay.open');
            if (openModal) window.closeModal(openModal.id);
        }
    });

    // Live Notification Bell Count Polling (Every 10s if logged in)
    const notifBadge = document.getElementById('topbarNotifBadge');
    if (notifBadge) {
        function checkNotifications() {
            fetch('/PrintWeb/api/notifications.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.unread_count !== undefined) {
                        if (data.unread_count > 0) {
                            notifBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                            notifBadge.style.display = 'inline-flex';
                        } else {
                            notifBadge.style.display = 'none';
                        }
                    }
                })
                .catch(err => console.warn('Notification sync paused:', err));
        }
        setInterval(checkNotifications, 10000);
    }

    // Live Queue Auto-Refresh (on queue pages)
    const queueLiveTable = document.getElementById('liveQueueContainer');
    if (queueLiveTable) {
        const urlParams = new URLSearchParams(window.location.search);
        const currentJobId = urlParams.get('job');
        
        if (currentJobId) {
            setInterval(function () {
                fetch('/PrintWeb/api/queue.php?job_id=' + encodeURIComponent(currentJobId))
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.job) {
                            const statusElem = document.getElementById('liveJobStatus');
                            if (statusElem && data.badge) {
                                statusElem.innerHTML = data.badge;
                            }
                            if (data.job.print_status === 'completed') {
                                const progBar = document.getElementById('jobProgressBar');
                                if (progBar) {
                                    progBar.style.width = '100%';
                                    progBar.className = 'progress-bar bg-success';
                                }
                            }
                        }
                    })
                    .catch(e => console.warn(e));
            }, 3000);
        }
    }

    // Client-side form validation
    const forms = document.querySelectorAll('[data-validate]');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(function (field) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // Password confirmation matching
            const p1 = form.querySelector('input[name="password"]');
            const p2 = form.querySelector('input[name="confirm_password"]');
            if (p1 && p2 && p1.value !== p2.value) {
                p2.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
    });
});
