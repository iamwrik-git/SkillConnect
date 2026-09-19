<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($page_title)) {
    $page_title = 'SkillConnect';
}

// --- SELF-HEALING DATABASE FALLBACK ---
// We dynamically fetch the picture via your system's auth logic if it's missing from the current session
if (!isset($_SESSION['profile_photo']) && function_exists('getCurrentUserId')) {
    $current_uid = getCurrentUserId();
    if ($current_uid) {
        try {
            if (!isset($pdo) && file_exists(__DIR__ . '/db_connect.php')) {
                require_once __DIR__ . '/db_connect.php';
            }
            
            if (isset($pdo)) {
                $check_stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE user_id = ?");
                $check_stmt->execute([$current_uid]);
                $user_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                if ($user_row) {
                    $_SESSION['profile_photo'] = $user_row['profile_photo'] ?? 'default.jpg';
                }
            }
        } catch (PDOException $e) {
            error_log("Header DB Fallback Error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
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

            <div class="nav-search">
                <form action="search.php" method="GET" class="search-form">
                    <input type="text" name="q" placeholder="Search mentors, skills..." class="search-input" aria-label="Global search">
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
                        $session_photo = $_SESSION['profile_photo'] ?? '';
                        
                        if (!empty($session_photo) && $session_photo !== 'default.jpg'): 
                        ?>
                            <!-- Render actual user photo using matching assets/images/profile path structure -->
                            <img src="assets/images/profile/<?= htmlspecialchars($session_photo, ENT_QUOTES, 'UTF-8') ?>" alt="Me" class="nav-avatar-img">
                        <?php else: ?>
                            <!-- Fallback to original CSS gray circle if no custom photo exists -->
                            <span class="avatar-placeholder"></span>
                        <?php endif; ?>
                        
                        <span class="nav-text">Me ▼</span>
                    </button>
                    <div class="dropdown-popup profile-popup">
                        <div class="popup-body">
                            <div class="profile-info-block">
                                <?php if (isset($_SESSION['name'])): ?>
                                    <div class="profile-name"><?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <?php endif; ?>
                                
                                <?php if (isset($_SESSION['role'])): ?>
                                    <div class="profile-role"><?= htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8') ?></div>
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
                            <li><a href="dashboard.php" class="sidebar-link">Dashboard</a></li>
                            <li><a href="search.php" class="sidebar-link">Find Trainers</a></li>
                            <li><a href="profile.php" class="sidebar-link">Profile</a></li>
                            <li><a href="edit_profile.php" class="sidebar-link">Edit Profile</a></li>
                        </ul>
                    </nav>

                    <nav class="sidebar-section">
                        <h3 class="sidebar-heading">MY SPACE</h3>
                        <ul class="sidebar-list">
                            <li><a href="profile.php" class="sidebar-link">My Skills</a></li>
                            <li><a href="dashboard.php" class="sidebar-link">My Mentorships</a></li>
                            <li>
                                <div class="sidebar-link disabled">
                                    Saved Trainers
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
