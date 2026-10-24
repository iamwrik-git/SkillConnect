<?php
require_once __DIR__ . '/../includes/session.php';

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = (int) getCurrentUserId();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? null;
$sessionToken = $_SESSION['onboarding_csrf'] ?? null;
if (!is_string($submittedToken) || !is_string($sessionToken) || $submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    $_SESSION['onboarding_error'] = 'Your session expired. Please try again.';
    header('Location: ../dashboard.php');
    exit;
}

try {
    $userStmt = $pdo->prepare('SELECT name, bio, exp_level, security_question, security_answer_hash, onboarding_completed FROM users WHERE user_id = ? LIMIT 1');
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
    if ($action === 'save_profile') {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $level = (string) ($_POST['exp_level'] ?? '');
        $bio = trim((string) ($_POST['bio'] ?? ''));
        if ($name === '' || strlen($name) > 100) {
            $_SESSION['onboarding_error'] = 'Enter your full name (maximum 100 characters).';
        } elseif (!in_array($level, ['Beginner', 'Intermediate', 'Expert'], true)) {
            $_SESSION['onboarding_error'] = 'Select your expertise level.';
        } elseif ($bio === '' || strlen($bio) > 300) {
            $_SESSION['onboarding_error'] = 'Write a short bio (1–300 characters).';
        } else {
            $update = $pdo->prepare('UPDATE users SET name = ?, exp_level = ?, bio = ? WHERE user_id = ?');
            $update->execute([$name, $level, $bio, $userId]);
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $_SESSION['role'] ?? 'trainee';
            header('Location: ../dashboard.php');
            exit;
        }
    } elseif ($action === 'save_security') {
        $profileComplete = trim((string) ($user['name'] ?? '')) !== ''
            && in_array(($user['exp_level'] ?? ''), ['Beginner', 'Intermediate', 'Expert'], true)
            && trim((string) ($user['bio'] ?? '')) !== '';
        if (!$profileComplete) {
            $_SESSION['onboarding_error'] = 'Complete your profile before setting up account recovery.';
        } else {
            $questionInput = $_POST['security_question'] ?? '';
            $answerInput = $_POST['security_answer'] ?? '';
            $confirmInput = $_POST['confirm_security_answer'] ?? '';
            $question = is_string($questionInput) ? $questionInput : '';
            $answer = is_string($answerInput) ? trim($answerInput) : '';
            $confirm = is_string($confirmInput) ? trim($confirmInput) : '';
            $allowedQuestions = ['school', 'pet', 'nickname', 'city'];
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
