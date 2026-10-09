<?php
// my_skills.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

// Authentication & Identity
requireLogin();
$user_id = getCurrentUserId();
$user_role = $_SESSION['role'] ?? 'trainee';

// Set role-based wording
if ($user_role === 'trainer') {
    $page_subtitle = "Manage the skills you teach and showcase in your profile.";
    $empty_state_text = "Add the skills you want to teach.";
} else {
    $page_subtitle = "Manage the skills you want to learn and showcase in your profile.";
    $empty_state_text = "Add the skills you want to learn.";
}

// Fetch user's current skills
$user_skills = [];
try {
    $skillStmt = $pdo->prepare("
        SELECT s.skill_id, s.skill_name 
        FROM user_skills us
        JOIN skills s ON us.skill_id = s.skill_id 
        WHERE us.user_id = ?
        ORDER BY s.skill_name ASC
    ");
    $skillStmt->execute([$user_id]);
    $user_skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User Skills Fetch Error: " . $e->getMessage());
}

$current_skill_count = count($user_skills);

// Fetch available skills for the dropdown (exclude already added ones)
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

// Output Header
$page_title = 'My Skills - SkillConnect';
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<div class="edit-profile-page"> <!-- Reusing wrapper for consistent max-width and centering -->

    <!-- Page Header -->
    <header class="profile-header-top">
        <div>
            <h1 class="page-title">My Skills</h1>
            <p class="page-subtitle"><?= htmlspecialchars($page_subtitle, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </header>

    <!-- Flash Messages (A1 Toasts Target) -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="profile-alert alert-success js-toast-target" style="display: none;">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="profile-alert alert-danger js-toast-target" style="display: none;">
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Main Content Grid -->
    <div class="my-skills-grid">
        
        <!-- LEFT COLUMN: Skills Management -->
        <div class="skills-management-col">
            
            <!-- FIX: Added "card" and "edit-card" wrappers to match the visual design -->
            <section class="card edit-card" style="padding: 2rem !important; border: 1px solid var(--border-color); border-radius: var(--radius-lg); background-color: var(--surface-color); box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
                
                <div class="current-skills-box" style="background: #ffffff; border: none; padding: 0; margin-bottom: 0;">
                    <div class="box-header" style="margin-bottom: 1.5rem;">
                        <div class="box-title">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            <h3 style="font-size: 1.15rem; font-weight: 700; margin-left: 0.25rem;">Current Skills <span class="skill-count" style="font-weight: 500; color: var(--text-secondary);">(<?= $current_skill_count ?> / 3)</span></h3>
                        </div>
                        <p style="margin-left: 2.2rem; margin-top: -0.25rem; font-size: 0.9rem;">These are the skills associated with your profile.</p>
                    </div>

                    <?php if ($current_skill_count === 0): ?>
                        <!-- A6 Empty State -->
                        <div class="empty-state" style="box-shadow: none; border: 1px dashed var(--border-color); padding: 2rem 1.5rem; background: #f8fafc; border-radius: var(--radius-md);">
                            <div class="empty-state-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                            </div>
                            <h3>No skills added yet</h3>
                            <p style="margin-bottom: 0;"><?= htmlspecialchars($empty_state_text, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    <?php else: ?>
                        <!-- Existing Skills List -->
                        <div class="edit-skill-list" style="margin-bottom: 2rem;">
                            <?php foreach ($user_skills as $skill): ?>
                                <div class="edit-skill-item" style="padding: 1rem 1.25rem;">
                                    <span class="skill-name" style="font-size: 1rem;"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    
                                    <!-- A2 Confirmation Target Form -->
                                    <form action="actions/skill_process.php" method="POST" style="margin:0;" class="js-confirm-remove-skill" data-skill-name="<?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="remove_skill">
                                        <input type="hidden" name="skill_id" value="<?= (int)$skill['skill_id'] ?>">
                                        <button type="submit" class="btn-remove-skill" data-loading-text="Removing...">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">

                <!-- Add Skill Container -->
                <div class="add-skill-section">
                    <div class="box-title" style="margin-bottom: 0.25rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-left: 0.25rem;">Add a New Skill</h3>
                    </div>
                    <p class="text-muted" style="margin-bottom: 1.5rem; font-size: 0.9rem; color: var(--text-secondary); margin-left: 2.2rem;">Select a skill from the list to add to your profile.</p>

                                    <?php if ($current_skill_count >= 3): ?>
                    <!-- YELLOW WARNING STATE: Max Limit Reached -->
                    <div class="skill-limit-tracker is-maxed" style="margin-left: 2.2rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>You have reached the maximum limit. (3 / 3)</span>
                    </div>
                <?php else: ?>
                    <form action="actions/skill_process.php" method="POST" class="add-skill-flex" novalidate style="margin-left: 2.2rem; gap: 1rem;">
                        <input type="hidden" name="action" value="add_skill">
                        
                        <select name="skill_id" required class="form-input" style="flex: 1; padding: 0.8rem 1rem;">
                            <option value="">-- Select a Skill --</option>
                            <?php foreach ($available_skills as $avail_skill): ?>
                                <option value="<?= (int)$avail_skill['skill_id'] ?>">
                                    <?= htmlspecialchars($avail_skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-primary" style="gap: 0.5rem; padding: 0.8rem 1.5rem;" data-loading-text="Adding...">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Add
                        </button>
                    </form>
                    
                    <!-- FLAT GREY STATE: Normal Limit Tracker -->
                    <div class="skill-limit-tracker is-normal" style="margin-left: 2.2rem; margin-top: 1.5rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
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
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><circle cx="12" cy="11" r="3"></circle></svg>
                    </div>
                    <div class="header-text">
                        <h2 style="font-size: 1.15rem; margin-top: 8px;">Why add skills?</h2>
                    </div>
                </div>
                <div class="why-skills-list">
                    <div class="why-skills-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span>Help trainers find relevant learners</span>
                    </div>
                    <div class="why-skills-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span>Showcase your learning interests</span>
                    </div>
                    <div class="why-skills-item">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span>Make your profile more informative</span>
                    </div>
                </div>
            </section>
        </div>

    </div>
</div>

<?php 
// Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
