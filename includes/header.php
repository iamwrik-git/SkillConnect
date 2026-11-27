<?php
// includes/header.php

if (!isset($page_title)) {
    $page_title = 'SkillConnect';
}

// Refresh the signed-in user's identity from the database on each authenticated page.
// This prevents the header avatar/name from becoming stale after login or profile updates.
if (isset($pdo, $_SESSION['user_id'])) {
    try {
        $identityStmt = $pdo->prepare(
            "SELECT name, role, profile_photo FROM users WHERE user_id = ? LIMIT 1"
        );
        $identityStmt->execute([(int) $_SESSION['user_id']]);
        $identity = $identityStmt->fetch(PDO::FETCH_ASSOC);

        if ($identity) {
            $_SESSION['name'] = $identity['name'] ?? ($_SESSION['name'] ?? 'User');
            $_SESSION['role'] = $identity['role'] ?? ($_SESSION['role'] ?? 'trainee');
            $_SESSION['profile_photo'] = $identity['profile_photo'] ?? 'default.jpg';
        }
    } catch (PDOException $e) {
        error_log('Header identity refresh failed: ' . $e->getMessage());
    }
}

// Get the role to conditionally render sidebar items
$user_role = $_SESSION['role'] ?? 'trainee';

// Get the current page filename to apply the active blue marker
$current_page = basename($_SERVER['PHP_SELF']);

// Use the project logo asset and refresh its URL when the file changes.
$logo_file = __DIR__ . '/../assets/images/skillconnect-logo.svg';
$logo_version = is_file($logo_file) ? filemtime($logo_file) : time();

// ==========================================
// FETCH SEARCH AUTO-COMPLETE SUGGESTIONS
// ==========================================
$search_suggestions = [];
if (isset($pdo)) {
    try {
        // Grab all skill names AND trainer names to populate the dropdown
        $sgStmt = $pdo->query("
            SELECT skill_name AS suggestion FROM skills
            UNION
            SELECT name AS suggestion FROM users WHERE role = 'trainer' AND (is_admin = 0 OR is_admin IS NULL) AND email NOT LIKE '%admin%'
        ");
        $search_suggestions = $sgStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Search Suggestions Error: " . $e->getMessage());
    }
}

// Load up to three skills for the compact profile dropdown using the existing schema.
$nav_skills = [];
if (isset($pdo, $_SESSION['user_id'])) {
    try {
        $navSkillsStmt = $pdo->prepare("
            SELECT s.skill_name
            FROM user_skills us
            INNER JOIN skills s ON s.skill_id = us.skill_id
            WHERE us.user_id = ?
            ORDER BY s.skill_name ASC
            LIMIT 3
        ");
        $navSkillsStmt->execute([(int) $_SESSION['user_id']]);
        $nav_skills = $navSkillsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log('Header profile skills fetch failed: ' . $e->getMessage());
    }
}

// Notifications are stored separately from mentorship records.
$nav_notifications = [];
$nav_unread_notifications = 0;
$nav_unread_messages = 0;
if (isset($pdo, $_SESSION['user_id'])) {
    try {
        $notificationCountStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND is_read = 0"
        );
        $notificationCountStmt->execute([(int) $_SESSION['user_id']]);
        $nav_unread_notifications = (int) $notificationCountStmt->fetchColumn();

        $msgCountStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND notification_type = 'message' AND is_read = 0"
        );
        $msgCountStmt->execute([(int) $_SESSION['user_id']]);
        $nav_unread_messages = (int) $msgCountStmt->fetchColumn();

        $notificationListStmt = $pdo->prepare("
            SELECT n.notification_id, n.sender_user_id, n.request_id, n.notification_type, n.message, n.is_read, n.created_at,
                   u.name AS sender_name
            FROM notifications n
            LEFT JOIN users u ON n.sender_user_id = u.user_id
            WHERE n.recipient_user_id = ?
            ORDER BY n.created_at DESC, n.notification_id DESC
            LIMIT 5
        ");
        $notificationListStmt->execute([(int) $_SESSION['user_id']]);
        $nav_notifications = $notificationListStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Header notifications fetch failed: ' . $e->getMessage());
    }
}

$notification_csrf = csrfToken('notification_csrf');
$notificationTimeLabel = static function ($createdAt): string {
    try {
        $created = new DateTime((string) $createdAt);
        $seconds = max(0, time() - $created->getTimestamp());
        if ($seconds < 60) return 'Just now';
        if ($seconds < 3600) return (int) floor($seconds / 60) . ' min ago';
        if ($seconds < 86400) return (int) floor($seconds / 3600) . ' hr ago';
        if ($seconds < 604800) {
            $days = (int) floor($seconds / 86400);
            return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
        }
        return $created->format('M j');
    } catch (Throwable $e) {
        return '';
    }
};

$nav_js_file = __DIR__ . '/../assets/js/nav.js';
$nav_js_version = is_file($nav_js_file) ? filemtime($nav_js_file) : time();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Cache buster active for development -->
    <link rel="icon" type="image/png" href="/SkillConnect/assets/images/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <script>
        // Immediately apply saved theme before first paint to prevent flash
        (function() {
            var theme = localStorage.getItem('skillconnect_user_theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
        })();
    </script>
    <script src="assets/js/nav.js?v=<?= (int) $nav_js_version ?>" defer></script>
</head>

<body>
    <script>
        if (localStorage.getItem('skillconnect_user_theme') === 'dark') {
            document.body.classList.add('theme-dark');
        }
    </script>
    <div class="app-layout">

        <!-- TOP NAVIGATION -->
        <?php require_once __DIR__ . '/partials/top_nav.php'; ?>

        <div class="app-body">

            <!-- SIDEBAR -->
            <?php require_once __DIR__ . '/partials/sidebar.php'; ?>

            <!-- Mobile sidebar backdrop; main.js uses this to close the drawer. -->
            <button type="button" class="sidebar-backdrop" aria-label="Close navigation menu" tabindex="-1"></button>

            <!-- Shared logout confirmation used by both sidebar and profile dropdown. -->
            <?php require_once __DIR__ . '/partials/logout_modal.php'; ?>

            <!-- MAIN CONTENT AREA -->
            <main class="main-content">
