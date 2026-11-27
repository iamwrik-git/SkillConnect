<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/recovery_helpers.php';

if (!isRecoveryFlowActive()) {
    header('Location: forgot_password.php');
    exit;
}

$flow = &$_SESSION['password_recovery'];
if (!empty($flow['verified'])) {
    header('Location: reset_password.php');
    exit;
}

$error = $_SESSION['recovery_error'] ?? '';
unset($_SESSION['recovery_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrfValid($token)) {
        unset($_SESSION['password_recovery']);
        $_SESSION['recovery_error'] = 'Your session token expired. Please start again.';
        header('Location: forgot_password.php');
        exit;
    }

    $flowForLimit = $_SESSION['password_recovery'] ?? [];
    $ipForLimit = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $accountForLimit = is_array($flowForLimit) ? (string) ($flowForLimit['user_id'] ?? 'unknown') : 'unknown';
    $recoveryLimitKey = hash('sha256', $ipForLimit . '|' . $accountForLimit);

    if (rateLimitExceeded('recovery_ip', $ipForLimit, 20, 1800) || rateLimitExceeded('recovery_answer', $recoveryLimitKey, 8, 1800)) {
        unset($_SESSION['password_recovery']);
        $_SESSION['recovery_error'] = 'Too many recovery attempts. Please wait before starting again.';
        header('Location: forgot_password.php');
        exit;
    }

    $answerInput = $_POST['answer'] ?? '';
    $answer = is_string($answerInput) ? trim($answerInput) : '';
    if ($answer === '' || strlen($answer) > 255) {
        $error = 'Enter your security answer.';
    } else {
        $_SESSION['recovery_failed_attempts'] = (int)($_SESSION['recovery_failed_attempts'] ?? 0);
        if ((int)($flow['attempts'] ?? 0) >= 5 || $_SESSION['recovery_failed_attempts'] >= 8) {
            unset($_SESSION['password_recovery']);
            $_SESSION['recovery_error'] = 'Too many incorrect attempts. Start the recovery process again.';
            header('Location: forgot_password.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare('SELECT security_question, security_answer_hash FROM users WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int)$flow['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !isRecoveryConfigured($user)) {
                unset($_SESSION['password_recovery']);
                $_SESSION['recovery_unavailable'] = true;
                header('Location: forgot_password.php');
                exit;
            }

            if (password_verify(normalizeSecurityAnswer($answer), $user['security_answer_hash'])) {
                $flow['verified'] = true;
                $flow['verified_at'] = time();
                unset($_SESSION['recovery_failed_attempts']);
                session_regenerate_id(true);
                header('Location: reset_password.php');
                exit;
            }

            $flow['attempts'] = (int)($flow['attempts'] ?? 0) + 1;
            $_SESSION['recovery_failed_attempts']++;
            if ($flow['attempts'] >= 5 || $_SESSION['recovery_failed_attempts'] >= 8) {
                unset($_SESSION['password_recovery']);
                $_SESSION['recovery_error'] = 'Too many incorrect attempts. Start the recovery process again.';
                header('Location: forgot_password.php');
                exit;
            }
            $error = 'That answer does not match. Please try again.';
        } catch (PDOException $e) {
            error_log('Recovery answer lookup failed: ' . $e->getMessage());
            $error = 'An unexpected error occurred. Please try again later.';
        }
    }
}

try {
    $stmt = $pdo->prepare('SELECT security_question, security_answer_hash FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int)$flow['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Recovery question lookup failed: ' . $e->getMessage());
    skillConnectErrorResponse(500, 'We could not verify your recovery details right now. Please try again later.');
    exit;
}

if (!$user || !isRecoveryConfigured($user)) {
    unset($_SESSION['password_recovery']);
    $_SESSION['recovery_unavailable'] = true;
    header('Location: forgot_password.php');
    exit;
}

$questions = getSecurityQuestions();
$questionText = $questions[$user['security_question']] ?? 'Your security question';
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Identity - SkillConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/recovery.css">
    <link rel="icon" type="image/png" href="/SkillConnect/assets/images/favicon.png">
    <script>
        try {
            document.documentElement.classList.remove('theme-dark');
            document.documentElement.classList.add('theme-light');
            if (document.body) {
                document.body.classList.remove('theme-dark');
                document.body.classList.add('theme-light');
            }
        } catch (e) {}
    </script>
</head>

<body class="auth-page recovery-auth-page">
    <div class="login-workspace recovery-workspace">
        <main class="recovery-form-panel">
            <a class="recovery-brand-mobile" href="login.php">
                <img src="assets/images/skillconnect-logo.svg" alt="SkillConnect">
            </a>
            <section class="recovery-form-content">
                <a class="recovery-top-back" href="forgot_password.php">← Back</a>
                <div class="recovery-page-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        <path d="m9 12 2 2 4-4" />
                    </svg>
                </div>
                <div class="form-header">
                    <h1>Verify Your Identity</h1>
                    <p>Answer your security question to continue.</p>
                </div>
                <?php if ($error !== ''): ?>
                    <div class="inline-error-banner" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form action="verify_recovery.php" method="post" class="auth-form recovery-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label for="security-question-display">Security Question</label>
                        <div class="recovery-question-display" id="security-question-display"><?= htmlspecialchars($questionText, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="form-group">
                        <label for="recovery-answer">Your Answer</label>
                        <div class="password-wrapper">
                            <input class="form-input" type="password" id="recovery-answer" name="answer" autocomplete="off" maxlength="255" placeholder="Enter your answer" required>
                            <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show answer">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">Verify Answer <span aria-hidden="true">→</span></button>
                </form>
                <div class="recovery-back-row">
                    <a href="login.php">Back to Sign In</a>
                </div>
            </section>
        </main>
        <?php require_once __DIR__ . '/includes/partials/recovery_aside.php'; ?>
    </div>
    <script src="assets/js/main.js"></script>
</body>

</html>
