<?php
// login.php

// 1. Include authentication helpers and check login status
require_once 'includes/auth.php';

// 2. Redirect authenticated users away from the login page
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication - SkillConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Hidden state trigger to manage sliding form views smoothly -->
    <input type="checkbox" id="form-toggle" class="state-trigger" hidden>

    <!-- Main Workspace Floating Container -->
    <div class="login-workspace">
        
        <!-- Left Side: Interactive Content Window (Login & Register Forms) -->
        <div class="login-form-panel">
            
            <div class="form-brand">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="6" cy="7" r="2.5" fill="#2563eb"/><circle cx="18" cy="5" r="2.5" fill="#2563eb"/><circle cx="7" cy="19" r="2.5" fill="#2563eb"/><path d="M6 7 L12 12 L18 5 M12 12 L7 19 M12 12 C19 13 20 19 7 19" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                SkillConnect
            </div>

            <!-- Global Error Display -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-banner">
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <!-- Slider Wrapper for horizontal animation -->
            <div class="form-slider-wrapper">
                <div class="form-view-window">
                    
                    <!-- =======================================================
                         VIEW A: LOGIN COMPONENT 
                         ======================================================= -->
                    <div class="form-view login-view">
                        <div class="form-header">
                            <h1>Welcome Back</h1>
                            <p>Don't have an account yet? <label for="form-toggle" class="switch-auth-link">Sign Up</label></p>
                        </div>

                        <form action="actions/auth_process.php" method="POST" class="auth-form">
                            <input type="hidden" name="action" value="login">

                            <div class="form-group">
                                <label for="login-email">Email Address</label>
                                <input type="email" id="login-email" name="email" class="form-input" required autocomplete="email" placeholder="name@example.com">
                            </div>

                            <div class="form-group">
                                <label for="login-password">Password</label>
                                <input type="password" id="login-password" name="password" class="form-input" required autocomplete="current-password" placeholder="••••••••">
                            </div>

                            <button type="submit" class="submit-btn">Sign In</button>
                        </form>
                    </div>

                    <!-- =======================================================
                         VIEW B: REGISTRATION COMPONENT
                         ======================================================= -->
                    <div class="form-view register-view">
                        <div class="form-header">
                            <h1>Get Started</h1>
                            <p>Already have an account? <label for="form-toggle" class="switch-auth-link">Sign In</label></p>
                        </div>

                        <form action="actions/auth_process.php" method="POST" class="auth-form">
                            <input type="hidden" name="action" value="register">

                            <div class="form-group">
                                <label for="reg-name">Full Name</label>
                                <input type="text" id="reg-name" name="name" class="form-input" required autocomplete="name" placeholder="John Doe">
                            </div>

                            <div class="form-group">
                                <label for="reg-email">Email Address</label>
                                <input type="email" id="reg-email" name="email" class="form-input" required autocomplete="email" placeholder="name@example.com">
                            </div>

                            <div class="form-group">
                                <label for="reg-password">Password</label>
                                <input type="password" id="reg-password" name="password" class="form-input" required minlength="8" placeholder="Minimum 8 characters">
                            </div>

                            <!-- Modern Custom Interactive Option Cards for Account Type Selection -->
                            <div class="form-group">
                                <label>Account Type</label>
                                <div class="modern-selector-group">
                                    
                                    <input type="radio" id="role-trainee" name="role" value="trainee" required hidden>
                                    <label for="role-trainee" class="selector-card">
                                        <span class="card-icon">🎓</span>
                                        <span class="card-text-group">
                                            <span class="card-title">Trainee</span>
                                            <span class="card-desc">Looking to learn</span>
                                        </span>
                                    </label>

                                    <input type="radio" id="role-trainer" name="role" value="trainer" required hidden>
                                    <label for="role-trainer" class="selector-card">
                                        <span class="card-icon">💼</span>
                                        <span class="card-text-group">
                                            <span class="card-title">Trainer</span>
                                            <span class="card-desc">Looking to teach</span>
                                        </span>
                                    </label>

                                </div>
                            </div>

                            <button type="submit" class="submit-btn">Create Account</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>

        <!-- Right Side: SkillConnect Mentorship Network Illustration Panel -->
        <div class="login-illustration-panel">
            <div class="illustration-wrapper">
                <svg viewBox="0 0 500 360" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Background Connecting Network Lines -->
                    <path d="M140 180 Q 250 100 360 180" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="6 6"/>
                    <path d="M140 180 Q 250 260 360 180" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="6 6"/>

                    <!-- Central Knowledge Exchange Core Badge -->
                    <circle cx="250" cy="180" r="35" fill="#ffffff" filter="drop-shadow(0 8px 16px rgba(37,99,235,0.12))"/>
                    <circle cx="250" cy="180" r="22" fill="#eff6ff"/>
                    <path d="M242 180L248 186L258 174" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

                    <!-- Left User Avatar (Trainer Node) -->
                    <g transform="translate(90, 130)">
                        <circle cx="50" cy="50" r="45" fill="#ffffff" filter="drop-shadow(0 10px 20px rgba(0,0,0,0.08))"/>
                        <circle cx="50" cy="38" r="18" fill="#dbeafe"/>
                        <circle cx="50" cy="36" r="14" fill="#2563eb"/>
                        <path d="M25 78 C 25 65 36 60 50 60 C 64 60 75 65 75 78" fill="#2563eb"/>
                    </g>

                    <!-- Right User Avatar (Trainee Node) -->
                    <g transform="translate(310, 130)">
                        <circle cx="50" cy="50" r="45" fill="#ffffff" filter="drop-shadow(0 10px 20px rgba(0,0,0,0.08))"/>
                        <circle cx="50" cy="38" r="18" fill="#ede9fe"/>
                        <circle cx="50" cy="36" r="14" fill="#7c3aed"/>
                        <path d="M25 78 C 25 65 36 60 50 60 C 64 60 75 65 75 78" fill="#7c3aed"/>
                    </g>

                    <!-- Floating Skill Badges -->
                    <g transform="translate(180, 75)">
                        <rect x="0" y="0" width="64" height="28" rx="14" fill="#ffffff" filter="drop-shadow(0 4px 8px rgba(0,0,0,0.08))"/>
                        <circle cx="14" cy="14" r="6" fill="#f59e0b"/>
                        <text x="26" y="18" font-family="sans-serif" font-size="11" font-weight="bold" fill="#1e293b">Java</text>
                    </g>

                    <g transform="translate(260, 240)">
                        <rect x="0" y="0" width="72" height="28" rx="14" fill="#ffffff" filter="drop-shadow(0 4px 8px rgba(0,0,0,0.08))"/>
                        <circle cx="14" cy="14" r="6" fill="#3b82f6"/>
                        <text x="26" y="18" font-family="sans-serif" font-size="11" font-weight="bold" fill="#1e293b">Python</text>
                    </g>

                    <g transform="translate(180, 260)">
                        <rect x="0" y="0" width="56" height="28" rx="14" fill="#ffffff" filter="drop-shadow(0 4px 8px rgba(0,0,0,0.08))"/>
                        <circle cx="14" cy="14" r="6" fill="#10b981"/>
                        <text x="26" y="18" font-family="sans-serif" font-size="11" font-weight="bold" fill="#1e293b">AI/ML</text>
                    </g>
                </svg>
            </div>
            
            <div class="illustration-caption">
                <h3>Connect, Learn, and Master Together</h3>
                <p>Bridge the gap between trainees and expert trainers.</p>
            </div>
        </div>

    </div>

</body>
</html>
