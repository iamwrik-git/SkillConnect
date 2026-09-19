<?php
// search.php

// 1. Session initialization and core includes
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

// 2. Authentication & Authorization
requireLogin();
$current_user_id = getCurrentUserId();
$current_user_role = $_SESSION['role'] ?? '';

// Restrict access: Only trainees can search for trainers
if ($current_user_role !== 'trainee') {
    header("Location: dashboard.php");
    exit();
}

// 3. Fetch all available skills for the search dropdown
$all_skills = [];
try {
    $skillStmt = $pdo->query("SELECT skill_id, skill_name FROM skills ORDER BY skill_name ASC");
    $all_skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Skills Fetch Error (search.php): " . $e->getMessage());
}

// 4. Handle Search Request
$search_skill_id = filter_input(INPUT_GET, 'skill_id', FILTER_VALIDATE_INT);
$search_performed = false;
$search_error = '';
$selected_skill_name = '';
$trainers = [];

if ($search_skill_id && $search_skill_id > 0) {
    $search_performed = true;

    try {
        // Validate that the requested skill actually exists
        $checkStmt = $pdo->prepare("SELECT skill_name FROM skills WHERE skill_id = ?");
        $checkStmt->execute([$search_skill_id]);
        $skill_row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$skill_row) {
            $search_error = "The selected skill does not exist.";
        } else {
            $selected_skill_name = $skill_row['skill_name'];

            // Fetch trainers who teach this exact skill
            $trainerStmt = $pdo->prepare("
                SELECT DISTINCT u.user_id, u.name, u.profile_photo, u.bio, u.exp_level
                FROM users u
                JOIN user_skills us ON u.user_id = us.user_id
                WHERE u.role = 'trainer' AND us.skill_id = ?
                ORDER BY u.name ASC
            ");
            $trainerStmt->execute([$search_skill_id]);
            $trainers = $trainerStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Trainer Search Error (search.php): " . $e->getMessage());
        $search_error = "An unexpected error occurred while searching for trainers.";
    }
}

// 5. Output Header
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
            
            <a href="search.php" class="sidebar-link active">Find Trainers</a>
            
            <a href="profile.php" class="sidebar-link">Profile</a>
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
        
        <header class="page-header">
            <h1 class="page-title">Find Trainers</h1>
        </header>

        <!-- Search Interface -->
        <section class="dashboard-card search-card">
            <h2 class="card-title search-title">Search by Skill</h2>
            <p class="search-description">
                Select a skill to find qualified trainers who teach it.
            </p>
            
            <form action="search.php" method="GET" class="search-form">
                <select name="skill_id" required class="form-control search-select">
                    <option value="">-- Select a Skill --</option>
                    <?php foreach ($all_skills as $skill): ?>
                        <option value="<?= (int)$skill['skill_id'] ?>" <?= ($search_skill_id === (int)$skill['skill_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="submit-btn search-btn">Search</button>
            </form>
        </section>

        <!-- Search Results Area -->
        <?php if ($search_performed): ?>
            
            <div class="search-results-header">
                <h3 class="search-results-title">
                    Search Results for "<?= htmlspecialchars($selected_skill_name, ENT_QUOTES, 'UTF-8') ?>"
                </h3>
            </div>

            <?php if ($search_error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($search_error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            
            <?php elseif (empty($trainers)): ?>
                <div class="empty-state">
                    <p class="empty-state-text">No trainers found for this skill yet.</p>
                    <p class="empty-state-subtext">Try selecting a different skill.</p>
                </div>
            
            <?php else: ?>
                <!-- Trainers Grid -->
                <div class="content-grid">
                    <?php foreach ($trainers as $trainer): ?>
                        <section class="dashboard-card trainer-card">
                            
                            <!-- Trainer Header (Photo & Name) -->
                            <div class="trainer-header">
                                <?php 
                                    $photo_filename = !empty($trainer['profile_photo']) ? $trainer['profile_photo'] : 'default.jpg';
                                    $photo_path = 'assets/images/profile/' . htmlspecialchars($photo_filename, ENT_QUOTES, 'UTF-8');
                                ?>
                                <img src="<?= $photo_path ?>" alt="Trainer Photo" class="trainer-photo">
                                
                                <div class="trainer-info">
                                    <h4 class="trainer-name">
                                        <?= htmlspecialchars($trainer['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </h4>
                                    <span class="trainer-badge">Trainer</span>
                                </div>
                            </div>

                            <!-- Trainer Details (Experience & Bio) -->
                            <div class="trainer-details">
                                <div class="trainer-exp">
                                    <strong class="trainer-label">Experience:</strong> 
                                    <?= !empty($trainer['exp_level']) ? htmlspecialchars($trainer['exp_level'], ENT_QUOTES, 'UTF-8') : 'Not specified' ?>
                                </div>
                                
                                <div class="trainer-bio">
                                    <?php if (!empty($trainer['bio'])): ?>
                                        <?= htmlspecialchars($trainer['bio'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        <span class="trainer-bio-empty">No bio added yet.</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- View Profile Action -->
                            <div class="trainer-action">
                                <a href="profile.php?user_id=<?= (int)$trainer['user_id'] ?>" class="action-button btn-view-profile">
                                    View Profile
                                </a>
                            </div>

                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- Initial State / No search performed -->
            <div class="empty-state search-empty-container">
                <p class="search-empty-icon">🔍</p>
                <p class="empty-state-text">Select a skill from the dropdown above to discover qualified trainers.</p>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php 
// 6. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
