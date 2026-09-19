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
// NEW: FETCH SEARCH AUTO-COMPLETE SUGGESTIONS
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

            <!-- ==========================================
                 UPDATED: GLOBAL SEARCH BAR WITH DATALIST
                 ========================================== -->
            <div class="nav-search">
                <form action="search.php" method="GET" class="search-form">
                    <!-- Added list="globalSearchOptions" to link to the datalist below -->
                    <input type="text" name="q" placeholder="Search mentors, skills..." class="search-input" aria-label="Global search" list="globalSearchOptions" autocomplete="off">
                    
                    <!-- Native HTML5 Autocomplete Dropdown -->
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
                            <li><a href="dashboard.php" class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
                            
                            <?php if ($user_role === 'trainee'): ?>
                                <li><a href="search.php" class="sidebar-link <?= $current_page === 'search.php' ? 'active' : '' ?>">Find Trainers</a></li>
                            <?php endif; ?>
                            
                            <li><a href="profile.php" class="sidebar-link <?= $current_page === 'profile.php' ? 'active' : '' ?>">Profile</a></li>
                            <li><a href="edit_profile.php" class="sidebar-link <?= $current_page === 'edit_profile.php' ? 'active' : '' ?>">Edit Profile</a></li>
                        </ul>
                    </nav>

                    <nav class="sidebar-section">
                        <h3 class="sidebar-heading">MY SPACE</h3>
                        <ul class="sidebar-list">
                            <li><a href="profile.php" class="sidebar-link">My Skills</a></li>
                            
                            <!-- Dynamically rename based on role -->
                            <li><a href="dashboard.php" class="sidebar-link"><?= $user_role === 'trainer' ? 'My Trainees' : 'My Mentorships' ?></a></li>
                            
                            <li>
                                <div class="sidebar-link disabled">
                                    <?= $user_role === 'trainer' ? 'Saved Trainees' : 'Saved Trainers' ?>
                                    <span class="badge badge-sidebar">Coming Soon</span>
                                </div>
                            </li>
                        </ul>
                    </nav>

                    <nav class="sidebar-section">
                        <h3 class="sidebar-heading">EXPLORE</h3>
                        <ul class="sidebar-list">
                            <li>
                                <div class="sidebar-link disabled">
                                    Communities
                                    <span class="badge badge-sidebar">Coming Soon</span>
                                </div>
                            </li>
                            <li>
                                <div class="sidebar-link disabled">
                                    Leaderboard
                                    <span class="badge badge-sidebar">Coming Soon</span>
                                </div>
                            </li>
                        </ul>
                    </nav>

                </div>

                <div class="sidebar-footer">
                    <a href="logout.php" class="sidebar-link logout-link">Logout</a>
                </div>
            </aside>

            <!-- MAIN CONTENT AREA -->
            <main class="main-content">
