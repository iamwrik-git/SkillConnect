<?php
// actions/security_update.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
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
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_new_password'] ?? '';

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
        $question         = $_POST['security_question'] ?? '';
        $answer           = $_POST['security_answer'] ?? '';
        $confirm          = $_POST['confirm_security_answer'] ?? '';
        $current_password = $_POST['current_password_recovery'] ?? '';

        // Check if recovery columns exist to prevent PDO crash
        if (!array_key_exists('security_question', $user) || !array_key_exists('security_answer_hash', $user)) {
            $_SESSION['error'] = "Database schema error: Recovery columns are missing. Please ensure 'security_question' and 'security_answer_hash' exist in the users table.";
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

        if (empty($question)) {
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
            if ($answer !== $confirm) {
                $_SESSION['error'] = "Security answers do not match.";
                header("Location: ../edit_profile.php#security");
                exit();
            }
            
            // Normalize before hashing (lowercase and trim)
            $normalized_answer = strtolower(trim($answer));
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
    $_SESSION['error'] = "An unexpected database error occurred. Please try again.";
    header("Location: ../edit_profile.php#security");
    exit();
}

header("Location: ../edit_profile.php");
exit();
?>
