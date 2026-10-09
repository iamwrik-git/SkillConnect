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
$name = $_SESSION['name'] ?? 'User';

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

// Implement UX Layout Rules: Slice arrays for compact preview
$display_accepted = array_slice($accepted_mentorships, 0, 1);
$display_pending  = array_slice($pending_requests, 0, 2);
$display_history  = array_slice($rejected_history, 0, 2);

// Include the existing header
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}

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
                <h1 class="hero-title">Welcome, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
                <p class="hero-subtitle">
                    <?php if ($role === 'trainee'): ?>
                        Keep learning, keep growing.
                    <?php else: ?>
                        Share your expertise and guide the next generation.
                    <?php endif; ?>
                </p>
            </div>
            <div class="hero-graphic" style="right: 1rem;">
                <svg width="240" height="120" viewBox="0 0 240 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Abstract Background Leaves/Shapes -->
                    <path d="M220 80C220 50 190 40 190 40C190 40 180 70 200 90C210 100 220 80 220 80Z" fill="#93C5FD" opacity="0.6"/>
                    <path d="M30 110C30 80 60 70 60 70C60 70 70 100 50 120C40 130 30 110 30 110Z" fill="#BFDBFE" opacity="0.6"/>
                    <path d="M120 120 C120 70 160 50 160 50 C160 50 155 90 180 110 C190 118 170 120 120 120Z" fill="#DBEAFE" opacity="0.8"/>
                    <!-- Lightbulb motif -->
                    <circle cx="170" cy="35" r="14" fill="#FDE047"/>
                    <path d="M165 46 L175 46 L173 52 L167 52 Z" fill="#FBBF24"/>
                    <path d="M168 52 L172 52 L171 56 L169 56 Z" fill="#B45309"/>
                    <!-- Student/Trainer character -->
                    <circle cx="100" cy="45" r="16" fill="#FCA5A5"/> 
                    <path d="M80 110 C80 85 90 75 100 75 C110 75 120 85 120 110 Z" fill="#2563EB"/>
                    <!-- Laptop element -->
                    <rect x="110" y="75" width="56" height="34" rx="2" fill="#1E293B"/> 
                    <rect x="100" y="105" width="76" height="5" rx="2" fill="#475569"/> 
                    <rect x="115" y="79" width="46" height="26" fill="#E2E8F0"/> 
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

            <div class="stat-card">
                <div class="stat-icon icon-purple">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                    </svg>
                </div>
                <div class="stat-details">
                    <div class="stat-value text-muted stat-value-text">Coming Soon</div>
                    <div class="stat-label"><?= $role === 'trainer' ? 'Saved Trainees' : 'Saved Trainers' ?></div>
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
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

                <a href="edit_profile.php" class="action-card">
                    <div class="action-icon bg-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

                <a href="my_skills.php" class="action-card">
                    <div class="action-icon bg-purple">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
            </div>
        </div>

        <!-- 4. MAIN DASHBOARD CONTENT GRID -->
        <div class="dashboard-content-grid">

            <?php if ($role === 'trainee'): ?>
                <!-- ================= TRAINEE VIEW ================= -->

                <!-- LEFT COLUMN -->
                <div class="dashboard-column">
                    <div class="card activity-section" style="padding-bottom: 1.5rem;">
                        <div class="card-header" style="margin-bottom: 0;">
                            <h3 class="card-title">My Mentors</h3>
                            <?php if (count($accepted_mentorships) > 0): ?>
                            <a href="mentorships.php" class="text-muted" style="font-size:1.2rem; text-decoration:none;">›</a>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($accepted_mentorships)): ?>
                            <div class="empty-state" style="margin-top: 1rem;">
                                <div class="empty-state-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                </div>
                                <h3>No mentors yet</h3>
                                <p>Find a trainer who matches your learning goals.</p>
                                <a href="search.php" class="btn btn-primary empty-state-action">Find Trainers</a>
                            </div>
                        <?php else: ?>
                            <div class="activity-list" style="margin-bottom: 1rem;">
                                <?php foreach ($display_accepted as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-success">Mentor</span>
                                            <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn btn-secondary btn-sm">Profile</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="mentorships.php" class="btn btn-primary full-width-btn">View My Mentorships &rarr;</a>
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
                                <div class="empty-state-icon icon-orange">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                </div>
                                <h3>No pending requests</h3>
                                <p>Your mentorship requests will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($display_pending as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-warning">Pending</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($pending_requests) > count($display_pending)): ?>
                                <div style="text-align: center; margin-top: 1rem;">
                                    <a href="mentorships.php" class="view-all-link">View All Requests &rarr;</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="card activity-section mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Request History</h3>
                        </div>
                        <?php if (empty($rejected_history)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon icon-gray">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                                </div>
                                <h3>No request history</h3>
                                <p>Your previous mentorship requests will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($display_history as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile" class="activity-avatar">
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
                            <?php if (count($rejected_history) > count($display_history)): ?>
                                <div style="text-align: center; margin-top: 1rem;">
                                    <a href="mentorships.php" class="view-all-link">View Full History &rarr;</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
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
                                <div class="empty-state-icon icon-orange">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                </div>
                                <h3>No pending requests</h3>
                                <p>New mentorship requests from trainees will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($display_pending as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile" class="activity-avatar">

                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?> • <?= date('M j', strtotime($req['created_at'])) ?></p>
                                        </div>

                                        <div class="activity-actions form-group-inline">
                                            <form action="actions/mentorship_process.php" method="POST" class="mentorship-form inline-form">
                                                <input type="hidden" name="action" value="accept_request">
                                                <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Accepting...">Accept</button>
                                            </form>
                                            <form action="actions/mentorship_process.php" method="POST" class="mentorship-form inline-form js-confirm-reject-request">
                                                <input type="hidden" name="action" value="reject_request">
                                                <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" data-loading-text="Rejecting...">Reject</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($pending_requests) > count($display_pending)): ?>
                                <div style="text-align: center; margin-top: 1rem;">
                                    <a href="mentorships.php" class="view-all-link">View All Requests &rarr;</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card activity-section mt-4">
                        <div class="card-header">
                            <h3 class="card-title">Request History</h3>
                        </div>
                        <?php if (empty($rejected_history)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon icon-gray">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                                </div>
                                <h3>No request history</h3>
                                <p>Your previous mentorship requests will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach ($display_history as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile" class="activity-avatar">
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
                            <?php if (count($rejected_history) > count($display_history)): ?>
                                <div style="text-align: center; margin-top: 1rem;">
                                    <a href="mentorships.php" class="view-all-link">View Full History &rarr;</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="dashboard-column">
                    <div class="card activity-section" style="padding-bottom: 1.5rem;">
                        <div class="card-header" style="margin-bottom: 0;">
                            <h3 class="card-title">Accepted Trainees</h3>
                            <?php if (count($accepted_mentorships) > 0): ?>
                            <a href="mentorships.php" class="text-muted" style="font-size:1.2rem; text-decoration:none;">›</a>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($accepted_mentorships)): ?>
                            <div class="empty-state" style="margin-top: 1rem;">
                                <div class="empty-state-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                </div>
                                <h3>No trainees yet</h3>
                                <p>Trainees you accept will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="activity-list" style="margin-bottom: 1rem;">
                                <?php foreach ($display_accepted as $req): ?>
                                    <div class="activity-item compact">
                                        <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile" class="activity-avatar">
                                        <div class="activity-details">
                                            <h4><a href="profile.php?user_id=<?= (int)$req['user_id'] ?>"><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></a></h4>
                                            <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?></p>
                                        </div>
                                        <div class="activity-actions">
                                            <span class="badge badge-success">Trainee</span>
                                            <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn btn-secondary btn-sm">Profile</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <a href="mentorships.php" class="btn btn-primary full-width-btn">View My Trainees &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    <?php endif; ?>

</div>

<?php
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
