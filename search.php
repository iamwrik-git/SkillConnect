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

// Helper function to resolve profile photo or generate UI-Avatar Initials
if (!function_exists('getProfilePhotoUrl')) {
    function getProfilePhotoUrl($photo, $name)
    {
        if (empty($photo) || $photo === 'default.jpg') {
            return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=eff6ff&color=2563eb&size=120&bold=true";
        }
        return 'assets/images/profile/' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8');
    }
}

// 3. Fetch ONLY the current user's added skills for the search dropdown
$user_skills = [];
try {
    $skillStmt = $pdo->prepare("
        SELECT s.skill_id, s.skill_name 
        FROM skills s
        JOIN user_skills us ON s.skill_id = us.skill_id
        WHERE us.user_id = ?
        ORDER BY s.skill_name ASC
    ");
    $skillStmt->execute([$current_user_id]);
    $user_skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User Skills Fetch Error (search.php): " . $e->getMessage());
}

// 4. Handle Search Requests (Dropdown OR Global Text Search)
$search_skill_id = filter_input(INPUT_GET, 'skill_id', FILTER_VALIDATE_INT);
$search_query = trim($_GET['q'] ?? ''); // Grabs text from the top nav bar
$search_performed = false;
$search_error = '';
$selected_skill_name = '';
$trainers = [];

// SCENARIO A: User used the visual Dropdown (Exact Skill Match from their profile)
if ($search_skill_id && $search_skill_id > 0) {
    $search_performed = true;

    try {
        $checkStmt = $pdo->prepare("SELECT skill_name FROM skills WHERE skill_id = ?");
        $checkStmt->execute([$search_skill_id]);
        $skill_row = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$skill_row) {
            $search_error = "The selected skill does not exist.";
        } else {
            $selected_skill_name = $skill_row['skill_name'];

            $trainerStmt = $pdo->prepare("
                SELECT DISTINCT u.user_id, u.name, u.profile_photo, u.bio, u.exp_level
                FROM users u
                JOIN user_skills us ON u.user_id = us.user_id
                WHERE u.role = 'trainer' 
                  AND us.skill_id = ?
                  AND u.user_id NOT IN (
                      SELECT trainer_id 
                      FROM mentorships 
                      WHERE trainee_id = ? AND status IN ('pending', 'accepted')
                  )
                ORDER BY u.name ASC
            ");
            $trainerStmt->execute([$search_skill_id, $current_user_id]);
            $fetched_trainers = $trainerStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Trainer Search Error (Dropdown): " . $e->getMessage());
        $search_error = "An unexpected error occurred while searching for trainers.";
    }
} // SCENARIO B: User used the Global Search Bar (Text Match for ANY Name OR ANY Skill)
elseif (!empty($search_query)) {
    $search_performed = true;
    $selected_skill_name = '"' . $search_query . '"'; // Display what they typed

    try {
        $like_term = '%' . $search_query . '%';

        // Prioritizes accepted trainers at the top using a CASE statement
        $trainerStmt = $pdo->prepare("
            SELECT DISTINCT u.user_id, u.name, u.profile_photo, u.bio, u.exp_level,
                   (SELECT COUNT(*) FROM mentorships m 
                    WHERE m.trainee_id = ? 
                      AND m.trainer_id = u.user_id 
                      AND m.status = 'accepted') AS is_connected
            FROM users u
            JOIN user_skills us ON u.user_id = us.user_id
            JOIN skills s ON us.skill_id = s.skill_id
            WHERE u.role = 'trainer' 
              AND (u.name LIKE ? OR s.skill_name LIKE ?)
            ORDER BY is_connected DESC, u.name ASC
        ");
        $trainerStmt->execute([$current_user_id, $like_term, $like_term]);
        $fetched_trainers = $trainerStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Trainer Search Error (Text Query): " . $e->getMessage());
        $search_error = "An unexpected error occurred while searching for trainers.";
    }
}


// Attach skills to the fetched trainers for the UI cards
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

// 5. Output Header
$page_title = 'Find Trainers - SkillConnect';
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<div class="search-page-wrapper">

    <!-- Header Section -->
    <header class="search-header-top">
        <div class="header-titles">
            <h1 class="page-title">Find Trainers</h1>
            <p class="page-subtitle">Discover skilled trainers and start your learning journey.</p>
        </div>
        <div class="header-callout">
            <div class="callout-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="callout-text">
                <strong>Learn from real people</strong>
                <p>Connect with experienced trainers and gain practical knowledge.</p>
            </div>
        </div>
    </header>

    <!-- Visual Search Box (For specific skills added to profile) -->
    <section class="card search-box-card">
        <div class="search-box-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </div>
        <div class="search-box-content">
            <h2>Search by Skill</h2>
            <p>Select a skill you want to learn to find qualified trainers who teach it.</p>

            <form action="search.php" method="GET" class="search-inline-form">
                <?php if (empty($user_skills)): ?>
                    <select class="form-input" disabled>
                        <option>No skills added to your profile yet</option>
                    </select>
                    <a href="edit_profile.php" class="btn btn-primary">Add Skills First</a>
                <?php else: ?>
                    <select name="skill_id" required class="form-input">
                        <option value="">-- Select a Skill --</option>
                        <?php foreach ($user_skills as $skill): ?>
                            <option value="<?= (int)$skill['skill_id'] ?>" <?= ($search_skill_id === (int)$skill['skill_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary search-submit-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        Search
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <!-- Search Results Area -->
    <?php if ($search_performed): ?>

        <?php if ($search_error): ?>
            <div class="profile-alert alert-danger" style="margin-top: 2rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <?= htmlspecialchars($search_error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php else: ?>

            <!-- Search Results Header -->
            <div class="search-results-header">
                <div class="results-title-group">
                    <h3>Search Results for <?= htmlspecialchars($selected_skill_name, ENT_QUOTES, 'UTF-8') ?></h3>
                    <span class="badge results-badge"><?= count($trainers) ?> trainers found</span>
                </div>
                <!-- UI-Only sort dropdown to match reference image -->
                <div class="results-sort">
                    <span class="sort-label">Sort by:</span>
                    <select class="form-input sort-select">
                        <option>Name (A - Z)</option>
                    </select>
                </div>
            </div>

            <?php if (empty($trainers)): ?>
                <!-- Empty State: No Trainers Found -->
                <div class="search-empty-state card">
                    <div class="empty-icon-wrapper">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <div class="magnify-overlay">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </div>
                    </div>
                    <div class="empty-text">
                        <h3>No trainers found yet</h3>
                        <p>We couldn't find any trainers matching your search.<br>Try choosing a different skill or name.</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Trainers Grid -->
                <div class="trainer-grid">
                    <?php foreach ($trainers as $trainer): ?>
                        <div class="card trainer-card">

                            <!-- Trainer Header -->
                            <div class="trainer-card-header">
                                <img src="<?= getProfilePhotoUrl($trainer['profile_photo'] ?? '', $trainer['name']) ?>" alt="Trainer Photo" class="trainer-avatar">
                                <div class="trainer-info">
                                    <h4 class="trainer-name"><?= htmlspecialchars($trainer['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <div class="trainer-role-badge">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        Trainer
                                    </div>
                                    <div class="trainer-exp">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="18" y1="20" x2="18" y2="10"></line>
                                            <line x1="12" y1="20" x2="12" y2="4"></line>
                                            <line x1="6" y1="20" x2="6" y2="14"></line>
                                        </svg>
                                        Experience: <span><?= !empty($trainer['exp_level']) ? htmlspecialchars(ucfirst($trainer['exp_level']), ENT_QUOTES, 'UTF-8') : 'Not specified' ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bio -->
                            <div class="trainer-bio">
                                <?php if (!empty($trainer['bio'])): ?>
                                    <p><?= htmlspecialchars($trainer['bio'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php else: ?>
                                    <p class="italic" style="color: #94a3b8;">No bio added yet.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Skills -->
                            <div class="trainer-skills">
                                <h5>Skills</h5>
                                <div class="skill-chips-mini">
                                    <?php if (!empty($trainer['skills'])): ?>
                                        <?php foreach (array_slice($trainer['skills'], 0, 3) as $skill): ?>
                                            <span class="chip-mini"><?= htmlspecialchars($skill, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($trainer['skills']) > 3): ?>
                                            <span class="chip-mini">+<?= count($trainer['skills']) - 3 ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="chip-mini">No skills listed</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Action -->
                            <div class="trainer-card-footer">
                                <a href="profile.php?user_id=<?= (int)$trainer['user_id'] ?>" class="btn-outline-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    View Profile
                                </a>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    <?php else: ?>
        <!-- Initial State: Find the right trainer -->
        <div class="search-empty-state card">
            <div class="empty-icon-wrapper initial-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#bfdbfe" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path>
                </svg>
                <div class="magnify-overlay large">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
            </div>
            <div class="empty-text">
                <h3>Find the right trainer for you</h3>
                <p>Select a skill from the dropdown above to discover qualified trainers<br>and start your learning journey.</p>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
// 6. Output Footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>