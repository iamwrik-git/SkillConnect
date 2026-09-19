<?php
// edit_profile.php

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
$user_id = getCurrentUserId();

// 3. Fetch current user profile data
try {
    $stmt = $pdo->prepare("SELECT user_id, name, email, profile_photo, bio, exp_level, role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("System error: Authenticated user record could not be found.");
    }
} catch (PDOException $e) {
    error_log("Profile Fetch Error: " . $e->getMessage());
    die("An unexpected error occurred while loading your profile.");
}

// Helper for UI-Avatars (Fallback for profile photo)
if (!function_exists('getProfilePhotoUrl')) {
    function getProfilePhotoUrl($photo, $name) {
        if (empty($photo) || $photo === 'default.jpg') {
            return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=eff6ff&color=2563eb&size=120&bold=true";
        }
        return 'assets/images/profile/' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8');
    }
}

// 4. Fetch user's current skills
$user_skills = [];
try {
    $skillStmt = $pdo->prepare("
        SELECT s.skill_id, s.skill_name 
        FROM skills s 
        JOIN user_skills us ON s.skill_id = us.skill_id 
        WHERE us.user_id = ?
    ");
    $skillStmt->execute([$user_id]);
    $user_skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User Skills Fetch Error: " . $e->getMessage());
}

$current_skill_count = count($user_skills);

// 5. Fetch available skills for the dropdown (exclude already added ones)
$available_skills = [];
if ($current_skill_count < 3) {
    try {
        $availStmt = $pdo->prepare("
            SELECT skill_id, skill_name 
            FROM skills 
            WHERE skill_id NOT IN (
                SELECT skill_id FROM user_skills WHERE user_id = ?
            )
            ORDER BY skill_name ASC
        ");
        $availStmt->execute([$user_id]);
        $available_skills = $availStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Available Skills Fetch Error: " . $e->getMessage());
    }
}

// 6. Output Header
$page_title = 'Edit Profile - SkillConnect';
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<div class="edit-profile-page">

    <!-- Page Header -->
    <header class="profile-header-top">
        <div>
            <h1 class="page-title">Edit Profile</h1>
            <p class="page-subtitle">Update your information and manage your skills.</p>
        </div>
        <a href="dashboard.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Dashboard
        </a>
    </header>

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

    <!-- Main Content Grid -->
    <div class="edit-profile-grid">
        
        <!-- LEFT COLUMN: Personal Information -->
        <section class="card edit-card">
            <div class="edit-card-header">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <div class="header-text">
                    <h2>Personal Information</h2>
                    <p>Update your basic details and profile information.</p>
                </div>
            </div>
            
            <form action="actions/profile_update.php" method="POST" enctype="multipart/form-data" class="edit-profile-form">
                <input type="hidden" name="action" value="update_profile">

                <!-- Profile Photo Configuration -->
                <div class="photo-upload-container">
                    <img src="<?= getProfilePhotoUrl($user['profile_photo'] ?? '', $user['name']) ?>" alt="Profile Photo" class="photo-preview">
                    <div class="photo-upload-controls">
                        <h3>Profile Photo</h3>
                        <p>Upload a new profile photo (JPG, PNG, WEBP)</p>
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg, image/png, image/webp" class="file-input-clean">
                    </div>
                </div>

                <!-- Input Fields -->
                <div class="form-group">
                    <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address (Read-Only)</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" readonly class="form-input readonly">
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">Account Role (Read-Only)</label>
                    <input type="text" id="role" value="<?= htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?>" readonly class="form-input readonly">
                </div>

                <div class="form-group">
                    <label class="form-label" for="exp_level">Experience Level</label>
                    <select id="exp_level" name="exp_level" class="form-input">
                        <option value="">Not specified</option>
                        <option value="Beginner" <?= ($user['exp_level'] ?? '') === 'Beginner' ? 'selected' : '' ?>>Beginner</option>
                        <option value="Intermediate" <?= ($user['exp_level'] ?? '') === 'Intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="Expert" <?= ($user['exp_level'] ?? '') === 'Expert' ? 'selected' : '' ?>>Expert</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="bio">Bio</label>
                    <textarea id="bio" name="bio" rows="4" placeholder="Tell us about yourself..." class="form-input text-area"><?= htmlspecialchars($user['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary full-width-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Save Profile Changes
                </button>
            </form>
        </section>

        <!-- RIGHT COLUMN: Skills Management -->
        <section class="card edit-card">
            <div class="edit-card-header">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                <div class="header-text">
                    <h2>Skills Management</h2>
                    <p>Add or remove skills (Maximum 3 skills).</p>
                </div>
            </div>

            <!-- Current Skills Container -->
            <div class="current-skills-box">
                <div class="box-header">
                    <div class="box-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <h3>Current Skills <span class="skill-count">( <?= $current_skill_count ?> / 3 )</span></h3>
                    </div>
                    <p>These are the skills associated with your profile.</p>
                </div>

                <?php if ($current_skill_count === 0): ?>
                    <p class="text-muted italic">You haven't added any skills yet.</p>
                <?php else: ?>
                    <div class="edit-skill-list">
                        <?php foreach ($user_skills as $skill): ?>
                            <div class="edit-skill-item">
                                <span class="skill-name"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                
                                <form action="actions/skill_process.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="remove_skill">
                                    <input type="hidden" name="skill_id" value="<?= (int)$skill['skill_id'] ?>">
                                    <button type="submit" class="btn-remove-skill">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Remove
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Skill Container -->
            <div class="add-skill-section">
                <div class="box-title" style="margin-bottom: 0.25rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    <h3>Add a New Skill</h3>
                </div>
                <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.875rem;">Select a skill from the list to add to your profile.</p>

                <?php if ($current_skill_count >= 3): ?>
                    <div class="edit-info-alert alert-warning">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                        <div>
                            <strong>Skill Limit Information</strong>
                            <p>You can add a maximum of 3 skills. Please remove a skill to add a new one.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <form action="actions/skill_process.php" method="POST" class="add-skill-flex">
                        <input type="hidden" name="action" value="add_skill">
                        
                        <select name="skill_id" required class="form-input" style="flex: 1;">
                            <option value="">-- Select a Skill --</option>
                            <?php foreach ($available_skills as $avail_skill): ?>
                                <option value="<?= (int)$avail_skill['skill_id'] ?>">
                                    <?= htmlspecialchars($avail_skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-primary" style="gap: 0.5rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Add
                        </button>
                    </form>
                    
                    <div class="edit-info-alert alert-info">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        You can add up to 3 skills. (<?= $current_skill_count ?> / 3)
                    </div>
                <?php endif; ?>
            </div>

        </section>
    </div>

</div>

<?php 
// 7. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
