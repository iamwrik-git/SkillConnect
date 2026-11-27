<?php
// stay_tuned.php

require_once __DIR__ . '/includes/session.php';

require_once 'includes/auth.php';

// Enforce authentication so only logged-in users access this view
requireLogin();

$feature = $_GET['feature'] ?? '';
$featureTitle = 'Stay Tuned';
$featureMessage = 'This feature will be available in a future version of SkillConnect.';

if ($feature === 'messages') {
    $featureTitle = 'Messages Inbox';
    $featureMessage = 'A dedicated messages inbox is coming soon! You can already send direct messages to your connected mentors and mentees using the Message button on their profile cards.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($featureTitle, ENT_QUOTES, 'UTF-8') ?> - SkillConnect</title>
    <!-- Hook into global styles -->
    <link rel="icon" type="image/png" href="/SkillConnect/assets/images/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        (function() {
            var theme = localStorage.getItem('skillconnect_user_theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
        })();
    </script>
</head>

<body>

    <div class="stay-tuned-wrapper">
        <div class="stay-tuned-card">

            <!-- SkillConnect Logo -->
            <a href="dashboard.php" class="stay-tuned-logo">
                <img src="assets/images/skillconnect-logo.svg"
                    alt="SkillConnect"
                    class="stay-tuned-logo-image">
            </a>

            <h1 class="stay-tuned-title"><?= htmlspecialchars($featureTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="stay-tuned-message"><?= htmlspecialchars($featureMessage, ENT_QUOTES, 'UTF-8') ?></p>

            <!-- Safe return link so the user is not trapped -->
            <a href="dashboard.php" class="stay-tuned-back">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Dashboard
            </a>

        </div>
    </div>

</body>

</html>