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
                        width="220"
                        height="68"
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
                <!-- Network Dropdown (UI Only) -->
                <div class="nav-item has-dropdown" data-dropdown="network">
                    <button type="button" class="nav-btn" aria-expanded="false">
                        <span class="nav-text">Network</span>
                    </button>
                    <div class="dropdown-popup">
                        <div class="popup-header">Global Network</div>
                        <div class="popup-body">
                            <p>Discover people across SkillConnect.</p>
                            <span class="badge badge-coming-soon">Coming Soon</span>
                        </div>
                    </div>
                </div>

                <!-- Notifications Dropdown (UI Only) -->
                <div class="nav-item has-dropdown" data-dropdown="notifications">
                    <button type="button" class="nav-btn" aria-expanded="false">
                        <span class="nav-text">Notifications</span>
                    </button>
                    <div class="dropdown-popup">
                        <div class="popup-header">Notifications</div>
                        <div class="popup-body">
                            <p>No notifications yet.</p>
                        </div>
                    </div>
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

                    // Desktop uses hover/focus; the wide invisible bridge keeps the popup reachable.
                    wrapper.addEventListener('pointerenter', () => {
                        if (hoverMode.matches) setOpen(true);
                    });
                    wrapper.addEventListener('pointerleave', () => {
                        if (hoverMode.matches && !wrapper.contains(document.activeElement)) setOpen(false);
                    });
                    wrapper.addEventListener('focusin', () => setOpen(true));
                    wrapper.addEventListener('focusout', (event) => {
                        if (!wrapper.contains(event.relatedTarget) && hoverMode.matches) setOpen(false);
                    });

                    // Touch/mobile has no hover, so tap toggles the menu.
                    trigger.addEventListener('click', (event) => {
                        event.preventDefault();
                        if (hoverMode.matches) return;
                        event.stopPropagation();
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
