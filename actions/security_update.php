<?php
// actions/security_update.php

require_once __DIR__ . '/../includes/session.php';

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

requireLogin();
$user_id = getCurrentUserId();

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../edit_profile.php");
    exit();
}

// CSRF Protection validation
if (!csrfValid($_POST['csrf_token'] ?? null)) {
    $_SESSION['error'] = "Invalid security token. Please try again.";
    header("Location: ../edit_profile.php#security");
    exit();
}

$action = $_POST['action'] ?? '';

try {
    // Fetch entire user record to dynamically determine the password column name and avoid SQL crashes
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = "User record not found.";
        header("Location: ../edit_profile.php#security");
        exit();
    }

    // Dynamically identify the correct password column name (commonly 'password' or 'password_hash')
    $pass_col = 'password'; 
    if (array_key_exists('password_hash', $user)) {
        $pass_col = 'password_hash';
    } elseif (array_key_exists('pass', $user)) {
        $pass_col = 'pass';
    }

    $current_db_password = $user[$pass_col] ?? '';

    // ==========================================
    // 1. CHANGE PASSWORD
    // ==========================================
    if ($action === 'update_password') {
        $currentInput = $_POST['current_password'] ?? '';
        $newInput = $_POST['new_password'] ?? '';
        $confirmInput = $_POST['confirm_new_password'] ?? '';
        $current_password = is_string($currentInput) ? $currentInput : '';
        $new_password = is_string($newInput) ? $newInput : '';
        $confirm_password = is_string($confirmInput) ? $confirmInput : '';

        if (!password_verify($current_password, $current_db_password)) {
            $_SESSION['error'] = "Incorrect current password.";
            header("Location: ../edit_profile.php#security");
            exit();
        }

        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match.";
            header("Location: ../edit_profile.php#security");
            exit();
        }

        if (strlen($new_password) < 8 || !preg_match('/[0-9]/', $new_password) || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
            $_SESSION['error'] = "Password must be at least 8 characters and include a number and special character.";
            header("Location: ../edit_profile.php#security");
            exit();
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $updateStmt = $pdo->prepare("UPDATE users SET {$pass_col} = ? WHERE user_id = ?");
        $updateStmt->execute([$new_hash, $user_id]);

        $_SESSION['success'] = "Account password successfully updated.";
        header("Location: ../edit_profile.php#security");
        exit();
    }

    // ==========================================
    // 2. ACCOUNT RECOVERY
    // ==========================================
    elseif ($action === 'update_recovery') {
        $questionInput = $_POST['security_question'] ?? '';
        $answerInput = $_POST['security_answer'] ?? '';
        $confirmInput = $_POST['confirm_security_answer'] ?? '';
        $currentInput = $_POST['current_password_recovery'] ?? '';
        $question = is_string($questionInput) ? $questionInput : '';
        $answer = is_string($answerInput) ? $answerInput : '';
        $confirm = is_string($confirmInput) ? $confirmInput : '';
        $current_password = is_string($currentInput) ? $currentInput : '';

        // Check if recovery columns exist to prevent PDO crash
        if (!array_key_exists('security_question', $user) || !array_key_exists('security_answer_hash', $user)) {
            $_SESSION['error'] = "Account recovery settings are temporarily unavailable. Please try again later.";
            header("Location: ../edit_profile.php#security");
            exit();
        }

        // Must provide correct password to change recovery info
        if (!password_verify($current_password, $current_db_password)) {
            $_SESSION['error'] = "Incorrect current password. Cannot update recovery settings.";
            header("Location: ../edit_profile.php#security");
            exit();
        }

        $has_existing = !empty($user['security_answer_hash']);

        $allowedQuestions = ['school', 'pet', 'nickname', 'city'];
        if (!is_string($question) || !in_array($question, $allowedQuestions, true)) {
            $_SESSION['error'] = "Please select a security question.";
            header("Location: ../edit_profile.php#security");
            exit();
        }

        if (!$has_existing && empty($answer)) {
            $_SESSION['error'] = "Please provide a security answer.";
            header("Location: ../edit_profile.php#security");
            exit();
        }

        if (!empty($answer)) {
            if (!is_string($answer) || !is_string($confirm) || normalizeSecurityAnswer($answer) !== normalizeSecurityAnswer($confirm)) {
                $_SESSION['error'] = "Security answers do not match.";
                header("Location: ../edit_profile.php#security");
                exit();
            }
            
            // Normalize before hashing (lowercase and trim)
            $normalized_answer = normalizeSecurityAnswer($answer);
            $answer_hash = password_hash($normalized_answer, PASSWORD_DEFAULT);

            $updateStmt = $pdo->prepare("UPDATE users SET security_question = ?, security_answer_hash = ? WHERE user_id = ?");
            $updateStmt->execute([$question, $answer_hash, $user_id]);
        } else {
            // Update question only (keep existing answer)
            $updateStmt = $pdo->prepare("UPDATE users SET security_question = ? WHERE user_id = ?");
            $updateStmt->execute([$question, $user_id]);
        }

        $_SESSION['success'] = "Account recovery settings successfully updated.";
        header("Location: ../edit_profile.php#security");
        exit();
    }

} catch (PDOException $e) {
    error_log("Security Update Error: " . $e->getMessage());
    $_SESSION['error'] = "We could not update your account settings. Please try again later.";
    header("Location: ../edit_profile.php#security");
    exit();
}

header("Location: ../edit_profile.php");
exit();
?>
