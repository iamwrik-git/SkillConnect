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

    <button type="button" class="admin-sidebar-backdrop" aria-label="Close admin navigation" tabindex="-1"></button>
    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="admin-sidebar">
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
                <button type="button" class="admin-menu-toggle" aria-label="Open admin navigation" aria-expanded="false" aria-controls="admin-sidebar">
                    <span></span><span></span><span></span>
                </button>
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
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2m-11 0 1 14h12l1-14M10 10v6m4-6v6"/></svg>
                <span>Delete</span>
            </button>
        </div>
    </dialog>

    <script>
        // Responsive admin navigation; all existing navigation links are reused.
        (() => {
            const toggle = document.querySelector('.admin-menu-toggle');
            const sidebar = document.querySelector('.admin-sidebar');
            const backdrop = document.querySelector('.admin-sidebar-backdrop');
            if (!toggle || !sidebar || !backdrop) return;
            const close = () => {
                document.body.classList.remove('admin-sidebar-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Open admin navigation');
            };
            toggle.addEventListener('click', () => {
                const open = document.body.classList.toggle('admin-sidebar-open');
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Close admin navigation' : 'Open admin navigation');
            });
            backdrop.addEventListener('click', close);
            sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
            window.addEventListener('resize', () => { if (window.innerWidth > 760) close(); });
        })();

        // Branded confirmation modal. The existing POST form (including CSRF token)
        // is submitted unchanged only after the user explicitly confirms.
        (() => {
            const modal = document.getElementById('sc-confirm-modal');
            const title = document.getElementById('sc-confirm-title');
            const message = document.getElementById('sc-confirm-message');
            const warning = document.getElementById('sc-confirm-warning');
            const cancel = document.getElementById('sc-confirm-cancel');
            const close = document.getElementById('sc-confirm-close');
            const submit = document.getElementById('sc-confirm-submit');
            const submitLabel = submit?.querySelector('span');
            if (!modal || !title || !message || !warning || !cancel || !close || !submit || !submitLabel) return;

            let pendingForm = null;
            const closeModal = () => {
                if (modal.open) modal.close();
                pendingForm = null;
            };

            document.querySelectorAll('form.js-confirm-delete').forEach(form => {
                form.addEventListener('submit', event => {
                    if (form.dataset.confirmed === 'true') {
                        delete form.dataset.confirmed;
                        return;
                    }
                    event.preventDefault();
                    pendingForm = form;
                    title.textContent = form.dataset.confirmTitle || 'Confirm deletion';
                    message.textContent = form.dataset.confirmMessage || 'Are you sure you want to delete this item?';
                    warning.querySelector('p').textContent = form.dataset.confirmWarning || 'This action cannot be undone. The selected record may be permanently removed.';
                    submitLabel.textContent = form.dataset.confirmAction || 'Delete';
                    modal.showModal();
                    cancel.focus();
                });
            });

            cancel.addEventListener('click', closeModal);
            close.addEventListener('click', closeModal);
            modal.addEventListener('click', event => {
                if (event.target === modal) closeModal();
            });
            modal.addEventListener('cancel', event => {
                event.preventDefault();
                closeModal();
            });
            submit.addEventListener('click', () => {
                if (!pendingForm) return;
                const form = pendingForm;
                closeModal();
                form.dataset.confirmed = 'true';
                HTMLFormElement.prototype.submit.call(form);
            });
        })();
    </script>
</body>
</html>
