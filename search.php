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

// 4. Handle Search Requests and Sorting Validation
$search_skill_id = filter_input(INPUT_GET, 'skill_id', FILTER_VALIDATE_INT);
$search_query = trim($_GET['q'] ?? ''); 
$sort = $_GET['sort'] ?? 'name_asc';

$search_performed = false;
$search_error = '';
$selected_skill_name = '';
$trainers = [];

/* 
 * Sorting Logic & Documentation
 * Unspecified experience evaluates to 0. 
 * High to Low: Expert(3), Intermediate(2), Beginner(1), Unspecified(0).
 * Low to High: Unspecified(0), Beginner(1), Intermediate(2), Expert(3).
 */
switch ($sort) {
    case 'name_desc':
        $order_by_sql = "u.name DESC";
        break;
    case 'exp_desc':
        $order_by_sql = "CASE u.exp_level WHEN 'Expert' THEN 3 WHEN 'Intermediate' THEN 2 WHEN 'Beginner' THEN 1 ELSE 0 END DESC, u.name ASC";
        break;
    case 'exp_asc':
        $order_by_sql = "CASE u.exp_level WHEN 'Expert' THEN 3 WHEN 'Intermediate' THEN 2 WHEN 'Beginner' THEN 1 ELSE 0 END ASC, u.name ASC";
        break;
    case 'name_asc':
    default:
        $order_by_sql = "u.name ASC";
        $sort = 'name_asc';
        break;
}

// SCENARIO A: User used the visual Dropdown
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
                ORDER BY $order_by_sql
            ");
            $trainerStmt->execute([$search_skill_id, $current_user_id]);
            $fetched_trainers = $trainerStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Trainer Search Error (Dropdown): " . $e->getMessage());
        $search_error = "An unexpected error occurred while searching for trainers.";
    }
} 
// SCENARIO B: User used the Global Search Bar
elseif (!empty($search_query)) {
    $search_performed = true;
    $selected_skill_name = '"' . $search_query . '"'; 

    try {
        $like_term = '%' . $search_query . '%';

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
            ORDER BY is_connected DESC, $order_by_sql
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

// ---- ADDED FOR SAVED TRAINERS FEATURE ----
$saved_trainers = [];
if ($current_user_role === 'trainee') {
    try {
        $st_stmt = $pdo->prepare("SELECT trainer_id FROM saved_trainers WHERE user_id = ?");
        $st_stmt->execute([$current_user_id]);
        $saved_trainers = $st_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Saved Trainers fetch failed in search.php: " . $e->getMessage());
    }
}
// ------------------------------------------

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

    <!-- Visual Search Box -->
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

            <form action="search.php" method="GET" class="search-inline-form" novalidate>
                <?php if (empty($user_skills)): ?>
                    <select class="form-input" disabled>
                        <option>No skills added to your profile yet</option>
                    </select>
                    <a href="my_skills.php" class="btn btn-primary">Add Skills First</a>
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
            <div class="profile-alert alert-danger js-toast-target" style="margin-top: 2rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <?= htmlspecialchars($search_error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php else: ?>

            <!-- Search Results Header & Sorter -->
            <div class="search-results-header">
                <div class="results-title-group">
                    <h3>Search Results for <?= htmlspecialchars($selected_skill_name, ENT_QUOTES, 'UTF-8') ?></h3>
                    <span class="badge results-badge"><?= count($trainers) ?> trainers found</span>
                </div>
                
                <div class="results-sort">
                    <form action="search.php" method="GET" class="sort-form">
                        <?php if (!empty($search_query)): ?>
                            <input type="hidden" name="q" value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <?php if (!empty($search_skill_id)): ?>
                            <input type="hidden" name="skill_id" value="<?= htmlspecialchars($search_skill_id, ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        
                        <span class="sort-label">Sort by:</span>
                        <select name="sort" class="form-input sort-select" onchange="this.form.submit()">
                            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name (A - Z)</option>
                            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name (Z - A)</option>
                            <option value="exp_desc" <?= $sort === 'exp_desc' ? 'selected' : '' ?>>Experience (High to Low)</option>
                            <option value="exp_asc" <?= $sort === 'exp_asc' ? 'selected' : '' ?>>Experience (Low to High)</option>
                        </select>
                    </form>
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
                        <h3>No trainers found</h3>
                        <p style="margin-bottom: 1rem;">We couldn't find a trainer for this skill yet.<br>Try another skill.</p>
                        <button type="button" class="btn btn-primary" onclick="document.querySelector('.search-inline-form select').focus()">Change Skill</button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Trainers Grid -->
                <div class="trainer-grid">
                    <?php foreach ($trainers as $trainer): ?>
                        <div class="card trainer-card">

                            <!-- Trainer Header -->
                            <div class="trainer-card-header" style="position: relative; padding-right: 2.5rem;">
                                <img src="<?= getProfilePhotoUrl($trainer['profile_photo'] ?? '', $trainer['name']) ?>" alt="Trainer Photo" class="trainer-avatar">
                                <div class="trainer-info">
                                    <h4 class="trainer-name"><?= htmlspecialchars($trainer['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <span class="trainer-role-text">Trainer</span>
                                    <div class="trainer-exp">
                                        <?= !empty($trainer['exp_level']) ? htmlspecialchars(ucfirst($trainer['exp_level']), ENT_QUOTES, 'UTF-8') . ' experience' : 'Experience not specified' ?>
                                    </div>
                                </div>
                                
                                <!-- Bookmark Form -->
                                <?php if ($current_user_role === 'trainee'): ?>
                                    <form action="actions/saved_trainer_process.php" method="POST" class="bookmark-form" style="position: absolute; top: -4px; right: -4px; margin: 0;">
                                        <input type="hidden" name="trainer_id" value="<?= (int)$trainer['user_id'] ?>">
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                                        
                                        <?php if (in_array($trainer['user_id'], $saved_trainers)): ?>
                                            <input type="hidden" name="action" value="remove">
                                            <button type="submit" class="bookmark-btn saved" aria-label="Remove saved trainer" title="Remove saved trainer">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="save">
                                            <button type="submit" class="bookmark-btn unsaved" aria-label="Save trainer" title="Save trainer">
                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <!-- Bio (Line clamped) -->
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
                            <div class="trainer-card-footer">
                                <a href="profile.php?user_id=<?= (int)$trainer['user_id'] ?>" class="btn btn-outline-primary">
                                    View Profile
                                </a>
                                
                                <form action="actions/mentorship_process.php" method="POST" class="mentorship-request-form">
                                    <input type="hidden" name="action" value="send_request">
                                    <input type="hidden" name="trainer_id" value="<?= (int)$trainer['user_id'] ?>">
                                    <button type="submit" class="btn btn-primary" data-loading-text="Requesting...">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                        Request Mentorship
                                    </button>
                                </form>
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
