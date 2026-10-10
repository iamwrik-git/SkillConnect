<?php
// login.php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// 1. Determine Auth Mode State
$mode = $_GET['mode'] ?? 'login';
$isSignup = ($mode === 'signup');

// 2. Safely capture Errors and Form Data
$error = $_SESSION['error'] ?? null;
$formData = $_SESSION['form_data'] ?? [];

// 3. Immediately consume the error so it cannot leak to other pages
unset($_SESSION['error']);
unset($_SESSION['form_data']);

// 4. Extract safe preserved values (Passwords are intentionally excluded)
$loginEmail = !$isSignup ? ($formData['email'] ?? '') : '';
$signupName = $isSignup ? ($formData['name'] ?? '') : '';
$signupEmail = $isSignup ? ($formData['email'] ?? '') : '';
$signupRole = $isSignup ? ($formData['role'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In or Sign Up - SkillConnect</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

    <!-- CSS State Checkbox: Pre-checked if redirect came from a sign-up error -->
    <input type="checkbox" id="form-toggle" class="state-trigger" <?= $isSignup ? 'checked' : '' ?> hidden>

    <div class="login-workspace">
        
        <!-- =======================================================
             PANEL 1: AUTHENTICATION FORMS
             ======================================================= -->
        <div class="login-form-panel">
            <div class="form-view-container">
                
                <!-- VIEW A: SIGN IN -->
                <div class="form-view login-view">
                    <div class="form-header">
                        <h1>Welcome Back</h1>
                        <p>Sign in to continue your learning journey with expert mentors.</p>
                    </div>

                    <?php if (!$isSignup && $error): ?>
                        <div class="inline-error-banner">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="actions/auth_process.php" method="POST" class="auth-form" novalidate>
                        <input type="hidden" name="action" value="login">

                        <div class="form-group">
                            <label for="login-email">Email Address</label>
                            <input type="email" id="login-email" name="email" class="form-input" value="<?= htmlspecialchars($loginEmail, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" placeholder="name@example.com">
                        </div>

                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <div class="password-wrapper">
                                <input type="password" id="login-password" name="password" class="form-input" required autocomplete="current-password" placeholder="••••••••">
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="submit-btn" data-loading-text="Signing In...">Sign In &rarr;</button>
                        
                        <div class="auth-divider">
                            <span>or</span>
                        </div>

                        <p class="auth-bottom-text">Don't have an account yet? <label for="form-toggle" class="switch-auth-link">Sign Up</label></p>
                    </form>
                </div>

                <!-- VIEW B: SIGN UP -->
                <div class="form-view register-view">
                    <div class="form-header">
                        <h1>Create Your Account</h1>
                        <p>Join SkillConnect and start your learning journey today.</p>
                    </div>

                    <?php if ($isSignup && $error): ?>
                        <div class="inline-error-banner">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="actions/auth_process.php" method="POST" class="auth-form" novalidate>
                        <input type="hidden" name="action" value="register">

                        <div class="form-group">
                            <label for="reg-name">Full Name</label>
                            <input type="text" id="reg-name" name="name" class="form-input" value="<?= htmlspecialchars($signupName, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="name" placeholder="Enter your name">
                        </div>

                        <div class="form-group">
                            <label for="reg-email">Email Address</label>
                            <input type="email" id="reg-email" name="email" class="form-input" value="<?= htmlspecialchars($signupEmail, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" placeholder="name@example.com">
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <div class="modern-selector-group">
                                <input type="radio" id="role-trainee" name="role" value="trainee" required hidden <?= $signupRole === 'trainee' ? 'checked' : '' ?>>
                                <label for="role-trainee" class="selector-card">
                                    <span class="card-icon">🎓</span>
                                    <span class="card-text-group">
                                        <span class="card-title">Trainee</span>
                                        <span class="card-desc">Looking to learn</span>
                                    </span>
                                </label>

                                <input type="radio" id="role-trainer" name="role" value="trainer" required hidden <?= $signupRole === 'trainer' ? 'checked' : '' ?>>
                                <label for="role-trainer" class="selector-card">
                                    <span class="card-icon">💼</span>
                                    <span class="card-text-group">
                                        <span class="card-title">Trainer</span>
                                        <span class="card-desc">Looking to teach</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group form-group-split">
                            <div class="half-width">
                                <label for="reg-password">Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="reg-password" name="password" class="form-input" required minlength="8" placeholder="••••••••">
                                    <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="half-width">
                                <label for="reg-confirm-password">Confirm Password</label>
                                <div class="password-wrapper">
                                    <input type="password" id="reg-confirm-password" name="confirm_password" class="form-input" required placeholder="••••••••">
                                    <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="submit-btn" data-loading-text="Creating Account...">Create Account &rarr;</button>

                        <div class="auth-divider">
                            <span>or</span>
                        </div>

                        <p class="auth-bottom-text">Already have an account? <label for="form-toggle" class="switch-auth-link">Sign In</label></p>
                    </form>
                </div>
            </div>
        </div>

        <!-- =======================================================
             PANEL 2: VISUAL OVERLAY (Slides Right <-> Left)
             ======================================================= -->
        <div class="login-illustration-panel">
            
            <!-- Static Asset: object-fit: cover guarantees no white space -->
            <img src="assets/images/skillconnect-auth-login.jpg" alt="SkillConnect learner exploring programming, data science, and machine learning at a laptop" class="auth-illustration-img">
            <div class="auth-illustration-overlay"></div>

            <!-- Top Brand -->
            <div class="illustration-brand">
                <h2><span class="text-primary">Skill</span>Connect</h2>
            </div>

            <!-- Dynamic Text based on Login/Signup state anchored at the bottom -->
            <div class="panel-typography text-login-state">
                <h3>Learn from Experts. Build Real Skills.</h3>
                <p>Connect with skilled trainers, gain hands-on knowledge and grow with a supportive community.</p>
            </div>

            <div class="panel-typography text-signup-state">
                <h3>Turn Your Skills Into Real Opportunities.</h3>
                <p>Join a growing community of learners and expert trainers, and take the next step in your journey.</p>
            </div>

        </div>

    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
