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

// 4. Fetch user's current skills (Kept for Profile Completion Calculation)
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

// ============================================================
// PROFILE COMPLETION CALCULATION (DERIVED UI - NO DB CHANGE)
// ============================================================
$points = 0;

$hasPhoto = (!empty($user['profile_photo']) && $user['profile_photo'] !== 'default.jpg');
$hasName  = !empty(trim($user['name']));
$hasEmail = !empty(trim($user['email']));
$hasExp   = !empty($user['exp_level']);
$hasBio   = !empty(trim($user['bio']));
$skillPts = min(3, $current_skill_count); // Cap at 3 for calculation

if ($hasPhoto) $points += 1;
if ($hasName)  $points += 1;
if ($hasEmail) $points += 1;
if ($hasExp)   $points += 1;
if ($hasBio)   $points += 1;
$points += $skillPts;

$maxPoints = 8;
$completionPercentage = round(($points / $maxPoints) * 100);

// SVG Circle Math
$circleRadius = 45;
$circleCircumference = 2 * pi() * $circleRadius;
$circleOffset = $circleCircumference - (($completionPercentage / 100) * $circleCircumference);

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
            <p class="page-subtitle">Update your basic details and profile information.</p>
        </div>
    </header>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="profile-alert alert-success js-toast-target">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="profile-alert alert-danger js-toast-target">
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
                <div class="header-icon-blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                </div>
                <div class="header-text">
                    <h2>Personal Information</h2>
                    <p>Update your basic details and profile information.</p>
                </div>
            </div>
            
            <form action="actions/profile_update.php" method="POST" enctype="multipart/form-data" class="edit-profile-form" novalidate>
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

                <button type="submit" class="btn btn-primary full-width-btn" style="margin-top: 1.5rem;" data-loading-text="Saving...">
                    Save Changes
                </button>
            </form>
        </section>

        <!-- RIGHT COLUMN: New Cards -->
        <div class="edit-right-column">
            
            <!-- CARD 1: Profile Completion -->
            <section class="card edit-card profile-completion-card">
                <div class="edit-card-header" style="border-bottom: none; padding-bottom: 0.5rem; margin-bottom: 1rem;">
                    <div class="header-icon-orange">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                    </div>
                    <div class="header-text" style="flex:1;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <h2 style="margin-bottom: 0;">Profile Completion</h2>
                        </div>
                        <p style="margin-top: 0.25rem;">Show how complete the user's profile is using existing data.</p>
                    </div>
                </div>

                <div class="completion-layout">
                    <!-- Left: Circular Indicator -->
                    <div class="completion-chart-col">
                        <div class="circular-chart-wrapper">
                            <svg class="circular-chart" viewBox="0 0 120 120" width="120" height="120">
                                <circle class="circle-bg" cx="60" cy="60" r="<?= $circleRadius ?>" fill="none" stroke="#e2e8f0" stroke-width="12"></circle>
                                <circle class="circle-progress" cx="60" cy="60" r="<?= $circleRadius ?>" fill="none" stroke="#10b981" stroke-width="12" stroke-linecap="round" 
                                    stroke-dasharray="<?= $circleCircumference ?>" 
                                    stroke-dashoffset="<?= $circleOffset ?>" 
                                    transform="rotate(-90 60 60)"></circle>
                            </svg>
                            <div class="percentage-text"><?= $completionPercentage ?>%</div>
                        </div>
                        <div class="chart-text">
                            <strong>Profile Complete</strong>
                            <p>Add a few more details to make your profile stand out.</p>
                        </div>
                    </div>

                    <!-- Right: Checklist -->
                    <div class="completion-list-col">
                        <?php 
                        $checklistItems = [
                            ['label' => 'Profile Photo', 'done' => $hasPhoto, 'status' => $hasPhoto ? 'Completed' : 'Pending'],
                            ['label' => 'Full Name', 'done' => $hasName, 'status' => $hasName ? 'Completed' : 'Pending'],
                            ['label' => 'Email Address', 'done' => $hasEmail, 'status' => $hasEmail ? 'Completed' : 'Pending'],
                            ['label' => 'Experience Level', 'done' => $hasExp, 'status' => $hasExp ? 'Completed' : 'Pending'],
                            ['label' => 'Bio', 'done' => $hasBio, 'status' => $hasBio ? 'Completed' : 'Pending'],
                            ['label' => 'Add 3 Skills', 'done' => ($skillPts === 3), 'status' => $skillPts . '/3 added']
                        ];
                        
                        foreach ($checklistItems as $item): 
                            $isDone = $item['done'];
                        ?>
                            <div class="checklist-item">
                                <div class="item-label">
                                    <?php if ($isDone): ?>
                                        <svg class="check-icon done" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#10b981"></circle><polyline points="8 12 11 15 16 9" stroke="#ffffff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>
                                    <?php else: ?>
                                        <svg class="check-icon pending" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="#cbd5e1" stroke-width="2"></circle></svg>
                                    <?php endif; ?>
                                    <span><?= $item['label'] ?></span>
                                </div>
                                <span class="item-status <?= $isDone ? 'status-done' : 'status-pending' ?>"><?= $item['status'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- CARD 2: Manage Your Skills -->
            <section class="card edit-card manage-skills-card">
                 <div class="edit-card-header" style="border-bottom: none; margin-bottom: 0.5rem; padding-bottom: 0;">
                    <div class="header-icon-blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    </div>
                    <div class="header-text">
                        <h2>Manage Your Skills</h2>
                        <p style="margin-top: 0.25rem; font-size: 0.9rem; line-height: 1.5; color: #475569;">
                            Add, remove or update the skills you want to <?= $user['role'] === 'trainer' ? 'teach' : 'learn' ?>.<br>
                            You can manage up to 3 skills in your profile.
                        </p>
                        
                        <a href="my_skills.php" class="btn btn-primary" style="margin-top: 1.25rem;">
                            Go to My Skills &rarr;
                        </a>
                    </div>
                </div>
            </section>

        </div>
    </div>

</div>

<?php 
// 7. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
