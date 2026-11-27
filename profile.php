<?php
// profile.php

// 1. Session initialization and core includes
require_once __DIR__ . '/includes/session.php';
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

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
$active_tab = ($_GET['tab'] ?? '') === 'skills' ? 'skills' : 'profile';

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

// 6. Fetch Available Skills for Adding (if own profile)
$all_skills = [];
if ($is_own_profile && !$user_not_found) {
    try {
        $allSkillsStmt = $pdo->prepare("
            SELECT skill_id, skill_name 
            FROM skills 
            WHERE skill_id NOT IN (
                SELECT skill_id FROM user_skills WHERE user_id = ?
            )
            ORDER BY skill_name ASC
        ");
        $allSkillsStmt->execute([$current_user_id]);
        $all_skills = $allSkillsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("All Skills Fetch Error (profile.php): " . $e->getMessage());
    }
}

// 6b. Check Profile Incompleteness (Bio, Expertise Level for trainers, and Skills)
$is_profile_incomplete = false;
$missing_profile_fields = [];

if ($is_own_profile && !$user_not_found) {
    // 1. Check bio
    if (trim((string)($profile_user['bio'] ?? '')) === '') {
        $missing_profile_fields[] = [
            'type' => 'bio',
            'label' => 'Bio',
            'desc' => 'Add a short bio to introduce yourself.'
        ];
    }
    // 2. Check expertise level (trainer only)
    if ($current_user_role === 'trainer') {
        $exp = (string)($profile_user['exp_level'] ?? '');
        if (!in_array($exp, ['Beginner', 'Intermediate', 'Expert'], true)) {
            $missing_profile_fields[] = [
                'type' => 'exp_level',
                'label' => 'Expertise Level',
                'desc' => 'Select your experience or teaching level.'
            ];
        }
    }
    // 3. Check skills
    if (empty($profile_skills)) {
        $missing_profile_fields[] = [
            'type' => 'skills',
            'label' => 'Skills',
            'desc' => ($current_user_role === 'trainer')
                ? 'Add at least one skill you can teach.'
                : 'Add at least one skill you want to learn.'
        ];
    }

    if (!empty($missing_profile_fields)) {
        $is_profile_incomplete = true;
    }
}

// 7. Fetch Mentorship Status
$mentorship_status = null;
$is_connected_mentorship = false;
if (!$user_not_found && !$is_own_profile) {
    try {
        if ($current_user_role === 'trainee' && $profile_user['role'] === 'trainer') {
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
        }

        $connCheck = $pdo->prepare("
            SELECT 1 FROM mentorships 
            WHERE status = 'accepted'
              AND ((trainer_id = ? AND trainee_id = ?) OR (trainee_id = ? AND trainer_id = ?))
            LIMIT 1
        ");
        $connCheck->execute([$current_user_id, $target_user_id, $current_user_id, $target_user_id]);
        if ($connCheck->fetchColumn()) {
            $is_connected_mentorship = true;
        }
    } catch (PDOException $e) {
        error_log("Mentorship Status Fetch Error (profile.php): " . $e->getMessage());
    }
}

// 8. Output Header
$page_title = $is_own_profile ? 'Profile - SkillConnect' : 'User Profile - SkillConnect';

$profile_has_custom_photo = !empty($profile_user['profile_photo']) && $profile_user['profile_photo'] !== 'default.jpg';
$profile_initials = 'U';
if (!$profile_has_custom_photo && !empty($profile_user['name'])) {
    $p_parts = array_values(array_filter(preg_split('/\s+/', trim($profile_user['name']))));
    if (count($p_parts) >= 2) {
        $profile_initials = mb_strtoupper(mb_substr($p_parts[0], 0, 1) . mb_substr(end($p_parts), 0, 1));
    } elseif (count($p_parts) === 1 && $p_parts[0] !== '') {
        $profile_initials = mb_strtoupper(mb_substr($p_parts[0], 0, 1));
    }
}

if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<div class="profile-page-wrapper">

    <!-- Flash Messages (Hidden container converted to floating toast) -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="profile-alert alert-success js-toast-target" style="display:none;" role="status">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="profile-alert alert-danger js-toast-target" style="display:none;" role="alert">
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Page Header -->
    <header class="profile-header-top">
        <div>
            <h1 class="page-title"><?= $is_own_profile ? 'Profile' : 'User Profile' ?></h1>
            <p class="page-subtitle">
                <?= $is_own_profile ? 'Manage your profile and showcase your skills.' : 'Explore this member\'s profile and mentorship opportunities.' ?>
            </p>
        </div>
        <?php if (!empty($_SESSION['is_admin'])): ?>
            <?php
            $returnParams = $_GET;
            unset($returnParams['user_id']);
            $returnQs = !empty($returnParams) ? '?' . http_build_query($returnParams) : '';
            ?>
            <a href="admin/manage_users.php<?= htmlspecialchars($returnQs, ENT_QUOTES, 'UTF-8') ?>" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Manage Users
            </a>
        <?php else: ?>
            <a href="dashboard.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Dashboard
            </a>
        <?php endif; ?>
    </header>

    <?php if ($user_not_found): ?>
        <div class="profile-alert alert-danger">
            The requested user profile does not exist or could not be found.
        </div>
    <?php elseif ($is_own_profile): ?>

        <?php if ($is_profile_incomplete): ?>
            <!-- Profile Incomplete Pop-up Alert -->
            <div class="profile-incomplete-backdrop" id="profileIncompleteModal" role="dialog" aria-modal="true" aria-labelledby="profileIncompleteTitle">
                <div class="profile-incomplete-card">
                    <button type="button" class="profile-incomplete-close" id="closeProfileIncompleteBtn" aria-label="Close dialog">&times;</button>
                    
                    <div class="profile-incomplete-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>

                    <h3 class="profile-incomplete-title" id="profileIncompleteTitle">Complete Your Profile</h3>
                    <p class="profile-incomplete-desc">Your profile is missing some important information. Setting up your profile helps you connect with <?= $profile_user['role'] === 'trainer' ? 'mentees' : 'mentors' ?>.</p>

                    <div class="profile-incomplete-list">
                        <?php foreach ($missing_profile_fields as $field): ?>
                            <div class="profile-incomplete-item">
                                <span class="profile-incomplete-bullet">⚠️</span>
                                <div class="profile-incomplete-item-text">
                                    <strong><?= htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars($field['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="profile-incomplete-actions">
                        <button type="button" class="btn btn-secondary" id="dismissProfileIncompleteBtn">Later</button>
                        <?php if (in_array('skills', array_column($missing_profile_fields, 'type'), true) && count($missing_profile_fields) === 1): ?>
                            <button type="button" class="btn btn-primary" id="btnGoToSkillsModal">Add Skills &rarr;</button>
                        <?php elseif (!in_array('skills', array_column($missing_profile_fields, 'type'), true)): ?>
                            <a href="edit_profile.php" class="btn btn-primary">Edit Profile &rarr;</a>
                        <?php else: ?>
                            <a href="edit_profile.php" class="btn btn-outline" style="border-radius:9999px;">Edit Profile</a>
                            <button type="button" class="btn btn-primary" id="btnGoToSkillsModal">Add Skills &rarr;</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Switch Menu: Profile & My Skills -->
        <nav class="edit-profile-tabs" aria-label="Profile navigation" role="tablist">
            <button type="button" class="tab-btn <?= $active_tab === 'profile' ? 'active' : '' ?>" id="tab-profile-button"
                role="tab" aria-selected="<?= $active_tab === 'profile' ? 'true' : 'false' ?>" tabindex="<?= $active_tab === 'profile' ? '0' : '-1' ?>" aria-controls="tab-profile" data-target="tab-profile">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4.5 21v-1.5a7.5 7.5 0 0 1 15 0V21"></path>
                </svg>
                <span>Profile</span>
            </button>
            <button type="button" class="tab-btn <?= $active_tab === 'skills' ? 'active' : '' ?>" id="tab-skills-button"
                role="tab" aria-selected="<?= $active_tab === 'skills' ? 'true' : 'false' ?>" tabindex="<?= $active_tab === 'skills' ? '0' : '-1' ?>" aria-controls="tab-skills" data-target="tab-skills">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                <span>My Skills</span>
            </button>
        </nav>

        <!-- TAB 1: Profile -->
        <div id="tab-profile" class="tab-content <?= $active_tab === 'profile' ? 'active' : '' ?>">
            <!-- Profile Identity Card -->
            <section class="profile-card profile-identity-card">
                <div class="profile-avatar-wrapper">
                    <?php if (!$profile_has_custom_photo): ?>
                        <div class="profile-display-photo profile-initials-avatar" aria-hidden="true"><?= htmlspecialchars($profile_initials, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php else: ?>
                        <img src="<?= getProfilePhotoUrl($profile_user['profile_photo'] ?? '', $profile_user['name']) ?>" alt="Profile Photo" class="profile-display-photo">
                    <?php endif; ?>
                </div>

                <div class="profile-details">
                    <h2 class="profile-name">
                        <?= htmlspecialchars($profile_user['name'], ENT_QUOTES, 'UTF-8') ?>
                    </h2>

                    <div class="profile-role-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <?= htmlspecialchars(($profile_user['role'] === 'trainer' ? 'Mentor' : 'Mentee'), ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div class="profile-meta-grid">
                        <?php if ($profile_user['role'] === 'trainer' && !empty($profile_user['exp_level'])): ?>
                            <div class="meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon">
                                    <line x1="18" y1="20" x2="18" y2="10"></line>
                                    <line x1="12" y1="20" x2="12" y2="4"></line>
                                    <line x1="6" y1="20" x2="6" y2="14"></line>
                                </svg>
                                <strong>Experience:</strong>
                                <span><?= htmlspecialchars(ucfirst($profile_user['exp_level']), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($profile_user['email'])): ?>
                            <div class="meta-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                <strong>Email:</strong>
                                <span><?= htmlspecialchars($profile_user['email'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="meta-note">(Only visible to you)</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Area: Designated Edit Profile Button -->
                <div class="profile-action-area">
                    <a href="edit_profile.php" class="profile-btn profile-btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit Profile
                    </a>
                </div>
            </section>

            <!-- Content Grid (About & Skills Overview) -->
            <div class="profile-content-grid">
                <section class="profile-card">
                    <h2 class="profile-section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <circle cx="12" cy="12" r="4"></circle>
                            <line x1="21.17" y1="8" x2="12" y2="8"></line>
                            <line x1="3.95" y1="6.06" x2="8.54" y2="14"></line>
                            <line x1="10.88" y1="21.94" x2="15.46" y2="14"></line>
                        </svg>
                        About
                    </h2>
                    <div class="profile-bio-text">
                        <?php if (!empty($profile_user['bio'])): ?>
                            <p><?= nl2br(htmlspecialchars($profile_user['bio'], ENT_QUOTES, 'UTF-8')) ?></p>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3 style="font-size: 1rem; color: var(--text-secondary); margin: 0; font-weight: 500;">No bio added yet.</h3>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="profile-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2 class="profile-section-title" style="margin-bottom: 0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                <polyline points="2 17 12 22 22 17"></polyline>
                                <polyline points="2 12 12 17 22 12"></polyline>
                            </svg>
                            <?= $profile_user['role'] === 'trainee' ? 'Skills I Want to Learn' : 'Skills I Teach' ?>
                        </h2>
                        <button type="button" class="btn btn-secondary" data-tab-switch="tab-skills" style="min-height: 32px; padding: 0.35rem 0.75rem; font-size: 0.82rem; border-radius: 8px; cursor: pointer;">
                            Manage Skills &rarr;
                        </button>
                    </div>

                    <div class="profile-skills-body">
                        <?php if (empty($profile_skills)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon" style="width: 44px; height: 44px; margin-bottom: 0.5rem;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="16 18 22 12 16 6"></polyline>
                                        <polyline points="8 6 2 12 8 18"></polyline>
                                    </svg>
                                </div>
                                <h3 style="font-size: 1rem; font-weight: 500; color: var(--text-secondary); margin: 0;">No skills added yet</h3>
                            </div>
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
        </div>

        <!-- TAB 2: My Skills -->
        <div id="tab-skills" class="tab-content <?= $active_tab === 'skills' ? 'active' : '' ?>">
            <?php
            $current_skill_count = count($profile_skills);
            $empty_state_text = ($profile_user['role'] === 'trainee')
                ? 'Add up to 3 skills you would like to learn and improve.'
                : 'Add up to 3 skills you can teach or mentor mentees in.';
            ?>
            <div class="my-skills-grid">
                <!-- LEFT COLUMN: Skills Management -->
                <div class="skills-management-col">
                    <section class="card edit-card" style="padding: 2rem !important; border: 1px solid var(--border-color); border-radius: var(--radius-lg); background-color: var(--surface-color); box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
                        <div class="current-skills-box" style="background: transparent; border: none; padding: 0; margin-bottom: 0;">
                            <div class="box-header" style="margin-bottom: 1.5rem;">
                                <div class="box-title">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <h3 style="font-size: 1.15rem; font-weight: 700; margin-left: 0.25rem;">Current Skills <span class="skill-count" style="font-weight: 500; color: var(--text-secondary);">(<?= $current_skill_count ?> / 3)</span></h3>
                                </div>
                                <p style="margin-left: 2.2rem; margin-top: -0.25rem; font-size: 0.9rem; color: var(--text-secondary);">These are the skills associated with your profile.</p>
                            </div>

                            <?php if (empty($profile_skills)): ?>
                                <!-- Empty State (Compact: Logo & Title only) -->
                                <div class="empty-state" style="box-shadow: none; border: 1px dashed var(--border-color); padding: 1rem 1.25rem; border-radius: var(--radius-md); text-align: center;">
                                    <div class="empty-state-icon" style="width: 38px; height: 38px; margin-bottom: 0.35rem;">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="16 18 22 12 16 6"></polyline>
                                            <polyline points="8 6 2 12 8 18"></polyline>
                                        </svg>
                                    </div>
                                    <h3 style="font-size: 0.95rem; font-weight: 600; margin: 0; color: var(--text-main);">No skills added yet</h3>
                                </div>
                            <?php else: ?>
                                <!-- Current Skills List -->
                                <div class="edit-skill-list">
                                    <?php foreach ($profile_skills as $skill): ?>
                                        <div class="edit-skill-item" style="padding: 1rem 1.25rem;">
                                            <span class="skill-name" style="font-size: 1rem;"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>

                                            <form action="actions/skill_process.php" method="POST" style="margin:0;" class="js-confirm-remove-skill" data-skill-name="<?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="remove_skill">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="skill_id" value="<?= (int)$skill['skill_id'] ?>">
                                                <input type="hidden" name="return_to" value="../profile.php?tab=skills">
                                                <button type="submit" class="btn-remove-skill" data-loading-text="Removing...">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Add Skill Form or Limit Reached Notice -->
                            <?php if ($current_skill_count < 3): ?>
                                <div class="add-skill-section" style="margin-top: 2rem;">
                                    <div class="box-header" style="margin-bottom: 1rem;">
                                        <div class="box-title">
                                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                            <h3 style="font-size: 1.15rem; font-weight: 700; margin-left: 0.25rem;">Add a Skill</h3>
                                        </div>
                                        <p style="margin-left: 2.2rem; margin-top: -0.25rem; font-size: 0.9rem; color: var(--text-secondary);">Select a skill from the list to add it to your profile.</p>
                                    </div>

                                    <form action="actions/skill_process.php" method="POST" class="add-skill-form" style="margin-left: 2.2rem;">
                                        <input type="hidden" name="action" value="add_skill">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="return_to" value="../profile.php?tab=skills">

                                        <div class="add-skill-flex">
                                            <div class="skill-search-combobox" id="skillCombobox">
                                                <input type="hidden" name="skill_id" id="skillIdInput" required>
                                                <div class="skill-combobox-control">
                                                    <svg class="skill-combobox-search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <circle cx="11" cy="11" r="8"></circle>
                                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                                    </svg>
                                                    <input type="text" 
                                                           id="skillSearchInput" 
                                                           class="form-input skill-combobox-input" 
                                                           placeholder="Search or choose a skill..." 
                                                           autocomplete="off"
                                                           role="combobox"
                                                           aria-expanded="false"
                                                           aria-controls="skillDropdownList"
                                                           aria-autocomplete="list">
                                                    <button type="button" class="skill-combobox-toggle" id="skillComboboxToggle" aria-label="Toggle skills list" tabindex="-1">
                                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                            <polyline points="6 9 12 15 18 9"></polyline>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="skill-combobox-dropdown" id="skillDropdownList" hidden>
                                                    <?php if (empty($all_skills)): ?>
                                                        <div class="skill-empty-message">No skills available to add</div>
                                                    <?php else: ?>
                                                        <ul class="skill-options-list" role="listbox">
                                                            <?php foreach ($all_skills as $avail_skill): ?>
                                                                <li class="skill-option-item" 
                                                                    role="option" 
                                                                    data-id="<?= (int)$avail_skill['skill_id'] ?>" 
                                                                    data-name="<?= htmlspecialchars($avail_skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                                    <span><?= htmlspecialchars($avail_skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                        <div class="skill-empty-message" id="skillEmptyMessage" style="display: none;">
                                                            No matching skills found
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary" data-loading-text="Adding..." style="white-space: nowrap;">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 0.4rem; vertical-align: middle;">
                                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                                </svg>
                                                Add Skill
                                            </button>
                                        </div>
                                    </form>


                                </div>
                            <?php else: ?>
                                <div class="limit-reached-banner is-full" style="margin-left: 2.2rem; margin-top: 1.5rem;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                    <span>You have reached the maximum limit of 3 skills. Remove an existing skill if you want to add a different one.</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($current_skill_count < 3): ?>
                                <div class="limit-reached-banner is-normal" style="margin-left: 2.2rem; margin-top: 1.5rem;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    <span>You can add up to 3 skills. (<?= $current_skill_count ?> / 3)</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- RIGHT COLUMN: Why add skills? -->
                <div class="why-skills-col">
                    <section class="card why-skills-card">
                        <div class="edit-card-header" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                            <div class="header-icon-blue" style="width: 40px; height: 40px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    <circle cx="12" cy="11" r="3"></circle>
                                </svg>
                            </div>
                            <div class="header-text">
                                <h2 style="font-size: 1.15rem; margin-top: 8px;">Why add skills?</h2>
                            </div>
                        </div>
                        <div class="why-skills-list">
                            <?php if ($profile_user['role'] === 'trainer'): ?>
                                <div class="why-skills-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span>Help mentees find your expertise in search</span>
                                </div>
                                <div class="why-skills-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span>Receive mentorship requests tailored to what you teach</span>
                                </div>
                                <div class="why-skills-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span>Build credibility and showcase your background</span>
                                </div>
                            <?php else: ?>
                                <div class="why-skills-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span>Help mentors find relevant learners</span>
                                </div>
                                <div class="why-skills-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span>Showcase your learning interests</span>
                                </div>
                                <div class="why-skills-item">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    <span>Make your profile more informative</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- Visitor View (Public Profile) -->
        <section class="profile-card profile-identity-card">
            <div class="profile-avatar-wrapper">
                <?php if (!$profile_has_custom_photo): ?>
                    <div class="profile-display-photo profile-initials-avatar" aria-hidden="true"><?= htmlspecialchars($profile_initials, ENT_QUOTES, 'UTF-8') ?></div>
                <?php else: ?>
                    <img src="<?= getProfilePhotoUrl($profile_user['profile_photo'] ?? '', $profile_user['name']) ?>" alt="Profile Photo" class="profile-display-photo">
                <?php endif; ?>
            </div>

            <div class="profile-details">
                <h2 class="profile-name">
                    <?= htmlspecialchars($profile_user['name'], ENT_QUOTES, 'UTF-8') ?>
                </h2>

                <div class="profile-role-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <?= htmlspecialchars(($profile_user['role'] === 'trainer' ? 'Mentor' : 'Mentee'), ENT_QUOTES, 'UTF-8') ?>
                </div>

                <?php if ($profile_user['role'] === 'trainer' && !empty($profile_user['exp_level'])): ?>
                <div class="profile-meta-grid">
                    <div class="meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="meta-icon">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        <strong>Experience:</strong>
                        <span><?= htmlspecialchars(ucfirst($profile_user['exp_level']), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Action Area: Mentorship Action for Trainee viewing Trainer -->
            <div class="profile-action-area">
                <?php if ($current_user_role === 'trainee' && $profile_user['role'] === 'trainer'): ?>
                    <?php if ($mentorship_status === 'pending'): ?>
                        <button disabled class="profile-btn btn-status-pending">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            Mentorship Request Pending
                        </button>
                    <?php elseif ($mentorship_status === 'accepted'): ?>
                        <div style="display:inline-flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <button disabled class="profile-btn btn-status-accepted">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                Connected
                            </button>
                            <a href="messages.php?user_id=<?= (int)$target_user_id ?>" class="profile-btn profile-btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                                Send Message
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="actions/mentorship_process.php" class="profile-action-form">
                            <input type="hidden" name="action" value="send_request">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="trainer_id" value="<?= (int)$target_user_id ?>">
                            <button type="submit" class="profile-btn profile-btn-primary" data-loading-text="Sending...">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="btn-icon">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                                Request Mentorship
                            </button>
                        </form>
                    <?php endif; ?>
                <?php elseif ($current_user_role === 'trainer' && $profile_user['role'] === 'trainee' && $is_connected_mentorship): ?>
                    <a href="messages.php?user_id=<?= (int)$target_user_id ?>" class="profile-btn profile-btn-primary" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        Send Message
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Content Grid (About & Skills) -->
        <div class="profile-content-grid">
            <section class="profile-card">
                <h2 class="profile-section-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <circle cx="12" cy="12" r="4"></circle>
                        <line x1="21.17" y1="8" x2="12" y2="8"></line>
                        <line x1="3.95" y1="6.06" x2="8.54" y2="14"></line>
                        <line x1="10.88" y1="21.94" x2="15.46" y2="14"></line>
                    </svg>
                    About
                </h2>
                <div class="profile-bio-text">
                    <?php if (!empty($profile_user['bio'])): ?>
                        <p><?= nl2br(htmlspecialchars($profile_user['bio'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3 style="font-size: 1rem; color: var(--text-secondary); margin: 0; font-weight: 500;">No bio added yet.</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="profile-card">
                <h2 class="profile-section-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    <?= $profile_user['role'] === 'trainee' ? 'Skills I Want to Learn' : 'Skills I Teach' ?>
                </h2>

                <div class="profile-skills-body">
                    <?php if (empty($profile_skills)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon" style="width: 44px; height: 44px; margin-bottom: 0.5rem;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="16 18 22 12 16 6"></polyline>
                                    <polyline points="8 6 2 12 8 18"></polyline>
                                </svg>
                            </div>
                            <h3 style="font-size: 1rem; font-weight: 500; color: var(--text-secondary); margin: 0;">No skills added yet</h3>
                        </div>
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
// 9. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
