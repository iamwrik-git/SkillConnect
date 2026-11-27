<?php
// login.php
require_once 'includes/auth.php';

// Set no-cache headers to prevent browser back-button from restoring a stale, locked form state
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect logged-in users directly to their appropriate dashboard
if (isLoggedIn()) {
    $target = !empty($_SESSION['is_admin']) ? "admin/dashboard.php" : "dashboard.php";
    header("Location: $target");
    exit();
}

// 1. Determine Auth Mode State & Fallbacks
$clearedNotice = isset($_GET['cleared']) ? 'Previous account setup was cancelled. You can create your account fresh below.' : null;
$mode = $_GET['mode'] ?? ($clearedNotice ? 'signup' : 'login');
$isSignup = ($mode === 'signup' || $clearedNotice !== null);

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
    <link rel="icon" type="image/png" href="/SkillConnect/assets/images/favicon.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        // General fallback: All login and signup flows always default to bright/white theme
        try {
            document.documentElement.classList.remove('theme-dark');
            document.documentElement.classList.add('theme-light');
            if (document.body) {
                document.body.classList.remove('theme-dark');
                document.body.classList.add('theme-light');
            }
        } catch (e) {}
    </script>
</head>

<body class="auth-page">

    <!-- CSS State Checkbox: Pre-checked if redirect came from a sign-up error -->
    <input type="checkbox" id="form-toggle" class="state-trigger" <?= $isSignup ? 'checked' : '' ?> hidden>

    <!-- Persistent mobile brand header, independent of the floating form card. -->
    <header class="auth-mobile-brand">
        <a href="index.php" aria-label="SkillConnect home">
            <img src="assets/images/skillconnect-logo.svg" alt="SkillConnect" class="auth-brand-logo">
        </a>
    </header>

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
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="actions/auth_process.php" method="POST" class="auth-form" novalidate>
                        <input type="hidden" name="action" value="login">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group">
                            <label for="login-email">Email Address</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="5" width="18" height="14" rx="1.5"></rect>
                                        <path d="m3 7 9 7 9-7"></path>
                                    </svg>
                                </span>
                                <input type="email" id="login-email" name="email" class="form-input" value="<?= htmlspecialchars($loginEmail, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email" placeholder="name@example.com">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <div class="auth-input-wrapper password-wrapper">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                                        <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                    </svg>
                                </span>
                                <input type="password" id="login-password" name="password" class="form-input" required autocomplete="current-password" placeholder="••••••••">
                                <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="forgot-password-row">
                            <a href="forgot_password.php">Forgot password?</a>
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

                    <?php if (!empty($clearedNotice)): ?>
                        <div class="auth-cleared-banner" role="status">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span><?= htmlspecialchars($clearedNotice, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($isSignup && $error): ?>
                        <div class="inline-error-banner">
                            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="actions/auth_process.php" method="POST" class="auth-form" id="register-form">
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-group">
                            <label for="reg-name">Full Name</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4"></circle>
                                        <path d="M4 21v-2a8 8 0 0 1 16 0v2z"></path>
                                    </svg>
                                </span>
                                <input type="text" id="reg-name" name="name" class="form-input" value="<?= htmlspecialchars($signupName, ENT_QUOTES, 'UTF-8') ?>" required maxlength="100" autocomplete="name" placeholder="Enter your name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg-email">Email Address</label>
                            <div class="auth-input-wrapper">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="5" width="18" height="14" rx="1.5"></rect>
                                        <path d="m3 7 9 7 9-7"></path>
                                    </svg>
                                </span>
                                <input type="email" id="reg-email" name="email" class="form-input" value="<?= htmlspecialchars($signupEmail, ENT_QUOTES, 'UTF-8') ?>" required maxlength="100" autocomplete="email" placeholder="name@example.com">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <div class="modern-selector-group">
                                <input type="radio" id="role-trainee" name="role" value="trainee" required hidden <?= $signupRole === 'trainee' ? 'checked' : '' ?>>
                                <label for="role-trainee" class="selector-card">
                                    <span class="card-icon">🎓</span>
                                    <span class="card-text-group">
                                        <span class="card-title">Mentee</span>
                                        <span class="card-desc">Looking to learn</span>
                                    </span>
                                </label>

                                <input type="radio" id="role-trainer" name="role" value="trainer" required hidden <?= $signupRole === 'trainer' ? 'checked' : '' ?>>
                                <label for="role-trainer" class="selector-card">
                                    <span class="card-icon">💼</span>
                                    <span class="card-text-group">
                                        <span class="card-title">Mentor</span>
                                        <span class="card-desc">Looking to teach</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group form-group-split">
                            <div class="half-width">
                                <label for="reg-password">Password</label>
                                <div class="auth-input-wrapper password-wrapper">
                                    <span class="auth-input-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                                            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                        </svg>
                                    </span>
                                    <input type="password" id="reg-password" name="password" class="form-input" required minlength="8" autocomplete="new-password" placeholder="••••••••">
                                    <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="half-width">
                                <label for="reg-confirm-password">Confirm Password</label>
                                <div class="auth-input-wrapper password-wrapper">
                                    <span class="auth-input-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                                            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                        </svg>
                                    </span>
                                    <input type="password" id="reg-confirm-password" name="confirm_password" class="form-input" required minlength="8" autocomplete="new-password" placeholder="••••••••">
                                    <button type="button" class="password-toggle-btn js-password-toggle" aria-label="Show password">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
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
                <img src="assets/images/skillconnect-logo.svg" alt="SkillConnect" class="auth-brand-logo">
            </div>

            <!-- Dynamic Text based on Login/Signup state anchored at the bottom -->
            <div class="panel-typography text-login-state">
                <h3>Learn from Experts. Build Real Skills.</h3>
                <p>Connect with skilled mentors, gain hands-on knowledge and grow with a supportive community.</p>
            </div>

            <div class="panel-typography text-signup-state">
                <h3>Turn Your Skills Into Real Opportunities.</h3>
                <p>Join a growing community of learners and expert mentors, and take the next step in your journey.</p>
            </div>

        </div>

    </div>

    <script src="assets/js/main.js" defer></script>
</body>

</html>