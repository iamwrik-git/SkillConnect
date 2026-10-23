<?php
session_start();

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$userId = (int) getCurrentUserId();
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

$allowedReturnPages = [
    'dashboard.php', 'mentorships.php', 'profile.php', 'search.php',
    'my_skills.php', 'saved_trainers.php', 'edit_profile.php', 'stay_tuned.php'
];
$returnTo = basename((string) ($_POST['return_to'] ?? 'dashboard.php'));
if (!in_array($returnTo, $allowedReturnPages, true)) {
    $returnTo = 'dashboard.php';
}

if ($action === 'open' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $notificationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($notificationId && $notificationId > 0) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_user_id = ?'
            );
            $stmt->execute([$notificationId, $userId]);
        } catch (PDOException $e) {
            error_log('Notification open action failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Notifications are temporarily unavailable.';
        }
    }
    header('Location: mentorships.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$submittedToken = (string) ($_POST['csrf_token'] ?? '');
$sessionToken = (string) ($_SESSION['notification_csrf'] ?? '');
if ($submittedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

try {
    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE recipient_user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
    } elseif ($action === 'clear_all') {
        // This only clears notification history; mentorship records are untouched.
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE recipient_user_id = ?');
        $stmt->execute([$userId]);
    } else {
        $_SESSION['error'] = 'Invalid notification action.';
    }
} catch (PDOException $e) {
    error_log('Notification action failed: ' . $e->getMessage());
    $_SESSION['error'] = 'Notifications are temporarily unavailable. Check that the notifications table has been created.';
}

header('Location: ' . $returnTo);
exit;
