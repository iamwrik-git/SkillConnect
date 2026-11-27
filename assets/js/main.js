// assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {

    // Responsive application navigation: preserve the existing sidebar links.
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const appSidebar = document.querySelector('.app-body > .sidebar');
    const sidebarBackdrop = document.querySelector('.sidebar-backdrop');

    if (menuToggle && appSidebar && sidebarBackdrop) {
        const closeSidebar = () => {
            document.body.classList.remove('sidebar-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', 'Open navigation menu');
        };
        const openSidebar = () => {
            document.body.classList.add('sidebar-open');
            menuToggle.setAttribute('aria-expanded', 'true');
            menuToggle.setAttribute('aria-label', 'Close navigation menu');
        };
        menuToggle.addEventListener('click', () => {
            document.body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
        });
        sidebarBackdrop.addEventListener('click', closeSidebar);
        appSidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', closeSidebar));
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeSidebar();
        });
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) closeSidebar();
        });
    }


    // =========================================================
    // A1: TOAST NOTIFICATIONS
    // =========================================================

    function getOrCreateToastContainer() {
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            toastContainer.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastContainer);
        }
        return toastContainer;
    }

    function dismissToast(toastElement) {
        if (!toastElement || toastElement.classList.contains('toast-closing')) {
            return;
        }

        toastElement.classList.add('toast-closing');

        let removed = false;
        const removeElement = () => {
            if (!removed && toastElement.parentNode) {
                removed = true;
                toastElement.remove();
            }
        };

        toastElement.addEventListener('animationend', removeElement, { once: true });
        // Fallback in case animationend does not fire (e.g. reduced-motion)
        setTimeout(removeElement, 350);
    }

    function escapeToastHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    window.showToast = function(messageText, type = 'info', title = '') {
        if (!messageText || typeof messageText !== 'string' || messageText.trim() === '') {
            return null;
        }

        const validTypes = ['success', 'error', 'danger', 'warning', 'info', 'welcome'];
        if (!validTypes.includes(type)) {
            type = 'info';
        }
        if (type === 'danger') type = 'error';

        // Check if message signifies a deletion / removal action (Item 7)
        const isDeletion = /\b(deleted|removed|unlinked|cancelled|rejected|cleared)\b/i.test(messageText);
        if (isDeletion && type === 'success') {
            type = 'error';
        }

        const toastContainer = getOrCreateToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'alert');

        let displayTitle = title;
        let iconSvg = '';
        if (type === 'welcome') {
            if (!displayTitle) displayTitle = 'Welcome to SkillConnect! 🎉';
            iconSvg = `
                <svg width="22" height="22" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>`;
        } else if (isDeletion) {
            if (!displayTitle) displayTitle = 'Removed';
            iconSvg = `
                <svg width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>`;
        } else if (type === 'success') {
            if (!displayTitle) displayTitle = 'Success';
            iconSvg = `
                <svg width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>`;
        } else if (type === 'error') {
            if (!displayTitle) displayTitle = 'Notice';
            iconSvg = `
                <svg width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>`;
        } else if (type === 'warning') {
            if (!displayTitle) displayTitle = 'Warning';
            iconSvg = `
                <svg width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>`;
        } else {
            if (!displayTitle) displayTitle = 'Info';
            iconSvg = `
                <svg width="20" height="20" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>`;
        }

        const duration = type === 'welcome' ? 4200 : (type === 'error' ? 4500 : 3700);

        toast.innerHTML = `
            <div class="toast-icon">${iconSvg}</div>
            <div class="toast-content">
                <div class="toast-title">${escapeToastHtml(displayTitle)}</div>
                <div class="toast-message">${escapeToastHtml(messageText)}</div>
            </div>
            <button
                type="button"
                class="toast-close"
                aria-label="Dismiss notification">&times;</button>
            <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
        `;

        toastContainer.appendChild(toast);

        let dismissTimeout = setTimeout(() => {
            if (document.body.contains(toast)) {
                dismissToast(toast);
            }
        }, duration);

        const progress = toast.querySelector('.toast-progress');
        toast.addEventListener('mouseenter', () => {
            clearTimeout(dismissTimeout);
            if (progress) progress.style.animationPlayState = 'paused';
        });

        toast.addEventListener('mouseleave', () => {
            if (progress) progress.style.animationPlayState = 'running';
            dismissTimeout = setTimeout(() => {
                if (document.body.contains(toast)) {
                    dismissToast(toast);
                }
            }, 2000);
        });

        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                clearTimeout(dismissTimeout);
                dismissToast(toast);
            });
        }

        return toast;
    };

    const flashMessages = document.querySelectorAll('.js-toast-target');
    if (flashMessages.length > 0) {
        flashMessages.forEach(msgElement => {
            let type = msgElement.dataset.toastType || 'info';
            if (!msgElement.dataset.toastType) {
                if (msgElement.classList.contains('alert-success')) {
                    type = 'success';
                } else if (msgElement.classList.contains('alert-danger')) {
                    type = 'error';
                } else if (msgElement.classList.contains('alert-warning')) {
                    type = 'warning';
                }
            }

            const customTitle = msgElement.dataset.toastTitle || '';
            const messageText = msgElement.textContent.trim();
            msgElement.style.display = 'none';

            if (messageText) {
                window.showToast(messageText, type, customTitle);
            }
        });
    }


    // =========================================================
    // A2: CONFIRMATION DIALOGS
    // =========================================================

    function showConfirmationModal(
        { title, message, confirmText, confirmClass },
        onConfirm
    ) {
        const backdrop = document.createElement('div');
        backdrop.className = 'confirm-modal-backdrop';

        const iconHtml = confirmClass === 'confirm-danger'
            ? `
                <svg width="28" height="28" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    <line x1="10" y1="11" x2="10" y2="17"></line>
                    <line x1="14" y1="11" x2="14" y2="17"></line>
                </svg>`
            : '';

        backdrop.innerHTML = `
            <div
                class="confirm-modal-content"
                role="dialog"
                aria-labelledby="confirm-modal-title"
                aria-describedby="confirm-modal-desc"
                aria-modal="true">

                <div class="confirm-modal-icon ${confirmClass}">
                    ${iconHtml}
                </div>

                <h3 id="confirm-modal-title" class="confirm-modal-title">
                    ${title}
                </h3>

                <p id="confirm-modal-desc" class="confirm-modal-message">
                    ${message}
                </p>

                <div class="confirm-modal-actions">
                    <button type="button" class="btn confirm-cancel">
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="btn ${confirmClass} confirm-action-btn">
                        ${confirmText}
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(backdrop);

        backdrop.getBoundingClientRect();
        backdrop.classList.add('show');

        const cancelBtn = backdrop.querySelector('.confirm-cancel');
        const confirmBtn = backdrop.querySelector('.confirm-action-btn');

        confirmBtn.focus();

        let modalClosed = false;

        function closeModal() {
            if (modalClosed) return;

            modalClosed = true;
            backdrop.classList.remove('show');

            document.removeEventListener('keydown', escListener);

            backdrop.addEventListener('transitionend', () => {
                backdrop.remove();
            }, { once: true });

            // Fallback if the transition event does not fire.
            setTimeout(() => {
                if (backdrop.isConnected) {
                    backdrop.remove();
                }
            }, 400);
        }

        function escListener(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        }

        document.addEventListener('keydown', escListener);

        cancelBtn.addEventListener('click', closeModal);

        backdrop.addEventListener('click', e => {
            if (e.target === backdrop) {
                closeModal();
            }
        });

        confirmBtn.addEventListener('click', () => {
            closeModal();
            onConfirm();
        });
    }


    // =========================================================
    // A3: BUTTON LOADING STATES
    // =========================================================

    function resetButtonState(btn) {
        if (!btn) return;
        btn.disabled = false;
        btn.classList.remove('is-loading', 'loading');
        btn.removeAttribute('aria-busy');
        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
        }
    }

    function setButtonLoadingState(btn) {
        if (!btn || btn.disabled) {
            return;
        }

        const loadingText = btn.getAttribute('data-loading-text');

        if (!loadingText) {
            return;
        }

        if (!btn.dataset.originalHtml) {
            btn.dataset.originalHtml = btn.innerHTML;
        }

        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');

        btn.innerHTML = `
            <svg
                class="spinner-icon"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                stroke-linejoin="round">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>

            ${loadingText}
        `;

        // Safety fallback: auto-restore button after 8 seconds if no navigation occurred
        setTimeout(() => {
            resetButtonState(btn);
        }, 8000);
    }

    // Restore buttons on browser "Back" navigation or cache restore
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('button.is-loading, button[aria-busy="true"]').forEach(btn => {
            resetButtonState(btn);
        });
    });


    // =========================================================
    // A4: PASSWORD VISIBILITY TOGGLE
    // =========================================================

    const passwordToggles =
        document.querySelectorAll('.js-password-toggle');

    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function () {
            const wrapper = this.closest('.password-wrapper');

            if (!wrapper) return;

            const input = wrapper.querySelector('input');

            if (!input) return;

            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';

            this.setAttribute(
                'aria-label',
                isPassword ? 'Hide password' : 'Show password'
            );

            this.innerHTML = isPassword
                ? `
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `
                : `
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
        });
    });


    // =========================================================
    // A5: LOGIN / SIGNUP PANEL SUPPORT
    // =========================================================

    const stateTrigger = document.querySelector('.state-trigger');

    if (stateTrigger) {
        stateTrigger.addEventListener('change', () => {
            document.dispatchEvent(
                new CustomEvent('auth:panel-change', {
                    detail: {
                        mode: stateTrigger.checked ? 'signup' : 'login'
                    }
                })
            );
        });
    }


    // =========================================================
    // A6: GLOBAL FORM INTERCEPTOR
    // =========================================================

    document.addEventListener('submit', e => {

        // -----------------------------------------------------
        // Remove saved mentor confirmation
        // -----------------------------------------------------

        const removeSavedForm =
            e.target.closest('.js-confirm-remove-saved');

        if (removeSavedForm) {
            e.preventDefault();

            const trainerName =
                removeSavedForm.getAttribute('data-trainer-name') ||
                'this mentor';

            showConfirmationModal({
                title: 'Remove Saved Mentor?',
                message:
                    `Are you sure you want to remove ${trainerName} from your saved mentors?`,
                confirmText: 'Remove',
                confirmClass: 'confirm-danger'
            }, () => {
                const submitBtn =
                    removeSavedForm.querySelector('button[type="submit"]');

                if (submitBtn) {
                    setButtonLoadingState(submitBtn);
                }

                removeSavedForm.submit();
            });

            return;
        }


        // -----------------------------------------------------
        // Remove skill confirmation
        // -----------------------------------------------------

        const removeSkillForm =
            e.target.closest('.js-confirm-remove-skill');

        if (removeSkillForm) {
            e.preventDefault();

            const skillName =
                removeSkillForm.getAttribute('data-skill-name') ||
                'this skill';

            showConfirmationModal({
                title: 'Remove Skill?',
                message:
                    `Are you sure you want to remove "${skillName}" from your profile?`,
                confirmText: 'Remove Skill',
                confirmClass: 'confirm-danger'
            }, () => {
                const submitBtn =
                    removeSkillForm.querySelector('button[type="submit"]');

                if (submitBtn) {
                    setButtonLoadingState(submitBtn);
                }

                removeSkillForm.submit();
            });

            return;
        }


        // -----------------------------------------------------
        // Reject mentorship confirmation
        // -----------------------------------------------------

        const rejectMentorshipForm =
            e.target.closest('.js-confirm-reject-request');

        if (rejectMentorshipForm) {
            e.preventDefault();

            showConfirmationModal({
                title: 'Reject mentorship request?',
                message:
                    'Are you sure you want to reject this mentorship request?',
                confirmText: 'Reject',
                confirmClass: 'confirm-danger'
            }, () => {
                const submitBtn =
                    rejectMentorshipForm.querySelector('button[type="submit"]');

                if (submitBtn) {
                    setButtonLoadingState(submitBtn);
                }

                rejectMentorshipForm.submit();
            });

            return;
        }


        // -----------------------------------------------------
        // Unlink accepted mentorship confirmation
        // -----------------------------------------------------
        const unlinkMentorshipForm =
            e.target.closest('.js-confirm-unlink-mentorship');

        if (unlinkMentorshipForm) {
            e.preventDefault();

            const connectionName =
                unlinkMentorshipForm.getAttribute('data-connection-name') || 'this user';
            const connectionRole =
                unlinkMentorshipForm.getAttribute('data-connection-role') || 'connection';
            const isMentee = (connectionRole === 'mentee' || connectionRole === 'trainee');

            showConfirmationModal({
                title: `Unlink ${isMentee ? 'Mentee' : 'Mentor'}?`,
                message: `Are you sure you want to remove ${connectionName} from your ${isMentee ? 'accepted mentees' : 'mentors'}? This will end the accepted mentorship connection.`,
                confirmText: 'Unlink',
                confirmClass: 'confirm-danger'
            }, () => {
                const submitBtn = unlinkMentorshipForm.querySelector('button[type="submit"]');
                if (submitBtn) setButtonLoadingState(submitBtn);
                unlinkMentorshipForm.submit();
            });

            return;
        }


        // -----------------------------------------------------
        // Standard form loading states
        // -----------------------------------------------------

        if (
            e.submitter &&
            e.submitter.hasAttribute('data-loading-text')
        ) {
            const btn = e.submitter;

            setTimeout(() => {
                setButtonLoadingState(btn);
            }, 0);
        }
    });

    // =========================================================
    // A7: EDIT PROFILE TABS & ACCOUNT RECOVERY MODAL
    // =========================================================

    const tabNavBtns = document.querySelectorAll('.edit-profile-tabs .tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    if (tabNavBtns.length > 0) {
        // Tab switching logic
        function switchTab(targetId) {
            tabNavBtns.forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
                btn.setAttribute('tabindex', '-1');
            });
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            const activeBtn = document.querySelector(`.edit-profile-tabs .tab-btn[data-target="${targetId}"]`);
            const activeContent = document.getElementById(targetId);

            if (activeBtn && activeContent) {
                activeBtn.classList.add('active');
                activeBtn.setAttribute('aria-selected', 'true');
                activeBtn.setAttribute('tabindex', '0');
                activeContent.classList.add('active');
                
                // Update URL hash transparently without page jumping
                history.replaceState(null, null, `#${targetId.replace('tab-', '')}`);
            }
        }

        // Click listeners for navigation tabs
        tabNavBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                switchTab(btn.getAttribute('data-target'));
            });
        });

        // In-page quick tab switch buttons (e.g., Manage Skills overview button)
        document.querySelectorAll('[data-tab-switch]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                switchTab(trigger.getAttribute('data-tab-switch'));
            });
        });

        // Deep-link routing based on hash string or query params
        if (window.location.hash === '#security') {
            switchTab('tab-security');
        } else if (window.location.hash === '#skills' || window.location.search.includes('tab=skills')) {
            switchTab('tab-skills');
        } else if (window.location.hash === '#profile') {
            switchTab('tab-profile');
        }
    }

    // Account recovery setup modal.
    // PHP renders this element only when recovery is unconfigured and the
    // current PHP session has not shown the prompt for this user.
    const recoveryModal = document.getElementById('recovery-setup-modal');

    if (recoveryModal) {
        const modalContent = recoveryModal.querySelector('.recovery-modal-content');
        const closeButtons = recoveryModal.querySelectorAll('#btn-maybe-later, .btn-close-modal');
        const setupNowButton = recoveryModal.querySelector('#btn-setup-now');
        let previouslyFocusedElement = null;
        let modalIsOpen = false;

        const closeRecoveryModal = () => {
            if (!modalIsOpen) return;
            modalIsOpen = false;
            recoveryModal.classList.remove('show');
            recoveryModal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('recovery-modal-open');
            document.removeEventListener('keydown', handleRecoveryModalKeydown);
            if (previouslyFocusedElement && typeof previouslyFocusedElement.focus === 'function') {
                previouslyFocusedElement.focus({ preventScroll: true });
            }
        };

        const handleRecoveryModalKeydown = event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeRecoveryModal();
                return;
            }
            if (event.key === 'Tab' && modalIsOpen && modalContent) {
                const focusable = Array.from(modalContent.querySelectorAll(
                    'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )).filter(element => element.offsetParent !== null);
                if (!focusable.length) {
                    event.preventDefault();
                    modalContent.focus();
                    return;
                }
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
        };

        const openRecoveryModal = () => {
            if (modalIsOpen) return;
            previouslyFocusedElement = document.activeElement;
            modalIsOpen = true;
            recoveryModal.classList.add('show');
            recoveryModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('recovery-modal-open');
            document.addEventListener('keydown', handleRecoveryModalKeydown);
            window.setTimeout(() => {
                const closeButton = recoveryModal.querySelector('.btn-close-modal');
                (closeButton || modalContent || recoveryModal).focus({ preventScroll: true });
            }, 0);
        };

        closeButtons.forEach(button => button.addEventListener('click', closeRecoveryModal));
        recoveryModal.addEventListener('click', event => {
            if (event.target === recoveryModal) closeRecoveryModal();
        });

        if (setupNowButton) {
            setupNowButton.addEventListener('click', () => {
                closeRecoveryModal();
                const securityTab = document.querySelector('.tab-btn[data-target="tab-security"]');
                if (securityTab) {
                    securityTab.click();
                } else {
                    window.location.hash = 'security';
                }
                window.setTimeout(() => {
                    const questionSelect = document.getElementById('security_question');
                    if (questionSelect) {
                        questionSelect.focus({ preventScroll: true });
                        questionSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }, 150);
            });
        }

        // PHP session state is authoritative; no sessionStorage/localStorage gate.
        window.setTimeout(openRecoveryModal, 250);
    }

    // =========================================================
    // A8: ACCEPTED MENTORSHIP ACTION MENU + UNLINK CONFIRMATION
    // =========================================================
    document.querySelectorAll('[data-mentorship-menu]').forEach(menu => {
        const toggle = menu.querySelector('.mentorship-more-toggle');
        const popup = menu.querySelector('.mentorship-more-menu');
        if (!toggle || !popup) return;

        const closeMenu = () => {
            popup.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', event => {
            event.stopPropagation();
            const shouldOpen = popup.hidden;
            document.querySelectorAll('.mentorship-more-menu').forEach(otherPopup => {
                otherPopup.hidden = true;
                const otherToggle = otherPopup.parentElement.querySelector('.mentorship-more-toggle');
                if (otherToggle) otherToggle.setAttribute('aria-expanded', 'false');
            });
            popup.hidden = !shouldOpen;
            toggle.setAttribute('aria-expanded', String(shouldOpen));
        });

        document.addEventListener('click', event => {
            if (!menu.contains(event.target)) closeMenu();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeMenu();
                toggle.focus();
            }
        });
    });

    // =========================================================
    // A9: AUTH REGISTRATION & PASSWORD VALIDATION
    // =========================================================
    (() => {
        const form = document.getElementById('register-form');
        if (!form) return;

        const password = document.getElementById('reg-password');
        const confirmPassword = document.getElementById('reg-confirm-password');
        const submitButton = form.querySelector('button[type="submit"]');

        function clearError(input) {
            if (!input) return;
            input.removeAttribute('aria-invalid');
            const wrapper = input.closest('.half-width') || input.parentElement?.parentElement;
            wrapper?.querySelector('.form-validation-error')?.remove();
        }

        function showError(input, message) {
            clearError(input);
            input.setAttribute('aria-invalid', 'true');
            const error = document.createElement('p');
            error.className = 'form-validation-error';
            error.setAttribute('role', 'alert');
            error.textContent = message;
            const wrapper = input.closest('.half-width') || input.parentElement?.parentElement;
            wrapper?.appendChild(error);
            input.focus();
        }

        function validateRegistration(event) {
            clearError(password);
            clearError(confirmPassword);

            let invalidInput = null;
            let message = '';

            if (!password.value) {
                invalidInput = password;
                message = 'Please enter a password.';
            } else if (password.value.length < 8 || !/[0-9]/.test(password.value) || !/[^A-Za-z0-9]/.test(password.value)) {
                invalidInput = password;
                message = 'Password must be at least 8 characters and include a number and special character.';
            } else if (!confirmPassword.value) {
                invalidInput = confirmPassword;
                message = 'Please confirm your password.';
            } else if (password.value !== confirmPassword.value) {
                invalidInput = confirmPassword;
                message = 'Passwords do not match.';
            }

            if (invalidInput) {
                if (event) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.classList.remove('is-loading', 'loading');
                    submitButton.removeAttribute('aria-busy');
                    submitButton.innerHTML = 'Create Account &rarr;';
                }
                showError(invalidInput, message);
                return false;
            }
            return true;
        }

        if (password) password.addEventListener('input', () => clearError(password));
        if (confirmPassword) confirmPassword.addEventListener('input', () => clearError(confirmPassword));

        if (submitButton) {
            submitButton.addEventListener('click', (event) => {
                if (!validateRegistration(event)) return;
            }, true);
        }

        form.addEventListener('submit', (event) => {
            validateRegistration(event);
        }, true);
    })();

    // =========================================================
    // A10: PROFILE INCOMPLETE MODAL (Consolidated)
    // =========================================================
    (() => {
        const modal = document.getElementById('profileIncompleteModal');
        if (!modal) return;

        function openModal() {
            modal.classList.add('is-active');
        }

        function closeModal() {
            modal.classList.remove('is-active');
        }

        const urlParams = new URLSearchParams(window.location.search);
        const isSkillsTab = urlParams.get('tab') === 'skills' || window.location.hash === '#skills';

        if (!isSkillsTab) {
            setTimeout(openModal, 150);
        }

        const closeBtn = document.getElementById('closeProfileIncompleteBtn');
        const dismissBtn = document.getElementById('dismissProfileIncompleteBtn');
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (dismissBtn) dismissBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('is-active')) {
                closeModal();
            }
        });

        const goToSkillsBtn = document.getElementById('btnGoToSkillsModal');
        if (goToSkillsBtn) {
            goToSkillsBtn.addEventListener('click', function() {
                closeModal();
                const tabSkillsBtn = document.getElementById('tab-skills-button');
                if (tabSkillsBtn) {
                    tabSkillsBtn.click();
                }
                setTimeout(function() {
                    const searchInput = document.getElementById('skillSearchInput');
                    if (searchInput) searchInput.focus();
                }, 120);
            });
        }
    })();

    // =========================================================
    // A11: SKILL SEARCH COMBOBOX (Consolidated)
    // =========================================================
    (() => {
        const combobox = document.getElementById('skillCombobox');
        if (!combobox) return;

        const input = document.getElementById('skillSearchInput');
        const hiddenId = document.getElementById('skillIdInput');
        const dropdown = document.getElementById('skillDropdownList');
        const toggle = document.getElementById('skillComboboxToggle');
        const items = dropdown.querySelectorAll('.skill-option-item');
        const emptyMsg = document.getElementById('skillEmptyMessage');
        const form = combobox.closest('form');

        function openDropdown() {
            dropdown.removeAttribute('hidden');
            combobox.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
        }

        function closeDropdown() {
            dropdown.setAttribute('hidden', '');
            combobox.classList.remove('is-open');
            input.setAttribute('aria-expanded', 'false');
        }

        function filterSkills(query) {
            const q = (query || '').trim().toLowerCase();
            let visibleCount = 0;
            items.forEach(item => {
                const name = (item.dataset.name || '').toLowerCase();
                if (!q || name.includes(q)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            if (emptyMsg) {
                emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
        }

        input.addEventListener('focus', function() {
            openDropdown();
            filterSkills(this.value);
        });

        input.addEventListener('click', function() {
            openDropdown();
        });

        if (toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (dropdown.hasAttribute('hidden')) {
                    openDropdown();
                    input.focus();
                    filterSkills(input.value);
                } else {
                    closeDropdown();
                }
            });
        }

        input.addEventListener('input', function() {
            openDropdown();
            filterSkills(this.value);
            const selectedItem = dropdown.querySelector('.skill-option-item.is-selected');
            if (selectedItem && selectedItem.dataset.name !== this.value) {
                selectedItem.classList.remove('is-selected');
                hiddenId.value = '';
            }
            input.classList.remove('is-invalid');
        });

        items.forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                hiddenId.value = this.dataset.id;
                input.value = this.dataset.name;

                items.forEach(i => i.classList.remove('is-selected'));
                this.classList.add('is-selected');

                input.classList.remove('is-invalid');
                closeDropdown();
            });
        });

        document.addEventListener('click', function(e) {
            if (!combobox.contains(e.target)) {
                closeDropdown();
                const selectedItem = dropdown.querySelector('.skill-option-item.is-selected');
                if (selectedItem) {
                    input.value = selectedItem.dataset.name;
                } else if (!hiddenId.value) {
                    input.value = '';
                    filterSkills('');
                }
            }
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDropdown();
            } else if (e.key === 'Enter') {
                if (!dropdown.hasAttribute('hidden')) {
                    const firstVisible = Array.from(items).find(i => i.style.display !== 'none');
                    if (firstVisible) {
                        e.preventDefault();
                        firstVisible.click();
                    }
                }
            }
        });

        if (form) {
            form.addEventListener('submit', function(e) {
                if (!hiddenId.value || parseInt(hiddenId.value, 10) <= 0) {
                    e.preventDefault();
                    input.classList.add('is-invalid');
                    input.focus();
                    openDropdown();
                    filterSkills('');
                }
            });
        }
    })();

    // =========================================================
    // A12: ONBOARDING MODAL & TOAST HANDLERS (Consolidated)
    // =========================================================
    (() => {
        const backdrop = document.getElementById('sc-onboarding-backdrop');
        if (backdrop) {
            document.body.classList.add('sc-onboarding-active');
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') event.preventDefault();
            });
            backdrop.addEventListener('click', function(event) {
                if (event.target === backdrop) event.preventDefault();
            });
            const firstInput = backdrop.querySelector('input:not([type="hidden"]), select, textarea');
            if (firstInput) {
                firstInput.focus({ preventScroll: true });
            }
        }

        document.querySelectorAll('.sc-security-answer-toggle').forEach(function(button) {
            button.addEventListener('click', function() {
                const input = document.getElementById(button.dataset.target);
                if (!input) return;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-pressed', show ? 'true' : 'false');
                button.setAttribute('aria-label', show ? 'Hide security answer' : (input.id === 'sc-security-confirm' ? 'Show confirmation answer' : 'Show security answer'));
                button.innerHTML = show ?
                    '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16.6 16.6 0 0 1-3.2 4.1"/><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"/></svg>' :
                    '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
            });
        });

        // Auto-dismiss onboarding success toast
        const toast = document.querySelector('.sc-onboarding-toast');
        if (toast) {
            const close = toast.querySelector('.sc-onboarding-toast-close');
            if (close) close.addEventListener('click', () => toast.remove());
            window.setTimeout(() => toast.remove(), 6500);
        }
    })();

    // =========================================================
    // A13: LANDING PAGE SMOOTH SCROLL & NAVIGATION
    // =========================================================
    (() => {
        document.querySelectorAll('.landing-page a[href^="#"]').forEach(function(link) {
            link.addEventListener('click', function(event) {
                const targetId = this.getAttribute('href');
                if (!targetId || targetId === '#') return;
                const target = document.querySelector(targetId);
                if (!target) return;
                event.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                if (history.replaceState) history.replaceState(null, '', targetId);
                const mobileMenu = document.querySelector('.lp-mobile-menu');
                if (mobileMenu) mobileMenu.removeAttribute('open');
            });
        });
    })();

});
