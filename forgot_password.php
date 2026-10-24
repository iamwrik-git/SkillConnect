<?php
require_once __DIR__ . '/includes/session.php';

// Explicitly cancel an in-progress recovery flow when the user goes back.
if (isset($_GET['cancel_recovery']) && $_GET['cancel_recovery'] === '1') {
    unset($_SESSION['password_recovery'], $_SESSION['recovery_failed_attempts'], $_SESSION['recovery_error'], $_SESSION['recovery_unavailable']);
    header('Location: forgot_password.php');
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';

function fp_csrf(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function fp_questions(): array {
    return ['school' => 'What was the name of your first school?', 'pet' => 'What was the name of your first pet?', 'nickname' => 'What was your childhood nickname?', 'city' => 'In what city were you born?'];
}
function fp_configured(array $user): bool {
    if (empty($user['security_question']) || !array_key_exists((string)$user['security_question'], fp_questions()) || empty($user['security_answer_hash'])) return false;
    return !empty(password_get_info((string)$user['security_answer_hash'])['algo']);
}
$error = $_SESSION['recovery_error'] ?? '';
unset($_SESSION['recovery_error']);
$unavailable = !empty($_SESSION['recovery_unavailable']);
unset($_SESSION['recovery_unavailable']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !is_string($_SESSION['csrf_token'] ?? null) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Your session expired. Please try again.';
    } elseif (rateLimitExceeded('recovery_start_ip', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 15, 1800)) {
        $error = 'Too many recovery attempts. Please wait before trying again.';
    } else {
        $emailInput = $_POST['email'] ?? '';
        $email = is_string($emailInput) ? trim($emailInput) : '';
        if ($email === '' || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT user_id, security_question, security_answer_hash FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user || !fp_configured($user)) {
                    $_SESSION['recovery_unavailable'] = true;
                    header('Location: forgot_password.php'); exit;
                }
                session_regenerate_id(true);
                $_SESSION['password_recovery'] = [
                    'user_id' => (int)$user['user_id'], 'started_at' => time(),
                    'expires_at' => time() + 900, 'verified' => false, 'attempts' => 0,
                    'flow_id' => bin2hex(random_bytes(32))
                ];
                header('Location: verify_recovery.php'); exit;
            } catch (PDOException $e) {
                error_log('Recovery account lookup failed: ' . $e->getMessage());
                $error = 'An unexpected error occurred. Please try again later.';
            }
        }
    }
}
$csrf = fp_csrf();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset Your Password - SkillConnect</title><link rel="stylesheet" href="assets/css/style.css">
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

</head>
<body class="auth-page recovery-auth-page"><div class="login-workspace recovery-workspace"><main class="recovery-form-panel"><a class="recovery-brand-mobile" href="login.php" aria-label="SkillConnect home"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect"></a><section class="recovery-form-content">
<?php if ($unavailable): ?>
<div class="recovery-page-icon recovery-error-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6m0-6 6 6"/></svg></div><div class="form-header"><h1>Account Recovery Not Available</h1><p>We couldn’t find a configured recovery method for this account. Please contact the administrator for assistance.</p></div><a class="submit-btn recovery-submit-link" href="login.php">Back to Sign In <span aria-hidden="true">→</span></a>
<?php else: ?>
<div class="recovery-page-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div><div class="form-header"><h1>Reset Your Password</h1><p>Enter the email address associated with your SkillConnect account.</p></div>
<?php if ($error !== ''): ?><div class="inline-error-banner" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form action="forgot_password.php" method="post" class="auth-form recovery-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label for="recovery-email">Email Address</label><input class="form-input" type="email" id="recovery-email" name="email" autocomplete="email" maxlength="100" placeholder="Enter your registered email" required></div><button type="submit" class="submit-btn">Continue <span aria-hidden="true">→</span></button></form><div class="recovery-back-row"><span>or</span><a href="login.php">Back to Sign In</a></div>
<?php endif; ?></section></main><aside class="login-illustration-panel recovery-illustration-panel" aria-label="SkillConnect learning community"><img src="assets/images/skillconnect-auth-login.jpg" alt="Learner using a laptop to explore programming and technology" class="auth-illustration-img"><div class="auth-illustration-overlay"></div><div class="illustration-brand"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect" class="auth-brand-logo"></div><div class="panel-typography"><h3>Learn from Experts. Build Real Skills.</h3><p>Connect with skilled trainers, gain hands-on knowledge and grow with a supportive community.</p></div></aside></div></body></html>
