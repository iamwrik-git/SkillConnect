<?php
/**
 * includes/partials/top_nav.php - Global top navigation bar
 */
?>
<header class="top-nav">
    <button type="button" class="mobile-menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="app-sidebar">
        <span></span><span></span><span></span>
    </button>
    <div class="nav-brand">
        <a href="dashboard.php" class="logo" aria-label="SkillConnect home">
            <img
                src="assets/images/skillconnect-logo.svg?v=<?= (int) $logo_version ?>"
                alt="SkillConnect"
                class="logo-image"
                width="190"
                height="59"
                fetchpriority="high">
        </a>
    </div>

    <!-- GLOBAL SEARCH BAR WITH DATALIST -->
    <div class="nav-search">
        <form action="search.php" method="GET" class="search-form">
            <input type="text" name="q" placeholder="Search mentors, skills..." class="search-input" aria-label="Global search" list="globalSearchOptions" autocomplete="off">
            <datalist id="globalSearchOptions">
                <?php foreach ($search_suggestions as $suggestion): ?>
                    <option value="<?= htmlspecialchars($suggestion, ENT_QUOTES, 'UTF-8') ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </form>
    </div>

    <div class="nav-actions">
        <!-- Network Dropdown — click controlled for consistency with Notifications -->
        <div class="nav-item has-dropdown network-menu" data-dropdown="network">
            <button type="button" class="nav-btn network-menu-trigger" aria-expanded="false" aria-controls="network-dropdown" aria-haspopup="true" id="network-menu-trigger">
                <span class="nav-text">Network</span>
            </button>
            <div class="dropdown-popup network-popup" id="network-dropdown" aria-hidden="true" aria-labelledby="network-menu-trigger">
                <div class="popup-header">Global Network</div>
                <div class="popup-body">
                    <p>Discover people across SkillConnect.</p>
                    <span class="badge badge-coming-soon">Coming Soon</span>
                </div>
            </div>
        </div>

        <!-- Role-aware mentorship notifications -->
        <div class="nav-item has-dropdown notification-menu" data-dropdown="notifications">
            <button type="button" class="nav-btn notification-menu-trigger" aria-expanded="false" aria-controls="notifications-dropdown" aria-haspopup="true" id="notifications-menu-trigger">
                <span class="nav-text">Notifications</span>
                <?php if ($nav_unread_notifications > 0): ?>
                    <span class="notification-dot" aria-label="<?= (int) $nav_unread_notifications ?> unread notifications"></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-popup notification-popup" id="notifications-dropdown" aria-hidden="true" aria-labelledby="notifications-menu-trigger">
                <div class="notification-popup-header">
                    <div>
                        <h2>Notifications</h2>
                        <?php if ($nav_unread_notifications > 0): ?>
                            <p><?= (int) $nav_unread_notifications ?> unread notification<?= $nav_unread_notifications === 1 ? '' : 's' ?></p>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="notifications_action.php" class="notification-header-action">
                        <input type="hidden" name="action" value="mark_all_read">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($notification_csrf, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" <?= $nav_unread_notifications === 0 ? 'disabled aria-disabled="true"' : '' ?>>Mark all as read</button>
                    </form>
                </div>
                <div class="notification-list">
                    <?php if (empty($nav_notifications)): ?>
                        <div class="notification-empty">
                            <span class="notification-empty-icon" aria-hidden="true">✓</span>
                            <strong>No notifications yet.</strong>
                            <span>Mentorship updates will appear here.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($nav_notifications as $notification): ?>
                            <?php
                            $notificationType = (string) ($notification['notification_type'] ?? 'mentorship_request');
                            $notificationIcon = $notificationType === 'mentorship_accepted' ? 'accepted' : ($notificationType === 'mentorship_rejected' ? 'rejected' : 'request');
                            $notificationIsUnread = (int) ($notification['is_read'] ?? 0) === 0;
                            ?>
                            <form method="post" action="notifications_action.php" class="notification-item-form">
                                <input type="hidden" name="action" value="open">
                                <input type="hidden" name="notification_id" value="<?= (int) $notification['notification_id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($notification_csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="notification-item <?= $notificationIsUnread ? 'is-unread' : 'is-read' ?>">
                                    <span class="notification-item-icon <?= htmlspecialchars($notificationIcon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                                        <?php if ($notificationIcon === 'accepted'): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="9"></circle>
                                                <path d="m8 12 2.5 2.5L16 9"></path>
                                            </svg>
                                        <?php elseif ($notificationIcon === 'rejected'): ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="9"></circle>
                                                <path d="m9 9 6 6m0-6-6 6"></path>
                                            </svg>
                                        <?php else: ?>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="10" cy="7" r="4"></circle>
                                                <path d="M20 8v6m-3-3h6"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </span>
                                    <span class="notification-item-content">
                                        <span class="notification-item-message"><?= htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="notification-item-time"><?= htmlspecialchars($notificationTimeLabel($notification['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    </span>
                                    <?php if ($notificationIsUnread): ?><span class="notification-item-unread-dot" aria-label="Unread"></span><?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="notification-popup-footer">
                    <span>Only mentorship updates.</span>
                    <?php if (!empty($nav_notifications)): ?>
                        <form method="post" action="notifications_action.php" class="notification-clear-form" id="notification-clear-form">
                            <input type="hidden" name="action" value="clear_all">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($notification_csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" id="notification-clear-trigger">Clear all</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <dialog class="notification-confirm-dialog" id="notification-clear-confirm" aria-labelledby="notification-clear-title">
                <div class="notification-confirm-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 7h16"></path>
                        <path d="M10 11v6m4-6v6"></path>
                        <path d="m6 7 1 13h10l1-13"></path>
                        <path d="M9 7V4h6v3"></path>
                    </svg>
                </div>
                <h2 id="notification-clear-title">Clear notifications?</h2>
                <div class="notification-confirm-actions">
                    <button type="button" class="notification-confirm-cancel" id="notification-clear-cancel">Cancel</button>
                    <button type="button" class="notification-confirm-accept" id="notification-clear-accept">Clear all</button>
                </div>
            </dialog>
        </div>

        <!-- Profile dropdown -->
        <div class="nav-item has-dropdown profile-menu" data-dropdown="profile">
            <?php
            $nav_photo = $_SESSION['profile_photo'] ?? '';
            $nav_name = $_SESSION['name'] ?? 'User';
            $nav_role = $_SESSION['role'] ?? 'trainee';
            $show_initials = empty($nav_photo) || $nav_photo === 'default.jpg';
            $name_parts = array_values(array_filter(preg_split('/\\s+/', trim($nav_name))));
            $initials = 'U';
            if (count($name_parts) >= 2) {
                $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 1) . mb_substr(end($name_parts), 0, 1));
            } elseif (count($name_parts) === 1 && $name_parts[0] !== '') {
                $initials = mb_strtoupper(mb_substr($name_parts[0], 0, 1));
            }

            $nav_avatar_url = '';
            if (!$show_initials) {
                $safe_photo = basename((string) $nav_photo);
                $photo_path = __DIR__ . '/../../assets/images/profile/' . $safe_photo;
                $photo_version = is_file($photo_path) ? filemtime($photo_path) : time();
                $nav_avatar_url = 'assets/images/profile/' . rawurlencode($safe_photo) . '?v=' . $photo_version;
            }
            ?>
            <button
                type="button"
                class="nav-btn me-btn profile-menu-trigger"
                aria-expanded="false"
                aria-controls="profile-dropdown"
                aria-haspopup="true"
                id="profile-menu-trigger">
                <?php if ($show_initials): ?>
                    <span class="avatar-placeholder initials-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <img src="<?= htmlspecialchars($nav_avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="" class="avatar-placeholder profile-nav-avatar">
                <?php endif; ?>
                <span class="profile-menu-label"><?= htmlspecialchars(explode(' ', trim($nav_name))[0] ?: 'Me', ENT_QUOTES, 'UTF-8') ?></span>
            </button>

            <div class="dropdown-popup profile-popup" id="profile-dropdown" role="menu" aria-labelledby="profile-menu-trigger" aria-hidden="true">
                <div class="profile-popup-identity">
                    <?php if ($show_initials): ?>
                        <span class="profile-popup-avatar initials-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($nav_avatar_url, ENT_QUOTES, 'UTF-8') ?>" alt="" class="profile-popup-avatar">
                    <?php endif; ?>
                    <div class="profile-popup-user">
                        <div class="profile-popup-name"><?= htmlspecialchars($nav_name, ENT_QUOTES, 'UTF-8') ?></div>
                        <span class="profile-popup-role"><?= htmlspecialchars($nav_role === 'trainer' ? 'Mentor' : 'Mentee', ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>

                <section class="profile-popup-skills" aria-label="Your skills">
                    <div class="profile-popup-skills-heading">
                        <span>My Skills <span class="profile-popup-skill-count">(<?= count($nav_skills) ?>)</span></span>
                        <a href="profile.php?tab=skills" class="profile-popup-view-all">View all</a>
                    </div>
                    <?php if (!empty($nav_skills)): ?>
                        <div class="profile-popup-skill-list">
                            <?php foreach ($nav_skills as $skill_index => $skill_name): ?>
                                <span class="profile-skill-chip profile-skill-chip-<?= (int) ($skill_index % 3) ?>"><?= htmlspecialchars($skill_name, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="profile-popup-no-skills">No skills added yet.</p>
                    <?php endif; ?>
                </section>

                <div class="profile-popup-actions">
                    <a href="profile.php" class="profile-menu-link is-featured" role="menuitem">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>View Profile</span>
                    </a>
                    <a href="edit_profile.php" class="profile-menu-link is-featured" role="menuitem">
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        <span>Edit Profile</span>
                    </a>
                    <a href="logout.php?csrf_token=<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>" class="profile-menu-link profile-menu-logout" role="menuitem" data-confirm-logout>
                        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Log out</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
