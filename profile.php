<?php
// profile.php

// 1. Session initialization and core includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

// 2. Authentication & Identity
requireLogin();
$current_user_id = getCurrentUserId();
$current_user_role = $_SESSION['role'] ?? 'trainee';

// 3. Determine which profile to display
$target_user_id = $current_user_id; // Default to own profile

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $target_user_id = (int)$_GET['user_id'];
}

$is_own_profile = ($target_user_id === $current_user_id);

// 4. Fetch Target User Profile Data
$profile_user = null;
$user_not_found = false;

try {
    // Only fetch required, non-sensitive fields
    $stmt = $pdo->prepare("SELECT user_id, name, email, profile_photo, bio, exp_level, role FROM users WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile_user) {
        $user_not_found = true;
    }
} catch (PDOException $e) {
    error_log("Profile Fetch Error (profile.php): " . $e->getMessage());
    $user_not_found = true;
}

// 5. Fetch Target User's Skills
$profile_skills = [];
if (!$user_not_found) {
    try {
        $skillStmt = $pdo->prepare("
            SELECT s.skill_id, s.skill_name 
            FROM user_skills us 
            JOIN skills s ON us.skill_id = s.skill_id 
            WHERE us.user_id = ?
            ORDER BY s.skill_name ASC
        ");
        $skillStmt->execute([$target_user_id]);
        $profile_skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("User Skills Fetch Error (profile.php): " . $e->getMessage());
    }
}

// 6. Fetch Mentorship Status (Trainee viewing Trainer)
$mentorship_status = null;
if (!$user_not_found && !$is_own_profile && $current_user_role === 'trainee' && $profile_user['role'] === 'trainer') {
    try {
        $statusStmt = $pdo->prepare("
            SELECT status 
            FROM mentorships 
            WHERE trainee_id = ? 
              AND trainer_id = ? 
              AND status IN ('pending', 'accepted')
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $statusStmt->execute([$current_user_id, $target_user_id]);
        $active_request = $statusStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($active_request) {
            $mentorship_status = $active_request['status'];
        }
    } catch (PDOException $e) {
        error_log("Mentorship Status Fetch Error (profile.php): " . $e->getMessage());
    }
}

// 7. Output Header
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<div class="dashboard-layout">

    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <h2 class="sidebar-logo">SkillConnect</h2>
        <nav class="sidebar-nav">
            <p class="sidebar-section-title">Main</p>
            <a href="dashboard.php" class="sidebar-link">Dashboard</a>
            
            <?php if ($current_user_role === 'trainee'): ?>
                <a href="search.php" class="sidebar-link">Find Trainers</a>
            <?php endif; ?>
            
            <!-- Highlight 'Profile' only if viewing own profile -->
            <a href="profile.php" class="sidebar-link <?= $is_own_profile ? 'active' : '' ?>">Profile</a>
            <a href="edit_profile.php" class="sidebar-link">Edit Profile</a>
            
            <p class="sidebar-section-title">Explore</p>
            <a href="#" onclick="alert('Stay tuned — this feature is planned for a future version of SkillConnect.'); return false;" class="sidebar-link future">Communities</a>
            <a href="#" onclick="alert('Stay tuned — this feature is planned for a future version of SkillConnect.'); return false;" class="sidebar-link future">Leaderboard</a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-logout">Logout</a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger profile-error-container">
                <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <header class="page-header">
            <h1 class="page-title"><?= $is_own_profile ? 'My Profile' : 'User Profile' ?></h1>
            <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
        </header>

        <?php if ($user_not_found): ?>
            <!-- Error State: User not found -->
            <div class="alert alert-danger profile-error-container">
                The requested user profile does not exist or could not be found.
            </div>
            <a href="dashboard.php" class="action-button btn-return">Return to Dashboard</a>
        <?php else: ?>

            <div class="content-grid align-start">
                
                <!-- Profile Identity Card -->
                <section class="dashboard-card profile-identity-card">
                    <?php 
                        $photo_filename = !empty($profile_user['profile_photo']) ? $profile_user['profile_photo'] : 'default.jpg';
                        $photo_path = 'assets/images/profile/' . htmlspecialchars($photo_filename, ENT_QUOTES, 'UTF-8');
                    ?>
                    <img src="<?= $photo_path ?>" alt="Profile Photo" class="profile-display-photo">
                    
                    <div class="profile-details">
                        <h2 class="profile-name">
                            <?= htmlspecialchars($profile_user['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h2>
                        
                        <!-- Role Badge -->
                        <span class="profile-role-badge">
                            <?= htmlspecialchars(ucfirst($profile_user['role']), ENT_QUOTES, 'UTF-8') ?>
                        </span>

                        <div class="profile-meta">
                            <!-- Show Experience -->
                            <div>
                                <strong>Experience:</strong> 
                                <?= !empty($profile_user['exp_level']) ? htmlspecialchars($profile_user['exp_level'], ENT_QUOTES, 'UTF-8') : 'Not specified' ?>
                            </div>
                            
                            <!-- Show Email ONLY if it is the user's own profile -->
                            <?php if ($is_own_profile && !empty($profile_user['email'])): ?>
                                <div>
                                    <strong>Email:</strong> 
                                    <?= htmlspecialchars($profile_user['email'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Area -->
                    <div class="profile-action-area">
                        <?php if ($is_own_profile): ?>
                            <a href="edit_profile.php" class="action-button btn-profile">Edit Profile</a>
                        <?php elseif ($current_user_role === 'trainee' && $profile_user['role'] === 'trainer'): ?>
                            
                            <?php if ($mentorship_status === 'pending'): ?>
                                <button disabled class="submit-btn btn-profile btn-disabled">
                                    Mentorship Request Pending
                                </button>
                            <?php elseif ($mentorship_status === 'accepted'): ?>
                                <button disabled class="submit-btn btn-profile btn-disabled">
                                    Already Connected
                                </button>
                            <?php else: ?>
                                <form method="POST" action="actions/mentorship_process.php">
                                    <input type="hidden" name="action" value="send_request">
                                    <input type="hidden" name="trainer_id" value="<?= (int)$target_user_id ?>">
                                    <button type="submit" class="submit-btn btn-profile">Request Mentorship</button>
                                </form>
                            <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </section>

                <!-- About / Bio Section -->
                <section class="dashboard-card">
                    <h2 class="card-title section-title">About</h2>
                    <p class="profile-bio-text">
                        <?php if (!empty($profile_user['bio'])): ?>
                            <?= htmlspecialchars($profile_user['bio'], ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            <span class="profile-bio-empty">No bio added yet.</span>
                        <?php endif; ?>
                    </p>
                </section>

                <!-- Skills Section -->
                <section class="dashboard-card">
                    <h2 class="card-title section-title">
                        <?= $profile_user['role'] === 'trainee' ? 'Skills I Want to Learn' : 'Skills I Teach' ?>
                    </h2>

                    <div class="profile-skills-wrapper">
                        <?php if (empty($profile_skills)): ?>
                            <div class="empty-state">
                                <p class="empty-state-text">No skills added yet.</p>
                            </div>
                        <?php else: ?>
                            <ul class="skill-list">
                                <?php foreach ($profile_skills as $skill): ?>
                                    <li class="skill-item">
                                        <span class="skill-name"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>

            </div>

        <?php endif; ?>
    </main>
</div>

<?php 
// 8. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
