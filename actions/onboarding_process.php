<?php
require_once __DIR__ . '/../includes/session.php';

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/recovery_helpers.php';
requireLogin();

$userId = (int) getCurrentUserId();
$actionInput = $_POST['action'] ?? ($_GET['action'] ?? '');
$submittedToken = $_POST['csrf_token'] ?? ($_GET['csrf'] ?? null);
$sessionToken = $_SESSION['onboarding_csrf'] ?? null;

if (!is_string($submittedToken) || !is_string($sessionToken) || $submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    $_SESSION['onboarding_error'] = 'Your session expired. Please try again.';
    header('Location: ../dashboard.php');
    exit;
}

// Fallback: User cancelled onboarding to start registration over from scratch
if ($actionInput === 'cancel_onboarding') {
    try {
        $check = $pdo->prepare('SELECT user_id, onboarding_completed FROM users WHERE user_id = ? LIMIT 1');
        $check->execute([$userId]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)($row['onboarding_completed'] ?? 1) === 0) {
            $pdo->prepare('DELETE FROM users WHERE user_id = ? AND onboarding_completed = 0')->execute([$userId]);
        }
    } catch (PDOException $e) {
        error_log('Cancel onboarding cleanup error: ' . $e->getMessage());
    }

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header('Location: ../login.php?mode=signup&cleared=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

try {
    $userStmt = $pdo->prepare('SELECT user_id, onboarding_completed FROM users WHERE user_id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log('Onboarding request could not find the authenticated user record.');
        skillConnectErrorResponse(404, 'This account is no longer available. Please sign in again.');
        exit;
    }
    if ((int) ($user['onboarding_completed'] ?? 1) === 1) {
        $_SESSION['onboarding_completed'] = 1;
        header('Location: ../dashboard.php');
        exit;
    }

    $actionInput = $_POST['action'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';
    if ($action === 'save_security') {
        $questionInput = $_POST['security_question'] ?? '';
        $answerInput = $_POST['security_answer'] ?? '';
        $confirmInput = $_POST['confirm_security_answer'] ?? '';
        $question = is_string($questionInput) ? $questionInput : '';
        $answer = is_string($answerInput) ? trim($answerInput) : '';
        $confirm = is_string($confirmInput) ? trim($confirmInput) : '';
        $allowedQuestions = array_keys(getSecurityQuestions());

        if (!in_array($question, $allowedQuestions, true)) {
            $_SESSION['onboarding_error'] = 'Choose a security question.';
        } elseif ($answer === '' || strlen($answer) > 255) {
            $_SESSION['onboarding_error'] = 'Enter your security answer (maximum 255 characters).';
        } elseif (!hash_equals(normalizeSecurityAnswer($answer), normalizeSecurityAnswer($confirm))) {
            $_SESSION['onboarding_error'] = 'Security answers do not match.';
        } else {
            // Security answers are case-insensitive; store the normalized answer for recovery too.
            $answerHash = password_hash(normalizeSecurityAnswer($answer), PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE users SET security_question = ?, security_answer_hash = ?, onboarding_completed = 1 WHERE user_id = ?');
            $update->execute([$question, $answerHash, $userId]);
            $_SESSION['onboarding_completed'] = 1;
            $_SESSION['onboarding_success'] = true;
            unset($_SESSION['onboarding_error']);
            header('Location: ../dashboard.php');
            exit;
        }
    } else {
        $_SESSION['onboarding_error'] = 'Invalid setup step. Please try again.';
    }
} catch (PDOException $e) {
    error_log('Onboarding processing failed: ' . $e->getMessage());
    $_SESSION['onboarding_error'] = 'We could not save your setup right now. Please try again.';
}

header('Location: ../dashboard.php');
exit;
