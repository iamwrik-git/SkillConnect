<?php
/**
 * includes/partials/sidebar.php - Global application sidebar
 */
?>
<aside class="sidebar" id="app-sidebar">
    <div class="sidebar-scrollable">

        <nav class="sidebar-section">
            <h3 class="sidebar-heading">MAIN</h3>
            <ul class="sidebar-list">
                <li>
                    <a href="dashboard.php" class="sidebar-link <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                <?php if ($user_role !== 'trainer'): ?>
                <li>
                    <a href="search.php" class="sidebar-link <?= $current_page === 'search.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <span>Find Mentors</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="messages.php" class="sidebar-link <?= ($current_page === 'messages.php') ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span>Messages</span>
                        <?php if (!empty($nav_unread_messages) && $nav_unread_messages > 0): ?>
                            <span class="sidebar-unread-pill" aria-label="<?= (int)$nav_unread_messages ?> unread messages"><?= (int)$nav_unread_messages ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </nav>

        <nav class="sidebar-section">
            <h3 class="sidebar-heading">MY SPACE</h3>
            <ul class="sidebar-list">
                <li>
                    <a href="profile.php" class="sidebar-link <?= in_array($current_page, ['profile.php', 'my_skills.php']) ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="mentorships.php" class="sidebar-link <?= $current_page === 'mentorships.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span><?= $user_role === 'trainer' ? 'My Mentees' : 'My Mentors' ?></span>
                    </a>
                </li>
                <?php if ($user_role !== 'trainer'): ?>
                <li>
                    <a href="saved_trainers.php" class="sidebar-link <?= $current_page === 'saved_trainers.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span>Saved Mentors</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>

        <nav class="sidebar-section">
            <h3 class="sidebar-heading">EXPLORE</h3>
            <ul class="sidebar-list">
                <li>
                    <a href="stay_tuned.php" class="sidebar-link <?= $current_page === 'stay_tuned.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Communities</span>
                    </a>
                </li>
                <li>
                    <a href="stay_tuned.php" class="sidebar-link <?= $current_page === 'stay_tuned.php' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                            <path d="M4 22h16"></path>
                            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path>
                            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path>
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                        </svg>
                        <span>Leaderboard</span>
                    </a>
                </li>
            </ul>
        </nav>

    </div>

    <div class="sidebar-footer">
        <a href="logout.php?csrf_token=<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>" class="logout-link" data-confirm-logout>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</aside>
