<?php
/**
 * includes/partials/logout_modal.php - Shared logout confirmation modal
 */
?>
<div class="confirm-modal-backdrop logout-confirm-backdrop" id="logout-confirm-modal" aria-hidden="true">
    <section class="confirm-modal-content logout-confirm-content" role="dialog" aria-modal="true" aria-labelledby="logout-confirm-title" aria-describedby="logout-confirm-message" tabindex="-1">
        <div class="confirm-modal-icon confirm-danger logout-confirm-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 17l5-5-5-5"></path>
                <path d="M15 12H3"></path>
                <path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path>
            </svg>
        </div>
        <h2 class="confirm-modal-title" id="logout-confirm-title">Log out of SkillConnect?</h2>
        <p class="confirm-modal-message" id="logout-confirm-message">Are you sure you want to log out? You will need to sign in again to access your account.</p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn confirm-cancel" id="logout-cancel-btn">Stay logged in</button>
            <form id="logout-action-form" method="POST" action="logout.php" style="display:inline; margin:0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn confirm-danger" id="logout-confirm-btn">Log out</button>
            </form>
        </div>
    </section>
</div>
