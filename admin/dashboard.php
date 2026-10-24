<?php
// admin/dashboard.php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connect.php';

// Fetch Statistics
try {
    // Aggregate related dashboard counters together to reduce database round trips.
    $userStats = $pdo->query("
        SELECT COUNT(*) AS total_users,
               COALESCE(SUM(CASE WHEN role = 'trainee' THEN 1 ELSE 0 END), 0) AS trainees,
               COALESCE(SUM(CASE WHEN role = 'trainer' THEN 1 ELSE 0 END), 0) AS trainers
        FROM users
    ")->fetch(PDO::FETCH_ASSOC);

    $requestStats = $pdo->query("
        SELECT COUNT(*) AS total_requests,
               COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
               COALESCE(SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END), 0) AS accepted,
               COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected
        FROM mentorships
    ")->fetch(PDO::FETCH_ASSOC);

    $totalUsers = (int) ($userStats['total_users'] ?? 0);
    $trainees = (int) ($userStats['trainees'] ?? 0);
    $trainers = (int) ($userStats['trainers'] ?? 0);
    $totalRequests = (int) ($requestStats['total_requests'] ?? 0);
    $pending = (int) ($requestStats['pending'] ?? 0);
    $accepted = (int) ($requestStats['accepted'] ?? 0);
    $rejected = (int) ($requestStats['rejected'] ?? 0);

    $pendingPct = $totalRequests > 0 ? round(($pending / $totalRequests) * 100) : 0;
    $acceptedPct = $totalRequests > 0 ? round(($accepted / $totalRequests) * 100) : 0;
    $rejectedPct = $totalRequests > 0 ? round(($rejected / $totalRequests) * 100) : 0;

    $recentUsers = $pdo->query("SELECT user_id, name, email, role, created_at, profile_photo FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (PDOException $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $totalUsers = $trainees = $trainers = $totalRequests = 0;
    $pending = $accepted = $rejected = 0;
    $recentUsers = [];
}

// Start Content Buffer
ob_start();
?>
<div class="dashboard-header">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?> 👋</h1>
    <p>Here's an overview of your SkillConnect platform.</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-blue-light text-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <div class="stat-info">
            <p class="stat-label">Total Users</p>
            <h3 class="stat-value"><?= number_format($totalUsers) ?></h3>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon bg-green-light text-green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
        </div>
        <div class="stat-info">
            <p class="stat-label">Trainees</p>
            <h3 class="stat-value"><?= number_format($trainees) ?></h3>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-purple-light text-purple">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        </div>
        <div class="stat-info">
            <p class="stat-label">Trainers</p>
            <h3 class="stat-value"><?= number_format($trainers) ?></h3>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon bg-orange-light text-orange">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4c0-1.1.9-2 2-2h8a2 2 0 0 1 2 2v5Z"></path><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"></path></svg>
        </div>
        <div class="stat-info">
            <p class="stat-label">Mentorship Requests</p>
            <h3 class="stat-value"><?= number_format($totalRequests) ?></h3>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Mentorship Overview Chart Mimic -->
    <div class="admin-card">
        <h3 class="card-title">Mentorship Requests Overview</h3>
        <div class="chart-container flex-center">
            <?php
            // Calculate conic gradient dynamically
            $acceptEnd = $acceptedPct;
            $pendEnd = $acceptedPct + $pendingPct;
            $gradient = "conic-gradient(#10b981 0% {$acceptEnd}%, #3b82f6 {$acceptEnd}% {$pendEnd}%, #ef4444 {$pendEnd}% 100%)";
            if ($totalRequests === 0) $gradient = "conic-gradient(#e2e8f0 0% 100%)";
            ?>
            <div class="donut-chart" style="background: <?= $gradient ?>;">
                <div class="donut-inner">
                    <div class="donut-text">
                        <h2><?= $totalRequests ?></h2>
                        <p>Total Requests</p>
                    </div>
                </div>
            </div>
            
            <div class="chart-legend">
                <div class="legend-item">
                    <span class="legend-color bg-blue"></span>
                    <span class="legend-label">Pending</span>
                    <span class="legend-value"><?= $pending ?> (<?= $pendingPct ?>%)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color bg-green"></span>
                    <span class="legend-label">Accepted</span>
                    <span class="legend-value"><?= $accepted ?> (<?= $acceptedPct ?>%)</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color bg-red"></span>
                    <span class="legend-label">Rejected</span>
                    <span class="legend-value"><?= $rejected ?> (<?= $rejectedPct ?>%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="admin-card">
        <h3 class="card-title">Quick Actions</h3>
        <div class="quick-actions-grid">
            <a href="manage_users.php" class="qa-card">
                <div class="qa-icon bg-blue-light"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                <div class="qa-text">
                    <h4>Manage Users</h4>
                    <p>View, search and manage all users</p>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
            
            <a href="manage_skills.php" class="qa-card">
                <div class="qa-icon bg-purple-light"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg></div>
                <div class="qa-text">
                    <h4>Manage Skills</h4>
                    <p>Add, edit or remove platform skills</p>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="chevron"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </a>
        </div>
    </div>
</div>

<div class="admin-card mt-6">
    <div class="card-header-flex">
        <h3 class="card-title">Recent Registrations</h3>
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
                    <td colspan="5" class="text-center">No users found.</td>
                </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($recentUsers as $user): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar-sm bg-blue text-white">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </span>
                                <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge-<?= $user['role'] === 'trainer' ? 'purple' : 'green' ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
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
