<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/recovery_helpers.php';

$success = !empty($_SESSION['recovery_reset_success']);
if ($success) {
    unset($_SESSION['recovery_reset_success']);
}

$error = $_SESSION['recovery_error'] ?? '';
unset($_SESSION['recovery_error']);

if (!$success && !isRecoveryFlowActive(true)) {
    header('Location: forgot_password.php');
    exit;
}

if (!$success && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrfValid($token)) {
        unset($_SESSION['password_recovery']);
        $_SESSION['recovery_error'] = 'Your session token expired. Please start again.';
        header('Location: forgot_password.php');
        exit;
    }

    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Both password fields are required.';
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8 || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        $error = 'Password must be at least 8 characters and include a number and special character.';
    } else {
        try {
            $flow = $_SESSION['password_recovery'];
            $stmt = $pdo->prepare('SELECT user_id, security_question, security_answer_hash FROM users WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int)$flow['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !isRecoveryConfigured($user)) {
                unset($_SESSION['password_recovery']);
                $_SESSION['recovery_unavailable'] = true;
                header('Location: forgot_password.php');
                exit;
            }

            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $update->execute([$hash, (int)$flow['user_id']]);

            if ($update->rowCount() !== 1) {
                $check = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
                $check->execute([(int)$flow['user_id']]);
                if (!password_verify($newPassword, (string)$check->fetchColumn())) {
                    throw new RuntimeException('Password update was not confirmed.');
                }
            }

            unset(
                $_SESSION['password_recovery'],
                $_SESSION['recovery_failed_attempts'],
                $_SESSION['user_id'],
                $_SESSION['name'],
                $_SESSION['role'],
                $_SESSION['is_admin']
            );
            session_regenerate_id(true);
            $_SESSION['recovery_reset_success'] = true;
            header('Location: reset_password.php');
            exit;
        } catch (Throwable $e) {
            error_log('Password recovery reset failed: ' . $e->getMessage());
            $error = 'The password could not be updated. Please try again.';
        }
    }
}

$csrf = $success ? '' : csrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? 'Password Reset Successful' : 'Set a New Password' ?> - SkillConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/recovery.css">
    <link rel="icon" type="image/png" href="/SkillConnect/assets/images/favicon.png">
</head>

<body class="auth-page recovery-auth-page">
    <div class="login-workspace recovery-workspace">
        <main class="recovery-form-panel">
            <a class="recovery-brand-mobile" href="login.php">
                <img src="assets/images/skillconnect-logo.svg" alt="SkillConnect">
            </a>
            <section class="recovery-form-content">
                <?php if ($success): ?>
                    <div class="recovery-page-icon recovery-success-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="m5 12 4 4L19 6" />
                        </svg>
                    </div>
                    <div class="form-header">
                        <h1>Password Reset Successful!</h1>
                        <p>Your password has been updated successfully. You can now sign in with your new password.</p>
                    </div>
                    <a class="submit-btn recovery-submit-link" href="login.php">Back to Sign In <span aria-hidden="true">→</span></a>
                <?php else: ?>
                    <a class="recovery-top-back" href="forgot_password.php?cancel_recovery=1">← Back</a>
                    <div class="recovery-page-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="4" y="10" width="16" height="11" rx="2" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                    </div>
                    <div class="form-header">
                        <h1>Set a New Password</h1>
                        <p>Create a new password for your account.</p>
                    </div>
                    <?php if ($error !== ''): ?>
                        <div class="inline-error-banner" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <form action="reset_password.php" method="post" class="auth-form recovery-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <div class="password-wrapper">
                                <input class="form-input" type="password" id="new-password" name="new_password" autocomplete="new-password" required>
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                            <div class="recovery-password-requirements">Password requirements: at least 8 characters, one number, and one special character.</div>
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm Password</label>
                            <div class="password-wrapper">
                                <input class="form-input" type="password" id="confirm-password" name="confirm_password" autocomplete="new-password" required>
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="submit-btn">Reset Password <span aria-hidden="true">→</span></button>
                    </form>
                <?php endif; ?>
            </section>
        </main>
        <?php require_once __DIR__ . '/includes/partials/recovery_aside.php'; ?>
    </div>
    <script src="assets/js/main.js"></script>
</body>

</html>
