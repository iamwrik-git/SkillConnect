/**
 * assets/js/admin.js - SkillConnect Administrative Control Panel Scripts
 */

document.addEventListener('DOMContentLoaded', () => {

    // 1. Responsive admin navigation drawer
    (() => {
        const toggle = document.querySelector('.admin-menu-toggle');
        const sidebar = document.querySelector('.admin-sidebar');
        const backdrop = document.querySelector('.admin-sidebar-backdrop');
        if (!toggle || !sidebar || !backdrop) return;

        const close = () => {
            document.body.classList.remove('admin-sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', 'Open admin navigation');
        };

        toggle.addEventListener('click', () => {
            const open = document.body.classList.toggle('admin-sidebar-open');
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', open ? 'Close admin navigation' : 'Open admin navigation');
        });

        backdrop.addEventListener('click', close);
        sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', close));

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') close();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 760) close();
        });
    })();

    // 2. Logout & Exit Website Modals
    (() => {
        const logoutModal = document.getElementById('logout-confirm-modal');
        const logoutCancel = document.getElementById('logout-cancel-btn');
        const logoutConfirm = document.getElementById('logout-confirm-btn');
        const logoutForm = document.getElementById('admin-logout-form');

        const openLogout = (e) => {
            if (e) e.preventDefault();
            logoutModal?.classList.add('show');
            logoutModal?.setAttribute('aria-hidden', 'false');
            document.body.classList.add('admin-modal-open');
            logoutCancel?.focus();
        };

        const closeLogout = () => {
            logoutModal?.classList.remove('show');
            logoutModal?.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('admin-modal-open');
        };

        document.querySelectorAll('[data-confirm-logout]').forEach(btn => {
            btn.addEventListener('click', openLogout);
        });
        logoutCancel?.addEventListener('click', closeLogout);
        logoutConfirm?.addEventListener('click', () => {
            closeLogout();
            logoutForm?.submit();
        });
        logoutModal?.addEventListener('click', (e) => {
            if (e.target === logoutModal) closeLogout();
        });

        // Exit Website Modal
        const exitModal = document.getElementById('exit-website-confirm-modal');
        const exitCancel = document.getElementById('exit-cancel-btn');

        const openExit = (e) => {
            if (e) e.preventDefault();
            exitModal?.classList.add('show');
            exitModal?.setAttribute('aria-hidden', 'false');
            document.body.classList.add('admin-modal-open');
            exitCancel?.focus();
        };

        const closeExit = () => {
            exitModal?.classList.remove('show');
            exitModal?.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('admin-modal-open');
        };

        document.querySelectorAll('[data-confirm-exit]').forEach(btn => {
            btn.addEventListener('click', openExit);
        });
        exitCancel?.addEventListener('click', closeExit);
        exitModal?.addEventListener('click', (e) => {
            if (e.target === exitModal) closeExit();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (logoutModal && logoutModal.classList.contains('show')) closeLogout();
                if (exitModal && exitModal.classList.contains('show')) closeExit();
            }
        });
    })();

    // 3. Delete Confirmation Dialog
    (() => {
        const modal = document.getElementById('sc-confirm-modal');
        const title = document.getElementById('sc-confirm-title');
        const message = document.getElementById('sc-confirm-message');
        const warning = document.getElementById('sc-confirm-warning');
        const cancel = document.getElementById('sc-confirm-cancel');
        const close = document.getElementById('sc-confirm-close');
        const submit = document.getElementById('sc-confirm-submit');
        const submitLabel = submit?.querySelector('span');
        if (!modal || !title || !message || !warning || !cancel || !close || !submit || !submitLabel) return;

        let pendingForm = null;
        const closeModal = () => {
            if (modal.open) modal.close();
            pendingForm = null;
        };

        document.querySelectorAll('form.js-confirm-delete').forEach(form => {
            form.addEventListener('submit', event => {
                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;
                    return;
                }
                event.preventDefault();
                pendingForm = form;
                title.textContent = form.dataset.confirmTitle || 'Confirm deletion';
                message.textContent = form.dataset.confirmMessage || 'Are you sure you want to delete this item?';
                warning.querySelector('p').textContent = form.dataset.confirmWarning || 'This action cannot be undone. The selected record may be permanently removed.';
                submitLabel.textContent = form.dataset.confirmAction || 'Delete';
                modal.showModal();
                cancel.focus();
            });
        });

        cancel.addEventListener('click', closeModal);
        close.addEventListener('click', closeModal);
        modal.addEventListener('click', event => {
            if (event.target === modal) closeModal();
        });
        modal.addEventListener('cancel', event => {
            event.preventDefault();
            closeModal();
        });
        submit.addEventListener('click', () => {
            if (!pendingForm) return;
            const form = pendingForm;
            closeModal();
            form.dataset.confirmed = 'true';
            HTMLFormElement.prototype.submit.call(form);
        });
    })();

    // 4. Toast Notification System
    (() => {
        const container = document.getElementById('admin-toast-container');
        if (!container) return;

        const setupToast = (toast) => {
            const duration = parseInt(toast.dataset.autoDismiss || '3700', 10);
            const progress = toast.querySelector('.admin-toast-progress');
            if (progress) {
                progress.style.animationDuration = duration + 'ms';
            }

            let dismissTimeout = setTimeout(() => {
                dismiss(toast);
            }, duration);

            const closeBtn = toast.querySelector('.admin-toast-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    clearTimeout(dismissTimeout);
                    dismiss(toast);
                });
            }

            toast.addEventListener('mouseenter', () => {
                clearTimeout(dismissTimeout);
                if (progress) progress.style.animationPlayState = 'paused';
            });

            toast.addEventListener('mouseleave', () => {
                if (progress) progress.style.animationPlayState = 'running';
                dismissTimeout = setTimeout(() => {
                    dismiss(toast);
                }, 1800);
            });
        };

        const dismiss = (toast) => {
            if (toast.classList.contains('hiding')) return;
            toast.classList.add('hiding');
            toast.addEventListener('animationend', () => {
                toast.remove();
            }, { once: true });
        };

        container.querySelectorAll('.admin-toast').forEach(setupToast);

        window.showAdminToast = (message, type = 'success', title = '') => {
            const isDeletion = /\b(deleted|removed|unlinked|cancelled|rejected|cleared)\b/i.test(message || '');
            if (isDeletion && type === 'success') {
                type = 'danger';
            }

            const isSuccess = type === 'success';
            const displayTitle = title || (isDeletion ? 'Deleted' : (isSuccess ? 'Success' : 'Notice'));
            const autoDismissTime = (type === 'danger' || type === 'error') ? '4100' : '3700';

            const toast = document.createElement('div');
            toast.className = `admin-toast admin-toast-${type}`;
            toast.setAttribute('role', 'alert');
            toast.dataset.autoDismiss = autoDismissTime;

            let iconSvg = '';
            if (isDeletion) {
                iconSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
            } else if (isSuccess) {
                iconSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            } else {
                iconSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
            }

            toast.innerHTML = `
                <div class="admin-toast-icon">${iconSvg}</div>
                <div class="admin-toast-content">
                    <div class="admin-toast-title">${displayTitle}</div>
                    <div class="admin-toast-message">${message}</div>
                </div>
                <button type="button" class="admin-toast-close" aria-label="Dismiss notification">&times;</button>
                <div class="admin-toast-progress"></div>
            `;
            container.appendChild(toast);
            setupToast(toast);
        };
    })();

    // 5. Clickable Table Rows Delegation
    (() => {
        document.addEventListener('click', (e) => {
            const row = e.target.closest('.clickable-row');
            if (!row) return;
            if (e.target.closest('button, form, a, input, select, textarea, .btn-action, .js-confirm-delete, [data-confirm-delete]')) {
                return;
            }
            const href = row.dataset.href;
            if (href) {
                window.location.href = href;
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const row = document.activeElement?.closest?.('.clickable-row');
                if (row && !document.activeElement.closest('button, a, input, select, textarea')) {
                    const href = row.dataset.href;
                    if (href) {
                        window.location.href = href;
                    }
                }
            }
        });
    })();

    // 6. Admin Bright / Dark Theme Mode Toggle
    (() => {
        const toggleBtn = document.getElementById('admin-theme-toggle-btn');
        if (!toggleBtn) return;

        const updateState = (isLight) => {
            if (isLight) {
                document.documentElement.classList.add('theme-light');
                document.body.classList.add('theme-light');
                toggleBtn.setAttribute('title', 'Switch to Dark Mode');
                toggleBtn.setAttribute('aria-label', 'Switch to Dark Mode');
            } else {
                document.documentElement.classList.remove('theme-light');
                document.body.classList.remove('theme-light');
                toggleBtn.setAttribute('title', 'Switch to Bright Mode');
                toggleBtn.setAttribute('aria-label', 'Switch to Bright Mode');
            }
        };

        // Initialize state based on storage or existing class
        const currentTheme = localStorage.getItem('skillconnect_admin_theme');
        const isCurrentlyLight = currentTheme === 'light' || document.documentElement.classList.contains('theme-light');
        updateState(isCurrentlyLight);

        toggleBtn.addEventListener('click', () => {
            const willBeLight = !document.documentElement.classList.contains('theme-light');
            localStorage.setItem('skillconnect_admin_theme', willBeLight ? 'light' : 'dark');
            updateState(willBeLight);
        });
    })();
});

// 7. Global Helper for Manage Skills Inline Edit Toggle
window.toggleEdit = function(id) {
    const form = document.getElementById('edit-form-' + id);
    const text = document.getElementById('skill-text-' + id);
    const actions = document.getElementById('actions-' + id);
    if (!form || !text || !actions) return;

    if (form.style.display === 'none' || getComputedStyle(form).display === 'none') {
        form.style.display = 'flex';
        text.style.display = 'none';
        actions.style.display = 'none';
        const input = form.querySelector('input[type="text"]');
        if (input) input.focus();
    } else {
        form.style.display = 'none';
        text.style.display = 'inline';
        actions.style.display = 'flex';
    }
};
