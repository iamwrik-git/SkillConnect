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
            
            <?php if ($user['role'] === 'trainee'): ?>
                <a href="search.php" class="sidebar-link">Find Trainers</a>
            <?php endif; ?>
            
            <a href="profile.php" class="sidebar-link">Profile</a>
            <a href="edit_profile.php" class="sidebar-link active">Edit Profile</a>
            
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
        
        <header class="page-header">
            <h1 class="page-title">Edit Profile</h1>
            <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
        </header>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="content-grid align-start">
            
            <!-- ==========================================
                 PROFILE UPDATE FORM
                 ========================================== -->
            <section class="dashboard-card">
                <h2 class="card-title section-title">Personal Information</h2>
                
                <form action="actions/profile_update.php" method="POST" enctype="multipart/form-data" class="profile-form">
                    <input type="hidden" name="action" value="update_profile">

                    <!-- Profile Photo Configuration -->
                    <div class="profile-photo-group">
                        <?php 
                            $photo_filename = !empty($user['profile_photo']) ? $user['profile_photo'] : 'default.jpg';
                            $photo_path = 'assets/images/profile/' . htmlspecialchars($photo_filename, ENT_QUOTES, 'UTF-8');
                        ?>
                        <img src="<?= $photo_path ?>" alt="Profile Photo" class="profile-photo-preview">
                        
                        <div class="profile-photo-inputs">
                            <label for="profile_photo">Update Photo</label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg, image/png, image/webp" class="file-input">
                        </div>
                    </div>

                    <!-- Input Fields -->
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="100" class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address (Read-Only)</label>
                        <input type="email" id="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" readonly class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="role">Account Role (Read-Only)</label>
                        <input type="text" id="role" value="<?= htmlspecialchars(ucfirst($user['role']), ENT_QUOTES, 'UTF-8') ?>" readonly class="form-control">
                    </div>

                    <div class="form-group">
                        <label for="exp_level">Experience Level</label>
                        <select id="exp_level" name="exp_level" class="form-control">
                            <option value="">-- Select Level (Optional) --</option>
                            <option value="Beginner" <?= $user['exp_level'] === 'Beginner' ? 'selected' : '' ?>>Beginner</option>
                            <option value="Intermediate" <?= $user['exp_level'] === 'Intermediate' ? 'selected' : '' ?>>Intermediate</option>
                            <option value="Expert" <?= $user['exp_level'] === 'Expert' ? 'selected' : '' ?>>Expert</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" rows="4" class="form-control text-area"><?= htmlspecialchars($user['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <button type="submit" class="submit-btn profile-submit-btn">Save Profile Changes</button>
                </form>
            </section>

            <!-- ==========================================
                 SKILL MANAGEMENT SECTION
                 ========================================== -->
            <section class="dashboard-card">
                <h2 class="card-title section-title">
                    <?= $user['role'] === 'trainee' ? 'Skills I Want to Learn' : 'Skills I Teach' ?>
                </h2>

                <!-- Render User's Active Skills -->
                <div class="current-skills-container">
                    <?php if ($current_skill_count === 0): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">You haven't added any skills yet.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($user_skills as $skill): ?>
                                <li class="skill-item">
                                    <span class="skill-name"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    
                                    <!-- Remove Skill Action -->
                                    <form action="actions/skill_process.php" method="POST" class="remove-skill-form">
                                        <input type="hidden" name="action" value="remove_skill">
                                        <input type="hidden" name="skill_id" value="<?= (int)$skill['skill_id'] ?>">
                                        <button type="submit" class="remove-btn">Remove</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Add Skill Interface -->
                <div class="add-skill-container">
                    <h3 class="add-skill-header">Add a Skill (<?= $current_skill_count ?>/3)</h3>
                    
                    <?php if ($current_skill_count >= 3): ?>
                        <div class="alert alert-warning">
                            You have reached the maximum limit of 3 skills. Please remove a skill to add a new one.
                        </div>
                    <?php elseif (empty($available_skills)): ?>
                        <div class="alert alert-info">
                            You have added all available skills!
                        </div>
                    <?php else: ?>
                        <!-- Form to assign a new skill -->
                        <form action="actions/skill_process.php" method="POST" class="add-skill-form">
                            <input type="hidden" name="action" value="add_skill">
                            
                            <select name="skill_id" required class="form-control add-skill-select">
                                <option value="">-- Select a Skill --</option>
                                <?php foreach ($available_skills as $avail_skill): ?>
                                    <option value="<?= (int)$avail_skill['skill_id'] ?>">
                                        <?= htmlspecialchars($avail_skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <button type="submit" class="add-btn">Add</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>
</div>

<?php 
// 7. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
