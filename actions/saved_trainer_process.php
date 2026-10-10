<?php
// actions/saved_trainer_process.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';

// Enforce authentication
requireLogin();
$current_user_id = getCurrentUserId();
$role = $_SESSION['role'] ?? '';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit();
}

// Enforce role restrictions: Only trainees can save trainers
if ($role !== 'trainee') {
    $_SESSION['error'] = "Only trainees can save trainers.";
    header("Location: ../dashboard.php");
    exit();
}

// Sanitize and validate inputs
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
$trainer_id = filter_input(INPUT_POST, 'trainer_id', FILTER_VALIDATE_INT);
$return_to = $_POST['return_to'] ?? '../search.php';

// Prevent open redirects
if (strpos($return_to, 'http') === 0 || strpos($return_to, '//') === 0) {
    $return_to = '../search.php';
}

if (!$trainer_id || $trainer_id <= 0) {
    $_SESSION['error'] = "Invalid trainer ID.";
    header("Location: " . $return_to);
    exit();
}

// Prevent self-saving
if ($current_user_id === $trainer_id) {
    $_SESSION['error'] = "You cannot save your own profile.";
    header("Location: " . $return_to);
    exit();
}

try {
    // Validate that the target account exists and is actually a trainer
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$trainer_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target || $target['role'] !== 'trainer') {
        $_SESSION['error'] = "The target account is not a valid trainer.";
        header("Location: " . $return_to);
        exit();
    }

    if ($action === 'save') {
        // Use INSERT IGNORE to leverage the DB unique constraint and handle duplicates gracefully
        $stmt = $pdo->prepare("INSERT IGNORE INTO saved_trainers (user_id, trainer_id) VALUES (?, ?)");
        $stmt->execute([$current_user_id, $trainer_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Trainer bookmarked successfully.";
        }
        // If rowCount is 0, it means the trainer is already saved; silently ignore to avoid annoying the user

    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM saved_trainers WHERE user_id = ? AND trainer_id = ?");
        $stmt->execute([$current_user_id, $trainer_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Trainer removed from saved list.";
        }
    } else {
        $_SESSION['error'] = "Invalid action specified.";
    }

} catch (PDOException $e) {
    error_log("Database Error in saved_trainer_process: " . $e->getMessage());
    $_SESSION['error'] = "An unexpected error occurred. Please try again later.";
}

// Perform a safe POST redirect preserving original URL parameters
header("Location: " . $return_to);
exit();
