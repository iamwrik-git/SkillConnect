<?php
// dashboard.php

// 1. Include db connection and authentication helpers
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

// 2. Enforce authentication and redirect if not logged in
requireLogin();

// 3. Retrieve core session data
$user_id = getCurrentUserId();
$role = $_SESSION['role'] ?? '';
$name = trim((string)($_SESSION['name'] ?? 'User'));
$first_name = preg_split('/\s+/', $name)[0] ?? 'User';

// First-login onboarding state. The database migration adds onboarding_completed;
// old accounts default to complete, while registration explicitly sets new accounts to 0.
$onboarding_user = [];
$show_onboarding_security = false;
$onboarding_error = (string) ($_SESSION['onboarding_error'] ?? '');
unset($_SESSION['onboarding_error']);
$show_onboarding_success = !empty($_SESSION['onboarding_success']);
unset($_SESSION['onboarding_success']);
if (empty($_SESSION['onboarding_csrf']) || !is_string($_SESSION['onboarding_csrf'])) {
    $_SESSION['onboarding_csrf'] = bin2hex(random_bytes(32));
}
$onboarding_csrf = $_SESSION['onboarding_csrf'];

try {
    $onboardingStmt = $pdo->prepare(
        'SELECT security_question, security_answer_hash, onboarding_completed FROM users WHERE user_id = ? LIMIT 1'
    );
    $onboardingStmt->execute([(int) $user_id]);
    $onboarding_user = $onboardingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $onboardingComplete = (int) ($onboarding_user['onboarding_completed'] ?? 1) === 1;
    $_SESSION['onboarding_completed'] = $onboardingComplete ? 1 : 0;

    if (!$onboardingComplete) {
        $securityIsComplete = !empty($onboarding_user['security_question'])
            && !empty($onboarding_user['security_answer_hash']);

        if ($securityIsComplete) {
            // Recover gracefully if the security values were saved but the final flag was not.
            $finishStmt = $pdo->prepare('UPDATE users SET onboarding_completed = 1 WHERE user_id = ?');
            $finishStmt->execute([(int) $user_id]);
            $_SESSION['onboarding_completed'] = 1;
            $show_onboarding_success = true;
        } else {
            $show_onboarding_security = true;
        }
    }
} catch (PDOException $e) {
    error_log('Onboarding state lookup failed: ' . $e->getMessage());
    http_response_code(500);
    skillConnectErrorResponse(500, 'We could not load your dashboard right now. Please try again later.');
    exit;
}

// Hero banner mode: show after signup (with welcome tag & text) or once upon sign in (welcome back).
$hero_mode = $_SESSION['dashboard_hero_mode'] ?? null;
$preview_mentor_banner = false;
if (isset($_GET['preview_hero'])) {
    if ($_GET['preview_hero'] === 'signup') {
        $hero_mode = 'signup';
    } elseif ($_GET['preview_hero'] === 'mentor') {
        $hero_mode = null;
        $preview_mentor_banner = true;
    } else {
        $hero_mode = 'welcome_back';
    }
}
if (isset($_GET['preview_banner']) && $_GET['preview_banner'] === 'mentor') {
    $hero_mode = null;
    $preview_mentor_banner = true;
}
// Only clear the hero banner mode once the user can actually see the dashboard (not blocked by onboarding modal)
if (!$show_onboarding_security) {
    unset($_SESSION['dashboard_hero_mode']);
}

// 4. Fetch Database Information
$skills = [];
$pending_requests = [];
$accepted_mentorships = [];
$rejected_history = [];
$db_error = false;

try {
    // Fetch Skills
    $skill_stmt = $pdo->prepare("
        SELECT s.skill_name 
        FROM user_skills us 
        JOIN skills s ON us.skill_id = s.skill_id 
        WHERE us.user_id = ?
    ");
    $skill_stmt->execute([$user_id]);
    $skills = $skill_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Mentorships based on role
    if ($role === 'trainee') {
        $mentorship_stmt = $pdo->prepare("
            SELECT m.request_id, m.status, m.created_at, 
                   u.user_id, u.name, u.profile_photo, u.exp_level,
                   (SELECT GROUP_CONCAT(s.skill_name ORDER BY s.skill_name SEPARATOR ', ')
                    FROM user_skills us
                    JOIN skills s ON s.skill_id = us.skill_id
                    WHERE us.user_id = u.user_id) AS connected_skills
            FROM mentorships m
            JOIN users u ON m.trainer_id = u.user_id
            WHERE m.trainee_id = ? AND (u.is_admin = 0 OR u.is_admin IS NULL)
            ORDER BY m.created_at DESC
        ");
    } elseif ($role === 'trainer') {
        $mentorship_stmt = $pdo->prepare("
            SELECT m.request_id, m.status, m.created_at, 
                   u.user_id, u.name, u.profile_photo, u.exp_level,
                   (SELECT GROUP_CONCAT(s.skill_name ORDER BY s.skill_name SEPARATOR ', ')
                    FROM user_skills us
                    JOIN skills s ON s.skill_id = us.skill_id
                    WHERE us.user_id = u.user_id) AS connected_skills
            FROM mentorships m
            JOIN users u ON m.trainee_id = u.user_id
            WHERE m.trainer_id = ?
            ORDER BY m.created_at DESC
        ");
    }

    if (isset($mentorship_stmt)) {
        $mentorship_stmt->execute([$user_id]);
        $mentorships = $mentorship_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($mentorships as $m) {
            if ($m['status'] === 'pending') {
                $pending_requests[] = $m;
            } elseif ($m['status'] === 'accepted') {
                $accepted_mentorships[] = $m;
            } elseif ($m['status'] === 'rejected') {
                $rejected_history[] = $m;
            }
        }
    }

    // ---- SAVED TRAINERS & TOP MENTOR SUGGESTIONS (TRAINEE ONLY) ----
    $saved_trainers_count = 0;
    $saved_trainer_ids = [];
    $suggested_mentors = [];

    if ($role === 'trainee') {
        // Fetch saved trainers IDs
        $st_stmt = $pdo->prepare("SELECT trainer_id FROM saved_trainers WHERE user_id = ?");
        $st_stmt->execute([$user_id]);
        $saved_trainer_ids = $st_stmt->fetchAll(PDO::FETCH_COLUMN);
        $saved_trainers_count = count($saved_trainer_ids);

        // Trainee's skill names for matching
        $trainee_skill_names = array_column($skills, 'skill_name');
        $lower_trainee_skills = array_map('strtolower', $trainee_skill_names);

        // Fetch candidate trainers (excluding self and mentors already accepted)
        $top_mentor_query = "
            SELECT u.user_id, u.name, u.profile_photo, u.exp_level,
                   (SELECT COUNT(*) FROM mentorships WHERE trainer_id = u.user_id AND status = 'accepted') AS mentee_count,
                   GROUP_CONCAT(DISTINCT s.skill_name ORDER BY s.skill_name SEPARATOR '||') AS trainer_skills
            FROM users u
            LEFT JOIN user_skills us ON u.user_id = us.user_id
            LEFT JOIN skills s ON us.skill_id = s.skill_id
            WHERE u.role = 'trainer' 
              AND (u.is_admin = 0 OR u.is_admin IS NULL)
              AND u.email NOT LIKE '%admin%'
              AND u.user_id != ?
              AND u.user_id NOT IN (
                  SELECT trainer_id FROM mentorships WHERE trainee_id = ? AND status = 'accepted'
              )
            GROUP BY u.user_id
        ";
        $top_stmt = $pdo->prepare($top_mentor_query);
        $top_stmt->execute([$user_id, $user_id]);
        $all_candidate_mentors = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all_candidate_mentors as &$c) {
            $t_skills = !empty($c['trainer_skills']) ? explode('||', $c['trainer_skills']) : [];
            $matched = [];
            foreach ($t_skills as $ts) {
                if (in_array(strtolower($ts), $lower_trainee_skills, true)) {
                    $matched[] = $ts;
                }
            }
            $c['matched_skills'] = $matched;
            $c['has_match'] = !empty($matched);
            $c['match_count'] = count($matched);
            // Display skill: use matched skill if exists, otherwise first trainer skill, or 'General Mentorship'
            $c['display_skill'] = !empty($matched) ? $matched[0] : (!empty($t_skills) ? $t_skills[0] : 'General Mentorship');
        }
        unset($c);

        // Sort candidates:
        // 1. Mentors with matching skills first
        // 2. Mentors with higher matching skills count
        // 3. Mentors with higher accepted mentee count
        // 4. Mentors with higher experience level
        // 5. Recent user ID
        usort($all_candidate_mentors, function ($a, $b) {
            if ($b['has_match'] !== $a['has_match']) {
                return $b['has_match'] ? 1 : -1;
            }
            if ($b['match_count'] !== $a['match_count']) {
                return $b['match_count'] <=> $a['match_count'];
            }
            if ($b['mentee_count'] !== $a['mentee_count']) {
                return $b['mentee_count'] <=> $a['mentee_count'];
            }
            $expRank = ['Expert' => 3, 'Intermediate' => 2, 'Beginner' => 1];
            $rankA = $expRank[$a['exp_level'] ?? ''] ?? 0;
            $rankB = $expRank[$b['exp_level'] ?? ''] ?? 0;
            if ($rankB !== $rankA) {
                return $rankB <=> $rankA;
            }
            return $b['user_id'] <=> $a['user_id'];
        });

        // Limit to top 5 mentors (matching the 5-card row layout)
        $suggested_mentors = array_slice($all_candidate_mentors, 0, 5);
    }
    // -----------------------------------------------------------------

} catch (PDOException $e) {
    error_log("Dashboard DB Error: " . $e->getMessage());
    $db_error = true;
}

// Implement UX Layout Rules: Slice arrays for compact preview
$display_accepted = array_slice($accepted_mentorships, 0, 1);
$display_pending  = array_slice($pending_requests, 0, 2);
$display_history  = array_slice($rejected_history, 0, 2);

// Load the shared header using an absolute filesystem path.
$page_title = 'Dashboard | SkillConnect';
require_once __DIR__ . '/includes/header.php';

?>

<div class="dashboard-page">

    <?php if ($show_onboarding_success): ?>
        <div class="sc-onboarding-toast" role="status" aria-live="polite">
            <span class="sc-onboarding-toast-icon" aria-hidden="true">✓</span>
            <div><strong>Profile completed!</strong><span>Your account is now set up.</span></div>
            <button type="button" class="sc-onboarding-toast-close" aria-label="Dismiss notification">×</button>
        </div>
    <?php endif; ?>

    <?php if ($show_onboarding_security): ?>
        <?php require_once __DIR__ . '/includes/partials/onboarding_modal.php'; ?>
    <?php endif; ?>

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

    <?php if ($db_error): ?>
        <section class="card error-card">
            <h3 class="card-title text-danger">Dashboard Data Unavailable</h3>
            <p>We encountered an issue loading your dashboard data. Please try again later.</p>
        </section>
    <?php elseif (!in_array($role, ['trainee', 'trainer'])): ?>
        <section class="card error-card">
            <h3 class="card-title text-danger">Account Configuration Error</h3>
            <p>Your account does not have a valid role assigned. Please contact the administrator.</p>
        </section>
    <?php else: ?>

        <!-- 1. HERO SECTION (Shown for new signup or on first view after sign in) -->
        <?php if ($hero_mode !== null): ?>
            <div class="dashboard-hero <?= $hero_mode === 'signup' ? 'hero-welcome-signup' : '' ?>">
                <div class="hero-content">
                    <?php if ($hero_mode === 'signup'): ?>
                        <span class="hero-welcome-tag">
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            Welcome to SkillConnect
                        </span>
                        <h1 class="hero-title">Welcome, <?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?>!</h1>
                        <p class="hero-subtitle">
                            <?php if ($role === 'trainee'): ?>
                                We're excited to have you here! Explore expert mentors, add your skills, and accelerate your growth.
                            <?php else: ?>
                                Welcome to our mentor community! Share your expertise, guide eager mentees, and shape the next generation.
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <h1 class="hero-title">Welcome back, <?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?>!</h1>
                        <p class="hero-subtitle">
                            <?php if ($role === 'trainee'): ?>
                                Keep learning, keep growing.
                            <?php else: ?>
                                Share your expertise and guide the next generation.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Exact Replica Graphic: Waves, Plant, and Text -->
                <div class="hero-graphic">
                    <svg viewBox="0 0 550 150" height="100%" preserveAspectRatio="xMaxYMax meet" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Overlapping Background Waves -->
                        <path d="M0 150 Q 150 70, 300 120 T 550 40 V 150 Z" fill="#DCE8FA" opacity="0.6" />
                        <path d="M120 150 Q 250 100, 380 130 T 550 80 V 150 Z" fill="#C5DDFC" opacity="0.5" />
                        <path d="M250 150 Q 320 120, 420 140 T 550 110 V 150 Z" fill="#93C5FD" opacity="0.3" />

                        <!-- Plant Stem -->
                        <path d="M 370 150 Q 365 90, 380 30" stroke="#1E40AF" stroke-width="4" stroke-linecap="round" />

                        <!-- Top Right Leaf -->
                        <path d="M 376 55 C 385 15, 425 15, 430 30 C 435 45, 400 75, 376 55 Z" fill="#60A5FA" />
                        <path d="M 376 55 Q 405 40, 420 30" stroke="#2563EB" stroke-width="2" stroke-linecap="round" />

                        <!-- Mid Left Leaf -->
                        <path d="M 372 100 C 330 65, 290 85, 295 105 C 300 125, 350 120, 372 100 Z" fill="#3B82F6" />
                        <path d="M 372 100 Q 330 95, 310 95" stroke="#1E40AF" stroke-width="2" stroke-linecap="round" />

                        <!-- Bottom Right Leaf -->
                        <path d="M 370 130 C 390 95, 430 105, 420 125 C 410 145, 380 145, 370 130 Z" fill="#93C5FD" />

                        <!-- Handwritten Stacked Text -->
                        <g fill="#3B82F6" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-style="italic" font-size="22" font-weight="500" letter-spacing="0.5">
                            <text x="450" y="65">Learn</text>
                            <text x="450" y="95">Connect</text>
                            <text x="450" y="125">Grow</text>
                        </g>
                        <!-- Slanted Underline -->
                        <line x1="450" y1="138" x2="515" y2="130" stroke="#3B82F6" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </div>
            </div>
        <?php elseif (($role === 'trainee' && empty($accepted_mentorships)) || !empty($preview_mentor_banner)): ?>
            <!-- 1B. PROMO BANNER (Shown for trainees without mentors once welcome hero banner is gone) -->
            <div class="dashboard-hero dashboard-mentor-banner">
                <div class="hero-content">
                    <h1 class="hero-title" style="color: #ffffff !important; text-shadow: 0 1px 3px rgba(0,0,0,0.35);">Learn. Connect. Grow Together.</h1>
                    <p class="hero-subtitle" style="color: rgba(255, 255, 255, 0.95) !important; text-shadow: 0 1px 2px rgba(0,0,0,0.25);">Connect with skilled people, get mentorship, share knowledge and grow your skills.</p>
                    <div class="hero-actions">
                        <a href="search.php" class="btn btn-banner-action">Find a Mentor</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2. STATISTICS -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z" />
                        <path d="M6 12v5c3 3 9 3 12 0v-5" />
                    </svg>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= count($skills) ?></div>
                    <div class="stat-label"><?= $role === 'trainer' ? 'Skills I Teach' : 'Skills Added' ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <div class="stat-details">
                    <!-- Maintain true total count -->
                    <div class="stat-value"><?= count($accepted_mentorships) ?></div>
                    <div class="stat-label"><?= $role === 'trainer' ? 'My Mentees' : 'My Mentors' ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 6v6l4 2" />
                    </svg>
                </div>
                <div class="stat-details">
                    <!-- Maintain true total count -->
                    <div class="stat-value"><?= count($pending_requests) ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </div>

            <!-- Trainees ONLY get the Saved Mentors stat card. -->
            <?php if ($role === 'trainee'): ?>
                <a href="saved_trainers.php" class="stat-card stat-card-link" style="text-decoration: none; color: inherit; display: flex; transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div class="stat-icon icon-purple">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                        </svg>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?= (int)$saved_trainers_count ?></div>
                        <div class="stat-label">Saved Mentors</div>
                    </div>
                    <div style="margin-left: auto; color: #94a3b8; font-size: 1.25rem;">›</div>
                </a>
            <?php endif; ?>
        </div>

        <!-- 3. QUICK ACTIONS -->
        <div class="quick-actions-section">
            <h2 class="section-title">Quick Actions</h2>

            <div class="quick-actions-grid">
                <?php if ($role === 'trainee'): ?>
                    <a href="search.php" class="action-card theme-blue">
                        <div class="action-icon">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <path d="M21 21l-4.35-4.35" />
                            </svg>
                        </div>
                        <div class="action-text">
                            <h3>Find Mentors</h3>
                            <p>Search by skill and connect with mentors.</p>
                        </div>
                        <div class="action-arrow">›</div>
                    </a>
                <?php endif; ?>

                <a href="edit_profile.php" class="action-card theme-green">
                    <div class="action-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </div>
                    <div class="action-text">
                        <h3>Edit Profile</h3>
                        <p>Keep your profile updated.</p>
                    </div>
                    <div class="action-arrow">›</div>
                </a>

                <a href="profile.php?tab=skills" class="action-card theme-purple">
                    <div class="action-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z" />
                            <path d="M2 17l10 5 10-5" />
                            <path d="M2 12l10 5 10-5" />
                        </svg>
                    </div>
                    <div class="action-text">
                        <h3>Manage Skills</h3>
                        <p>Add or update your skills.</p>
                    </div>
                    <div class="action-arrow">›</div>
                </a>

                <?php if ($role === 'trainee'): ?>
                    <a href="saved_trainers.php" class="action-card theme-blue">
                        <div class="action-icon">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div class="action-text">
                            <h3>Saved Mentors</h3>
                            <p>View your bookmarked mentors.</p>
                        </div>
                        <div class="action-arrow">›</div>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- TOP MENTOR SUGGESTIONS (TRAINEE ONLY) -->
        <?php if ($role === 'trainee' && !empty($suggested_mentors)): ?>
            <section class="top-mentors-section" aria-labelledby="top-mentors-title">
                <div class="top-mentors-header">
                    <h2 id="top-mentors-title" class="section-title">Top Mentors</h2>
                    <a href="search.php" class="view-all-link">View all <span aria-hidden="true">→</span></a>
                </div>

                <div class="top-mentors-grid">
                    <?php foreach ($suggested_mentors as $mentor): 
                        $m_id = (int)$mentor['user_id'];
                        $is_saved = in_array($m_id, $saved_trainer_ids, true);
                        $exp_text = !empty($mentor['exp_level']) ? htmlspecialchars($mentor['exp_level'], ENT_QUOTES, 'UTF-8') : 'Mentor';
                        $avatar_url = getProfilePhotoUrl($mentor['profile_photo'] ?? '', $mentor['name']);
                        $mentee_cnt = (int)$mentor['mentee_count'];
                        $mentee_text = $mentee_cnt > 0 ? $mentee_cnt . '+ Mentees' : '0 Mentees';
                    ?>
                        <div class="mentor-suggest-card" 
                             onclick="window.location.href='profile.php?user_id=<?= $m_id ?>'"
                             role="link" 
                             tabindex="0"
                             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href='profile.php?user_id=<?= $m_id ?>';}">
                            
                            <!-- Bookmark button top-right -->
                            <form action="actions/saved_trainer_process.php" method="POST" class="mentor-card-bookmark-form" onclick="event.stopPropagation();">
                                <input type="hidden" name="trainer_id" value="<?= $m_id ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="return_to" value="dashboard.php">
                                <?php if ($is_saved): ?>
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="mentor-bookmark-btn is-saved" aria-label="Remove <?= htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8') ?> from saved mentors" title="Remove from saved">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="save">
                                    <button type="submit" class="mentor-bookmark-btn is-unsaved" aria-label="Bookmark <?= htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8') ?>" title="Save mentor">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </form>

                            <!-- Profile Avatar with badge -->
                            <div class="mentor-suggest-avatar-wrap">
                                <img src="<?= $avatar_url ?>" alt="<?= htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8') ?>" class="mentor-suggest-avatar">
                                <span class="mentor-suggest-avatar-badge" title="Verified Mentor" aria-hidden="true">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                </span>
                            </div>

                            <!-- Name -->
                            <h3 class="mentor-suggest-name"><?= htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8') ?></h3>

                            <!-- Expertise Level -->
                            <div class="mentor-suggest-exp"><?= $exp_text ?></div>

                            <!-- Matching / Primary Skill Pill -->
                            <div class="mentor-suggest-skill" title="<?= $mentor['has_match'] ? 'Matches your skill interest' : 'Skill offered' ?>">
                                <?= htmlspecialchars($mentor['display_skill'], ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <!-- Trainee / Mentee Count -->
                            <div class="mentor-suggest-mentees">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                                <span><?= $mentee_text ?></span>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- 4. ROLE-SPECIFIC CONNECTIONS -->
        <?php if ($role === 'trainee'): ?>
            <section class="card dashboard-people-section">
                <div class="card-header dashboard-section-header">
                    <h3 class="card-title">My Mentors</h3>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <?php if (!empty($accepted_mentorships)): ?>
                            <span class="badge-pill-active"><?= count($accepted_mentorships) ?> active</span>
                        <?php endif; ?>
                        <a href="mentorships.php" class="view-all-link">View All Mentorships <span aria-hidden="true">→</span></a>
                    </div>
                </div>
                <?php if (empty($accepted_mentorships)): ?>
                    <div class="empty-state dashboard-empty-state">
                        <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg></div>
                        <h3>No mentors yet</h3>
                        <p>Find a mentor who matches your learning goals.</p>
                        <a href="search.php" class="btn btn-primary empty-state-action">Find Mentors</a>
                    </div>
                <?php else: ?>
                    <div class="connection-card-list">
                        <?php 
                        $first_conn_skill = '';
                        foreach ($accepted_mentorships as $req): 
                            $skills_arr = !empty($req['connected_skills']) ? array_filter(array_map('trim', explode(',', $req['connected_skills']))) : [];
                            if (!$first_conn_skill && !empty($skills_arr)) {
                                $first_conn_skill = reset($skills_arr);
                            }
                            $card_user_id = (int)$req['user_id'];
                            $card_name = $req['name'];
                            $card_photo = $req['profile_photo'] ?? null;
                            $card_exp_level = $req['exp_level'] ?? null;
                            $card_skills = $skills_arr;
                            $card_primary_skill = !empty($skills_arr) ? reset($skills_arr) : '';
                            $card_role_label = 'Mentor';
                            $card_request_id = (int)($req['request_id'] ?? 0);
                            $card_show_unlink = false;
                            $card_compact = false;
                            $card_view_label = 'View Profile';
                            require __DIR__ . '/includes/partials/connection_card.php';
                        endforeach; 
                        ?>
                    </div>
                    <div class="connection-tip">
                        <span class="connection-tip-icon">Tip:</span>
                        <span>Use <strong>Message</strong> to chat and collaborate without leaving this page.</span>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="card dashboard-people-section">
                <div class="card-header dashboard-section-header">
                    <h3 class="card-title">My Mentees</h3>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <?php if (!empty($accepted_mentorships)): ?>
                            <span class="badge-pill-active"><?= count($accepted_mentorships) ?> active</span>
                        <?php endif; ?>
                        <a href="mentorships.php" class="view-all-link">View All Mentees <span aria-hidden="true">→</span></a>
                    </div>
                </div>
                <?php if (empty($accepted_mentorships)): ?>
                    <div class="empty-state dashboard-empty-state">
                        <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
                            </svg></div>
                        <h3>No mentees yet</h3>
                        <p>Mentees you accept will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="connection-card-list">
                        <?php 
                        $first_conn_skill = '';
                        foreach ($accepted_mentorships as $req): 
                            $skills_arr = !empty($req['connected_skills']) ? array_filter(array_map('trim', explode(',', $req['connected_skills']))) : [];
                            if (!$first_conn_skill && !empty($skills_arr)) {
                                $first_conn_skill = reset($skills_arr);
                            }
                            $card_user_id = (int)$req['user_id'];
                            $card_name = $req['name'];
                            $card_photo = $req['profile_photo'] ?? null;
                            $card_exp_level = $req['exp_level'] ?? null;
                            $card_skills = $skills_arr;
                            $card_primary_skill = !empty($skills_arr) ? reset($skills_arr) : '';
                            $card_role_label = 'Mentee';
                            $card_request_id = (int)($req['request_id'] ?? 0);
                            $card_show_unlink = false;
                            $card_compact = false;
                            $card_view_label = 'View Profile';
                            require __DIR__ . '/includes/partials/connection_card.php';
                        endforeach; 
                        ?>
                    </div>
                    <div class="connection-tip">
                        <span class="connection-tip-icon">Tip:</span>
                        <span>Use <strong>Message</strong> to chat and collaborate without leaving this page.</span>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <!-- 5. REQUEST PANELS -->
        <div class="dashboard-requests-grid">
            <section class="card activity-section dashboard-request-card">
                <div class="card-header dashboard-section-header">
                    <h3 class="card-title">Pending Requests <span class="dashboard-count">(<?= count($pending_requests) ?>)</span></h3>
                    <a href="mentorships.php" class="view-all-link">View All <span aria-hidden="true">→</span></a>
                </div>
                <?php if (empty($pending_requests)): ?>
                    <div class="empty-state dashboard-empty-state">
                        <div class="empty-state-icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg></div>
                        <h3>No pending requests</h3>
                        <p><?= $role === 'trainer' ? 'New mentorship requests from mentees will appear here.' : 'Your mentorship requests will appear here.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="connection-card-list">
                        <?php foreach ($display_pending as $req): 
                            $skills_arr = !empty($req['connected_skills']) ? array_filter(array_map('trim', explode(',', $req['connected_skills']))) : [];
                            $card_user_id = (int)$req['user_id'];
                            $card_name = $req['name'];
                            $card_photo = $req['profile_photo'] ?? null;
                            $card_exp_level = $req['exp_level'] ?? null;
                            $card_skills = [];
                            $card_primary_skill = !empty($skills_arr) ? reset($skills_arr) : '';
                            $card_role_label = $role === 'trainer' ? 'Mentee' : 'Mentor';
                            $card_status = 'pending';
                            $card_request_id = (int)($req['request_id'] ?? 0);
                            $card_is_incoming_request = ($role === 'trainer');
                            $card_show_unlink = false;
                            $card_compact = true;
                            $card_view_label = 'View';
                            $card_return_to = 'dashboard.php';
                            require __DIR__ . '/includes/partials/connection_card.php';
                        endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card activity-section dashboard-request-card">
                <div class="card-header dashboard-section-header">
                    <h3 class="card-title">Request History <span class="dashboard-count">(<?= count($rejected_history) ?>)</span></h3>
                    <a href="mentorships.php" class="view-all-link">View All <span aria-hidden="true">→</span></a>
                </div>
                <?php if (empty($rejected_history)): ?>
                    <div class="empty-state dashboard-empty-state">
                        <div class="empty-state-icon icon-gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                                <path d="M3 3v5h5" />
                                <path d="M12 7v5l4 2" />
                            </svg></div>
                        <h3>No request history</h3>
                        <p>Your previous mentorship requests will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="connection-card-list">
                        <?php foreach ($display_history as $req): 
                            $skills_arr = !empty($req['connected_skills']) ? array_filter(array_map('trim', explode(',', $req['connected_skills']))) : [];
                            $card_user_id = (int)$req['user_id'];
                            $card_name = $req['name'];
                            $card_photo = $req['profile_photo'] ?? null;
                            $card_exp_level = $req['exp_level'] ?? null;
                            $card_skills = [];
                            $card_primary_skill = !empty($skills_arr) ? reset($skills_arr) : '';
                            $card_role_label = $role === 'trainer' ? 'Mentee' : 'Mentor';
                            $card_status = 'rejected';
                            $card_request_id = (int)($req['request_id'] ?? 0);
                            $card_date = !empty($req['created_at']) ? date('M j, Y', strtotime($req['created_at'])) : '';
                            $card_show_unlink = false;
                            $card_compact = true;
                            $card_view_label = 'View';
                            $card_return_to = 'dashboard.php';
                            require __DIR__ . '/includes/partials/connection_card.php';
                        endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>

</div>

<?php
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>