<?php
// admin/manage_skills.php
require_once '../includes/auth.php';
requireAdmin();
require_once '../includes/db_connect.php';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }
    
    $action = $_POST['action'];

    try {
        if ($action === 'add_skill') {
            $skillName = trim($_POST['skill_name']);
            if (empty($skillName) || strlen($skillName) > 50) {
                $_SESSION['admin_error'] = "Skill name is invalid or exceeds 50 characters.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO skills (skill_name) VALUES (?)");
                $stmt->execute([$skillName]);
                $_SESSION['admin_success'] = "Skill added successfully.";
            }
        } elseif ($action === 'edit_skill') {
            $skillId = (int)$_POST['skill_id'];
            $skillName = trim($_POST['skill_name']);
            if (empty($skillName) || strlen($skillName) > 50) {
                $_SESSION['admin_error'] = "Skill name is invalid or exceeds 50 characters.";
            } else {
                $stmt = $pdo->prepare("UPDATE skills SET skill_name = ? WHERE skill_id = ?");
                $stmt->execute([$skillName, $skillId]);
                $_SESSION['admin_success'] = "Skill updated successfully.";
            }
        } elseif ($action === 'delete_skill') {
            $skillId = (int)$_POST['skill_id'];
            // user_skills is handled by ON DELETE CASCADE natively.
            $stmt = $pdo->prepare("DELETE FROM skills WHERE skill_id = ?");
            $stmt->execute([$skillId]);
            $_SESSION['admin_success'] = "Skill and its associations removed successfully.";
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation (Duplicate)
            $_SESSION['admin_error'] = "A skill with this name already exists.";
        } else {
            error_log("Manage Skills DB Error: " . $e->getMessage());
            $_SESSION['admin_error'] = "Database operation failed.";
        }
    }
    
    // Redirect to self to prevent form resubmission, preserve GET state if needed.
    header("Location: manage_skills.php");
    exit();
}

// Search and Filter
$search = trim($_GET['search'] ?? '');
$viewAll = isset($_GET['view_all']) && $_GET['view_all'] == 1;

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = $viewAll ? 999999 : 10; // "View All" overrides limit
$offset = ($page - 1) * 10; // Offset remains fixed to 10 if switching back, but View All disregards offset logically

$whereClause = "";
$params = [];

if ($search !== '') {
    $whereClause = "WHERE skill_name LIKE ?";
    $params[] = "%$search%";
}

try {
    $totalSkillsCount = $pdo->query("SELECT COUNT(*) FROM skills")->fetchColumn();
    
    // Count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM skills $whereClause");
    $countStmt->execute($params);
    $totalRows = $countStmt->fetchColumn();
    $totalPages = ceil($totalRows / 10); // Calculate pages based on 10 per page

    // Always sort alphabetically by name, then ID
    $query = "SELECT skill_id, skill_name FROM skills $whereClause ORDER BY skill_name ASC, skill_id ASC";
    if (!$viewAll) {
        $query .= " LIMIT $limit OFFSET $offset";
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $skills = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Manage Skills Fetch Error: " . $e->getMessage());
    $skills = []; $totalRows = 0; $totalPages = 1;
}

// Preserve GET for pagination
function getSkillsPageLink($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}

ob_start();
?>
<div class="page-header">
    <div>
        <h1>Manage Skills</h1>
        <p>Add, edit, or remove the global technologies available on SkillConnect.</p>
    </div>
</div>

<div class="skills-layout">
    <!-- Top Row: Stats and Add Form -->
    <div class="skills-top-grid">
        <div class="stat-card">
            <div class="stat-icon bg-purple-light text-purple"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg></div>
            <div class="stat-info">
                <p class="stat-label">Total Skills</p>
                <h3 class="stat-value"><?= number_format($totalSkillsCount) ?></h3>
            </div>
        </div>

        <div class="admin-card add-skill-card">
            <h3 class="card-title text-sm mb-2">Add New Skill</h3>
            <form method="POST" class="add-skill-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="add_skill">
                <div class="input-with-button">
                    <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    <input type="text" name="skill_name" placeholder="e.g. Python, Machine Learning" required maxlength="50">
                    <button type="submit" class="btn btn-primary">Add Skill</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="admin-card">
        <div class="filter-bar skills-filter">
            <form method="GET" class="search-form w-full">
                <div class="search-input-wrap w-full">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Search skills by name..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="manage_skills.php" class="btn btn-outline">Clear</a>
            </form>
        </div>

        <div class="card-header-flex mt-4">
            <h3 class="card-title">Platform Skills Directory</h3>
            <div class="flex-center gap-4">
                <span class="text-sm text-gray">
                    Showing <?= $viewAll ? $totalRows : min($offset + 1, $totalRows) . '-' . min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> skills
                </span>
                
                <?php
                // Toggle view_all param
                $toggleParams = $_GET;
                if ($viewAll) {
                    unset($toggleParams['view_all']);
                    $toggleText = "Show First 10";
                } else {
                    $toggleParams['view_all'] = 1;
                    $toggleText = "View All Skills";
                }
                $toggleQs = http_build_query($toggleParams);
                ?>
                <a href="?<?= $toggleQs ?>" class="btn btn-outline btn-sm"><?= $toggleText ?></a>
            </div>
        </div>

        <div class="table-wrapper">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th width="10%">#</th>
                        <th width="40%">Skill Name</th>
                        <th width="20%">Skill ID</th>
                        <th width="30%" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($skills)): ?>
                    <tr>
                        <td colspan="4" class="text-center">No skills found.</td>
                    </tr>
                    <?php else: ?>
                        <?php $i = $viewAll ? 1 : $offset + 1; foreach ($skills as $skill): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <!-- Editable Form -->
                                <form method="POST" class="edit-skill-inline" style="display: none;" id="edit-form-<?= $skill['skill_id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="edit_skill">
                                    <input type="hidden" name="skill_id" value="<?= $skill['skill_id'] ?>">
                                    <input type="text" name="skill_name" value="<?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>" required maxlength="50" class="inline-edit-input">
                                    <button type="submit" class="btn-action btn-view" title="Save"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg></button>
                                    <button type="button" class="btn-action text-gray" onclick="toggleEdit(<?= $skill['skill_id'] ?>)" title="Cancel"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
                                </form>
                                <span id="skill-text-<?= $skill['skill_id'] ?>"><?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td><span class="badge badge-green-light text-green">ID: <?= $skill['skill_id'] ?></span></td>
                            <td class="text-right">
                                <div class="action-buttons justify-end" id="actions-<?= $skill['skill_id'] ?>">
                                    <button type="button" class="btn-action btn-view" onclick="toggleEdit(<?= $skill['skill_id'] ?>)">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        Edit
                                    </button>
                                    <form method="POST" class="inline-form js-confirm-delete" data-confirm-message="Deleting this skill will also remove it from all users who currently have it associated with their profiles. This action cannot be undone.">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="delete_skill">
                                        <input type="hidden" name="skill_id" value="<?= $skill['skill_id'] ?>">
                                        <button type="submit" class="btn-action btn-delete">
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

        <!-- Pagination (Hidden if View All is active) -->
        <?php if (!$viewAll && $totalPages > 1): ?>
        <div class="pagination">
            <a href="<?= $page > 1 ? getSkillsPageLink($page - 1) : '#' ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">&lt;</a>
            
            <?php for($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == 1 || $p == $totalPages || abs($p - $page) <= 2): ?>
                    <a href="<?= getSkillsPageLink($p) ?>" class="page-link <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php elseif (abs($p - $page) == 3): ?>
                    <span class="page-dots">...</span>
                <?php endif; ?>
            <?php endfor; ?>

            <a href="<?= $page < $totalPages ? getSkillsPageLink($page + 1) : '#' ?>" class="page-link <?= $page >= $totalPages ? 'disabled' : '' ?>">&gt;</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleEdit(id) {
    const form = document.getElementById('edit-form-' + id);
    const text = document.getElementById('skill-text-' + id);
    const actions = document.getElementById('actions-' + id);
    
    if (form.style.display === 'none') {
        form.style.display = 'flex';
        text.style.display = 'none';
        actions.style.display = 'none';
    } else {
        form.style.display = 'none';
        text.style.display = 'inline';
        actions.style.display = 'flex';
    }
}
</script>

<?php
$content = ob_get_clean();
$pageTitle = 'Manage Skills';
$activeMenu = 'manage_skills';
require 'admin_layout.php';
?>
