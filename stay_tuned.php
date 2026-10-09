<?php
// stay_tuned.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/auth.php';

// Enforce authentication so only logged-in users access this view
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stay Tuned - SkillConnect</title>
    <!-- Hook into global styles -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="stay-tuned-wrapper">
        <div class="stay-tuned-card">
            
            <!-- SkillConnect Logo -->
            <a href="dashboard.php" class="stay-tuned-logo">
                <div class="stay-tuned-logo-mark"></div>
                <div class="stay-tuned-logo-text">SkillConnect</div>
            </a>
            
            <h1 class="stay-tuned-title">Stay Tuned</h1>
            <p class="stay-tuned-message">This feature will be available in a future version of SkillConnect.</p>
            
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
