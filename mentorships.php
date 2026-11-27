<?php
// mentorships.php

require_once 'includes/db_connect.php';
require_once 'includes/auth.php';

// Enforce authentication
requireLogin();

$user_id = getCurrentUserId();
$role = $_SESSION['role'] ?? 'trainee';

$page_title = $role === 'trainer' ? 'My Mentees' : 'My Mentors';

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
?>

<div class="mentorships-page">

    <div class="mentorships-header">
        <h1 class="page-title"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="page-subtitle">Manage your <?= $role === 'trainer' ? 'mentees' : 'mentors' ?>, pending requests and history in one place.</p>
    </div>

    <!-- 1. ACCEPTED MENTORSHIPS -->
    <div class="mentorship-list-card">
        <div class="mentorship-list-header">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <h3><?= $role === 'trainer' ? 'My Mentees' : 'My Mentors' ?> (<?= count($accepted) ?>)</h3>
                <?php if (!empty($accepted)): ?>
                    <span class="badge-pill-active"><?= count($accepted) ?> active</span>
                <?php endif; ?>
            </div>
            <p><?= $role === 'trainer' ? 'Mentees you are mentoring.' : 'Mentors who have accepted your mentorship requests.' ?></p>
        </div>
        <div class="mentorship-list-body">
            <?php if (empty($accepted)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon icon-blue">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <h4>No active <?= $role === 'trainer' ? 'mentees' : 'mentors' ?> yet</h4>
                    <p><?= $role === 'trainer' ? 'When you accept mentorship requests, your mentees will appear here.' : 'When mentors accept your requests, they will appear here.' ?></p>
                </div>
            <?php else: ?>
                <div class="connection-card-list">
                    <?php 
                    $first_conn_skill = '';
                    foreach ($accepted as $req): 
                        $skills_arr = !empty($req['skills']) ? array_unique(array_filter(array_map('trim', explode(',', $req['skills'])))) : [];
                        if (!$first_conn_skill && !empty($skills_arr)) {
                            $first_conn_skill = reset($skills_arr);
                        }
                        $card_user_id = (int)$req['user_id'];
                        $card_name = $req['name'];
                        $card_photo = $req['profile_photo'] ?? null;
                        $card_exp_level = $req['exp_level'] ?? null;
                        $card_skills = $skills_arr;
                        $card_primary_skill = !empty($skills_arr) ? reset($skills_arr) : '';
                        $card_role_label = $role === 'trainer' ? 'Mentee' : 'Mentor';
                        $card_request_id = (int)($req['request_id'] ?? 0);
                        $card_show_unlink = true;
                        $card_compact = false;
                        $card_view_label = 'View Profile';
                        require __DIR__ . '/includes/partials/connection_card.php';
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. PENDING REQUESTS -->
    <div class="mentorship-list-card">
        <div class="mentorship-list-header">
            <h3>Pending Requests (<?= count($pending) ?>)</h3>
            <p><?= $role === 'trainer' ? 'Mentees who have requested your mentorship.' : 'Your mentorship requests that are waiting for a response.' ?></p>
        </div>
        <div class="mentorship-list-body">
            <?php if (empty($pending)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon icon-orange">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <h4>No pending requests</h4>
                    <p><?= $role === 'trainer' ? 'You have no pending mentorship requests waiting for review.' : 'You have no mentorship requests awaiting response.' ?></p>
                </div>
            <?php else: ?>
                <div class="connection-card-list">
                    <?php foreach ($pending as $req): 
                        $skills_arr = !empty($req['skills']) ? array_unique(array_filter(array_map('trim', explode(',', $req['skills'])))) : [];
                        $card_user_id = (int)$req['user_id'];
                        $card_name = $req['name'];
                        $card_photo = $req['profile_photo'] ?? null;
                        $card_exp_level = $req['exp_level'] ?? null;
                        $card_skills = $skills_arr;
                        $card_primary_skill = !empty($skills_arr) ? reset($skills_arr) : '';
                        $card_role_label = $role === 'trainer' ? 'Mentee' : 'Mentor';
                        $card_status = 'pending';
                        $card_request_id = (int)($req['request_id'] ?? 0);
                        $card_is_incoming_request = ($role === 'trainer');
                        $card_show_unlink = false;
                        $card_compact = false;
                        $card_view_label = 'View Profile';
                        $card_return_to = 'mentorships.php';
                        require __DIR__ . '/includes/partials/connection_card.php';
                    endforeach; ?>
                </div>
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
                <div class="empty-state">
                    <div class="empty-state-icon icon-rejected">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </div>
                    <h4>No request history</h4>
                    <p><?= $role === 'trainer' ? 'Archived or declined mentorship requests will appear here.' : 'Past or rejected mentorship requests will be archived here.' ?></p>
                </div>
            <?php else: ?>
                <div class="connection-card-list">
                    <?php foreach ($history as $req): 
                        $skills_arr = !empty($req['skills']) ? array_unique(array_filter(array_map('trim', explode(',', $req['skills'])))) : [];
                        $card_user_id = (int)$req['user_id'];
                        $card_name = $req['name'];
                        $card_photo = $req['profile_photo'] ?? null;
                        $card_exp_level = $req['exp_level'] ?? null;
                        $card_skills = $skills_arr;
                        $card_primary_skill = !empty($skills_arr) ? reset($skills_arr) : '';
                        $card_role_label = $role === 'trainer' ? 'Mentee' : 'Mentor';
                        $card_status = 'rejected';
                        $card_request_id = (int)($req['request_id'] ?? 0);
                        $card_date = !empty($req['created_at']) ? date('M j, Y', strtotime($req['created_at'])) : '';
                        $card_show_unlink = false;
                        $card_compact = false;
                        $card_view_label = 'View Profile';
                        $card_return_to = 'mentorships.php';
                        require __DIR__ . '/includes/partials/connection_card.php';
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>