/**
 * assets/js/nav.js - Navigation Dropdowns and Confirmation Modals
 */

document.addEventListener('DOMContentLoaded', () => {

    // 1. Network Menu Dropdown
    (() => {
        const trigger = document.getElementById('network-menu-trigger');
        const menu = document.getElementById('network-dropdown');
        const wrapper = trigger && trigger.closest('.network-menu');
        if (!trigger || !menu || !wrapper || wrapper.dataset.networkReady === 'true') return;
        wrapper.dataset.networkReady = 'true';

        const setOpen = (open) => {
            wrapper.classList.toggle('show', open);
            trigger.setAttribute('aria-expanded', String(open));
            menu.setAttribute('aria-hidden', String(!open));
        };

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const willOpen = trigger.getAttribute('aria-expanded') !== 'true';
            if (willOpen) {
                const notifications = document.querySelector('.notification-menu');
                if (notifications) {
                    notifications.classList.remove('show');
                    const notificationTrigger = document.getElementById('notifications-menu-trigger');
                    const notificationMenu = document.getElementById('notifications-dropdown');
                    if (notificationTrigger) notificationTrigger.setAttribute('aria-expanded', 'false');
                    if (notificationMenu) notificationMenu.setAttribute('aria-hidden', 'true');
                }
                const profile = document.querySelector('.profile-menu');
                if (profile) {
                    profile.classList.remove('show');
                    const profileTrigger = document.getElementById('profile-menu-trigger');
                    const profileMenu = document.getElementById('profile-dropdown');
                    if (profileTrigger) profileTrigger.setAttribute('aria-expanded', 'false');
                    if (profileMenu) profileMenu.setAttribute('aria-hidden', 'true');
                }
            }
            setOpen(willOpen);
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) setOpen(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                trigger.focus();
            }
        });
    })();

    // 2. Clear Notifications Confirm Dialog
    (() => {
        const clearForm = document.getElementById('notification-clear-form');
        const clearTrigger = document.getElementById('notification-clear-trigger');
        const dialog = document.getElementById('notification-clear-confirm');
        const cancelButton = document.getElementById('notification-clear-cancel');
        const acceptButton = document.getElementById('notification-clear-accept');
        if (clearForm && clearTrigger && dialog && cancelButton && acceptButton && !clearForm.dataset.confirmReady) {
            clearForm.dataset.confirmReady = 'true';
            clearForm.addEventListener('submit', (event) => {
                event.preventDefault();
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else if (window.confirm('Clear notifications? Mentorship requests will not be affected.')) {
                    clearForm.submit();
                }
            });
            cancelButton.addEventListener('click', () => dialog.close());
            acceptButton.addEventListener('click', () => {
                dialog.close();
                clearForm.submit();
            });
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) dialog.close();
            });
        }
    })();

    // 3. Notifications Dropdown
    (() => {
        const trigger = document.getElementById('notifications-menu-trigger');
        const menu = document.getElementById('notifications-dropdown');
        const wrapper = trigger && trigger.closest('.notification-menu');
        if (!trigger || !menu || !wrapper || wrapper.dataset.notificationsReady === 'true') return;
        wrapper.dataset.notificationsReady = 'true';

        const setOpen = (open) => {
            wrapper.classList.toggle('show', open);
            trigger.setAttribute('aria-expanded', String(open));
            menu.setAttribute('aria-hidden', String(!open));
        };

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const willOpen = trigger.getAttribute('aria-expanded') !== 'true';
            if (willOpen) {
                const network = document.querySelector('.network-menu');
                if (network) {
                    network.classList.remove('show');
                    const networkTrigger = document.getElementById('network-menu-trigger');
                    const networkMenu = document.getElementById('network-dropdown');
                    if (networkTrigger) networkTrigger.setAttribute('aria-expanded', 'false');
                    if (networkMenu) networkMenu.setAttribute('aria-hidden', 'true');
                }
                const profile = document.querySelector('.profile-menu');
                if (profile) {
                    profile.classList.remove('show');
                    const profileTrigger = document.getElementById('profile-menu-trigger');
                    const profileMenu = document.getElementById('profile-dropdown');
                    if (profileTrigger) profileTrigger.setAttribute('aria-expanded', 'false');
                    if (profileMenu) profileMenu.setAttribute('aria-hidden', 'true');
                }
            }
            setOpen(willOpen);
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) setOpen(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                trigger.focus();
            }
        });
    })();

    // 4. Profile Dropdown Menu (Hover on desktop, Click on touch)
    (() => {
        const trigger = document.getElementById('profile-menu-trigger');
        const menu = document.getElementById('profile-dropdown');
        const wrapper = trigger && trigger.closest('.profile-menu');
        if (!trigger || !menu || !wrapper || wrapper.dataset.profileMenuReady === 'true') return;
        wrapper.dataset.profileMenuReady = 'true';

        const hoverMode = window.matchMedia('(hover: hover) and (pointer: fine)');

        const closeOtherMenus = () => {
            const network = document.querySelector('.network-menu');
            if (network) {
                network.classList.remove('show');
                const networkTrigger = document.getElementById('network-menu-trigger');
                const networkMenu = document.getElementById('network-dropdown');
                if (networkTrigger) networkTrigger.setAttribute('aria-expanded', 'false');
                if (networkMenu) networkMenu.setAttribute('aria-hidden', 'true');
            }
            const notifications = document.querySelector('.notification-menu');
            if (notifications) {
                notifications.classList.remove('show');
                const notificationTrigger = document.getElementById('notifications-menu-trigger');
                const notificationMenu = document.getElementById('notifications-dropdown');
                if (notificationTrigger) notificationTrigger.setAttribute('aria-expanded', 'false');
                if (notificationMenu) notificationMenu.setAttribute('aria-hidden', 'true');
            }
        };

        const setOpen = (open) => {
            if (open) closeOtherMenus();
            wrapper.classList.toggle('show', open);
            trigger.setAttribute('aria-expanded', String(open));
            menu.setAttribute('aria-hidden', String(!open));
        };

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            setOpen(trigger.getAttribute('aria-expanded') !== 'true');
        });

        wrapper.addEventListener('mouseenter', () => {
            if (hoverMode.matches) closeOtherMenus();
        });

        wrapper.addEventListener('focusout', (event) => {
            if (!wrapper.contains(event.relatedTarget)) setOpen(false);
        });

        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) setOpen(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                setOpen(false);
                trigger.focus();
            }
        });

        menu.addEventListener('click', (event) => {
            if (event.target.closest('a')) setOpen(false);
        });

        if (hoverMode.addEventListener) {
            hoverMode.addEventListener('change', () => setOpen(false));
        }
    })();

    // 5. Logout Confirmation Modal
    (() => {
        const modal = document.getElementById('logout-confirm-modal');
        const dialog = modal && modal.querySelector('.logout-confirm-content');
        const cancelButton = document.getElementById('logout-cancel-btn');
        const confirmButton = document.getElementById('logout-confirm-btn');
        if (!modal || !dialog || !cancelButton || !confirmButton || modal.dataset.ready === 'true') return;
        modal.dataset.ready = 'true';

        let logoutUrl = 'logout.php';
        let lastFocusedElement = null;

        const openModal = (url, source) => {
            logoutUrl = url || 'logout.php';
            lastFocusedElement = source;
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('logout-confirm-open');
            window.setTimeout(() => cancelButton.focus(), 30);
        };

        const closeModal = () => {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('logout-confirm-open');
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            }
        };

        document.addEventListener('click', (event) => {
            const logoutLink = event.target.closest('[data-confirm-logout]');
            if (!logoutLink) return;
            event.preventDefault();
            openModal(logoutLink.href, logoutLink);
        });

        cancelButton.addEventListener('click', closeModal);
        confirmButton.addEventListener('click', (event) => {
            const form = document.getElementById('logout-action-form');
            if (form) {
                event.preventDefault();
                form.submit();
            } else {
                window.location.href = logoutUrl;
            }
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });

        document.addEventListener('keydown', (event) => {
            if (!modal.classList.contains('show')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
            }
            if (event.key === 'Tab') {
                const focusable = [cancelButton, confirmButton];
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    })();

    // 6. In-Page Direct Message Modal Controller
    (() => {
        const backdrop = document.getElementById('sc-message-backdrop');
        if (!backdrop) return;

        const closeBtn = document.getElementById('sc-message-close-btn');
        const cancelBtn = document.getElementById('sc-message-cancel-btn');
        const form = document.getElementById('sc-message-form');
        const submitBtn = document.getElementById('sc-message-submit-btn');
        const recipientName = document.getElementById('sc-message-recipient-name');
        const recipientTopic = document.getElementById('sc-message-topic');
        const recipientIdInput = document.getElementById('sc-message-recipient-id');
        const textarea = document.getElementById('sc-message-text');
        const counter = document.getElementById('sc-message-char-counter');
        let triggerButton = null;

        const openModal = (btn) => {
            triggerButton = btn;
            const uId = btn.dataset.userId || '';
            const uName = btn.dataset.userName || 'User';
            const uSkill = btn.dataset.skill || '';
            const uRole = btn.dataset.role || 'Mentorship';

            if (recipientName) recipientName.textContent = uName;
            if (recipientTopic) {
                recipientTopic.textContent = 'Mentorship conversation';
            }
            if (recipientIdInput) recipientIdInput.value = uId;
            if (textarea) textarea.value = '';
            if (counter) counter.textContent = '0';

            backdrop.removeAttribute('hidden');
            document.body.classList.add('sc-message-modal-active');
            if (textarea) {
                setTimeout(() => textarea.focus(), 50);
            }
        };

        const closeModal = () => {
            backdrop.setAttribute('hidden', '');
            document.body.classList.remove('sc-message-modal-active');
            if (triggerButton) {
                triggerButton.focus();
                triggerButton = null;
            }
        };

        document.addEventListener('click', (event) => {
            const btn = event.target.closest('.js-open-message-modal');
            if (btn) {
                event.preventDefault();
                openModal(btn);
            }
        });

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) closeModal();
        });

        document.addEventListener('keydown', (event) => {
            if (!backdrop.hasAttribute('hidden') && event.key === 'Escape') {
                event.preventDefault();
                closeModal();
            }
        });

        if (textarea && counter) {
            textarea.addEventListener('input', () => {
                counter.textContent = String(textarea.value.length);
            });
        }

        if (form && submitBtn) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const text = textarea ? textarea.value.trim() : '';
                if (!text) return;

                const origText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = `<span>Sending...</span>`;

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();
                    if (data && data.success) {
                        closeModal();
                        showInPageToast(data.message || 'Message sent successfully!');
                    } else {
                        alert(data.error || 'Could not send message. Please try again.');
                    }
                } catch (err) {
                    console.error('Message send error:', err);
                    form.submit(); // fallback to standard submit
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
            });
        }

        function showInPageToast(message) {
            const toast = document.createElement('div');
            toast.className = 'sc-onboarding-toast';
            toast.setAttribute('role', 'status');
            toast.innerHTML = `
                <span class="sc-onboarding-toast-icon" aria-hidden="true">✓</span>
                <div><strong>Message Sent</strong><span>${message}</span></div>
                <button type="button" class="sc-onboarding-toast-close" aria-label="Close">×</button>
            `;
            document.body.appendChild(toast);
            const close = toast.querySelector('.sc-onboarding-toast-close');
            if (close) close.addEventListener('click', () => toast.remove());
            setTimeout(() => {
                toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                setTimeout(() => toast.remove(), 300);
            }, 4500);
        }
    })();
});
