<?php
require_once __DIR__ . '/includes/session.php';

require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$userId = (int) getCurrentUserId();
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

$allowedReturnPages = [
    'dashboard.php', 'mentorships.php', 'profile.php', 'search.php',
    'my_skills.php', 'saved_trainers.php', 'edit_profile.php', 'stay_tuned.php'
];
$returnRaw = $_POST['return_to'] ?? 'dashboard.php';
$returnTo = is_string($returnRaw) ? basename($returnRaw) : 'dashboard.php';
if (!in_array($returnTo, $allowedReturnPages, true)) {
    $returnTo = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$submittedToken = $_POST['csrf_token'] ?? null;
$sessionToken = $_SESSION['notification_csrf'] ?? null;
if (!is_string($submittedToken) || !is_string($sessionToken) || $submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    skillConnectErrorResponse(403, 'Your request could not be verified. Refresh the page and try again.');
    exit;
}

if ($action === 'open') {
    $notificationId = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
    if ($notificationId && $notificationId > 0) {
        try {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND recipient_user_id = ?');
            $stmt->execute([$notificationId, $userId]);
        } catch (PDOException $e) {
            error_log('Notification open action failed: ' . $e->getMessage());
            $_SESSION['error'] = 'Notifications are temporarily unavailable.';
        }
    }
    header('Location: ' . $returnTo);
    exit;
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
    $_SESSION['error'] = 'Notifications are temporarily unavailable. Please try again later.';
}

header('Location: ' . $returnTo);
exit;
