<?php
// admin/admin_layout.php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

// Ensure a valid session-bound CSRF token exists for administrative forms.
$admin_csrf_token = csrfToken();

// Determine admin name
$adminName = $_SESSION['name'] ?? 'Admin';

// Default fallback for $activeMenu
$activeMenu = $activeMenu ?? '';

// Helper for active navigation
if (!function_exists('isActive')) {
    function isActive($menuName, $activeMenu)
    {
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
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body class="admin-body">

    <button type="button" class="admin-sidebar-backdrop" aria-label="Close admin navigation" tabindex="-1"></button>

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="admin-sidebar">
        <!-- Brand aligned with header line -->
        <div class="sidebar-brand">
            <a href="dashboard.php" class="sidebar-brand-link" aria-label="SkillConnect Admin Home">
                <img src="../assets/images/skillconnect-logo.svg" alt="SkillConnect" class="admin-brand-logo">
            </a>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-heading">MAIN</div>
            <ul class="sidebar-list">
                <li>
                    <a href="dashboard.php" class="nav-link <?= isActive('dashboard', $activeMenu) ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="manage_users.php" class="nav-link <?= isActive('manage_users', $activeMenu) ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="manage_skills.php" class="nav-link <?= isActive('manage_skills', $activeMenu) ?>">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        </svg>
                        <span>Manage Skills</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <a href="../index.php" class="sidebar-link-btn nav-link-website" id="admin-exit-btn" data-confirm-exit="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Back to Website</span>
            </a>
            <form id="admin-logout-form" method="POST" action="../logout.php" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            </form>
            <a href="../logout.php?csrf_token=<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>" class="sidebar-link-btn nav-link-logout" id="admin-logout-btn" data-confirm-logout="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="admin-main">
        <!-- HEADER -->
        <header class="admin-header">
            <div class="header-left">
                <button type="button" class="admin-menu-toggle" aria-label="Open admin navigation" aria-expanded="false" aria-controls="admin-sidebar">
                    <span></span><span></span><span></span>
                </button>
            </div>
            <div class="header-right">
                <!-- Simple Security Shield + Admin Text (Requirement 8) -->
                <div class="admin-security-badge" title="Administrator Control Panel">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Admin</span>
                </div>
                <!-- Info Logo on the right side of Admin badge (Auth Gateway) -->
                <a href="infoAuth.php" class="header-info-btn" title="System Info" aria-label="System Info">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </a>
            </div>
        </header>

        <!-- DYNAMIC PAGE CONTENT -->
        <div class="admin-content-wrapper">
            <?= $content ?? '' ?>
        </div>
    </main>

    <!-- FLOATING TOAST NOTIFICATION CONTAINER (Point 4) -->
    <div id="admin-toast-container" class="admin-toast-container" aria-live="polite" aria-atomic="true">
        <?php if (isset($_SESSION['admin_success'])): ?>
            <div class="admin-toast admin-toast-success" role="alert" data-auto-dismiss="4500">
                <div class="admin-toast-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div class="admin-toast-content">
                    <div class="admin-toast-title">Success</div>
                    <div class="admin-toast-message"><?= htmlspecialchars($_SESSION['admin_success'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <button type="button" class="admin-toast-close" aria-label="Dismiss notification">&times;</button>
                <div class="admin-toast-progress"></div>
            </div>
            <?php unset($_SESSION['admin_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['admin_error'])): ?>
            <div class="admin-toast admin-toast-danger" role="alert" data-auto-dismiss="5000">
                <div class="admin-toast-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <div class="admin-toast-content">
                    <div class="admin-toast-title">Notice</div>
                    <div class="admin-toast-message"><?= htmlspecialchars($_SESSION['admin_error'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <button type="button" class="admin-toast-close" aria-label="Dismiss notification">&times;</button>
                <div class="admin-toast-progress"></div>
            </div>
            <?php unset($_SESSION['admin_error']); ?>
        <?php endif; ?>
    </div>

    <!-- Shared SkillConnect confirmation modal for destructive actions. -->
    <dialog class="sc-confirm-modal" id="sc-confirm-modal" aria-labelledby="sc-confirm-title" aria-describedby="sc-confirm-message">
        <button type="button" class="sc-confirm-close" id="sc-confirm-close" aria-label="Close confirmation dialog">&times;</button>
        <div class="sc-confirm-symbol" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18M8 6V4h8v2m-11 0 1 14h12l1-14M10 10v6m4-6v6" />
            </svg>
        </div>
        <div class="sc-confirm-heading">
            <span class="sc-confirm-eyebrow">PLEASE CONFIRM</span>
            <h2 id="sc-confirm-title">Confirm deletion</h2>
            <p id="sc-confirm-message">Are you sure you want to delete this item?</p>
        </div>
        <div class="sc-confirm-warning" id="sc-confirm-warning">
            <span class="sc-warning-mark" aria-hidden="true">!</span>
            <p><strong>This action cannot be undone.</strong> The selected record and its associated data may be permanently removed.</p>
        </div>
        <div class="sc-confirm-actions">
            <button type="button" class="sc-confirm-cancel" id="sc-confirm-cancel">Cancel</button>
            <button type="button" class="sc-confirm-delete" id="sc-confirm-submit">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 6h18M8 6V4h8v2m-11 0 1 14h12l1-14M10 10v6m4-6v6" />
                </svg>
                <span>Delete</span>
            </button>
        </div>
    </dialog>

    <!-- Logout confirmation modal -->
    <div class="confirm-modal-backdrop admin-modal-backdrop" id="logout-confirm-modal" aria-hidden="true">
        <section class="confirm-modal-content admin-confirm-content" role="dialog" aria-modal="true" aria-labelledby="logout-confirm-title" aria-describedby="logout-confirm-message" tabindex="-1">
            <div class="confirm-modal-icon confirm-danger logout-confirm-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </div>
            <h2 class="confirm-modal-title" id="logout-confirm-title">Log out of Admin Dashboard?</h2>
            <p class="confirm-modal-message" id="logout-confirm-message">Are you sure you want to end your administrative session? You will need to sign in again to access the control panel.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="btn btn-cancel" id="logout-cancel-btn">Stay logged in</button>
                <button type="button" class="btn btn-confirm-danger" id="logout-confirm-btn">Log out</button>
            </div>
        </section>
    </div>

    <!-- Exit confirmation modal -->
    <div class="confirm-modal-backdrop admin-modal-backdrop" id="exit-website-confirm-modal" aria-hidden="true">
        <section class="confirm-modal-content admin-confirm-content" role="dialog" aria-modal="true" aria-labelledby="exit-confirm-title" aria-describedby="exit-confirm-message" tabindex="-1">
            <div class="confirm-modal-icon confirm-info logout-confirm-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
            </div>
            <h2 class="confirm-modal-title" id="exit-confirm-title">Return to Main Website?</h2>
            <p class="confirm-modal-message" id="exit-confirm-message">You are about to leave the administrative control panel and return to the main SkillConnect platform.</p>
            <div class="confirm-modal-actions">
                <button type="button" class="btn btn-cancel" id="exit-cancel-btn">Stay in Admin</button>
                <a href="../index.php" class="btn btn-confirm-primary" id="exit-confirm-btn">Go to Website</a>
            </div>
        </section>
    </div>

    <!-- Consolidated Admin Scripts -->
    <script src="../assets/js/admin.js" defer></script>
</body>

</html>
