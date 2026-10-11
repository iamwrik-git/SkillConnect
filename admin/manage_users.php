<?php
// admin/manage_users.php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connect.php';

// Handle Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }
    
    $deleteId = (int)$_POST['user_id'];
    
    if ($deleteId === $_SESSION['user_id']) {
        $_SESSION['admin_error'] = "You cannot delete your own administrator account.";
    } else {
        try {
            // Note: Cascade deletion rules on the DB should handle user_skills and mentorships.
            // If they are not natively cascading in the schema, doing it explicitly:
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM user_skills WHERE user_id = ?")->execute([$deleteId]);
            $pdo->prepare("DELETE FROM mentorships WHERE trainer_id = ? OR trainee_id = ?")->execute([$deleteId, $deleteId]);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$deleteId]);
            $pdo->commit();
            
            $_SESSION['admin_success'] = "User successfully deleted.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Delete User Error: " . $e->getMessage());
            $_SESSION['admin_error'] = "Failed to delete user.";
        }
    }
    header("Location: manage_users.php");
    exit();
}

// Search and Filter logic
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build Query
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter === 'trainee' || $roleFilter === 'trainer') {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}

$whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

try {
    // Stats
    $totalUsersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $traineesCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'trainee'")->fetchColumn();
    $trainersCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'trainer'")->fetchColumn();

    // Pagination Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereClause");
    $countStmt->execute($params);
    $totalRows = $countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $limit);

    // Fetch Data
    $query = "SELECT user_id, name, email, role, exp_level, created_at, is_admin 
              FROM users 
              $whereClause 
              ORDER BY created_at DESC 
              LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Manage Users Error: " . $e->getMessage());
    $users = [];
    $totalRows = 0; $totalPages = 1;
}

// Preserve GET parameters for pagination links
function getPageLink($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

ob_start();
?>
<div class="page-header">
    <div>
        <h1>Manage Users</h1>
        <p>View and manage all registered users of SkillConnect.</p>
    </div>
</div>

<div class="stats-grid grid-3">
    <div class="stat-card">
        <div class="stat-icon bg-blue-light text-blue"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
        <div class="stat-info">
            <p class="stat-label">Total Users</p>
            <h3 class="stat-value"><?= number_format($totalUsersCount) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-green-light text-green"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg></div>
        <div class="stat-info">
            <p class="stat-label">Trainees</p>
            <h3 class="stat-value"><?= number_format($traineesCount) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-purple-light text-purple"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
        <div class="stat-info">
            <p class="stat-label">Trainers</p>
            <h3 class="stat-value"><?= number_format($trainersCount) ?></h3>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="filter-bar">
        <form method="GET" class="search-form">
            <div class="search-input-wrap">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            
            <select name="role" class="filter-select">
                <option value="">All Roles</option>
                <option value="trainee" <?= $roleFilter === 'trainee' ? 'selected' : '' ?>>Trainee</option>
                <option value="trainer" <?= $roleFilter === 'trainer' ? 'selected' : '' ?>>Trainer</option>
            </select>
            
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="manage_users.php" class="btn btn-outline">Reset</a>
        </form>
    </div>

    <div class="card-header-flex">
        <h3 class="card-title">Registered Users</h3>
        <span class="text-sm text-gray">Showing <?= min($offset + 1, $totalRows) ?>-<?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> users</span>
    </div>

    <div class="table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Experience Level</th>
                    <th>Joined On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" class="text-center">No matching users found.</td>
                </tr>
                <?php else: ?>
                    <?php $i = $offset + 1; foreach ($users as $user): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar-sm bg-blue text-white">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </span>
                                <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if($user['is_admin']) echo ' <span title="Administrator" style="color:var(--primary); font-size:12px; margin-left:4px;">★</span>'; ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="badge badge-<?= $user['role'] === 'trainer' ? 'purple' : 'green' ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td><?= !empty($user['exp_level']) ? ucfirst(htmlspecialchars($user['exp_level'])) : '-' ?></td>
                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        <td>
                            <div class="action-buttons">
                                <a href="view_user.php?id=<?= $user['user_id'] ?>" class="btn-action btn-view">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    View
                                </a>
                                <form method="POST" class="inline-form js-confirm-delete" data-confirm-message="Are you sure you want to delete <?= htmlspecialchars($user['name']) ?>? This cascades to mentorships and skills.">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <button type="submit" class="btn-action btn-delete" <?= $user['user_id'] == $_SESSION['user_id'] ? 'disabled title="Cannot delete yourself"' : '' ?>>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="<?= $page > 1 ? getPageLink($page - 1) : '#' ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">&lt;</a>
        
        <?php for($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p == 1 || $p == $totalPages || abs($p - $page) <= 2): ?>
                <a href="<?= getPageLink($p) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php elseif (abs($p - $page) == 3): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
        <?php endfor; ?>

        <a href="<?= $page < $totalPages ? getPageLink($page + 1) : '#' ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>">&gt;</a>
    </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
$pageTitle = 'Manage Users';
$activeMenu = 'manage_users';
require 'admin_layout.php';
?>
