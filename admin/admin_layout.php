<?php
// admin/admin_layout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Determine admin name and avatar
$adminName = $_SESSION['name'] ?? 'Admin';
$adminAvatar = "https://ui-avatars.com/api/?name=" . urlencode($adminName) . "&background=eff6ff&color=2563eb&size=40&bold=true";

// FIX: Provide a default fallback for $activeMenu to prevent undefined variable errors
$activeMenu = $activeMenu ?? '';

// Helper for active navigation (wrapped to prevent redeclaration errors)
if (!function_exists('isActive')) {
    function isActive($menuName, $activeMenu) {
        return $menuName === $activeMenu ? 'active' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin Panel') ?> - SkillConnect</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-body">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <span class="brand-text"><span class="text-primary">Skill</span>Connect</span>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link <?= isActive('dashboard', $activeMenu) ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            <a href="manage_users.php" class="nav-link <?= isActive('manage_users', $activeMenu) ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Manage Users
            </a>
            <a href="manage_skills.php" class="nav-link <?= isActive('manage_skills', $activeMenu) ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                Manage Skills
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a href="../index.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                Back to Website
            </a>
            <a href="../logout.php" class="nav-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Logout
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="admin-main">
        <!-- HEADER -->
        <header class="admin-header">
            <div class="header-left">
                <!-- Mobile trigger if needed in future -->
            </div>
            <div class="header-right">
                <button class="notification-btn" aria-label="Notifications">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span class="indicator"></span>
                </button>
                <div class="admin-profile">
                    <img src="<?= $adminAvatar ?>" alt="Admin Avatar" class="header-avatar">
                    <span class="header-name"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?> <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg></span>
                </div>
            </div>
        </header>

        <!-- DYNAMIC PAGE CONTENT -->
        <div class="admin-content-wrapper">
            <?php 
                // Display flash messages
                if (isset($_SESSION['admin_success'])) {
                    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['admin_success']) . '</div>';
                    unset($_SESSION['admin_success']);
                }
                if (isset($_SESSION['admin_error'])) {
                    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['admin_error']) . '</div>';
                    unset($_SESSION['admin_error']);
                }
                
                // Echo the specific page content
                echo $content ?? ''; 
            ?>
        </div>
    </main>

    <script>
        // Simple script to handle delete confirmations
        document.querySelectorAll('.js-confirm-delete').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm(this.dataset.confirmMessage || 'Are you sure you want to delete this item?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
