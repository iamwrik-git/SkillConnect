document.addEventListener('DOMContentLoaded', () => {
    // ==========================================================================
    // A1: EXISTING TOAST NOTIFICATIONS
    // ==========================================================================
    const flashMessages = document.querySelectorAll('.js-toast-target');

    if (flashMessages.length > 0) {
        let toastContainer = document.querySelector('.toast-container');

        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            toastContainer.setAttribute('aria-live', 'polite');
            document.body.appendChild(toastContainer);
        }

        flashMessages.forEach(msgElement => {
            let type = 'info';

            if (msgElement.classList.contains('alert-success')) {
                type = 'success';
            } else if (msgElement.classList.contains('alert-danger')) {
                type = 'error';
            } else if (msgElement.classList.contains('alert-warning')) {
                type = 'warning';
            }

            const messageText = msgElement.textContent.trim();
            msgElement.style.display = 'none';

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', 'alert');

            let iconSvg = '';

            if (type === 'success') {
                iconSvg = `
                    <svg width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>`;
            } else if (type === 'error') {
                iconSvg = `
                    <svg width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>`;
            } else if (type === 'warning') {
                iconSvg = `
                    <svg width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>`;
            } else {
                iconSvg = `
                    <svg width="18" height="18" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>`;
            }

            toast.innerHTML = `
                <div class="toast-icon">${iconSvg}</div>

                <div class="toast-content">
                    <div class="toast-message">${messageText}</div>
                </div>

                <button
                    type="button"
                    class="toast-close"
                    aria-label="Close Notification"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            `;

            toastContainer.appendChild(toast);

            const closeBtn = toast.querySelector('.toast-close');

            closeBtn.addEventListener('click', () => {
                dismissToast(toast);
            });

            const duration = type === 'error' ? 6000 : 4000;

            setTimeout(() => {
                if (document.body.contains(toast)) {
                    dismissToast(toast);
                }
            }, duration);
        });

        function dismissToast(toastElement) {
            toastElement.classList.add('toast-closing');

            toastElement.addEventListener('animationend', () => {
                toastElement.remove();
            });
        }
    }


    // ==========================================================================
    // A2: CONFIRMATION DIALOGS
    // ==========================================================================

    function showConfirmationModal(
        { title, message, confirmText, confirmClass },
        onConfirm
    ) {
        const backdrop = document.createElement('div');
        backdrop.className = 'confirm-modal-backdrop';

        const iconHtml =
            confirmClass === 'confirm-danger'
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
                aria-modal="true"
            >
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
                        class="btn ${confirmClass} confirm-action-btn"
                    >
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

        function closeModal() {
            backdrop.classList.remove('show');

            document.removeEventListener('keydown', escListener);

            backdrop.addEventListener('transitionend', () => {
                if (document.body.contains(backdrop)) {
                    backdrop.remove();
                }
            });
        }

        function escListener(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        }

        document.addEventListener('keydown', escListener);

        cancelBtn.addEventListener('click', closeModal);

        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                closeModal();
            }
        });

        confirmBtn.addEventListener('click', () => {
            closeModal();
            onConfirm();
        });
    }


    // ==========================================================================
    // A3: BUTTON LOADING / PROCESSING STATES
    // ==========================================================================

    function setButtonLoadingState(btn) {
        const loadingText = btn.getAttribute('data-loading-text');

        if (!loadingText) {
            return;
        }

        btn.disabled = true;
        btn.classList.add('is-loading');

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
                stroke-linejoin="round"
            >
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>

            ${loadingText}
        `;
    }


    // ==========================================================================
    // A4: PASSWORD VISIBILITY TOGGLE
    // ==========================================================================

    const passwordToggles =
        document.querySelectorAll('.js-password-toggle');

    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function () {

            const wrapper = this.closest('.password-wrapper');

            if (!wrapper) {
                return;
            }

            const input = wrapper.querySelector('input');

            if (!input) {
                return;
            }

            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';

            this.setAttribute(
                'aria-label',
                isPassword ? 'Hide password' : 'Show password'
            );

            if (isPassword) {

                this.innerHTML = `
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                        </path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;

            } else {

                this.innerHTML = `
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z">
                        </path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
            }
        });
    });


    // ==========================================================================
    // GLOBAL FORM INTERCEPTOR
    // ==========================================================================

    document.addEventListener('submit', (e) => {

        // ----------------------------------------------------------------------
        // Remove Skill confirmation
        // ----------------------------------------------------------------------

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
                    removeSkillForm.querySelector(
                        'button[type="submit"]'
                    );

                if (submitBtn) {
                    setButtonLoadingState(submitBtn);
                }

                removeSkillForm.submit();
            });

            return;
        }


        // ----------------------------------------------------------------------
        // Reject Mentorship confirmation
        // ----------------------------------------------------------------------

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
                    rejectMentorshipForm.querySelector(
                        'button[type="submit"]'
                    );

                if (submitBtn) {
                    setButtonLoadingState(submitBtn);
                }

                rejectMentorshipForm.submit();
            });

            return;
        }


        // ----------------------------------------------------------------------
        // Standard form loading states
        // ----------------------------------------------------------------------

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
});