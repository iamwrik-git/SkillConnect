<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/helpers.php';

// Fetch Statistics
try {
    // 1. User Stats (Admin excluded)
    $userStats = $pdo->query("
        SELECT COUNT(*) AS total_users,
               COALESCE(SUM(CASE WHEN role = 'trainee' THEN 1 ELSE 0 END), 0) AS trainees,
               COALESCE(SUM(CASE WHEN role = 'trainer' THEN 1 ELSE 0 END), 0) AS trainers
        FROM users
        WHERE (is_admin = 0 OR is_admin IS NULL)
    ")->fetch(PDO::FETCH_ASSOC);

    // 2. Mentorship Request Stats
    $requestStats = $pdo->query("
        SELECT COUNT(*) AS total_requests,
               COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
               COALESCE(SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted,
               COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected
        FROM mentorships
    ")->fetch(PDO::FETCH_ASSOC);

    // 3. Active Users Today (Admin excluded)
    $activeTodayCount = (int) $pdo->query("
        SELECT COUNT(*) 
        FROM users 
        WHERE (DATE(last_login) = CURDATE() OR DATE(created_at) = CURDATE()) 
          AND (is_admin = 0 OR is_admin IS NULL)
    ")->fetchColumn();

    $totalUsers = (int) ($userStats['total_users'] ?? 0);
    $trainees = (int) ($userStats['trainees'] ?? 0);
    $trainers = (int) ($userStats['trainers'] ?? 0);
    $totalRequests = (int) ($requestStats['total_requests'] ?? 0);
    $pending = (int) ($requestStats['pending'] ?? 0);
    $accepted = (int) ($requestStats['accepted'] ?? 0);
    $rejected = (int) ($requestStats['rejected'] ?? 0);

    // 4. Weekly Registrations (Last 7 days)
    $weeklyDays = [];
    for ($i = 6; $i >= 0; $i--) {
        $dayKey = date('Y-m-d', strtotime("-$i days"));
        $weeklyDays[$dayKey] = [
            'day_name' => date('D', strtotime($dayKey)),
            'date_label' => date('M j', strtotime($dayKey)),
            'mentees' => 0,
            'mentors' => 0,
        ];
    }
    $weeklyStmt = $pdo->query("
        SELECT DATE(created_at) AS reg_date,
               COALESCE(SUM(CASE WHEN role = 'trainee' THEN 1 ELSE 0 END), 0) AS mentees,
               COALESCE(SUM(CASE WHEN role = 'trainer' THEN 1 ELSE 0 END), 0) AS mentors
        FROM users
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
          AND (is_admin = 0 OR is_admin IS NULL)
        GROUP BY DATE(created_at)
    ");
    while ($row = $weeklyStmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($weeklyDays[$row['reg_date']])) {
            $weeklyDays[$row['reg_date']]['mentees'] = (int) $row['mentees'];
            $weeklyDays[$row['reg_date']]['mentors'] = (int) $row['mentors'];
        }
    }
    $maxDayVal = 1;
    foreach ($weeklyDays as $d) {
        $maxDayVal = max($maxDayVal, $d['mentees'], $d['mentors']);
    }

    // 5. Top Mentors (Admin excluded)
    $topMentors = $pdo->query("
        SELECT u.user_id, u.name, u.email, u.profile_photo, u.exp_level,
               COUNT(m.request_id) AS total_requests,
               COALESCE(SUM(CASE WHEN m.status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted_count
        FROM users u
        LEFT JOIN mentorships m ON u.user_id = m.trainer_id
        WHERE u.role = 'trainer' AND (u.is_admin = 0 OR u.is_admin IS NULL)
        GROUP BY u.user_id, u.name, u.email, u.profile_photo, u.exp_level
        ORDER BY accepted_count DESC, total_requests DESC, u.created_at ASC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 6. Most Demanded Skills (Admin excluded)
    $demandedSkills = $pdo->query("
        SELECT s.skill_id, s.skill_name,
               COUNT(us.user_id) AS total_count,
               COALESCE(SUM(CASE WHEN u.role = 'trainer' THEN 1 ELSE 0 END), 0) AS mentor_count,
               COALESCE(SUM(CASE WHEN u.role = 'trainee' THEN 1 ELSE 0 END), 0) AS mentee_count
        FROM skills s
        JOIN user_skills us ON s.skill_id = us.skill_id
        JOIN users u ON us.user_id = u.user_id AND (u.is_admin = 0 OR u.is_admin IS NULL)
        GROUP BY s.skill_id, s.skill_name
        ORDER BY total_count DESC, s.skill_name ASC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    $maxSkillCount = 1;
    foreach ($demandedSkills as $sk) {
        $maxSkillCount = max($maxSkillCount, (int) $sk['total_count']);
    }

    // 7. Recent Registrations (Admin excluded)
    $recentUsers = $pdo->query("
        SELECT user_id, name, email, role, created_at, profile_photo 
        FROM users 
        WHERE (is_admin = 0 OR is_admin IS NULL) 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $totalUsers = $trainees = $trainers = $totalRequests = $activeTodayCount = 0;
    $pending = $accepted = $rejected = 0;
    $weeklyDays = [];
    $maxDayVal = 1;
    $topMentors = [];
    $demandedSkills = [];
    $maxSkillCount = 1;
    $recentUsers = [];
}

// Start Content Buffer
ob_start();
?>
<!-- WELCOME BANNER (Modeled after original user dashboard) -->
<div class="dashboard-hero-banner">
    <div class="hero-text-wrap">
        <h1>Welcome, Admin 👋</h1>
        <p>Monitor platform performance, manage mentors and mentees, and track real-time mentorship activity.</p>
    </div>
    <div class="hero-badge-wrap">
        <span class="hero-system-pill">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <?= date('D, M j, Y') ?>
        </span>
    </div>
</div>

<!-- TOP STATUS METRICS: EXACTLY 4 CARDS (Requirement 5) -->
<div class="stats-grid-4">
    <div class="stat-card">
        <div class="stat-icon bg-blue-subtle text-blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="stat-info">
            <h3 class="stat-value"><?= number_format($totalUsers) ?></h3>
            <p class="stat-label">Total Users</p>
        </div>
        <svg class="stat-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-green-subtle text-green">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
        </div>
        <div class="stat-info">
            <h3 class="stat-value"><?= number_format($trainees) ?></h3>
            <p class="stat-label">Mentees</p>
        </div>
        <svg class="stat-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-purple-subtle text-purple">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </div>
        <div class="stat-info">
            <h3 class="stat-value"><?= number_format($trainers) ?></h3>
            <p class="stat-label">Mentors</p>
        </div>
        <svg class="stat-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-orange-subtle text-orange">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
        <div class="stat-info">
            <h3 class="stat-value"><?= number_format($totalRequests) ?></h3>
            <p class="stat-label">Pending Requests</p>
        </div>
        <svg class="stat-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </div>
</div>

<!-- 3-COLUMN ANALYTICS & QUICK ACTIONS (Requirement 5 & 6) -->
<div class="analytics-row">
    <!-- 1. LEFT: MENTORSHIP REQUESTS OVERVIEW (Sleek Donut, NO PERCENTAGES) -->
    <div class="admin-card chart-card">
        <div class="card-header-flex">
            <h3 class="card-title">Mentorship Overview</h3>
            <span class="card-tag">All-Time</span>
        </div>
        <div class="chart-container">
            <?php
            // Refined Donut Wheel Geometry (Stroke width 10px, Radius 52px)
            $radius = 52;
            $circumference = 2 * M_PI * $radius; // ~326.73

            $acceptedLen = $totalRequests > 0 ? ($accepted / $totalRequests) * $circumference : 0;
            $pendingLen = $totalRequests > 0 ? ($pending / $totalRequests) * $circumference : 0;
            $rejectedLen = $totalRequests > 0 ? ($rejected / $totalRequests) * $circumference : 0;

            $offsetAccepted = 0;
            $offsetPending = -$acceptedLen;
            $offsetRejected = -($acceptedLen + $pendingLen);
            ?>
            <div class="circle-wheel-wrap">
                <svg class="circle-wheel-svg" viewBox="0 0 130 130" width="130" height="130">
                    <!-- Subtle Dark Track -->
                    <circle cx="65" cy="65" r="<?= $radius ?>" fill="none" stroke="rgba(255, 255, 255, 0.06)" stroke-width="10" />

                    <?php if ($totalRequests > 0): ?>
                        <!-- Accepted Segment (Green) -->
                        <?php if ($accepted > 0): ?>
                            <circle cx="65" cy="65" r="<?= $radius ?>" fill="none" stroke="#10b981" stroke-width="10"
                                stroke-dasharray="<?= round($acceptedLen, 2) ?> <?= round($circumference - $acceptedLen, 2) ?>"
                                stroke-dashoffset="<?= round($offsetAccepted, 2) ?>"
                                stroke-linecap="round" class="chart-segment" />
                        <?php endif; ?>

                        <!-- Pending Segment (Blue) -->
                        <?php if ($pending > 0): ?>
                            <circle cx="65" cy="65" r="<?= $radius ?>" fill="none" stroke="#3b82f6" stroke-width="10"
                                stroke-dasharray="<?= round($pendingLen, 2) ?> <?= round($circumference - $pendingLen, 2) ?>"
                                stroke-dashoffset="<?= round($offsetPending, 2) ?>"
                                stroke-linecap="round" class="chart-segment" />
                        <?php endif; ?>

                        <!-- Rejected Segment (Red) -->
                        <?php if ($rejected > 0): ?>
                            <circle cx="65" cy="65" r="<?= $radius ?>" fill="none" stroke="#ef4444" stroke-width="10"
                                stroke-dasharray="<?= round($rejectedLen, 2) ?> <?= round($circumference - $rejectedLen, 2) ?>"
                                stroke-dashoffset="<?= round($offsetRejected, 2) ?>"
                                stroke-linecap="round" class="chart-segment" />
                        <?php endif; ?>
                    <?php endif; ?>
                </svg>
                <div class="circle-wheel-center">
                    <span class="wheel-number"><?= $totalRequests ?></span>
                    <span class="wheel-label">Requests</span>
                </div>
            </div>

            <!-- Legend without percentages (Requirement 6) -->
            <div class="chart-legend-clean">
                <div class="legend-row">
                    <span class="legend-bullet bg-blue"></span>
                    <span class="legend-name">Pending</span>
                    <span class="legend-count"><?= $pending ?></span>
                </div>
                <div class="legend-row">
                    <span class="legend-bullet bg-green"></span>
                    <span class="legend-name">Accepted</span>
                    <span class="legend-count"><?= $accepted ?></span>
                </div>
                <div class="legend-row">
                    <span class="legend-bullet bg-red"></span>
                    <span class="legend-name">Rejected</span>
                    <span class="legend-count"><?= $rejected ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. MIDDLE: WEEKLY USER REGISTRATIONS (Side-by-side Bar Chart) -->
    <div class="admin-card weekly-card">
        <div class="card-header-flex">
            <div>
                <h3 class="card-title">New Registrations</h3>
                <p class="card-subtitle">Weekly breakdown by user role</p>
            </div>
            <div class="bar-legend">
                <span class="bar-legend-item"><span class="bar-dot bg-green"></span> Mentees</span>
                <span class="bar-legend-item"><span class="bar-dot bg-purple"></span> Mentors</span>
            </div>
        </div>

        <div class="weekly-barchart-container">
            <div class="weekly-bars-flex">
                <?php foreach ($weeklyDays as $day):
                    $menteeHeight = $maxDayVal > 0 ? max(6, round(($day['mentees'] / $maxDayVal) * 100)) : 6;
                    $mentorHeight = $maxDayVal > 0 ? max(6, round(($day['mentors'] / $maxDayVal) * 100)) : 6;
                ?>
                    <div class="day-bar-group">
                        <div class="bars-pair">
                            <!-- Mentee Bar -->
                            <div class="bar-col bar-mentee" style="height: <?= $menteeHeight ?>%;"
                                title="<?= $day['mentees'] ?> Mentees on <?= $day['date_label'] ?>"
                                data-count="<?= $day['mentees'] ?>">
                                <?php if ($day['mentees'] > 0): ?>
                                    <span class="bar-val"><?= $day['mentees'] ?></span>
                                <?php endif; ?>
                            </div>
                            <!-- Mentor Bar -->
                            <div class="bar-col bar-mentor" style="height: <?= $mentorHeight ?>%;"
                                title="<?= $day['mentors'] ?> Mentors on <?= $day['date_label'] ?>"
                                data-count="<?= $day['mentors'] ?>">
                                <?php if ($day['mentors'] > 0): ?>
                                    <span class="bar-val"><?= $day['mentors'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="day-label"><?= $day['day_name'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 3. RIGHT: ACTIVE USERS ON TOP + QUICK ACTIONS STACKED (Requirement 5) -->
    <div class="admin-card actions-column-card">
        <h3 class="card-title mb-3">Quick Navigation</h3>
        <div class="actions-vertical-stack">
            <!-- Active Users on Top (Requirement 5) -->
            <div class="active-users-widget">
                <div class="active-users-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>
                </div>
                <div class="active-users-details">
                    <span class="active-users-num"><?= number_format($activeTodayCount) ?></span>
                    <span class="active-users-label">Active Users Today</span>
                </div>
                <span class="active-live-badge">Live</span>
            </div>

            <!-- Manage Users (Clean borderless item) -->
            <a href="manage_users.php" class="action-item-clean">
                <div class="action-icon bg-blue-subtle text-blue">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>
                <div class="action-item-text">
                    <h4>Manage Users</h4>
                    <p>Search, review and edit members</p>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="action-arrow">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>

            <!-- Manage Skills (Clean borderless item) -->
            <a href="manage_skills.php" class="action-item-clean">
                <div class="action-icon bg-purple-subtle text-purple">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                </div>
                <div class="action-item-text">
                    <h4>Manage Skills</h4>
                    <p>Add, edit or organize skills</p>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="action-arrow">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
        </div>
    </div>
</div>

<!-- TWO SIDE-BY-SIDE STATUS BARS (Top Mentors & Most Demanded Skills) -->
<div class="status-bars-row">
    <!-- Top Mentors -->
    <div class="admin-card">
        <div class="card-header-flex">
            <div>
                <h3 class="card-title">Top Mentors</h3>
                <p class="card-subtitle">Highest engagement on the platform</p>
            </div>
            <a href="manage_users.php?role=trainer" class="card-tag-link">View all &rarr;</a>
        </div>
        <div class="top-mentors-list">
            <?php if (empty($topMentors)): ?>
                <p class="text-muted text-sm py-4">No mentor activity recorded yet.</p>
            <?php else: ?>
                <?php $rank = 1;
                foreach ($topMentors as $mentor): ?>
                    <div class="top-mentor-item">
                        <span class="mentor-rank rank-<?= $rank ?>">#<?= $rank++ ?></span>
                        <div class="avatar-sm bg-purple text-white">
                            <?= strtoupper(substr($mentor['name'], 0, 1)) ?>
                        </div>
                        <div class="mentor-details">
                            <h4 class="mentor-name"><?= htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <span class="mentor-email"><?= htmlspecialchars($mentor['email'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="mentor-badges">
                            <span class="badge badge-purple"><?= htmlspecialchars($mentor['exp_level'] ?? 'Intermediate') ?></span>
                            <span class="mentor-stat-pill" title="Accepted Mentorships">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?= (int)$mentor['accepted_count'] ?> Accepted
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Most Demanded Skills -->
    <div class="admin-card">
        <div class="card-header-flex">
            <div>
                <h3 class="card-title">Most Demanded Skills</h3>
                <p class="card-subtitle">Platform-wide mentor & mentee demand</p>
            </div>
            <a href="manage_skills.php" class="card-tag-link">View all &rarr;</a>
        </div>
        <div class="demanded-skills-list">
            <?php if (empty($demandedSkills)): ?>
                <p class="text-muted text-sm py-4">No skill demand data available yet.</p>
            <?php else: ?>
                <?php foreach ($demandedSkills as $skill):
                    $pct = $maxSkillCount > 0 ? round(($skill['total_count'] / $maxSkillCount) * 100) : 0;
                ?>
                    <div class="demanded-skill-item">
                        <div class="skill-info-header">
                            <span class="demanded-skill-name"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="demanded-skill-counts">
                                <span class="badge-subtle"><?= $skill['mentee_count'] ?> Mentees</span>
                                <span class="badge-subtle"><?= $skill['mentor_count'] ?> Mentors</span>
                                <strong class="demanded-total"><?= $skill['total_count'] ?> Total</strong>
                            </span>
                        </div>
                        <div class="skill-progress-track">
                            <div class="skill-progress-fill" style="width: <?= max(8, $pct) ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- RECENT REGISTRATIONS TABLE (Admin hidden) -->
<div class="admin-card mt-6">
    <div class="card-header-flex">
        <div>
            <h3 class="card-title">Recent Registrations</h3>
            <p class="card-subtitle">Latest platform members</p>
        </div>
        <a href="manage_users.php" class="view-all-link">View All Users &rarr;</a>
    </div>
    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined On</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentUsers)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4">No recent users found.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1;
                    foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <div class="user-cell">
                                    <span class="avatar-sm bg-blue text-white">
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    </span>
                                    <span class="user-name-text"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role'] === 'trainer' ? 'purple' : 'green' ?>">
                                    <?= $user['role'] === 'trainer' ? 'Mentor' : 'Mentee' ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
require 'admin_layout.php';
?>
