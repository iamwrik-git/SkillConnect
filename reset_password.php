<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/includes/db_connect.php';
function rp_csrf(): string { if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function rp_questions(): array { return ['school' => 'What was the name of your first school?', 'pet' => 'What was the name of your first pet?', 'nickname' => 'What was your childhood nickname?', 'city' => 'In what city were you born?']; }
function rp_configured(array $u): bool { return !empty($u['security_question']) && array_key_exists((string)$u['security_question'], rp_questions()) && !empty($u['security_answer_hash']) && !empty(password_get_info((string)$u['security_answer_hash'])['algo']); }
function rp_active(): bool { $f = $_SESSION['password_recovery'] ?? null; if (!is_array($f) || empty($f['user_id']) || empty($f['expires_at']) || !is_int($f['expires_at']) || $f['expires_at'] < time() || empty($f['verified']) || empty($f['verified_at']) || $f['verified_at'] + 900 < time()) { unset($_SESSION['password_recovery']); return false; } return true; }
$success = !empty($_SESSION['recovery_reset_success']); if ($success) unset($_SESSION['recovery_reset_success']);
$error = $_SESSION['recovery_error'] ?? ''; unset($_SESSION['recovery_error']);
if (!$success && !rp_active()) { header('Location: forgot_password.php'); exit; }
if (!$success && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['password_recovery']); $_SESSION['recovery_error'] = 'Your session token expired. Please start again.'; header('Location: forgot_password.php'); exit;
    }
    $newPassword = (string)($_POST['new_password'] ?? ''); $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    if ($newPassword === '' || $confirmPassword === '') $error = 'Both password fields are required.';
    elseif (!hash_equals($newPassword, $confirmPassword)) $error = 'Passwords do not match.';
    elseif (strlen($newPassword) < 8 || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) $error = 'Password must be at least 8 characters and include a number and special character.';
    else {
        try {
            $flow = $_SESSION['password_recovery'];
            $stmt = $pdo->prepare('SELECT user_id, security_question, security_answer_hash FROM users WHERE user_id = ? LIMIT 1'); $stmt->execute([(int)$flow['user_id']]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || empty($user['security_question']) || !array_key_exists((string)$user['security_question'], rp_questions()) || empty($user['security_answer_hash']) || empty(password_get_info((string)$user['security_answer_hash'])['algo'])) {
                unset($_SESSION['password_recovery']); $_SESSION['recovery_unavailable'] = true; header('Location: forgot_password.php'); exit;
            }
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?'); $update->execute([$hash, (int)$flow['user_id']]);
            if ($update->rowCount() !== 1) { $check = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?'); $check->execute([(int)$flow['user_id']]); if (!password_verify($newPassword, (string)$check->fetchColumn())) throw new RuntimeException('Password update was not confirmed.'); }
            unset($_SESSION['password_recovery'], $_SESSION['recovery_failed_attempts'], $_SESSION['user_id'], $_SESSION['name'], $_SESSION['role'], $_SESSION['is_admin']);
            session_regenerate_id(true); $_SESSION['recovery_reset_success'] = true; header('Location: reset_password.php'); exit;
        } catch (Throwable $e) { error_log('Password recovery reset failed: ' . $e->getMessage()); $error = 'The password could not be updated. Please try again.'; }
    }
}
$csrf = $success ? '' : rp_csrf();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= $success ? 'Password Reset Successful' : 'Set a New Password' ?> - SkillConnect</title><link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" type="image/png" href="/SkillConnect/assets/images/favicon.png">
<style>
/* Password-recovery mobile layout repair. Scoped to these recovery pages. */
@media (max-width: 768px) {
    /* Reset inherited desktop sizing and flex/grid rules before stacking. */
    .recovery-auth-page,
    .recovery-auth-page *,
    .recovery-auth-page *::before,
    .recovery-auth-page *::after {
        box-sizing: border-box;
    }
    .recovery-auth-page .login-workspace.recovery-workspace {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        justify-content: flex-start !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        grid-template-columns: minmax(0, 1fr) !important;
        gap: 0 !important;
    }
    html, body.auth-page.recovery-auth-page {
        width: 100%;
        min-width: 0;
        min-height: 100%;
        overflow-x: clip;
        background: #fff;
    }
    body.auth-page.recovery-auth-page {
        display: block;
        min-height: 100vh;
        min-height: 100dvh;
        padding: 0;
    }
    .recovery-auth-page .recovery-workspace {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        height: auto;
        min-height: 100vh;
        min-height: 100dvh;
        margin: 0;
        overflow: visible;
        border-radius: 0;
        box-shadow: none;
        background: #fff;
    }
    .recovery-auth-page .recovery-form-panel {
        display: flex !important;
        flex-direction: column !important;
        flex: 0 0 auto !important;
        align-items: stretch !important;
        position: relative;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        height: auto;
        min-height: 100vh;
        min-height: 100dvh;
        padding: 16px clamp(20px, 5.5vw, 36px) 24px !important;
        overflow: visible;
        align-items: stretch;
        justify-content: flex-start;
        transform: none;
    }
    .recovery-auth-page .recovery-form-content {
        display: block !important;
        flex: 0 0 auto !important;
        width: 100% !important;
        max-width: 460px !important;
        min-width: 0 !important;
        margin: 0 auto;
        align-self: stretch;
    }
    .recovery-auth-page .recovery-brand-mobile {
        display: flex;
        flex: 0 0 auto;
        justify-content: center;
        align-items: center;
        width: 100%;
        margin: 0 auto 32px !important;
    }
    .recovery-auth-page .recovery-brand-mobile img {
        display: block;
        width: 176px;
        max-width: 72vw;
        max-height: 48px;
        height: auto;
        object-fit: contain;
    }
    .recovery-auth-page .recovery-illustration-panel {
        display: none !important;
    }
    .recovery-auth-page .recovery-top-back {
        display: inline-flex;
        max-width: 100%;
        margin: 0 0 22px;
        font-size: 15px;
        line-height: 1.4;
    }
    .recovery-auth-page .recovery-page-icon {
        width: 52px;
        height: 52px;
        margin: 0 0 16px;
        flex: 0 0 auto;
    }
    .recovery-auth-page .recovery-page-icon svg {
        width: 27px;
        height: 27px;
    }
    .recovery-auth-page .recovery-form-content .form-header {
        margin: 0 0 20px;
        text-align: left;
    }
    .recovery-auth-page .recovery-form-content .form-header h1 {
        display: block;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        font-size: clamp(25px, 5.5vw, 32px);
        line-height: 1.16;
        letter-spacing: -0.035em;
        word-break: normal;
        overflow-wrap: normal;
    }
    .recovery-auth-page .recovery-form-content .form-header p {
        font-size: 16px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }
    .recovery-auth-page .recovery-form,
    .recovery-auth-page .recovery-form .form-group,
    .recovery-auth-page .recovery-form .password-wrapper {
        width: 100%;
        min-width: 0;
    }
    .recovery-auth-page .recovery-form .form-group {
        margin-bottom: 14px;
    }
    .recovery-auth-page .recovery-form .form-group > label {
        font-size: 12px;
        line-height: 1.4;
        margin-bottom: 7px;
    }
    .recovery-auth-page .recovery-form .form-input {
        display: block;
        width: 100%;
        min-width: 0;
        min-height: 54px;
        padding: 14px 15px;
        font-size: 16px;
        border-radius: 12px;
    }
    .recovery-auth-page .recovery-form .password-wrapper .form-input {
        padding-right: 52px;
    }
    .recovery-auth-page .password-toggle-btn {
        right: 8px;
        width: 36px;
        height: 36px;
    }
    .recovery-auth-page .password-toggle-btn svg {
        width: 22px;
        height: 22px;
    }
    .recovery-auth-page .recovery-question-display {
        width: 100%;
        min-width: 0;
        padding: 14px 15px;
        font-size: 15px;
        line-height: 1.5;
        overflow-wrap: anywhere;
    }
    .recovery-auth-page .recovery-password-requirements {
        font-size: 13px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }
    .recovery-auth-page .recovery-form .submit-btn,
    .recovery-auth-page .recovery-submit-link {
        width: 100%;
        min-width: 0;
        min-height: 56px;
        padding: 14px 16px;
        margin-top: 2px;
        gap: 8px;
        font-size: 16px;
        line-height: 1.35;
        white-space: normal;
        overflow-wrap: anywhere;
        text-align: center;
    }
    .recovery-auth-page .recovery-back-row {
        display: flex;
        width: 100%;
        min-width: 0;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        margin-top: 16px;
        font-size: 15px;
        line-height: 1.5;
        text-align: center;
    }
    .recovery-auth-page .recovery-back-row a {
        overflow-wrap: anywhere;
    }
    .recovery-auth-page .inline-error-banner {
        width: 100%;
        min-width: 0;
        overflow-wrap: anywhere;
        font-size: 14px;
        line-height: 1.5;
    }
    .recovery-auth-page .recovery-form-content:has(.recovery-success-icon),
    .recovery-auth-page .recovery-form-content:has(.recovery-error-icon) {
        text-align: center;
        margin-top: auto;
        margin-bottom: auto;
    }
    .recovery-auth-page .recovery-form-content:has(.recovery-success-icon) .form-header,
    .recovery-auth-page .recovery-form-content:has(.recovery-error-icon) .form-header {
        text-align: center;
    }
}
@media (max-width: 768px) and (max-height: 760px) {
    .recovery-auth-page .recovery-form-panel {
        padding-top: 12px !important;
        padding-bottom: 16px !important;
    }
    .recovery-auth-page .recovery-brand-mobile {
        margin-bottom: 22px !important;
    }
    .recovery-auth-page .recovery-brand-mobile img {
        width: 160px;
        max-height: 42px;
    }
    .recovery-auth-page .recovery-page-icon {
        width: 46px;
        height: 46px;
        margin-bottom: 12px;
    }
    .recovery-auth-page .recovery-page-icon svg {
        width: 23px;
        height: 23px;
    }
    .recovery-auth-page .recovery-form-content .form-header {
        margin-bottom: 16px;
    }
    .recovery-auth-page .recovery-form-content .form-header h1 {
        font-size: clamp(24px, 5.2vw, 29px);
    }
    .recovery-auth-page .recovery-form-content .form-header p {
        font-size: 15px;
        line-height: 1.45;
    }
    .recovery-auth-page .recovery-form .form-group {
        margin-bottom: 12px;
    }
    .recovery-auth-page .recovery-form .form-input {
        min-height: 48px;
        padding-top: 11px;
        padding-bottom: 11px;
    }
    .recovery-auth-page .recovery-form .submit-btn,
    .recovery-auth-page .recovery-submit-link {
        min-height: 50px;
        padding-top: 11px;
        padding-bottom: 11px;
    }
    .recovery-auth-page .recovery-back-row {
        margin-top: 12px;
    }
}
@media (max-width: 380px) {
    .recovery-auth-page .recovery-form-panel {
        padding-right: 18px;
        padding-left: 18px;
    }
    .recovery-auth-page .recovery-brand-mobile {
        margin-bottom: 22px !important;
    }
    .recovery-auth-page .recovery-form-content .form-header h1 {
        font-size: 26px;
    }
}
</style>

</head><body class="auth-page recovery-auth-page"><div class="login-workspace recovery-workspace"><main class="recovery-form-panel"><a class="recovery-brand-mobile" href="login.php"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect"></a><section class="recovery-form-content">
<?php if ($success): ?><div class="recovery-page-icon recovery-success-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m5 12 4 4L19 6"/></svg></div><div class="form-header"><h1>Password Reset Successful!</h1><p>Your password has been updated successfully. You can now sign in with your new password.</p></div><a class="submit-btn recovery-submit-link" href="login.php">Back to Sign In <span aria-hidden="true">→</span></a>
<?php else: ?><a class="recovery-top-back" href="forgot_password.php?cancel_recovery=1">← Back</a><div class="recovery-page-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></div><div class="form-header"><h1>Set a New Password</h1><p>Create a new password for your account.</p></div>
<?php if ($error !== ''): ?><div class="inline-error-banner" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form action="reset_password.php" method="post" class="auth-form recovery-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label for="new-password">New Password</label><div class="password-wrapper"><input class="form-input" type="password" id="new-password" name="new_password" autocomplete="new-password" required><button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div><div class="recovery-password-requirements">Password requirements: at least 8 characters, one number, and one special character.</div></div><div class="form-group"><label for="confirm-password">Confirm Password</label><div class="password-wrapper"><input class="form-input" type="password" id="confirm-password" name="confirm_password" autocomplete="new-password" required><button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div><button type="submit" class="submit-btn">Reset Password <span aria-hidden="true">→</span></button></form><?php endif; ?></section></main><aside class="login-illustration-panel recovery-illustration-panel"><img src="assets/images/skillconnect-auth-login.jpg" alt="Learner using a laptop to explore technology" class="auth-illustration-img"><div class="auth-illustration-overlay"></div><div class="illustration-brand"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect" class="auth-brand-logo"></div><div class="panel-typography"><h3>Learn from Experts. Build Real Skills.</h3><p>Connect with skilled trainers, gain hands-on knowledge and grow with a supportive community.</p></div></aside></div><script src="assets/js/main.js"></script></body></html>
