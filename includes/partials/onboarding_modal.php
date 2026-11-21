<?php
/**
 * includes/partials/onboarding_modal.php - Account Security Onboarding Modal
 */
require_once __DIR__ . '/../recovery_helpers.php';
?>
<div class="sc-onboarding-backdrop" id="sc-onboarding-backdrop">
    <section class="sc-onboarding-modal sc-onboarding-security-modal" role="dialog" aria-modal="true" aria-labelledby="sc-onboarding-title" aria-describedby="sc-onboarding-description">
        <aside class="sc-onboarding-aside sc-onboarding-aside-security">
            <img class="sc-onboarding-logo" src="assets/images/skillconnect-logo.svg" alt="SkillConnect">
            <span class="sc-onboarding-step">ACCOUNT SECURITY</span>
            <div class="sc-security-illustration" aria-hidden="true">
                <div class="sc-security-halo"></div>
                <svg viewBox="0 0 100 112" fill="none">
                    <path d="M50 5 86 19v29c0 25-15 43-36 57C29 91 14 73 14 48V19L50 5Z" fill="url(#scShieldGradient)" />
                    <path d="M50 5 86 19v29c0 25-15 43-36 57V5Z" fill="#2563eb" opacity=".38" />
                    <rect x="35" y="43" width="30" height="25" rx="5" fill="white" />
                    <path d="M41 43v-7a9 9 0 0 1 18 0v7" stroke="white" stroke-width="5" stroke-linecap="round" />
                    <circle cx="50" cy="54" r="3" fill="#2563eb" />
                    <path d="M50 57v5" stroke="#2563eb" stroke-width="3" stroke-linecap="round" />
                    <defs>
                        <linearGradient id="scShieldGradient" x1="14" y1="5" x2="86" y2="105" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#60a5fa" />
                            <stop offset="1" stop-color="#1d4ed8" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <h2>Keep Your Account Safe</h2>
            <p id="sc-onboarding-description">Set up your security question to help recover your account if you ever forget your password.</p>
            <ul class="sc-security-benefits">
                <li><span>✓</span> Quick and easy setup</li>
                <li><span>✓</span> Helps keep your account secure</li>
                <li><span>✓</span> Used for password recovery</li>
            </ul>
        </aside>
        <div class="sc-onboarding-form-panel">
            <div class="sc-onboarding-form-heading">
                <span class="sc-onboarding-step-mobile">ACCOUNT SECURITY</span>
                <h2 id="sc-onboarding-title">Set Up Security Questions</h2>
                <p>Choose a security question and provide your answer.</p>
            </div>
            <?php if (!empty($onboarding_error)): ?>
                <div class="sc-onboarding-error" role="alert"><?= htmlspecialchars($onboarding_error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form action="actions/onboarding_process.php" method="post" class="sc-onboarding-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($onboarding_csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="save_security">
                <div class="sc-onboarding-field">
                    <label for="sc-security-question">Security Question <span>*</span></label>
                    <select id="sc-security-question" name="security_question" required>
                        <option value="">Select a security question</option>
                        <?php foreach (getSecurityQuestions() as $qKey => $qLabel): ?>
                            <option value="<?= htmlspecialchars($qKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($qLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sc-onboarding-field">
                    <label for="sc-security-answer">Your Answer <span>*</span></label>
                    <div style="position:relative;width:100%;">
                        <input id="sc-security-answer" type="password" name="security_answer" maxlength="255" autocomplete="off" required placeholder="Enter your answer" style="padding-right:48px;">
                        <button type="button" class="sc-security-answer-toggle" data-target="sc-security-answer" aria-label="Show security answer" aria-pressed="false" style="position:absolute;right:7px;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:34px;height:34px;padding:0;border:0;border-radius:7px;background:transparent;color:#64748b;cursor:pointer;">
                            <svg class="sc-eye-icon" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="sc-onboarding-field">
                    <label for="sc-security-confirm">Confirm Answer <span>*</span></label>
                    <div style="position:relative;width:100%;">
                        <input id="sc-security-confirm" type="password" name="confirm_security_answer" maxlength="255" autocomplete="off" required placeholder="Re-enter your answer" style="padding-right:48px;">
                        <button type="button" class="sc-security-answer-toggle" data-target="sc-security-confirm" aria-label="Show confirmation answer" aria-pressed="false" style="position:absolute;right:7px;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:34px;height:34px;padding:0;border:0;border-radius:7px;background:transparent;color:#64748b;cursor:pointer;">
                            <svg class="sc-eye-icon" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="sc-onboarding-submit">Save &amp; Finish <span aria-hidden="true">→</span></button>
                <p class="sc-onboarding-required-note"><span>*</span> Required fields</p>

                <!-- Fallback: Cancel onboarding and restart registration -->
                <div class="sc-onboarding-cancel-wrapper">
                    <a href="actions/onboarding_process.php?action=cancel_onboarding&csrf=<?= urlencode($onboarding_csrf) ?>"
                       class="sc-onboarding-cancel-link"
                       onclick="return confirm('Cancel this account setup and start creating your account again?');">
                        &larr; Cancel &amp; Create Account Again
                    </a>
                </div>
            </form>
        </div>
    </section>
</div>
