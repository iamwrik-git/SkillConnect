<?php
// actions/saved_trainer_process.php

require_once __DIR__ . '/../includes/session.php';

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
$action = is_string($_POST['action'] ?? null) ? trim($_POST['action']) : '';
$trainer_id = filter_input(INPUT_POST, 'trainer_id', FILTER_VALIDATE_INT);
$returnRaw = $_POST['return_to'] ?? '../search.php';
$return_to = '../search.php';
if (is_string($returnRaw)) {
    $parsed = parse_url($returnRaw);
    $page = basename((string) ($parsed['path'] ?? ''));
    $allowedPages = ['search.php', 'saved_trainers.php'];
    if (empty($parsed['scheme']) && empty($parsed['host']) && empty($parsed['user']) && empty($parsed['pass']) && in_array($page, $allowedPages, true)) {
        $return_to = '../' . $page;
        if ($page === 'search.php' && isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
            $safeQuery = [];
            if (isset($query['q']) && is_string($query['q'])) $safeQuery['q'] = substr($query['q'], 0, 100);
            if (isset($query['page']) && filter_var($query['page'], FILTER_VALIDATE_INT) && (int)$query['page'] > 0) $safeQuery['page'] = (int)$query['page'];
            if ($safeQuery) $return_to .= '?' . http_build_query($safeQuery);
        }
    }
}

if (!csrfValid($_POST['csrf_token'] ?? null)) {
    skillConnectErrorResponse(403, 'Your request could not be verified. Refresh the page and try again.');
    exit;
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
