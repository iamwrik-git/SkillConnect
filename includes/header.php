<?php
// includes/header.php

if (!isset($page_title)) {
    $page_title = 'SkillConnect';
}

// Get the role to conditionally render sidebar items
$user_role = $_SESSION['role'] ?? 'trainee';

// Get the current page filename to apply the active blue marker
$current_page = basename($_SERVER['PHP_SELF']);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <!-- Cache buster active for development -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
    <div class="app-layout">
        
        <!-- TOP NAVIGATION -->
        <header class="top-nav">
            <div class="nav-brand">
                <a href="dashboard.php" class="logo">
                    <span class="logo-mark">SC</span>
                    <span class="logo-text">SkillConnect</span>
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

                <!-- Me / Profile Popover -->
                <div class="nav-item has-dropdown" data-dropdown="profile">
                    <button type="button" class="nav-btn me-btn" aria-expanded="false">
                        <?php
                            $nav_photo = $_SESSION['profile_photo'] ?? '';
                            $nav_name = $_SESSION['name'] ?? 'User';
                            if (empty($nav_photo) || $nav_photo === 'default.jpg') {
                                $nav_avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($nav_name) . "&background=eff6ff&color=2563eb&bold=true";
                            } else {
                                $nav_avatar_url = 'assets/images/profile/' . htmlspecialchars($nav_photo, ENT_QUOTES, 'UTF-8');
                            }
                        ?>
                        <img src="<?= $nav_avatar_url ?>" alt="Me" class="avatar-placeholder" style="object-fit: cover;">
                        <span class="nav-text">Me ▼</span>
                    </button>
                    <div class="dropdown-popup profile-popup">
                        <div class="popup-body">
                            <div class="profile-info-block">
                                <?php if (isset($_SESSION['name'])): ?>
                                    <div class="profile-name"><?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($_SESSION['role'])): ?>
                                    <div class="profile-role"><?= htmlspecialchars(ucfirst($_SESSION['role']), ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="popup-actions">
                                <a href="profile.php" class="btn-view-profile">View Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="app-body">
            
            <!-- SIDEBAR -->
            <aside class="sidebar">
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
                    <a href="logout.php" class="logout-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                        <span>Logout</span>
                    </a>
                </div>
            </aside>

            <!-- MAIN CONTENT AREA -->
            <main class="main-content">
