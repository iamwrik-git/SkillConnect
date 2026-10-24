<?php
// admin/view_user.php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connect.php';

$viewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($viewId === 0) {
    $_SESSION['admin_error'] = "Invalid user ID.";
    header("Location: manage_users.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$viewId]);
    $user = $stmt->fetch();

    if (!$user) {
        $_SESSION['admin_error'] = "User not found.";
        header("Location: manage_users.php");
        exit();
    }

    $skillStmt = $pdo->prepare("
        SELECT s.skill_name 
        FROM user_skills us 
        JOIN skills s ON us.skill_id = s.skill_id 
        WHERE us.user_id = ?
        ORDER BY s.skill_name ASC
    ");
    $skillStmt->execute([$viewId]);
    $userSkills = $skillStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("View User Error: " . $e->getMessage());
    $_SESSION['admin_error'] = "We could not load that user right now. Please try again later.";
    header("Location: manage_users.php");
    exit();
}

$avatarUrl = empty($user['profile_photo']) || $user['profile_photo'] === 'default.jpg' 
    ? "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=eff6ff&color=2563eb&size=120&bold=true"
    : "../assets/images/profile/" . htmlspecialchars($user['profile_photo'], ENT_QUOTES, 'UTF-8');

ob_start();
?>
<div class="page-header">
    <div>
        <a href="manage_users.php" class="back-link mb-2 inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Manage Users
        </a>
        <h1>User Overview: <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h1>
    </div>
</div>

<div class="admin-card view-user-card">
    <div class="view-user-header">
        <img src="<?= $avatarUrl ?>" alt="Profile" class="view-user-avatar">
        <div class="view-user-title">
            <h2><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <span class="badge badge-<?= $user['role'] === 'trainer' ? 'purple' : 'green' ?> mt-1">
                <?= ucfirst($user['role']) ?>
            </span>
            <?php if($user['is_admin']): ?>
                <span class="badge badge-blue ml-2">Administrator</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="view-user-grid">
        <div class="view-user-section">
            <h3>Account Details</h3>
            <ul class="detail-list">
                <li><strong>Email:</strong> <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>User ID:</strong> <?= $user['user_id'] ?></li>
                <li><strong>Joined:</strong> <?= date('F j, Y, g:i a', strtotime($user['created_at'])) ?></li>
                <li><strong>Experience:</strong> <?= !empty($user['exp_level']) ? ucfirst(htmlspecialchars($user['exp_level'])) : 'Not specified' ?></li>
            </ul>
        </div>
        
        <div class="view-user-section">
            <h3>Bio</h3>
            <div class="bio-box">
                <?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8')) : '<span class="text-gray">No bio provided.</span>' ?>
            </div>
        </div>

        <div class="view-user-section col-span-2">
            <h3>Associated Skills</h3>
            <?php if (empty($userSkills)): ?>
                <p class="text-gray mt-2">No skills associated with this user.</p>
            <?php else: ?>
                <div class="skill-chips mt-2">
                    <?php foreach ($userSkills as $skill): ?>
                        <span class="chip bg-blue-light text-blue border-blue"><?= htmlspecialchars($skill, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'View User: ' . $user['name'];
$activeMenu = 'manage_users';
require 'admin_layout.php';
?>
