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
$page_title = $is_own_profile ? 'My Profile - SkillConnect' : 'User Profile - SkillConnect';
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<div class="profile-page">

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="profile-alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="profile-alert alert-danger">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Page Header -->
    <header class="profile-header-top">
        <div class="header-titles">
            <h1 class="page-title"><?= $is_own_profile ? 'My Profile' : 'User Profile' ?></h1>
            <p class="page-subtitle">
                <?= $is_own_profile ? 'Manage your profile and showcase your skills.' : 'Explore this learner\'s or trainer\'s profile.' ?>
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Dashboard
        </a>
    </header>

    <?php if ($user_not_found): ?>
        <!-- Error State: User not found -->
        <div class="profile-alert alert-danger">
            The requested user profile does not exist or could not be found.
        </div>
    <?php else: ?>

        <!-- Profile Identity Card -->
        <section class="card profile-identity-card">
            <?php 
                $photo_filename = !empty($profile_user['profile_photo']) ? $profile_user['profile_photo'] : 'default.jpg';
                $photo_path = 'assets/images/profile/' . htmlspecialchars($photo_filename, ENT_QUOTES, 'UTF-8');
            ?>
            <div class="profile-avatar-wrapper">
                <img src="<?= $photo_path ?>" alt="Profile Photo" class="profile-display-photo">
            </div>
            
            <div class="profile-details">
                <h2 class="profile-name">
                    <?= htmlspecialchars($profile_user['name'], ENT_QUOTES, 'UTF-8') ?>
                </h2>
                
                <!-- Role Badge -->
                <div class="profile-role-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <?= htmlspecialchars(ucfirst($profile_user['role']), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div class="profile-meta-grid">
                    <!-- Experience -->
                    <div class="meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                        <strong>Experience:</strong> 
                        <span><?= !empty($profile_user['exp_level']) ? htmlspecialchars(ucfirst($profile_user['exp_level']), ENT_QUOTES, 'UTF-8') : 'Not specified' ?></span>
                    </div>
                    
                    <!-- Email (ONLY if own profile) -->
                    <?php if ($is_own_profile && !empty($profile_user['email'])): ?>
                        <div class="meta-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <strong>Email:</strong> 
                            <span><?= htmlspecialchars($profile_user['email'], ENT_QUOTES, 'UTF-8') ?></span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lock-icon"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <span class="meta-note">(Only visible to you)</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Area -->
            <div class="profile-action-area">
                <?php if ($is_own_profile): ?>
                    <a href="edit_profile.php" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        Edit Profile
                    </a>
                <?php elseif ($current_user_role === 'trainee' && $profile_user['role'] === 'trainer'): ?>
                    
                    <?php if ($mentorship_status === 'pending'): ?>
                        <button disabled class="btn btn-secondary btn-status-pending">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Mentorship Request Pending
                        </button>
                    <?php elseif ($mentorship_status === 'accepted'): ?>
                        <button disabled class="btn btn-status-accepted">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            Already Connected
                        </button>
                    <?php else: ?>
                        <!-- STRICTLY PRESERVED POST ACTION -->
                        <form method="POST" action="actions/mentorship_process.php" class="profile-action-form">
                            <input type="hidden" name="action" value="send_request">
                            <input type="hidden" name="trainer_id" value="<?= (int)$target_user_id ?>">
                            <button type="submit" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                                Request Mentorship
                            </button>
                        </form>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </section>

        <!-- Content Grid (About & Skills) -->
        <div class="profile-content-grid">
            
            <!-- About Section -->
            <section class="card profile-about-card">
                <div class="card-header">
                    <h2 class="card-title profile-section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="4"></circle><line x1="21.17" y1="8" x2="12" y2="8"></line><line x1="3.95" y1="6.06" x2="8.54" y2="14"></line><line x1="10.88" y1="21.94" x2="15.46" y2="14"></line></svg>
                        About
                    </h2>
                </div>
                <div class="profile-bio-text">
                    <?php if (!empty($profile_user['bio'])): ?>
                        <p><?= nl2br(htmlspecialchars($profile_user['bio'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php else: ?>
                        <p class="profile-bio-empty">No bio added yet.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Skills Section -->
            <section class="card profile-skills-card">
                <div class="card-header">
                    <h2 class="card-title profile-section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                        <?= $profile_user['role'] === 'trainee' ? 'Skills I Want to Learn' : 'Skills I Teach' ?>
                    </h2>
                </div>

                <div class="profile-skills-wrapper">
                    <?php if (empty($profile_skills)): ?>
                        <p class="profile-bio-empty">No skills added yet.</p>
                    <?php else: ?>
                        <div class="profile-skill-chips">
                            <?php foreach ($profile_skills as $skill): ?>
                                <span class="skill-chip">
                                    <?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        </div>

    <?php endif; ?>

</div>

<?php 
// 8. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
