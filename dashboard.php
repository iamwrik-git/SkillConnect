<?php
// dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 1. Include db connection and authentication helpers
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

// 2. Enforce authentication and redirect if not logged in
requireLogin();

// 3. Retrieve core session data
$user_id = getCurrentUserId();
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'User';

// 4. Fetch Database Information
// THE EXISTING BACKEND LOGIC IS STRICTLY PRESERVED HERE
$skills = [];
$pending_requests = [];
$accepted_mentorships = [];
$rejected_history = [];
$db_error = false;

try {
    // Fetch Skills (Retained for statistics count)
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
                   u.user_id, u.name, u.profile_photo, u.exp_level
            FROM mentorships m
            JOIN users u ON m.trainer_id = u.user_id
            WHERE m.trainee_id = ?
            ORDER BY m.created_at DESC
        ");
    } elseif ($role === 'trainer') {
        $mentorship_stmt = $pdo->prepare("
            SELECT m.request_id, m.status, m.created_at, 
                   u.user_id, u.name, u.profile_photo, u.exp_level
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

} catch (PDOException $e) {
    error_log("Dashboard DB Error: " . $e->getMessage());
    $db_error = true;
}

// Include the existing header (which should handle <head>, stylesheets, and opening <body>)
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}

// Helper function to resolve profile photo
function getProfilePhotoPath($photo) {
    return empty($photo) ? 'default.jpg' : $photo;
}
?>

<div class="dashboard-page">

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
                <h1 class="hero-title">Welcome back, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
                <p class="hero-subtitle">
                    <?php if ($role === 'trainee'): ?>
                        Keep learning, keep growing.
                    <?php else: ?>
                        Share your expertise and guide the next generation.
                    <?php endif; ?>
                </p>
            </div>
            <div class="hero-graphic">
                <svg width="120" height="120" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill="currentColor" opacity="0.1"/>
                    <path d="M16 10L12 14L8 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>
                </svg>
            </div>
        </div>

        <!-- 2. STATISTICS -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= count($skills) ?></div>
                    <div class="stat-label"><?= $role === 'trainer' ? 'Skills I Teach' : 'Skills Added' ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= count($accepted_mentorships) ?></div>
                    <div class="stat-label"><?= $role === 'trainer' ? 'Accepted Trainees' : 'My Mentors' ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                </div>
                <div class="stat-details">
                    <div class="stat-value"><?= count($pending_requests) ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                </div>
                <div class="stat-details">
                    <div class="stat-value text-muted stat-value-text">Coming Soon</div>
                    <div class="stat-label">Saved Trainers</div>
                </div>
            </div>
        </div>

        <!-- 3. QUICK ACTIONS -->
        <div class="quick-actions-section">
            <h2 class="section-title">Quick Actions</h2>
            
            <div class="quick-actions-grid">
                <?php if ($role === 'trainee'): ?>
                    <a href="search.php" class="action-card">
                        <div class="action-icon bg-blue">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        </div>
                        <div class="action-text">
                            <h3>Find Trainers</h3>
                            <p>Search by skill and connect with mentors.</p>
                        </div>
                        <div class="action-arrow">›</div>
                    </a>
                <?php endif; ?>

                <a href="edit_profile.php" class="action-card">
                    <div class="action-icon bg-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div class="action-text">
                        <h3>Edit Profile</h3>
                        <p>Keep your profile updated.</p>
                    </div>
                    <div class="action-arrow">›</div>
                </a>

                <a href="edit_profile.php" class="action-card">
                    <div class="action-icon bg-purple">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                    </div>
                    <div class="action-text">
                        <h3>Manage Skills</h3>
                        <p>Add or update your skills.</p>
                    </div>
                    <div class="action-arrow">›</div>
                </a>
            </div>
        </div>

        <!-- 4. MAIN DASHBOARD CONTENT GRID (50/50 Layout) -->
        <div class="dashboard-content-grid">
            
            <?php if ($role === 'trainee'): ?>
                <!-- ================= TRAINEE VIEW ================= -->
                
                <!-- LEFT COLUMN -->
                <div class="dashboard-column">
                    <div class="card activity-section">
                        <div class="card-header">
                            <h3 class="card-title">My Mentors</h3>
                        </div>
                        <?php if (empty($accepted_mentorships)): ?>
                            <div class="empty-state">
                                <p>You don't have any accepted mentors yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($accepted_mentorships as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-success">Mentor</span>
                                            <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn btn-secondary btn-sm">Profile</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="dashboard-column">
                    <div class="card activity-section">
                        <div class="card-header">
                            <h3 class="card-title">Pending Requests</h3>
                        </div>
                        <?php if (empty($pending_requests)): ?>
                            <div class="empty-state">
                                <p>No pending mentorship requests.</p>
                                <a href="search.php" class="btn btn-primary empty-state-action">Find Trainers</a>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($pending_requests as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-warning">Pending</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($rejected_history)): ?>
                        <div class="card activity-section mt-4">
                            <div class="card-header">
                                <h3 class="card-title">Request History</h3>
                            </div>
                            <div class="activity-list">
                                <?php foreach ($rejected_history as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                            <p>Requested on <?= date('M j, Y', strtotime($req['created_at'])) ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-danger">Rejected</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- ================= TRAINER VIEW ================= -->
                
                <!-- LEFT COLUMN -->
                <div class="dashboard-column">
                    <div class="card activity-section">
                        <div class="card-header">
                            <h3 class="card-title">Pending Requests</h3>
                        </div>
                        <?php if (empty($pending_requests)): ?>
                            <div class="empty-state">
                                <p>No pending mentorship requests.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($pending_requests as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?> • <?= date('M j', strtotime($req['created_at'])) ?></p>
                                        </div>
                                        <div class="activity-actions form-group-inline">
                                            <!-- EXACT PRESERVED ACCEPT/REJECT FORMS -->
                                            <form action="actions/mentorship_process.php" method="POST" class="mentorship-form inline-form">
                                                <input type="hidden" name="action" value="accept_request">
                                                <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm">Accept</button>
                                            </form>
                                            <form action="actions/mentorship_process.php" method="POST" class="mentorship-form inline-form">
                                                <input type="hidden" name="action" value="reject_request">
                                                <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="dashboard-column">
                    <div class="card activity-section">
                        <div class="card-header">
                            <h3 class="card-title">Accepted Trainees</h3>
                        </div>
                        <?php if (empty($accepted_mentorships)): ?>
                            <div class="empty-state">
                                <p>You don't have any accepted trainees yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($accepted_mentorships as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-success">Accepted</span>
                                            <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn btn-secondary btn-sm">Profile</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($rejected_history)): ?>
                        <div class="card activity-section mt-4">
                            <div class="card-header">
                                <h3 class="card-title">Request History</h3>
                            </div>
                            <div class="activity-list">
                                <?php foreach ($rejected_history as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                            <p>Requested on <?= date('M j, Y', strtotime($req['created_at'])) ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-danger">Rejected</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>
    <?php endif; ?>

</div>

<?php 
// Include the existing footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php'; 
}
?>
