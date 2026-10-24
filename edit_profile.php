<?php
// edit_profile.php

// 1. Session initialization and core includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token for the new security forms
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

// 2. Authentication & Identity
requireLogin();
$user_id = getCurrentUserId();

// 3. Fetch current user profile data (Added security fields)
try {
    // We use a broader select here in case your password column is named differently
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("System error: Authenticated user record could not be found.");
    }
} catch (PDOException $e) {
    error_log("Profile Fetch Error: " . $e->getMessage());
    die("An unexpected error occurred while loading your profile.");
}

// 3b. Determine Account Recovery Status
$recovery_configured = !empty($user['security_question'])
    && !empty($user['security_answer_hash']);


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

// 5. PROFILE COMPLETION CALCULATION
$points = 0;

$hasPhoto = (!empty($user['profile_photo']) && $user['profile_photo'] !== 'default.jpg');
$hasName  = !empty(trim($user['name'] ?? ''));
$hasEmail = !empty(trim($user['email'] ?? ''));
$hasExp   = !empty($user['exp_level']);
$hasBio   = !empty(trim($user['bio'] ?? ''));
$skillPts = min(3, $current_skill_count);

if ($hasPhoto) $points += 1;
if ($hasName)  $points += 1;
if ($hasEmail) $points += 1;
if ($hasExp)$points += 1;
if ($hasBio)$points += 1;
$points +=$skillPts;

$maxPoints = 8;
$completionPercentage = round(($points / $maxPoints) * 100);

$circleRadius = 45;
$circleCircumference = 2 * pi() *$circleRadius;
$circleOffset =$circleCircumference - (($completionPercentage / 100) *$circleCircumference);

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
            <p class="page-subtitle">Manage your profile information and account settings.</p>
        </div>
    </header>

    <!-- Profile settings navigation -->
    <nav class="edit-profile-tabs" aria-label="Profile settings sections" role="tablist">
        <button type="button" class="tab-btn active" id="tab-personal-button"
                role="tab" aria-selected="true" tabindex="0" aria-controls="tab-personal" data-target="tab-personal">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="8" r="4"></circle>
                <path d="M4.5 21v-1.5a7.5 7.5 0 0 1 15 0V21"></path>
            </svg>
            <span>Personal Information</span>
        </button>
        <button type="button" class="tab-btn" id="tab-security-button"
                role="tab" aria-selected="false" tabindex="-1" aria-controls="tab-security" data-target="tab-security">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path>
                <rect x="9" y="10" width="6" height="5" rx="1"></rect>
                <path d="M10.5 10V8.5a1.5 1.5 0 0 1 3 0V10"></path>
            </svg>
            <span>Security &amp; Recovery</span>
        </button>
    </nav>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="profile-alert alert-success js-toast-target">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="profile-alert alert-danger js-toast-target">
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>


    <!-- TAB 1: Personal Information -->
    <div id="tab-personal" class="tab-content active">
        <div class="edit-profile-grid">
            
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

                    <div class="photo-upload-container">
                        <img src="<?= getProfilePhotoUrl($user['profile_photo'] ?? '',$user['name']) ?>" alt="Profile Photo" class="photo-preview">
                        <div class="photo-upload-controls">
                            <h3>Profile Photo</h3>
                            <p>Upload a new profile photo (JPG, PNG, WEBP)</p>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg, image/png, image/webp" class="file-input-clean">
                        </div>
                    </div>

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

            <div class="edit-right-column">
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

                        <div class="completion-list-col">
                            <?php 
                            $checklistItems = [
                                ['label' => 'Profile Photo', 'done' => $hasPhoto, 'status' =>$hasPhoto ? 'Completed' : 'Pending'],
                                ['label' => 'Full Name', 'done' => $hasName, 'status' =>$hasName ? 'Completed' : 'Pending'],
                                ['label' => 'Email Address', 'done' => $hasEmail, 'status' =>$hasEmail ? 'Completed' : 'Pending'],
                                ['label' => 'Experience Level', 'done' => $hasExp, 'status' =>$hasExp ? 'Completed' : 'Pending'],
                                ['label' => 'Bio', 'done' => $hasBio, 'status' =>$hasBio ? 'Completed' : 'Pending'],
                                ['label' => 'Add 3 Skills', 'done' => ($skillPts === 3), 'status' =>$skillPts . '/3 added']
                            ];
                            
                            foreach ($checklistItems as$item): 
                                $isDone =$item['done'];
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


    <!-- TAB 2: Security -->
    <div id="tab-security" class="tab-content">
        <div style="max-width: 850px;">
            
            <!-- Change Password Card -->
            <section class="card edit-card">
                <div class="security-card-header">
                    <div class="security-header-left">
                        <div class="header-icon-blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
                        </div>
                        <div class="header-text">
                            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; color: #0f172a;">Change Password</h2>
                            <p style="font-size: 0.875rem; color: #64748b;">Update your account password. You'll need to enter your current password to make changes.</p>
                        </div>
                    </div>
                </div>

                <form action="actions/security_update.php" method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group" style="max-width: 400px;">
                        <label class="form-label" for="current_password">Current Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="current_password" name="current_password" required class="form-input" placeholder="Enter your current password">
                            <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group-split">
                        <div class="half-width form-group">
                            <label class="form-label" for="new_password">New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password" required minlength="8" class="form-input" placeholder="Enter new password">
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>
                        <div class="half-width form-group">
                            <label class="form-label" for="confirm_new_password">Confirm New Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_new_password" name="confirm_new_password" required minlength="8" class="form-input" placeholder="Re-enter new password">
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="password-reqs" style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div>
                            <p>Password requirements:</p>
                            <ul>
                                <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> At least 8 characters</li>
                                <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Include a number</li>
                                <li><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Include a special character (e.g. @, #, $, %)</li>
                            </ul>
                        </div>
                        <button type="submit" class="btn btn-primary" data-loading-text="Updating...">Update Password</button>
                    </div>
                </form>
            </section>

            <!-- Account Recovery Card -->
            <section class="card edit-card">
                <div class="security-card-header">
                    <div class="security-header-left">
                        <div class="header-icon-blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </div>
                        <div class="header-text">
                            <h2 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; color: #0f172a;">Account Recovery</h2>
                            <p style="font-size: 0.875rem; color: #64748b;">Set up a security question and answer to help you recover your account if you ever forget your password.</p>
                        </div>
                    </div>
                    <?php if ($recovery_configured): ?>
                        <div class="security-status-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Recovery configured
                        </div>
                    <?php endif; ?>
                </div>

                <form action="actions/security_update.php" method="POST">
                    <input type="hidden" name="action" value="update_recovery">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-group">
                        <label class="form-label" for="security_question">Security Question</label>
                        <select id="security_question" name="security_question" class="form-input" required>
                            <option value="">Select a security question...</option>
                            <option value="school" <?= (($user['security_question'] ?? '') === 'school') ? 'selected' : '' ?>>What was the name of your first school?</option>
                            <option value="pet" <?= (($user['security_question'] ?? '') === 'pet') ? 'selected' : '' ?>>What was the name of your first pet?</option>
                            <option value="nickname" <?= (($user['security_question'] ?? '') === 'nickname') ? 'selected' : '' ?>>What was your childhood nickname?</option>
                            <option value="city" <?= (($user['security_question'] ?? '') === 'city') ? 'selected' : '' ?>>In what city were you born?</option>
                        </select>
                    </div>

                    <div class="form-group-split">
                        <div class="half-width form-group">
                            <label class="form-label" for="security_answer">Your Answer</label>
                            <div class="password-wrapper">
                                <input type="password" id="security_answer" name="security_answer" class="form-input" placeholder="<?= $recovery_configured ? 'Enter new answer (leave blank to keep current)' : 'Enter your answer' ?>" <?= $recovery_configured ? '' : 'required' ?>>
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show answer">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>
                        <div class="half-width form-group">
                            <label class="form-label" for="confirm_security_answer">Confirm Answer</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_security_answer" name="confirm_security_answer" class="form-input" placeholder="<?= $recovery_configured ? 'Re-enter new answer' : 'Re-enter your answer' ?>" <?= $recovery_configured ? '' : 'required' ?>>
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show answer">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="info-alert" style="display: flex; align-items: center; justify-content: space-between; background: #f8fafc; padding: 1.25rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 1rem;">
                        <div style="display: flex; gap: 12px; align-items: flex-start; flex: 1;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-top: 3px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                            <div style="flex: 1;">
                                <p style="margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #475569;">To change your recovery answer, you may be required to enter your current password for security.</p>
                                <div class="password-wrapper" style="max-width: 350px;">
                                    <input type="password" name="current_password_recovery" required class="form-input" placeholder="Enter current password" style="padding: 0.6rem 1rem; font-size: 0.875rem;">
                                    <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" data-loading-text="Saving..."><?= $recovery_configured ? 'Update Recovery Info' : 'Save Recovery Info' ?></button>
                    </div>
                </form>
            </section>
        </div>
    </div>

</div>

<!-- Inline Javascript for Tabs and Modal to bypass browser caching -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. TAB SWITCHING LOGIC
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    if (tabBtns.length > 0) {
        function switchTab(targetId) {
            tabBtns.forEach(btn => {
                btn.classList.remove('active');
                btn.setAttribute('aria-selected', 'false');
                btn.setAttribute('tabindex', '-1');
            });
            tabContents.forEach(content => content.classList.remove('active'));

            const activeBtn = document.querySelector(`.tab-btn[data-target="${targetId}"]`);
            const activeContent = document.getElementById(targetId);

            if (activeBtn && activeContent) {
                activeBtn.classList.add('active');
                activeBtn.setAttribute('aria-selected', 'true');
                activeBtn.setAttribute('tabindex', '0');
                activeContent.classList.add('active');
                
                history.replaceState(null, null, `#${targetId.replace('tab-', '')}`);
            }
        }

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                switchTab(btn.getAttribute('data-target'));
            });
        });

        // Deep-link routing 
        if (window.location.hash === '#security') {
            switchTab('tab-security');
        }
    }
});
</script>

<?php 
// 7. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
