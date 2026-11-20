<?php
/**
 * includes/partials/message_modal.php - In-page direct message modal for connections
 */
?>
<div class="sc-message-modal-backdrop" id="sc-message-backdrop" hidden>
    <div class="sc-message-dialog" role="dialog" aria-modal="true" aria-labelledby="sc-message-dialog-title">
        <div class="sc-message-header">
            <div>
                <h3 id="sc-message-dialog-title" class="sc-message-title">Message <span id="sc-message-recipient-name">Mentor</span></h3>
                <p class="sc-message-subtitle" id="sc-message-topic">Mentorship conversation</p>
            </div>
            <button type="button" class="sc-message-close-btn" id="sc-message-close-btn" aria-label="Close message dialog">&times;</button>
        </div>
        <form action="actions/message_process.php" method="POST" id="sc-message-form" class="sc-message-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="recipient_id" id="sc-message-recipient-id" value="">
            
            <div class="sc-message-field">
                <label for="sc-message-text" class="sc-message-label">Your Message</label>
                <textarea id="sc-message-text" name="message" class="sc-message-textarea" rows="4" maxlength="1000" required placeholder="Type your message here..."></textarea>
                <div class="sc-message-char-count"><span id="sc-message-char-counter">0</span> / 1000</div>
            </div>

            <div class="sc-message-actions">
                <button type="button" class="btn btn-secondary" id="sc-message-cancel-btn">Cancel</button>
                <button type="submit" class="btn btn-primary" id="sc-message-submit-btn">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    <span>Send Message</span>
                </button>
            </div>
        </form>
    </div>
</div>
