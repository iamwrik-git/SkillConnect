<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/includes/db_connect.php';
function vr_csrf(): string { if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function vr_questions(): array { return ['school' => 'What was the name of your first school?', 'pet' => 'What was the name of your first pet?', 'nickname' => 'What was your childhood nickname?', 'city' => 'In what city were you born?']; }
function vr_configured(array $u): bool { return !empty($u['security_question']) && array_key_exists((string)$u['security_question'], vr_questions()) && !empty($u['security_answer_hash']) && !empty(password_get_info((string)$u['security_answer_hash'])['algo']); }
function vr_active(): bool { $f = $_SESSION['password_recovery'] ?? null; if (!is_array($f) || empty($f['user_id']) || empty($f['expires_at']) || !is_int($f['expires_at']) || $f['expires_at'] < time()) { unset($_SESSION['password_recovery']); return false; } return true; }
if (!vr_active()) { header('Location: forgot_password.php'); exit; }
$flow = &$_SESSION['password_recovery'];
if (!empty($flow['verified'])) { header('Location: reset_password.php'); exit; }
$error = $_SESSION['recovery_error'] ?? ''; unset($_SESSION['recovery_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['password_recovery']); $_SESSION['recovery_error'] = 'Your session token expired. Please start again.'; header('Location: forgot_password.php'); exit;
    }
    $answer = trim((string)($_POST['answer'] ?? ''));
    if ($answer === '' || strlen($answer) > 255) $error = 'Enter your security answer.';
    else {
        $_SESSION['recovery_failed_attempts'] = (int)($_SESSION['recovery_failed_attempts'] ?? 0);
        if ((int)($flow['attempts'] ?? 0) >= 5 || $_SESSION['recovery_failed_attempts'] >= 8) {
            unset($_SESSION['password_recovery']); $_SESSION['recovery_error'] = 'Too many incorrect attempts. Start the recovery process again.'; header('Location: forgot_password.php'); exit;
        }
        try {
            $stmt = $pdo->prepare('SELECT security_question, security_answer_hash FROM users WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int)$flow['user_id']]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !vr_configured($user)) { unset($_SESSION['password_recovery']); $_SESSION['recovery_unavailable'] = true; header('Location: forgot_password.php'); exit; }
            if (password_verify(strtolower(trim($answer)), $user['security_answer_hash'])) {
                $flow['verified'] = true; $flow['verified_at'] = time(); unset($_SESSION['recovery_failed_attempts']); session_regenerate_id(true); header('Location: reset_password.php'); exit;
            }
            $flow['attempts'] = (int)($flow['attempts'] ?? 0) + 1; $_SESSION['recovery_failed_attempts']++;
            if ($flow['attempts'] >= 5 || $_SESSION['recovery_failed_attempts'] >= 8) { unset($_SESSION['password_recovery']); $_SESSION['recovery_error'] = 'Too many incorrect attempts. Start the recovery process again.'; header('Location: forgot_password.php'); exit; }
            $error = 'That answer does not match. Please try again.';
        } catch (PDOException $e) { error_log('Recovery answer lookup failed: ' . $e->getMessage()); $error = 'An unexpected error occurred. Please try again later.'; }
    }
}
try { $stmt = $pdo->prepare('SELECT security_question, security_answer_hash FROM users WHERE user_id = ? LIMIT 1'); $stmt->execute([(int)$flow['user_id']]); $user = $stmt->fetch(PDO::FETCH_ASSOC); }
catch (PDOException $e) { error_log('Recovery question lookup failed: ' . $e->getMessage()); http_response_code(500); exit('An unexpected error occurred. Please try again later.'); }
if (!$user || !vr_configured($user)) { unset($_SESSION['password_recovery']); $_SESSION['recovery_unavailable'] = true; header('Location: forgot_password.php'); exit; }
$questionText = vr_questions()[$user['security_question']]; $csrf = vr_csrf();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Verify Your Identity - SkillConnect</title><link rel="stylesheet" href="assets/css/style.css">
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

</head><body class="auth-page recovery-auth-page"><div class="login-workspace recovery-workspace"><main class="recovery-form-panel"><a class="recovery-brand-mobile" href="login.php"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect"></a><section class="recovery-form-content"><a class="recovery-top-back" href="forgot_password.php">← Back</a><div class="recovery-page-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg></div><div class="form-header"><h1>Verify Your Identity</h1><p>Answer your security question to continue.</p></div>
<?php if ($error !== ''): ?><div class="inline-error-banner" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form action="verify_recovery.php" method="post" class="auth-form recovery-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label for="security-question-display">Security Question</label><div class="recovery-question-display" id="security-question-display"><?= htmlspecialchars($questionText, ENT_QUOTES, 'UTF-8') ?></div></div><div class="form-group"><label for="recovery-answer">Your Answer</label><div class="password-wrapper"><input class="form-input" type="password" id="recovery-answer" name="answer" autocomplete="off" maxlength="255" placeholder="Enter your answer" required><button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show answer"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div><button type="submit" class="submit-btn">Verify Answer <span aria-hidden="true">→</span></button></form><div class="recovery-back-row"><a href="login.php">Back to Sign In</a></div></section></main><aside class="login-illustration-panel recovery-illustration-panel"><img src="assets/images/skillconnect-auth-login.jpg" alt="Learner using a laptop to explore technology" class="auth-illustration-img"><div class="auth-illustration-overlay"></div><div class="illustration-brand"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect" class="auth-brand-logo"></div><div class="panel-typography"><h3>Learn from Experts. Build Real Skills.</h3><p>Connect with skilled trainers, gain hands-on knowledge and grow with a supportive community.</p></div></aside></div><script src="assets/js/main.js"></script></body></html>
