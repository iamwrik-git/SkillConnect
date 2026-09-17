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

    <!-- Main Container Card -->
    <div class="auth-container">
        
        <!-- Left Column: Branding Showcase -->
        <div class="brand-panel">
            <div class="brand-logo">SkillConnect</div>
            
            <!-- Minimal Vector Graphic -->
            <div class="vector-wrapper">
                <!-- FIXED: Re-established complete valid W3C namespace -->
                <svg viewBox="0 0 200 200" xmlns="http://w3.org">
                    <circle cx="100" cy="100" r="80" fill="rgba(255, 255, 255, 0.1)"/>
                    <rect x="60" y="75" width="80" height="50" rx="8" fill="rgba(255, 255, 255, 0.2)"/>
                    <circle cx="100" cy="100" r="25" stroke="#ffffff" stroke-width="3" fill="none"/>
                    <path d="M60 100 H140" stroke="#ffffff" stroke-width="2" stroke-dasharray="4 4"/>
                </svg>
            </div>

            <!-- Login View Side Marketing Text -->
            <div class="brand-marketing text-login-view">
                <h2>Learn. Teach. Connect.</h2>
                <p>Learn from others. Share what you know.<br>Build your skills with SkillConnect.</p>
            </div>

            <!-- Register View Side Marketing Text -->
            <div class="brand-marketing text-register-view">
                <h2>Start Your Journey</h2>
                <p>Start learning. Start teaching.<br>Connect through skills.</p>
            </div>
        </div>

        <!-- Right Column: Interactive Content Window -->
        <div class="form-panel">
            
            <!-- Global Error Display -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-banner">
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php unset($_SESSION['error']); // Clear error after displaying ?>
            <?php endif; ?>

            <div class="form-view-window">
                
                <!-- =======================================================
                     VIEW A: LOGIN COMPONENT 
                     ======================================================= -->
                <div class="form-view login-view">
                    <div class="form-header">
                        <div class="title-group">
                            <h1>Welcome Back</h1>
                            <p class="subtitle">Please sign in to your SkillConnect account</p>
                        </div>
                        <label for="form-toggle" class="switch-auth-link">Create account</label>
                    </div>

                    <form action="actions/auth_process.php" method="POST" class="auth-form">
                        <input type="hidden" name="action" value="login">

                        <div class="form-group">
                            <label for="login-email">Email Address</label>
                            <input type="email" id="login-email" name="email" required autocomplete="email" placeholder="name@example.com">
                        </div>

                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="••••••••">
                        </div>

                        <button type="submit" class="submit-btn">Sign In</button>
                    </form>
                </div>

                <!-- =======================================================
                     VIEW B: REGISTRATION COMPONENT
                     ======================================================= -->
                <div class="form-view register-view">
                    <div class="form-header">
                        <div class="title-group">
                            <h1>Get Started</h1>
                            <p class="subtitle">Create your SkillConnect account</p>
                        </div>
                        <label for="form-toggle" class="switch-auth-link">Sign In instead</label>
                    </div>

                    <form action="actions/auth_process.php" method="POST" class="auth-form">
                        <input type="hidden" name="action" value="register">

                        <div class="form-group">
                            <label for="reg-name">Full Name</label>
                            <input type="text" id="reg-name" name="name" required autocomplete="name" placeholder="John Doe">
                        </div>

                        <div class="form-group">
                            <label for="reg-email">Email Address</label>
                            <input type="email" id="reg-email" name="email" required autocomplete="email" placeholder="name@example.com">
                        </div>

                        <div class="form-group">
                            <label for="reg-password">Password</label>
                            <input type="password" id="reg-password" name="password" required minlength="8" placeholder="Minimum 8 characters">
                        </div>

                        <!-- Modern Custom Interactive Option Cards for Account Type Selection -->
                        <div class="form-group">
                            <label>Account Type</label>
                            <div class="modern-selector-group">
                                
                                <!-- Option 1: Trainee Option Card -->
                                <input type="radio" id="role-trainee" name="role" value="trainee" required hidden>
                                <label for="role-trainee" class="selector-card">
                                    <span class="card-icon">🎓</span>
                                    <span class="card-text-group">
                                        <span class="card-title">Trainee</span>
                                        <span class="card-desc">Looking to learn</span>
                                    </span>
                                </label>

                                <!-- Option 2: Trainer Option Card -->
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

</body>
</html>
