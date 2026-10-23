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
            SELECT name AS suggestion FROM users WHERE role = 'trainer'
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

// Notifications are stored separately from mentorship records. If the table
// has not been created yet, keep the rest of the site usable and show an empty panel.
$nav_notifications = [];
$nav_unread_notifications = 0;
if (isset($pdo, $_SESSION['user_id'])) {
    try {
        $notificationCountStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE recipient_user_id = ? AND is_read = 0"
        );
        $notificationCountStmt->execute([(int) $_SESSION['user_id']]);
        $nav_unread_notifications = (int) $notificationCountStmt->fetchColumn();

        $notificationListStmt = $pdo->prepare("
            SELECT notification_id, request_id, notification_type, message, is_read, created_at
            FROM notifications
            WHERE recipient_user_id = ?
            ORDER BY created_at DESC, notification_id DESC
            LIMIT 5
        ");
        $notificationListStmt->execute([(int) $_SESSION['user_id']]);
        $nav_notifications = $notificationListStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Expected until the user creates the notifications table using the supplied SQL.
        error_log('Header notifications fetch failed: ' . $e->getMessage());
    }
}

if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['notification_csrf'])) {
    $_SESSION['notification_csrf'] = bin2hex(random_bytes(32));
}
$notification_csrf = $_SESSION['notification_csrf'] ?? '';
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
</head>
<body>
    <div class="app-layout">
        
        <!-- TOP NAVIGATION -->
        <header class="top-nav">
            <button type="button" class="mobile-menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="app-sidebar">
                <span></span><span></span><span></span>
            </button>
            <div class="nav-brand">
                <a href="dashboard.php" class="logo" aria-label="SkillConnect home">
                    <img
                        src="assets/images/skillconnect-logo.svg?v=<?= (int) $logo_version ?>"
                        alt="SkillConnect"
                        class="logo-image"
                        width="190"
                        height="59"
                        fetchpriority="high"
                    >
                </a>
            </div>

            <!-- GLOBAL SEARCH BAR WITH DATALIST -->
            <div class="nav-search">
                <form action="search.php" method="GET" class="search-form">
                    <input type="text" name="q" placeholder="Search mentors, skills..." class="search-input" aria-label="Global search" list="globalSearchOptions" autocomplete="off">
                    <datalist id="globalSearchOptions">
                        <?php foreach($search_suggestions as $suggestion): ?>
                            <option value="<?= htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </form>
            </div>

            <div class="nav-actions">
                <!-- Network Dropdown — click controlled for consistency with Notifications -->
                <div class="nav-item has-dropdown network-menu" data-dropdown="network">
                    <button type="button" class="nav-btn network-menu-trigger" aria-expanded="false" aria-controls="network-dropdown" aria-haspopup="true" id="network-menu-trigger">
                        <span class="nav-text">Network</span>
                    </button>
                    <div class="dropdown-popup network-popup" id="network-dropdown" aria-hidden="true" aria-labelledby="network-menu-trigger">
                        <div class="popup-header">Global Network</div>
                        <div class="popup-body">
                            <p>Discover people across SkillConnect.</p>
                            <span class="badge badge-coming-soon">Coming Soon</span>
                        </div>
                    </div>
                </div>

                <!-- Role-aware mentorship notifications -->
                <div class="nav-item has-dropdown notification-menu" data-dropdown="notifications">
                    <button type="button" class="nav-btn notification-menu-trigger" aria-expanded="false" aria-controls="notifications-dropdown" aria-haspopup="true" id="notifications-menu-trigger">
                        <span class="nav-text">Notifications</span>
                        <?php if ($nav_unread_notifications > 0): ?>
                            <span class="notification-dot" aria-label="<?= (int) $nav_unread_notifications ?> unread notifications"></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-popup notification-popup" id="notifications-dropdown" aria-hidden="true" aria-labelledby="notifications-menu-trigger">
                        <div class="notification-popup-header">
                            <div>
                                <h2>Notifications</h2>
                                <?php if ($nav_unread_notifications > 0): ?>
                                    <p><?= (int) $nav_unread_notifications ?> unread notification<?= $nav_unread_notifications === 1 ? '' : 's' ?></p>
                                <?php endif; ?>
                            </div>
                            <form method="post" action="notifications_action.php" class="notification-header-action">
                                <input type="hidden" name="action" value="mark_all_read">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($notification_csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" <?= $nav_unread_notifications === 0 ? 'disabled aria-disabled="true"' : '' ?>>Mark all as read</button>
                            </form>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($nav_notifications)): ?>
                                <div class="notification-empty">
                                    <span class="notification-empty-icon" aria-hidden="true">✓</span>
                                    <strong>No notifications yet.</strong>
                                    <span>Mentorship updates will appear here.</span>
                                </div>
                            <?php else: ?>
                                <?php foreach ($nav_notifications as $notification): ?>
                                    <?php
                                        $notificationType = (string) ($notification['notification_type'] ?? 'mentorship_request');
                                        $notificationIcon = $notificationType === 'mentorship_accepted' ? 'accepted' : ($notificationType === 'mentorship_rejected' ? 'rejected' : 'request');
                                        $notificationIsUnread = (int) ($notification['is_read'] ?? 0) === 0;
                                    ?>
                                    <a class="notification-item <?= $notificationIsUnread ? 'is-unread' : 'is-read' ?>" href="notifications_action.php?action=open&amp;id=<?= (int) $notification['notification_id'] ?>">
                                        <span class="notification-item-icon <?= htmlspecialchars($notificationIcon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                            <?php if ($notificationIcon === 'accepted'): ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.5 2.5L16 9"></path></svg>
                                            <?php elseif ($notificationIcon === 'rejected'): ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="m9 9 6 6m0-6-6 6"></path></svg>
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="10" cy="7" r="4"></circle><path d="M20 8v6m-3-3h6"></path></svg>
                                            <?php endif; ?>
                                        </span>
                                        <span class="notification-item-content">
                                            <span class="notification-item-message"><?= htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="notification-item-time"><?= htmlspecialchars($notificationTimeLabel($notification['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                        <?php if ($notificationIsUnread): ?><span class="notification-item-unread-dot" aria-label="Unread"></span><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-popup-footer">
                            <span>Only mentorship updates.</span>
                            <?php if (!empty($nav_notifications)): ?>
                                <form method="post" action="notifications_action.php" class="notification-clear-form" id="notification-clear-form">
                                    <input type="hidden" name="action" value="clear_all">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($notification_csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" id="notification-clear-trigger">Clear all</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <dialog class="notification-confirm-dialog" id="notification-clear-confirm" aria-labelledby="notification-clear-title">
                        <div class="notification-confirm-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M10 11v6m4-6v6"></path><path d="m6 7 1 13h10l1-13"></path><path d="M9 7V4h6v3"></path></svg>
                        </div>
                        <h2 id="notification-clear-title">Clear notifications?</h2>
                        <div class="notification-confirm-actions">
                            <button type="button" class="notification-confirm-cancel" id="notification-clear-cancel">Cancel</button>
                            <button type="button" class="notification-confirm-accept" id="notification-clear-accept">Clear all</button>
                        </div>
                    </dialog>
                </div>

                <!-- Profile dropdown -->
                <div class="nav-item has-dropdown profile-menu" data-dropdown="profile">
                    <?php
                        $nav_photo = $_SESSION['profile_photo'] ?? '';
                        $nav_name = $_SESSION['name'] ?? 'User';
                        $nav_role = $_SESSION['role'] ?? 'trainee';
                        $show_initials = empty($nav_photo) || $nav_photo === 'default.jpg';
                        $name_parts = array_values(array_filter(preg_split('/\\s+/', trim($nav_name))));
                        $initials = 'U';
                        if (count($name_parts) >= 2) {
                            $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 1) . mb_substr(end($name_parts), 0, 1));
                        } elseif (count($name_parts) === 1 && $name_parts[0] !== '') {
                            $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 1));
                        }

                        $nav_avatar_url = '';
                        if (!$show_initials) {
                            $safe_photo = basename((string) $nav_photo);
                            $photo_path = __DIR__ . '/../assets/images/profile/' . $safe_photo;
                            $photo_version = is_file($photo_path) ? filemtime($photo_path) : time();
                            $nav_avatar_url = 'assets/images/profile/' . rawurlencode($safe_photo) . '?v=' . $photo_version;
                        }
                    ?>
                    <button
                        type="button"
                        class="nav-btn me-btn profile-menu-trigger"
                        aria-expanded="false"
                        aria-controls="profile-dropdown"
                        aria-haspopup="true"
                        id="profile-menu-trigger"
                    >
                        <?php if ($show_initials): ?>
                            <span class="avatar-placeholder initials-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($nav_avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="" class="avatar-placeholder profile-nav-avatar">
                        <?php endif; ?>
                        <span class="profile-menu-label"><?= htmlspecialchars(explode(' ', trim($nav_name))[0] ?: 'Me', ENT_QUOTES, 'UTF-8') ?></span>
                    </button>

                    <div class="dropdown-popup profile-popup" id="profile-dropdown" role="menu" aria-labelledby="profile-menu-trigger" aria-hidden="true">
                        <div class="profile-popup-identity">
                            <?php if ($show_initials): ?>
                                <span class="profile-popup-avatar initials-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars($nav_avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="" class="profile-popup-avatar">
                            <?php endif; ?>
                            <div class="profile-popup-user">
                                <div class="profile-popup-name"><?= htmlspecialchars($nav_name, ENT_QUOTES, 'UTF-8') ?></div>
                                <span class="profile-popup-role"><?= htmlspecialchars(ucfirst($nav_role), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>

                        <section class="profile-popup-skills" aria-label="Your skills">
                            <div class="profile-popup-skills-heading">
                                <span>My Skills <span class="profile-popup-skill-count">(<?= count($nav_skills) ?>)</span></span>
                                <a href="my_skills.php" class="profile-popup-view-all">View all</a>
                            </div>
                            <?php if (!empty($nav_skills)): ?>
                                <div class="profile-popup-skill-list">
                                    <?php foreach ($nav_skills as $skill_index => $skill_name): ?>
                                        <span class="profile-skill-chip profile-skill-chip-<?= (int) ($skill_index % 3) ?>"><?= htmlspecialchars($skill_name, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="profile-popup-no-skills">No skills added yet.</p>
                            <?php endif; ?>
                        </section>

                        <div class="profile-popup-actions">
                            <a href="profile.php" class="profile-menu-link is-featured" role="menuitem">
                                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <span>View Profile</span>
                            </a>
                            <a href="logout.php" class="profile-menu-link profile-menu-logout" role="menuitem" data-confirm-logout>
                                <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                <span>Log out</span>
                            </a>
                        </div>
                    </div>
                </div>

                <script>
                (() => {
                    const trigger = document.getElementById('network-menu-trigger');
                    const menu = document.getElementById('network-dropdown');
                    const wrapper = trigger && trigger.closest('.network-menu');
                    if (!trigger || !menu || !wrapper || wrapper.dataset.networkReady === 'true') return;
                    wrapper.dataset.networkReady = 'true';
                    const setOpen = (open) => {
                        wrapper.classList.toggle('show', open);
                        trigger.setAttribute('aria-expanded', String(open));
                        menu.setAttribute('aria-hidden', String(!open));
                    };
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        const notifications = document.querySelector('.notification-menu');
                        if (notifications) {
                            notifications.classList.remove('show');
                            const notificationTrigger = document.getElementById('notifications-menu-trigger');
                            const notificationMenu = document.getElementById('notifications-dropdown');
                            if (notificationTrigger) notificationTrigger.setAttribute('aria-expanded', 'false');
                            if (notificationMenu) notificationMenu.setAttribute('aria-hidden', 'true');
                        }
                        setOpen(trigger.getAttribute('aria-expanded') !== 'true');
                    });
                    document.addEventListener('click', (event) => {
                        if (!wrapper.contains(event.target)) setOpen(false);
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                            setOpen(false);
                            trigger.focus();
                        }
                    });
                })();
                </script>
                <script>
                (() => {
                    const clearForm = document.getElementById('notification-clear-form');
                    const clearTrigger = document.getElementById('notification-clear-trigger');
                    const dialog = document.getElementById('notification-clear-confirm');
                    const cancelButton = document.getElementById('notification-clear-cancel');
                    const acceptButton = document.getElementById('notification-clear-accept');
                    if (clearForm && clearTrigger && dialog && cancelButton && acceptButton && !clearForm.dataset.confirmReady) {
                        clearForm.dataset.confirmReady = 'true';
                        clearForm.addEventListener('submit', (event) => {
                            event.preventDefault();
                            if (typeof dialog.showModal === 'function') dialog.showModal();
                            else if (window.confirm('Clear notifications? Mentorship requests will not be affected.')) clearForm.submit();
                        });
                        cancelButton.addEventListener('click', () => dialog.close());
                        acceptButton.addEventListener('click', () => {
                            dialog.close();
                            clearForm.submit();
                        });
                        dialog.addEventListener('click', (event) => {
                            if (event.target === dialog) dialog.close();
                        });
                    }
                })();
                </script>
                <script>
                (() => {
                    const trigger = document.getElementById('notifications-menu-trigger');
                    const menu = document.getElementById('notifications-dropdown');
                    const wrapper = trigger && trigger.closest('.notification-menu');
                    if (!trigger || !menu || !wrapper || wrapper.dataset.notificationsReady === 'true') return;
                    wrapper.dataset.notificationsReady = 'true';

                    const setOpen = (open) => {
                        wrapper.classList.toggle('show', open);
                        trigger.setAttribute('aria-expanded', String(open));
                        menu.setAttribute('aria-hidden', String(!open));
                    };

                    // Notifications are click-controlled on desktop and mobile.
                    // This prevents the notification panel from activating the adjacent profile hover menu.
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        const network = document.querySelector('.network-menu');
                        if (network) {
                            network.classList.remove('show');
                            const networkTrigger = document.getElementById('network-menu-trigger');
                            const networkMenu = document.getElementById('network-dropdown');
                            if (networkTrigger) networkTrigger.setAttribute('aria-expanded', 'false');
                            if (networkMenu) networkMenu.setAttribute('aria-hidden', 'true');
                        }
                        setOpen(trigger.getAttribute('aria-expanded') !== 'true');
                    });
                    document.addEventListener('click', (event) => {
                        if (!wrapper.contains(event.target)) setOpen(false);
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                            setOpen(false);
                            trigger.focus();
                        }
                    });
                })();
                </script>
                <script>
                (() => {
                    const trigger = document.getElementById('profile-menu-trigger');
                    const menu = document.getElementById('profile-dropdown');
                    const wrapper = trigger && trigger.closest('.profile-menu');
                    if (!trigger || !menu || !wrapper || wrapper.dataset.profileMenuReady === 'true') return;
                    wrapper.dataset.profileMenuReady = 'true';

                    const hoverMode = window.matchMedia('(hover: hover) and (pointer: fine)');
                    const setOpen = (open) => {
                        wrapper.classList.toggle('show', open);
                        trigger.setAttribute('aria-expanded', String(open));
                        menu.setAttribute('aria-hidden', String(!open));
                    };

                    // Keep the profile dropdown hover-friendly on desktop, with click support
                    // for touch devices. The notification panel is click-only and stays separated.
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        setOpen(trigger.getAttribute('aria-expanded') !== 'true');
                    });
                    wrapper.addEventListener('focusout', (event) => {
                        if (!wrapper.contains(event.relatedTarget)) setOpen(false);
                    });
                    document.addEventListener('click', (event) => {
                        if (!wrapper.contains(event.target)) setOpen(false);
                    });
                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape' && trigger.getAttribute('aria-expanded') === 'true') {
                            setOpen(false);
                            trigger.focus();
                        }
                    });
                    menu.addEventListener('click', (event) => {
                        if (event.target.closest('a')) setOpen(false);
                    });
                    if (hoverMode.addEventListener) hoverMode.addEventListener('change', () => setOpen(false));
                })();
                </script>
            </div>
        </header>

        <div class="app-body">
            
            <!-- SIDEBAR -->
            <aside class="sidebar" id="app-sidebar">
                <div class="sidebar-scrollable">
                    
                    <nav class="sidebar-section">
                        <h3 class="sidebar-heading">MAIN</h3>
                        <ul class="sidebar-list">
                            <li>
                                <a href="dashboard.php" class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    <span>Dashboard</span>
                                </a>
                            </li>
                            
                            <?php if ($user_role === 'trainee'): ?>
                                <li>
                                    <a href="search.php" class="sidebar-link <?= $current_page === 'search.php' ? 'active' : '' ?>">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                        <span>Find Trainers</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <li>
                                <a href="profile.php" class="sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    <span>Profile</span>
                                </a>
                            </li>
                            <li>
                                <a href="edit_profile.php" class="sidebar-link <?= $current_page === 'edit_profile.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    <span>Edit Profile</span>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <nav class="sidebar-section">
                        <h3 class="sidebar-heading">MY SPACE</h3>
                        <ul class="sidebar-list">
                            <li>
                                <a href="my_skills.php" class="sidebar-link <?= $current_page === 'my_skills.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                                    <span>My Skills</span>
                                </a>
                            </li>
                            <li>
                                <a href="mentorships.php" class="sidebar-link <?= $current_page === 'mentorships.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <span><?= $user_role === 'trainer' ? 'My Trainees' : 'My Mentorships' ?></span>
                                </a>
                            </li>
                            
                            <?php if ($user_role === 'trainee'): ?>
                            <li>
                                <a href="saved_trainers.php" class="sidebar-link <?= $current_page === 'saved_trainers.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                                    <span>Saved Trainers</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                        </ul>
                    </nav>

                    <nav class="sidebar-section">
                        <h3 class="sidebar-heading">EXPLORE</h3>
                        <ul class="sidebar-list">
                            <li>
                                <a href="stay_tuned.php" class="sidebar-link <?= $current_page === 'stay_tuned.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <span>Communities</span>
                                </a>
                            </li>
                            <li>
                                <a href="stay_tuned.php" class="sidebar-link <?= $current_page === 'stay_tuned.php' ? 'active' : '' ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path></svg>
                                    <span>Leaderboard</span>
                                </a>
                            </li>
                        </ul>
                    </nav>

                </div>

                <div class="sidebar-footer">
                    <a href="logout.php" class="logout-link" data-confirm-logout>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>Logout</span>
                    </a>
                </div>
            </aside>

            <!-- Mobile sidebar backdrop; main.js uses this to close the drawer. -->
            <button type="button" class="sidebar-backdrop" aria-label="Close navigation menu" tabindex="-1"></button>

            <!-- Shared logout confirmation used by both sidebar and profile dropdown. -->
            <div class="confirm-modal-backdrop logout-confirm-backdrop" id="logout-confirm-modal" aria-hidden="true">
                <section class="confirm-modal-content logout-confirm-content" role="dialog" aria-modal="true" aria-labelledby="logout-confirm-title" aria-describedby="logout-confirm-message" tabindex="-1">
                    <div class="confirm-modal-icon confirm-danger logout-confirm-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"></path></svg>
                    </div>
                    <h2 class="confirm-modal-title" id="logout-confirm-title">Log out of SkillConnect?</h2>
                    <p class="confirm-modal-message" id="logout-confirm-message">Are you sure you want to log out? You will need to sign in again to access your account.</p>
                    <div class="confirm-modal-actions">
                        <button type="button" class="btn confirm-cancel" id="logout-cancel-btn">Stay logged in</button>
                        <button type="button" class="btn confirm-danger" id="logout-confirm-btn">Log out</button>
                    </div>
                </section>
            </div>
            <script>
            (() => {
                const modal = document.getElementById('logout-confirm-modal');
                const dialog = modal && modal.querySelector('.logout-confirm-content');
                const cancelButton = document.getElementById('logout-cancel-btn');
                const confirmButton = document.getElementById('logout-confirm-btn');
                if (!modal || !dialog || !cancelButton || !confirmButton || modal.dataset.ready === 'true') return;
                modal.dataset.ready = 'true';

                let logoutUrl = 'logout.php';
                let lastFocusedElement = null;

                const openModal = (url, source) => {
                    logoutUrl = url || 'logout.php';
                    lastFocusedElement = source;
                    modal.classList.add('show');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('logout-confirm-open');
                    window.setTimeout(() => cancelButton.focus(), 30);
                };

                const closeModal = () => {
                    modal.classList.remove('show');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('logout-confirm-open');
                    if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') lastFocusedElement.focus();
                };

                document.addEventListener('click', (event) => {
                    const logoutLink = event.target.closest('[data-confirm-logout]');
                    if (!logoutLink) return;
                    event.preventDefault();
                    openModal(logoutLink.href, logoutLink);
                });

                cancelButton.addEventListener('click', closeModal);
                confirmButton.addEventListener('click', () => { window.location.href = logoutUrl; });
                modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
                document.addEventListener('keydown', (event) => {
                    if (!modal.classList.contains('show')) return;
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeModal();
                    }
                    if (event.key === 'Tab') {
                        const focusable = [cancelButton, confirmButton];
                        const first = focusable[0];
                        const last = focusable[focusable.length - 1];
                        if (event.shiftKey && document.activeElement === first) {
                            event.preventDefault(); last.focus();
                        } else if (!event.shiftKey && document.activeElement === last) {
                            event.preventDefault(); first.focus();
                        }
                    }
                });
            })();
            </script>

            <!-- MAIN CONTENT AREA -->
            <main class="main-content">
