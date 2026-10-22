<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
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
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
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
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset Your Password - SkillConnect</title><link rel="stylesheet" href="assets/css/style.css"></head>
<body class="auth-page recovery-auth-page"><div class="login-workspace recovery-workspace"><main class="recovery-form-panel"><a class="recovery-brand-mobile" href="login.php" aria-label="SkillConnect home"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect"></a><section class="recovery-form-content">
<?php if ($unavailable): ?>
<div class="recovery-page-icon recovery-error-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6m0-6 6 6"/></svg></div><div class="form-header"><h1>Account Recovery Not Available</h1><p>We couldn’t find a configured recovery method for this account. Please contact the administrator for assistance.</p></div><a class="submit-btn recovery-submit-link" href="login.php">Back to Sign In <span aria-hidden="true">→</span></a>
<?php else: ?>
<div class="recovery-page-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></div><div class="form-header"><h1>Reset Your Password</h1><p>Enter the email address associated with your SkillConnect account.</p></div>
<?php if ($error !== ''): ?><div class="inline-error-banner" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form action="forgot_password.php" method="post" class="auth-form recovery-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><div class="form-group"><label for="recovery-email">Email Address</label><input class="form-input" type="email" id="recovery-email" name="email" autocomplete="email" maxlength="100" placeholder="Enter your registered email" required></div><button type="submit" class="submit-btn">Continue <span aria-hidden="true">→</span></button></form><div class="recovery-back-row"><span>or</span><a href="login.php">Back to Sign In</a></div>
<?php endif; ?></section></main><aside class="login-illustration-panel recovery-illustration-panel" aria-label="SkillConnect learning community"><img src="assets/images/skillconnect-auth-login.jpg" alt="Learner using a laptop to explore programming and technology" class="auth-illustration-img"><div class="auth-illustration-overlay"></div><div class="illustration-brand"><img src="assets/images/skillconnect-logo.svg" alt="SkillConnect" class="auth-brand-logo"></div><div class="panel-typography"><h3>Learn from Experts. Build Real Skills.</h3><p>Connect with skilled trainers, gain hands-on knowledge and grow with a supportive community.</p></div></aside></div></body></html>
