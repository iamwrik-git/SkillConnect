<?php
// mentorships.php

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

// Enforce authentication
requireLogin();

$user_id = getCurrentUserId();
$role = $_SESSION['role'] ?? 'trainee';

$page_title = $role === 'trainer' ? 'My Trainees' : 'My Mentorships';

$accepted = [];
$pending = [];
$history = [];

try {
    // We execute the full fetch. We use a LEFT JOIN to gather skills grouped locally 
    // to present inline skill chips without causing additional API requests.
    if ($role === 'trainee') {
        $stmt = $pdo->prepare("
            SELECT m.request_id, m.status, m.created_at, 
                   u.user_id, u.name, u.profile_photo, u.exp_level,
                   GROUP_CONCAT(s.skill_name SEPARATOR ',') as skills
            FROM mentorships m
            JOIN users u ON m.trainer_id = u.user_id
            LEFT JOIN user_skills us ON u.user_id = us.user_id
            LEFT JOIN skills s ON us.skill_id = s.skill_id
            WHERE m.trainee_id = ?
            GROUP BY m.request_id
            ORDER BY m.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT m.request_id, m.status, m.created_at, 
                   u.user_id, u.name, u.profile_photo, u.exp_level,
                   GROUP_CONCAT(s.skill_name SEPARATOR ',') as skills
            FROM mentorships m
            JOIN users u ON m.trainee_id = u.user_id
            LEFT JOIN user_skills us ON u.user_id = us.user_id
            LEFT JOIN skills s ON us.skill_id = s.skill_id
            WHERE m.trainer_id = ?
            GROUP BY m.request_id
            ORDER BY m.created_at DESC
        ");
    }
    
    $stmt->execute([$user_id]);
    $all_mentorships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_mentorships as $m) {
        if ($m['status'] === 'accepted') {
            $accepted[] = $m;
        } elseif ($m['status'] === 'pending') {
            $pending[] = $m;
        } elseif ($m['status'] === 'rejected') {
            $history[] = $m;
        }
    }
} catch (PDOException $e) {
    error_log("Mentorships DB Error: " . $e->getMessage());
}

if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}

function getProfilePhotoUrl($photo, $name) {
    if (empty($photo) || $photo === 'default.jpg') {
        return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=eff6ff&color=2563eb&bold=true";
    }
    return 'assets/images/profile/' . htmlspecialchars($photo, ENT_QUOTES, 'UTF-8');
}
?>

<div class="mentorships-page">
    
    <div class="mentorships-header">
        <h1 class="page-title"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="page-subtitle">Manage your <?= $role === 'trainer' ? 'trainees' : 'mentors' ?>, pending requests and history in one place.</p>
    </div>

    <!-- 1. ACCEPTED MENTORSHIPS -->
    <div class="mentorship-list-card">
        <div class="mentorship-list-header">
            <h3><?= $role === 'trainer' ? 'Accepted Trainees' : 'My Mentors' ?> (<?= count($accepted) ?>)</h3>
            <p><?= $role === 'trainer' ? 'Trainees who have accepted your mentorship.' : 'Trainers who have accepted your mentorship requests.' ?></p>
        </div>
        <div class="mentorship-list-body">
            <?php if (empty($accepted)): ?>
                <div class="empty-state" style="border: none; box-shadow: none;">
                    <div class="empty-state-icon" style="margin-bottom: 1rem;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    </div>
                    <p>No <?= $role === 'trainer' ? 'trainees' : 'mentors' ?> yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($accepted as $req): ?>
                    <?php $skills_arr = !empty($req['skills']) ? array_unique(explode(',', $req['skills'])) : []; ?>
                    <div class="mentorship-list-item">
                        <div class="mentorship-user-info">
                            <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile">
                            <div>
                                <h4><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="mentorship-skills skill-chips-mini">
                            <?php foreach (array_slice($skills_arr, 0, 4) as $skill): ?>
                                <span class="chip-mini"><?= htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                            <?php if (count($skills_arr) > 4): ?>
                                <span class="chip-mini">+<?= count($skills_arr) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mentorship-actions">
                            <span class="badge badge-success"><?= $role === 'trainer' ? 'Trainee' : 'Mentor' ?></span>
                            <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn-view-profile-outline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                View Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. PENDING REQUESTS -->
    <div class="mentorship-list-card">
        <div class="mentorship-list-header">
            <h3>Pending Requests (<?= count($pending) ?>)</h3>
            <p><?= $role === 'trainer' ? 'Trainees who have requested your mentorship.' : 'Your mentorship requests that are waiting for a response.' ?></p>
        </div>
        <div class="mentorship-list-body">
            <?php if (empty($pending)): ?>
                <div class="empty-state" style="border: none; box-shadow: none;">
                    <p>No pending requests.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending as $req): ?>
                    <?php $skills_arr = !empty($req['skills']) ? array_unique(explode(',', $req['skills'])) : []; ?>
                    <div class="mentorship-list-item">
                        <div class="mentorship-user-info">
                            <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile">
                            <div>
                                <h4><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="mentorship-skills skill-chips-mini">
                            <?php foreach (array_slice($skills_arr, 0, 4) as $skill): ?>
                                <span class="chip-mini"><?= htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                            <?php if (count($skills_arr) > 4): ?>
                                <span class="chip-mini">+<?= count($skills_arr) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mentorship-actions">
                            <?php if ($role === 'trainee'): ?>
                                <span class="badge badge-warning">Pending</span>
                                <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn-view-profile-outline">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    View Profile
                                </a>
                            <?php else: ?>
                                <form action="actions/mentorship_process.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="accept_request">
                                    <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                    <button type="submit" class="btn btn-accept btn-sm" data-loading-text="Accepting...">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"></path></svg> 
                                        Accept
                                    </button>
                                </form>
                                <form action="actions/mentorship_process.php" method="POST" class="js-confirm-reject-request" style="margin:0;">
                                    <input type="hidden" name="action" value="reject_request">
                                    <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                    <button type="submit" class="btn btn-reject btn-sm" data-loading-text="Rejecting...">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> 
                                        Reject
                                    </button>
                                </form>
                                <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn-view-profile-outline" style="margin-left: 0.5rem;">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    View Profile
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. REQUEST HISTORY -->
    <div class="mentorship-list-card">
        <div class="mentorship-list-header">
            <h3>Request History (<?= count($history) ?>)</h3>
            <p><?= $role === 'trainer' ? 'Past requests that were not accepted.' : 'View your past mentorship requests.' ?></p>
        </div>
        <div class="mentorship-list-body">
            <?php if (empty($history)): ?>
                <div class="empty-state" style="border: none; box-shadow: none;">
                    <p>No request history.</p>
                </div>
            <?php else: ?>
                <?php foreach ($history as $req): ?>
                    <?php $skills_arr = !empty($req['skills']) ? array_unique(explode(',', $req['skills'])) : []; ?>
                    <div class="mentorship-list-item">
                        <div class="mentorship-user-info">
                            <img src="<?= getProfilePhotoUrl($req['profile_photo'], $req['name']) ?>" alt="Profile">
                            <div>
                                <h4><?= htmlspecialchars($req['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                <p><?= htmlspecialchars(ucfirst($req['exp_level'] ?? 'Not specified'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                        <div class="mentorship-skills skill-chips-mini">
                            <?php foreach (array_slice($skills_arr, 0, 4) as $skill): ?>
                                <span class="chip-mini"><?= htmlspecialchars(trim($skill), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="mentorship-actions">
                            <span class="badge badge-danger">Rejected</span>
                            <span class="date-text"><?= date('M j, Y', strtotime($req['created_at'])) ?></span>
                            <a href="profile.php?user_id=<?= (int)$req['user_id'] ?>" class="btn-view-profile-outline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                View Profile
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
