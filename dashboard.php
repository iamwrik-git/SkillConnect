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
$show_onboarding_profile = false;
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
        'SELECT name, bio, exp_level, security_question, security_answer_hash, onboarding_completed FROM users WHERE user_id = ? LIMIT 1'
    );
    $onboardingStmt->execute([(int) $user_id]);
    $onboarding_user = $onboardingStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $onboardingComplete = (int) ($onboarding_user['onboarding_completed'] ?? 1) === 1;
    $_SESSION['onboarding_completed'] = $onboardingComplete ? 1 : 0;

    if (!$onboardingComplete) {
        $profileIsComplete = trim((string) ($onboarding_user['name'] ?? '')) !== ''
            && in_array(($onboarding_user['exp_level'] ?? ''), ['Beginner', 'Intermediate', 'Expert'], true)
            && trim((string) ($onboarding_user['bio'] ?? '')) !== '';
        $securityIsComplete = !empty($onboarding_user['security_question'])
            && !empty($onboarding_user['security_answer_hash']);

        if ($profileIsComplete && $securityIsComplete) {
            // Recover gracefully if the security values were saved but the final flag was not.
            $finishStmt = $pdo->prepare('UPDATE users SET onboarding_completed = 1 WHERE user_id = ?');
            $finishStmt->execute([(int) $user_id]);
            $_SESSION['onboarding_completed'] = 1;
            $show_onboarding_success = true;
        } elseif (!$profileIsComplete) {
            $show_onboarding_profile = true;
        } else {
            $show_onboarding_security = true;
        }
    }
} catch (PDOException $e) {
    error_log('Onboarding state lookup failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load account setup. Please check the SkillConnect database migration and try again.');
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
            WHERE m.trainee_id = ?
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

    // ---- ADDED FOR SAVED TRAINERS FEATURE ----
    $saved_trainers_count = 0;
    if ($role === 'trainee') {
        $st_stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_trainers WHERE user_id = ?");
        $st_stmt->execute([$user_id]);
        $saved_trainers_count = $st_stmt->fetchColumn();
    }
    // ------------------------------------------

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

// Helper function to resolve profile photo or generate initials
function getProfilePhotoUrl($photo, $name)
{
    if (empty($photo) || $photo === 'default.jpg') {
        return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=eff6ff&color=2563eb&bold=true";
    }
    return 'assets/images/profile/' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8');
}
?>

<div class="dashboard-page">

    <?php if ($show_onboarding_success): ?>
        <div class="sc-onboarding-toast" role="status" aria-live="polite">
            <span class="sc-onboarding-toast-icon" aria-hidden="true">✓</span>
            <div><strong>Profile completed!</strong><span>Your account is now set up.</span></div>
            <button type="button" class="sc-onboarding-toast-close" aria-label="Dismiss notification">×</button>
        </div>
    <?php endif; ?>

    <?php if ($show_onboarding_profile || $show_onboarding_security): ?>
        <div class="sc-onboarding-backdrop" id="sc-onboarding-backdrop">
            <?php if ($show_onboarding_profile): ?>
                <section class="sc-onboarding-modal sc-onboarding-profile-modal" role="dialog" aria-modal="true" aria-labelledby="sc-onboarding-title" aria-describedby="sc-onboarding-description">
                    <aside class="sc-onboarding-aside sc-onboarding-aside-profile">
                        <img class="sc-onboarding-logo" src="assets/images/skillconnect-logo.svg" alt="SkillConnect">
                        <span class="sc-onboarding-step">STEP 1 OF 2</span>
                        <h2>Welcome to SkillConnect!</h2>
                        <p id="sc-onboarding-description">Let’s set up your profile so you can get the most out of your learning and mentorship experience.</p>
                        <div class="sc-onboarding-art" aria-hidden="true">
                            <div class="sc-art-orbit sc-art-orbit-one"></div><div class="sc-art-orbit sc-art-orbit-two"></div>
                            <div class="sc-art-laptop"><div class="sc-art-screen"><span></span><span></span><span></span><i></i><i></i><i></i></div><div class="sc-art-keyboard"></div></div>
                            <div class="sc-art-cap"><svg viewBox="0 0 48 48" fill="none"><path d="M4 17 24 7l20 10-20 10L4 17Z" fill="currentColor"/><path d="M12 22v11c7 6 17 6 24 0V22L24 28 12 22Z" fill="currentColor" opacity=".72"/><path d="M43 18v13" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></div>
                            <div class="sc-art-sparkle">✦</div>
                        </div>
                        <div class="sc-onboarding-aside-note"><span>✓</span> A better profile helps people connect with you.</div>
                    </aside>
                    <div class="sc-onboarding-form-panel">
                        <div class="sc-onboarding-form-heading"><span class="sc-onboarding-step-mobile">STEP 1 OF 2</span><h2 id="sc-onboarding-title">Complete Your Profile</h2><p>Provide a few details to get started.</p></div>
                        <?php if ($onboarding_error !== ''): ?><div class="sc-onboarding-error" role="alert"><?= htmlspecialchars($onboarding_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        <form action="actions/onboarding_process.php" method="post" class="sc-onboarding-form" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($onboarding_csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="save_profile">
                            <div class="sc-onboarding-field"><label for="sc-onboarding-name">Full Name <span>*</span></label><input id="sc-onboarding-name" name="full_name" type="text" maxlength="100" autocomplete="name" required value="<?= htmlspecialchars((string) ($onboarding_user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter your full name"></div>
                            <div class="sc-onboarding-field"><label for="sc-onboarding-level">Expertise Level <span>*</span></label><select id="sc-onboarding-level" name="exp_level" required><option value="">Select expertise level</option><option value="Beginner" <?= (($onboarding_user['exp_level'] ?? '') === 'Beginner') ? 'selected' : '' ?>>Beginner</option><option value="Intermediate" <?= (($onboarding_user['exp_level'] ?? '') === 'Intermediate') ? 'selected' : '' ?>>Intermediate</option><option value="Expert" <?= (($onboarding_user['exp_level'] ?? '') === 'Expert') ? 'selected' : '' ?>>Expert</option></select></div>
                            <div class="sc-onboarding-field"><label for="sc-onboarding-bio">Bio <span>*</span></label><textarea id="sc-onboarding-bio" name="bio" rows="4" maxlength="300" required placeholder="Tell us about yourself, your skills and interests..."><?= htmlspecialchars((string) ($onboarding_user['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea><div class="sc-onboarding-char-count"><span id="sc-onboarding-bio-count"><?= strlen((string) ($onboarding_user['bio'] ?? '')) ?></span>/300</div></div>
                            <button type="submit" class="sc-onboarding-submit">Save &amp; Continue <span aria-hidden="true">→</span></button>
                            <p class="sc-onboarding-required-note"><span>*</span> Required fields</p>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <section class="sc-onboarding-modal sc-onboarding-security-modal" role="dialog" aria-modal="true" aria-labelledby="sc-onboarding-title" aria-describedby="sc-onboarding-description">
                    <aside class="sc-onboarding-aside sc-onboarding-aside-security">
                        <img class="sc-onboarding-logo" src="assets/images/skillconnect-logo.svg" alt="SkillConnect">
                        <span class="sc-onboarding-step">STEP 2 OF 2</span>
                        <div class="sc-security-illustration" aria-hidden="true"><div class="sc-security-halo"></div><svg viewBox="0 0 100 112" fill="none"><path d="M50 5 86 19v29c0 25-15 43-36 57C29 91 14 73 14 48V19L50 5Z" fill="url(#scShieldGradient)"/><path d="M50 5 86 19v29c0 25-15 43-36 57V5Z" fill="#2563eb" opacity=".38"/><rect x="35" y="43" width="30" height="25" rx="5" fill="white"/><path d="M41 43v-7a9 9 0 0 1 18 0v7" stroke="white" stroke-width="5" stroke-linecap="round"/><circle cx="50" cy="54" r="3" fill="#2563eb"/><path d="M50 57v5" stroke="#2563eb" stroke-width="3" stroke-linecap="round"/><defs><linearGradient id="scShieldGradient" x1="14" y1="5" x2="86" y2="105" gradientUnits="userSpaceOnUse"><stop stop-color="#60a5fa"/><stop offset="1" stop-color="#1d4ed8"/></linearGradient></defs></svg></div>
                        <h2>Keep Your Account Safe</h2>
                        <p id="sc-onboarding-description">Set up your security question to help recover your account if you ever forget your password.</p>
                        <ul class="sc-security-benefits"><li><span>✓</span> Quick and easy setup</li><li><span>✓</span> Helps keep your account secure</li><li><span>✓</span> Used for password recovery</li></ul>
                    </aside>
                    <div class="sc-onboarding-form-panel">
                        <div class="sc-onboarding-form-heading"><span class="sc-onboarding-step-mobile">STEP 2 OF 2</span><h2 id="sc-onboarding-title">Set Up Security Questions</h2><p>Choose a security question and provide your answer.</p></div>
                        <?php if ($onboarding_error !== ''): ?><div class="sc-onboarding-error" role="alert"><?= htmlspecialchars($onboarding_error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
                        <form action="actions/onboarding_process.php" method="post" class="sc-onboarding-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($onboarding_csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="save_security">
                            <div class="sc-onboarding-field"><label for="sc-security-question">Security Question <span>*</span></label><select id="sc-security-question" name="security_question" required><option value="">Select a security question</option><option value="school">What was the name of your first school?</option><option value="pet">What was the name of your first pet?</option><option value="nickname">What was your childhood nickname?</option><option value="city">In what city were you born?</option></select></div>
                            <div class="sc-onboarding-field">
                                <label for="sc-security-answer">Your Answer <span>*</span></label>
                                <div style="position:relative;width:100%;">
                                    <input id="sc-security-answer" type="password" name="security_answer" maxlength="255" autocomplete="off" required placeholder="Enter your answer" style="padding-right:48px;">
                                    <button type="button" class="sc-security-answer-toggle" data-target="sc-security-answer" aria-label="Show security answer" aria-pressed="false" style="position:absolute;right:7px;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:34px;height:34px;padding:0;border:0;border-radius:7px;background:transparent;color:#64748b;cursor:pointer;">
                                        <svg class="sc-eye-icon" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="sc-onboarding-field">
                                <label for="sc-security-confirm">Confirm Answer <span>*</span></label>
                                <div style="position:relative;width:100%;">
                                    <input id="sc-security-confirm" type="password" name="confirm_security_answer" maxlength="255" autocomplete="off" required placeholder="Re-enter your answer" style="padding-right:48px;">
                                    <button type="button" class="sc-security-answer-toggle" data-target="sc-security-confirm" aria-label="Show confirmation answer" aria-pressed="false" style="position:absolute;right:7px;top:50%;transform:translateY(-50%);display:grid;place-items:center;width:34px;height:34px;padding:0;border:0;border-radius:7px;background:transparent;color:#64748b;cursor:pointer;">
                                        <svg class="sc-eye-icon" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                            </div>
                            <button type="submit" class="sc-onboarding-submit">Save &amp; Finish <span aria-hidden="true">→</span></button>
                            <p class="sc-onboarding-required-note"><span>*</span> Required fields</p>
                        </form>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($db_error): ?>
        <section class="card error-card">
            <h3 class="card-title text-danger">Database Error</h3>
            <p>We encountered an issue loading your dashboard data. Please try again later.</p>
        </section>
    <?php elseif (!in_array($role, ['trainee', 'trainer'])): ?>
        <section class="card error-card">
            <h3 class="card-title text-danger">Account Configuration Error</h3>
            <p>Your account does not have a valid role assigned. Please contact the administrator.</p>
        </section>
    <?php else: ?>

        <!-- 1. HERO SECTION -->
        <div class="dashboard-hero">
            <div class="hero-content">
                <h1 class="hero-title">Welcome back, <?= htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8') ?>!</h1>
                <p class="hero-subtitle">
                    <?php if ($role === 'trainee'): ?>
                        Keep learning, keep growing.
                    <?php else: ?>
                        Share your expertise and guide the next generation.
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Exact Replica Graphic: Waves, Plant, and Text -->
            <div class="hero-graphic">
                <svg viewBox="0 0 550 150" height="100%" preserveAspectRatio="xMaxYMax meet" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Overlapping Background Waves -->
                    <path d="M0 150 Q 150 70, 300 120 T 550 40 V 150 Z" fill="#DCE8FA" opacity="0.6"/>
                    <path d="M120 150 Q 250 100, 380 130 T 550 80 V 150 Z" fill="#C5DDFC" opacity="0.5"/>
                    <path d="M250 150 Q 320 120, 420 140 T 550 110 V 150 Z" fill="#93C5FD" opacity="0.3"/>

                    <!-- Plant Stem -->
                    <path d="M 370 150 Q 365 90, 380 30" stroke="#1E40AF" stroke-width="4" stroke-linecap="round"/>

                    <!-- Top Right Leaf -->
                    <path d="M 376 55 C 385 15, 425 15, 430 30 C 435 45, 400 75, 376 55 Z" fill="#60A5FA"/>
                    <path d="M 376 55 Q 405 40, 420 30" stroke="#2563EB" stroke-width="2" stroke-linecap="round"/>

                    <!-- Mid Left Leaf -->
                    <path d="M 372 100 C 330 65, 290 85, 295 105 C 300 125, 350 120, 372 100 Z" fill="#3B82F6"/>
                    <path d="M 372 100 Q 330 95, 310 95" stroke="#1E40AF" stroke-width="2" stroke-linecap="round"/>

                    <!-- Bottom Right Leaf -->
                    <path d="M 370 130 C 390 95, 430 105, 420 125 C 410 145, 380 145, 370 130 Z" fill="#93C5FD"/>

                    <!-- Handwritten Stacked Text -->
                    <g fill="#3B82F6" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-style="italic" font-size="22" font-weight="500" letter-spacing="0.5">
                        <text x="450" y="65">Learn</text>
                        <text x="450" y="95">Connect</text>
                        <text x="450" y="125">Grow</text>
                    </g>
                    <!-- Slanted Underline -->
                    <line x1="450" y1="138" x2="515" y2="130" stroke="#3B82F6" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>

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
                    <div class="stat-label"><?= $role === 'trainer' ? 'Accepted Trainees' : 'My Mentors' ?></div>
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

            <!-- Trainees ONLY get the Saved Trainers stat card. -->
            <?php if ($role === 'trainee'): ?>
                <a href="saved_trainers.php" class="stat-card stat-card-link" style="text-decoration: none; color: inherit; display: flex; transition: transform 0.2s ease, box-shadow 0.2s ease;">
                    <div class="stat-icon icon-purple">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                        </svg>
                    </div>
                    <div class="stat-details">
                        <div class="stat-value"><?= (int)$saved_trainers_count ?></div>
                        <div class="stat-label">Saved Trainers</div>
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
                            <h3>Find Trainers</h3>
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

                <a href="my_skills.php" class="action-card theme-purple">
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
                            <h3>Saved Trainers</h3>
                            <p>View your bookmarked mentors.</p>
                        </div>
                        <div class="action-arrow">›</div>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 4. ROLE-SPECIFIC CONNECTIONS -->
        <?php if ($role === 'trainee'): ?>
            <section class="card dashboard-people-section">
                <div class="card-header dashboard-section-header">
                    <h3 class="card-title">My Mentors</h3>
                    <a href="mentorships.php" class="view-all-link">View All Mentorships <span aria-hidden="true">→</span></a>
                </div>
                <?php if (empty($accepted_mentorships)): ?>
                    <div class="empty-state dashboard-empty-state">
                        <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                        <h3>No mentors yet</h3><p>Find a trainer who matches your learning goals.</p>
                        <a href="search.php" class="btn btn-primary empty-state-action">Find Trainers</a>
                    </div>
                <?php else: ?>
                    <div class="dashboard-people-list">
                        <?php foreach ($accepted_mentorships as $req): ?>
                            <div class="dashboard-person-row">
                                <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="" class="activity-avatar">
                                <div class="dashboard-person-identity">
                                    <div class="activity-details">
                                        <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                        <p><?= $role === 'trainee' ? 'Your mentor' : 'Accepted trainee' ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($req['connected_skills'])): ?>
                                    <div class="dashboard-person-skills" aria-label="Skills">
                                        <?php foreach (array_slice(array_filter(array_map('trim', explode(',', $req['connected_skills']))), 0, 4) as $personSkill): ?>
                                            <span class="dashboard-person-skill"><?= htmlspecialchars($personSkill, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <span class="dashboard-person-exp"><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Experience not specified'), ENT_QUOTES, 'UTF-8') ?></span>
                                <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn btn-secondary btn-sm dashboard-profile-button">View Profile</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="card dashboard-people-section">
                <div class="card-header dashboard-section-header">
                    <h3 class="card-title">Accepted Trainees</h3>
                    <a href="mentorships.php" class="view-all-link">View All Trainees <span aria-hidden="true">→</span></a>
                </div>
                <?php if (empty($accepted_mentorships)): ?>
                    <div class="empty-state dashboard-empty-state">
                        <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                        <h3>No trainees yet</h3><p>Trainees you accept will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="dashboard-people-list">
                        <?php foreach ($accepted_mentorships as $req): ?>
                            <div class="dashboard-person-row">
                                <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="" class="activity-avatar">
                                <div class="dashboard-person-identity">
                                    <div class="activity-details">
                                        <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                        <p><?= $role === 'trainee' ? 'Your mentor' : 'Accepted trainee' ?></p>
                                    </div>
                                </div>
                                <?php if (!empty($req['connected_skills'])): ?>
                                    <div class="dashboard-person-skills" aria-label="Skills">
                                        <?php foreach (array_slice(array_filter(array_map('trim', explode(',', $req['connected_skills']))), 0, 4) as $personSkill): ?>
                                            <span class="dashboard-person-skill"><?= htmlspecialchars($personSkill, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <span class="dashboard-person-exp"><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Experience not specified'), ENT_QUOTES, 'UTF-8') ?></span>
                                <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn btn-secondary btn-sm dashboard-profile-button">View Profile</a>
                            </div>
                        <?php endforeach; ?>
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
                        <div class="empty-state-icon icon-orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                        <h3>No pending requests</h3>
                        <p><?= $role === 'trainer' ? 'New mentorship requests from trainees will appear here.' : 'Your mentorship requests will appear here.' ?></p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($display_pending as $req): ?>
                            <div class="activity-item compact">
                                <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="" class="activity-avatar">
                                <div class="activity-details">
                                    <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                    <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?><?php if ($role === 'trainer'): ?> · <?= date('M j', strtotime($req['created_at'])) ?><?php endif; ?></p>
                                </div>
                                <?php if ($role === 'trainer'): ?>
                                    <div class="activity-actions form-group-inline">
                                        <form action="actions/mentorship_process.php" method="POST" class="mentorship-form inline-form"><input type="hidden" name="action" value="accept_request"><input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>"><button type="submit" class="btn btn-primary btn-sm" data-loading-text="Accepting...">Accept</button></form>
                                        <form action="actions/mentorship_process.php" method="POST" class="mentorship-form inline-form js-confirm-reject-request"><input type="hidden" name="action" value="reject_request"><input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>"><button type="submit" class="btn btn-secondary btn-sm" data-loading-text="Rejecting...">Reject</button></form>
                                    </div>
                                <?php else: ?><span class="badge badge-warning">Pending</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
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
                        <div class="empty-state-icon icon-gray"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg></div>
                        <h3>No request history</h3><p>Your previous mentorship requests will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($display_history as $req): ?>
                            <div class="activity-item compact">
                                <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="" class="activity-avatar">
                                <div class="activity-details"><h4><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></h4><p>Requested on <?= date('M j, Y', strtotime($req['created_at'])) ?></p></div>
                                <span class="badge badge-danger">Rejected</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const backdrop = document.getElementById('sc-onboarding-backdrop');
    if (backdrop) {
        document.body.classList.add('sc-onboarding-active');
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') event.preventDefault();
        });
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) event.preventDefault();
        });
        const firstInput = backdrop.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) firstInput.focus({preventScroll: true});
    }
    document.querySelectorAll('.sc-security-answer-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.target);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide security answer' : (input.id === 'sc-security-confirm' ? 'Show confirmation answer' : 'Show security answer'));
            button.innerHTML = show
                ? '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a16.6 16.6 0 0 1-3.2 4.1"/><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"/></svg>'
                : '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
        });
    });
    const bio = document.getElementById('sc-onboarding-bio');
    const count = document.getElementById('sc-onboarding-bio-count');
    if (bio && count) bio.addEventListener('input', () => count.textContent = String(bio.value.length));
    const toast = document.querySelector('.sc-onboarding-toast');
    if (toast) {
        const close = toast.querySelector('.sc-onboarding-toast-close');
        if (close) close.addEventListener('click', () => toast.remove());
        window.setTimeout(() => toast.remove(), 6500);
    }
});
</script>

<?php
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>