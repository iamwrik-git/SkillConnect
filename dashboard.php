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

// Include the existing header (which should handle <head>, stylesheets, and opening <body>)
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}

// Helper function to resolve profile photo
function getProfilePhotoPath($photo) {
    return empty($photo) ? 'default.jpg' : $photo;
}
?>

<div class="dashboard-layout">

    <!-- Left Sidebar Navigation -->
    <aside class="sidebar">
        <h2 class="sidebar-logo">SkillConnect</h2>
        
        <nav class="sidebar-nav">
            <p class="sidebar-section-title">Main</p>
            <a href="dashboard.php" class="sidebar-link active">Dashboard</a>
            
            <?php if ($role === 'trainee'): ?>
                <a href="search.php" class="sidebar-link">Find Trainers</a>
            <?php endif; ?>
            
            <a href="profile.php" class="sidebar-link">Profile</a>
            <a href="edit_profile.php" class="sidebar-link">Edit Profile</a>
            
            <!-- Future Features Section (Visual UI placeholders only) -->
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
        
        <!-- Welcome Hero Section -->
        <header class="hero-card">
            <h1 class="hero-title">Welcome, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!</h1>
            <p class="hero-subtitle">
                <?php if ($role === 'trainee'): ?>
                    Connect with skilled mentors and grow your knowledge.
                <?php elseif ($role === 'trainer'): ?>
                    Share your expertise and guide the next generation of learners.
                <?php endif; ?>
            </p>
        </header>

        <!-- Role-Specific Content Grid -->
        <div class="content-grid">
            
            <?php if ($db_error): ?>
                <section class="dashboard-card error-card">
                    <h3 class="card-title">Database Error</h3>
                    <p>We encountered an issue loading your dashboard data. Please try again later.</p>
                </section>
            <?php endif; ?>

            <?php if (!$db_error && $role === 'trainee'): ?>
                <!-- ================= TRAINEE VIEW ================= -->
                
                <section class="dashboard-card">
                    <h3 class="card-title">Skills I Want to Learn</h3>
                    <?php if (empty($skills)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">You haven't added any skills yet.</p>
                            <p class="empty-state-subtext">Add your skills to get started.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($skills as $skill): ?>
                                <li class="skill-item"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card">
                    <h3 class="card-title">My Mentors</h3>
                    <?php if (empty($accepted_mentorships)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">You don't have any accepted mentors yet.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($accepted_mentorships as $req): ?>
                                <li class="skill-item">
                                    <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" width="40" height="40" class="profile-thumb">
                                    
                                    <strong><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span>(<?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    
                                    <strong>- Mentor</strong>
                                    
                                    <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="action-button">View Profile</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card">
                    <h3 class="card-title">Pending Requests</h3>
                    <?php if (empty($pending_requests)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">No pending mentorship requests.</p>
                        </div>
                        <a href="search.php" class="action-button">Find Trainers</a>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($pending_requests as $req): ?>
                                <li class="skill-item">
                                    <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" width="40" height="40" class="profile-thumb">
                                    
                                    <strong><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span>(<?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    
                                    <em>- Pending</em>
                                    
                                    <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="action-button">View Profile</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card">
                    <h3 class="card-title">Request History</h3>
                    <?php if (empty($rejected_history)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">No rejected requests.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($rejected_history as $req): ?>
                                <li class="skill-item">
                                    <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" width="40" height="40" class="profile-thumb">
                                    
                                    <strong><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span>- Requested on <?= date('M j, Y', strtotime($req['created_at'])) ?></span>
                                    
                                    <strong>(Rejected)</strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

            <?php elseif (!$db_error && $role === 'trainer'): ?>
                <!-- ================= TRAINER VIEW ================= -->
                
                <section class="dashboard-card">
                    <h3 class="card-title">Skills I Teach</h3>
                    <?php if (empty($skills)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">You haven't added any teaching skills yet.</p>
                            <p class="empty-state-subtext">Add your skills to get started.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($skills as $skill): ?>
                                <li class="skill-item"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card">
                    <h3 class="card-title">Pending Requests</h3>
                    <?php if (empty($pending_requests)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">No pending mentorship requests.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($pending_requests as $req): ?>
                                <li class="skill-item">
                                    <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" width="40" height="40" class="profile-thumb">
                                    
                                    <strong><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span>(<?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    <span>- <?= date('M j, Y', strtotime($req['created_at'])) ?></span>
                                    
                                    <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="action-button">View Profile</a>
                                    
                                    <form action="actions/mentorship_process.php" method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="accept_request">
                                        <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                        <button type="submit" class="submit-btn">Accept</button>
                                    </form>
                                    
                                    <form action="actions/mentorship_process.php" method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="reject_request">
                                        <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                        <button type="submit" class="submit-btn">Reject</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card">
                    <h3 class="card-title">Accepted Trainees</h3>
                    <?php if (empty($accepted_mentorships)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">You don't have any accepted trainees yet.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($accepted_mentorships as $req): ?>
                                <li class="skill-item">
                                    <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" width="40" height="40" class="profile-thumb">
                                    
                                    <strong><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span>(<?= htmlspecialchars(ucfirst($req['exp_level']), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    
                                    <strong>- Accepted</strong>
                                    
                                    <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="action-button">View Profile</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="dashboard-card">
                    <h3 class="card-title">Request History</h3>
                    <?php if (empty($rejected_history)): ?>
                        <div class="empty-state">
                            <p class="empty-state-text">No rejected requests.</p>
                        </div>
                    <?php else: ?>
                        <ul class="skill-list">
                            <?php foreach ($rejected_history as $req): ?>
                                <li class="skill-item">
                                    <img src="assets/images/profile/<?= htmlspecialchars(getProfilePhotoPath($req['profile_photo']), ENT_QUOTES, 'UTF-8') ?>" alt="Profile" width="40" height="40" class="profile-thumb">
                                    
                                    <strong><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span>- Requested on <?= date('M j, Y', strtotime($req['created_at'])) ?></span>
                                    
                                    <strong>(Rejected)</strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
                
            <?php elseif (!$db_error): ?>
                <!-- ================= INVALID ROLE FALLBACK ================= -->
                <section class="dashboard-card error-card">
                    <h3 class="card-title">Account Configuration Error</h3>
                    <p>Your account does not have a valid role assigned. Please contact the administrator.</p>
                </section>
            <?php endif; ?>

        </div>
    </main>
</div>

<?php 
// Include the existing footer
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php'; 
}
?>
