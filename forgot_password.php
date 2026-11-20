<?php
require_once __DIR__ . '/includes/session.php';

// Explicitly cancel an in-progress recovery flow when the user goes back.
if (isset($_GET['cancel_recovery']) && $_GET['cancel_recovery'] === '1') {
    unset($_SESSION['password_recovery'], $_SESSION['recovery_failed_attempts'], $_SESSION['recovery_error'], $_SESSION['recovery_unavailable']);
    header('Location: forgot_password.php');
    exit;
}

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/recovery_helpers.php';

$error = $_SESSION['recovery_error'] ?? '';
unset($_SESSION['recovery_error']);
$submittedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrfValid($token)) {
        $error = 'Your session expired. Please try again.';
    } elseif (rateLimitExceeded('recovery_start_ip', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 15, 1800)) {
        $error = 'Too many recovery attempts. Please wait before trying again.';
    } else {
        $emailInput = $_POST['email'] ?? '';
        $email = is_string($emailInput) ? trim($emailInput) : '';
        $submittedEmail = $email;
        if ($email === '' || strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT user_id, security_question, security_answer_hash FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user || !isRecoveryConfigured($user)) {
                    $error = "We couldn't find a configured recovery method for this account. Please verify the email address or contact support.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['password_recovery'] = [
                        'user_id' => (int)$user['user_id'],
                        'started_at' => time(),
                        'expires_at' => time() + 900,
                        'verified' => false,
                        'attempts' => 0,
                        'flow_id' => bin2hex(random_bytes(32))
                    ];
                    header('Location: verify_recovery.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Recovery account lookup failed: ' . $e->getMessage());
                $error = 'An unexpected error occurred. Please try again later.';
            }
        }
    }
}
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - SkillConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/recovery.css">
    <link rel="icon" type="image/png" href="/SkillConnect/assets/images/favicon.png">
</head>

<body class="auth-page recovery-auth-page">
    <div class="login-workspace recovery-workspace">
        <main class="recovery-form-panel">
            <a class="recovery-brand-mobile" href="login.php" aria-label="SkillConnect home">
                <img src="assets/images/skillconnect-logo.svg" alt="SkillConnect">
            </a>
            <section class="recovery-form-content">
                <div class="recovery-page-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="m3 7 9 6 9-6" />
                    </svg>
                </div>
                <div class="form-header">
                    <h1>Reset Your Password</h1>
                    <p>Enter the email address associated with your SkillConnect account.</p>
                </div>
                <?php if ($error !== ''): ?>
                    <div class="inline-error-banner" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form action="forgot_password.php" method="post" class="auth-form recovery-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="recovery-email">Email Address</label>
                        <input class="form-input" type="email" id="recovery-email" name="email" value="<?= htmlspecialchars($submittedEmail, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" maxlength="100" placeholder="Enter your registered email" required>
                    </div>
                    <button type="submit" class="submit-btn">Continue <span aria-hidden="true">→</span></button>
                </form>
                <div class="recovery-back-row">
                    <span>or</span>
                    <a href="login.php">Back to Sign In</a>
                </div>
            </section>
        </main>
        <?php require_once __DIR__ . '/includes/partials/recovery_aside.php'; ?>
    </div>
</body>

</html>
