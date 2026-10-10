<?php
// saved_trainers.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

// Authentication & Identity
requireLogin();
$current_user_id = getCurrentUserId();
$current_user_role = $_SESSION['role'] ?? '';

// Restrict access
if ($current_user_role !== 'trainee') {
    $_SESSION['error'] = "Only trainees can access saved trainers.";
    header("Location: dashboard.php");
    exit();
}

// Helper function for photos
if (!function_exists('getProfilePhotoUrl')) {
    function getProfilePhotoUrl($photo, $name) {
        if (empty($photo) || $photo === 'default.jpg') {
            return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=eff6ff&color=2563eb&size=120&bold=true";
        }
        return 'assets/images/profile/' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8');
    }
}

$trainers = [];

try {
    // Retrieve saved trainers for the current user
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.profile_photo, u.bio, u.exp_level, st.saved_at
        FROM saved_trainers st
        JOIN users u ON st.trainer_id = u.user_id
        WHERE st.user_id = ?
        ORDER BY st.saved_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $fetched_trainers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch skills for these trainers
    if (!empty($fetched_trainers)) {
        foreach ($fetched_trainers as $t) {
            $t_id = $t['user_id'];
            $tsStmt = $pdo->prepare("
                SELECT s.skill_name 
                FROM skills s 
                JOIN user_skills us ON s.skill_id = us.skill_id 
                WHERE us.user_id = ?
            ");
            $tsStmt->execute([$t_id]);
            $t['skills'] = $tsStmt->fetchAll(PDO::FETCH_COLUMN);
            $trainers[] = $t;
        }
    }
} catch (PDOException $e) {
    error_log("Saved Trainers Fetch Error (saved_trainers.php): " . $e->getMessage());
    $db_error = true;
}

$page_title = 'Saved Trainers - SkillConnect';
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<div class="search-page-wrapper">

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="profile-alert alert-success js-toast-target" style="display:none;">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="profile-alert alert-danger js-toast-target" style="display:none;">
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Header Section -->
    <header class="search-header-top" style="align-items: center;">
        <div class="header-titles">
            <h1 class="page-title">Saved Trainers</h1>
            <p class="page-subtitle">Your bookmarked trainers, all in one place.</p>
        </div>
        <?php if (!empty($trainers)): ?>
            <div class="header-callout" style="padding: 0.5rem 1rem; margin: 0;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:#2563eb; margin-right:8px;"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                <strong style="color:#2563eb; font-size:0.9rem;"><?= count($trainers) ?> trainers saved</strong>
            </div>
        <?php endif; ?>
    </header>

    <?php if (empty($trainers)): ?>
        <!-- Empty State -->
        <div class="search-empty-state card" style="text-align: center; padding: 4rem 2rem !important; margin-top: 1rem;">
            <div class="empty-icon-wrapper initial-state" style="margin: 0 auto 1.5rem auto;">
                <div style="background-color: #eff6ff; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="empty-text">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">No saved trainers yet</h3>
                <p style="color: #64748b; margin-bottom: 1.5rem;">Bookmark trainers you're interested in<br>to find them here later.</p>
                <a href="search.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    Find Trainers
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Trainers Grid -->
        <div class="trainer-grid" style="margin-top: 1rem;">
            <?php foreach ($trainers as $trainer): ?>
                <div class="card trainer-card">

                    <!-- Trainer Header with Red Bookmark -->
                    <div class="trainer-card-header" style="position: relative; padding-right: 2.5rem;">
                        <img src="<?= getProfilePhotoUrl($trainer['profile_photo'] ?? '', $trainer['name']) ?>" alt="Trainer Photo" class="trainer-avatar">
                        <div class="trainer-info">
                            <h4 class="trainer-name"><?= htmlspecialchars($trainer['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <span class="trainer-role-text">Trainer</span>
                            <div class="trainer-exp">
                                <?= !empty($trainer['exp_level']) ? htmlspecialchars(ucfirst($trainer['exp_level']), ENT_QUOTES, 'UTF-8') . ' experience' : 'Experience not specified' ?>
                            </div>
                        </div>

                        <!-- Static Red Bookmark strictly for visual consistency with reference -->
                        <div style="position: absolute; top: 0; right: 0; color: #ef4444; padding: 4px;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                        </div>
                    </div>

                    <!-- Bio -->
                    <div class="trainer-bio">
                        <?php if (!empty($trainer['bio'])): ?>
                            <p class="trainer-bio-clamp"><?= htmlspecialchars($trainer['bio'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <p class="italic trainer-bio-clamp" style="color: #94a3b8;">No bio added yet.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Skills -->
                    <div class="trainer-skills">
                        <div class="skill-chips-mini">
                            <?php if (!empty($trainer['skills'])): ?>
                                <?php foreach (array_slice($trainer['skills'], 0, 4) as $skill): ?>
                                    <span class="chip-mini"><?= htmlspecialchars($skill, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                                <?php if (count($trainer['skills']) > 4): ?>
                                    <span class="chip-mini">+<?= count($trainer['skills']) - 4 ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="chip-mini">No skills listed</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="trainer-card-footer" style="display: flex; gap: 0.5rem; margin-top: auto;">
                        <a href="profile.php?user_id=<?= (int)$trainer['user_id'] ?>" class="btn btn-outline-primary" style="flex: 1;">
                            View Profile
                        </a>
                        
                        <form action="actions/saved_trainer_process.php" method="POST" class="mentorship-request-form js-confirm-remove-saved" data-trainer-name="<?= htmlspecialchars($trainer['name'], ENT_QUOTES, 'UTF-8') ?>" style="flex: 1; margin: 0; display: flex;">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="trainer_id" value="<?= (int)$trainer['user_id'] ?>">
                            <input type="hidden" name="return_to" value="../saved_trainers.php">
                            <button type="submit" class="btn btn-danger-light" style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 6px;" data-loading-text="Removing...">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                Remove
                            </button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
