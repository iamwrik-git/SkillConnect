<?php
// actions/saved_trainer_process.php

require_once __DIR__ . '/../includes/session.php';

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce authentication
requireLogin();
$current_user_id = getCurrentUserId();
$role = $_SESSION['role'] ?? '';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit();
}

// Enforce role restrictions: Only mentees can save mentors
if ($role !== 'trainee') {
    $_SESSION['error'] = "Only mentees can save mentors.";
    header("Location: ../dashboard.php");
    exit();
}

// Sanitize and validate inputs
$action = is_string($_POST['action'] ?? null) ? trim($_POST['action']) : '';
$trainer_id = filter_input(INPUT_POST, 'trainer_id', FILTER_VALIDATE_INT) ?: filter_var($_POST['trainer_id'] ?? null, FILTER_VALIDATE_INT);
$returnRaw = $_POST['return_to'] ?? '../search.php';
$return_to = '../search.php';
if (is_string($returnRaw)) {
    $parsed = parse_url($returnRaw);
    $page = basename((string) ($parsed['path'] ?? ''));
    $allowedPages = ['search.php', 'saved_trainers.php', 'dashboard.php'];
    if (empty($parsed['scheme']) && empty($parsed['host']) && empty($parsed['user']) && empty($parsed['pass']) && in_array($page, $allowedPages, true)) {
        $return_to = '../' . $page;
        if ($page === 'search.php' && isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
            $safeQuery = [];
            if (isset($query['q']) && is_string($query['q'])) {
                $q = trim($query['q']);
                if ($q !== '') {
                    $safeQuery['q'] = substr($q, 0, 100);
                }
            }
            if (isset($query['skill_id']) && filter_var($query['skill_id'], FILTER_VALIDATE_INT) && (int)$query['skill_id'] > 0) {
                $safeQuery['skill_id'] = (int)$query['skill_id'];
            }
            if (isset($query['sort']) && in_array($query['sort'], ['name_asc', 'name_desc', 'exp_asc', 'exp_desc'], true)) {
                $safeQuery['sort'] = $query['sort'];
            }
            if (isset($query['page']) && filter_var($query['page'], FILTER_VALIDATE_INT) && (int)$query['page'] > 0) {
                $safeQuery['page'] = (int)$query['page'];
            }
            if ($safeQuery) {
                $return_to .= '?' . http_build_query($safeQuery);
            }
        }
    }
}

if (!csrfValid($_POST['csrf_token'] ?? null)) {
    skillConnectErrorResponse(403, 'Your request could not be verified. Refresh the page and try again.');
    exit;
}

if (!$trainer_id || $trainer_id <= 0) {
    $_SESSION['error'] = "Invalid mentor ID.";
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
    // Validate that the target account exists and is actually a mentor (and not an admin)
    $stmt = $pdo->prepare("SELECT role, is_admin FROM users WHERE user_id = ?");
    $stmt->execute([$trainer_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target || $target['role'] !== 'trainer' || !empty($target['is_admin'])) {
        $_SESSION['error'] = "The target account is not a valid mentor.";
        header("Location: " . $return_to);
        exit();
    }

    if ($action === 'save') {
        // Use INSERT IGNORE to leverage the DB unique constraint and handle duplicates gracefully
        $stmt = $pdo->prepare("INSERT IGNORE INTO saved_trainers (user_id, trainer_id) VALUES (?, ?)");
        $stmt->execute([$current_user_id, $trainer_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Mentor bookmarked successfully.";
        }
        // If rowCount is 0, it means the trainer is already saved; silently ignore to avoid annoying the user

    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM saved_trainers WHERE user_id = ? AND trainer_id = ?");
        $stmt->execute([$current_user_id, $trainer_id]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Mentor removed from saved list.";
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
