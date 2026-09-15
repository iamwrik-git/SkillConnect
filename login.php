<?php 
session_start(); 

// Track which form to show based on what action failed
$show_signup = false;
if (isset($_SESSION['error'])) {
    // If the last action was 'register', make sure we display the signup container on reload
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $show_signup = true;
    }
}
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - SkillConnect</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-wrapper">
        <!-- Left Panel -->
        <div class="auth-left">
            <div class="brand-content">
                <h3 class="brand-logo">SkillConnect</h3>
                <h1 class="brand-tagline">Connect. Learn. Mentor.</h1>
                <p class="brand-subtext">Join the community to upgrade your skills or share your expertise.</p>
                <div class="brand-illustration">
                    <!-- FIXED: Closed missing comment tag from your source code snippet -->
                    <img src="assets/images/SkillConnect-Illustration.png" alt="SkillConnect Illustration">
                </div>
            </div>
        </div>
        
        <!-- Right Form Panel -->
        <div class="auth-right">
            
            <!-- Login Form Container -->
            <!-- FIXED: Added dynamic hidden class if signup form needs to be displayed -->
            <div class="form-container <?php echo $show_signup ? 'hidden' : ''; ?>" id="login-container">
                <span class="welcome-text">Welcome to SkillConnect</span>
                <h2 class="form-title">Sign in</h2>

                <!-- FIXED: Localized error alert output here -->
                <?php if (isset($_SESSION['error']) && !$show_signup): ?>
                    <div style="color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: 600; font-size: 14px;">
                        <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']); // Clear after showing
                        ?>
                    </div>
                <?php endif; ?>
                
                <form action="actions/auth_process.php" method="POST">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="input-group">
                        <label for="login_email">Enter your email address</label>
                        <input type="email" id="login_email" name="email" placeholder="name@example.com" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="login_password">Enter your Password</label>
                        <input type="password" id="login_password" name="password" placeholder="••••••••" required>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="#">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn-primary">Sign in</button>
                    
                    <p class="toggle-auth">
                        No Account? <a href="javascript:void(0);" onclick="toggleForms()">Sign up</a>
                    </p>
                </form>
            </div>

            <!-- Sign Up Form Container -->
            <!-- FIXED: Dynamic structural visibility toggled right inside server layout template -->
            <div class="form-container <?php echo $show_signup ? '' : 'hidden'; ?>" id="signup-container">
                <span class="welcome-text">Join SkillConnect</span>
                <h2 class="form-title">Create an Account</h2>

                <!-- FIXED: Localized error alert output inside signup container panel -->
                <?php if (isset($_SESSION['error']) && $show_signup): ?>
                    <div style="color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-weight: 600; font-size: 14px;">
                        <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']); // Clear after showing
                        ?>
                    </div>
                <?php endif; ?>
                
                <form action="actions/auth_process.php" method="POST">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="input-group">
                        <label for="reg_name">Full Name</label>
                        <input type="text" id="reg_name" name="name" placeholder="Wrik Sinha" required>
                    </div>

                    <div class="input-group">
                        <label for="reg_email">Email address</label>
                        <input type="email" id="reg_email" name="email" placeholder="name@example.com" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="reg_password">Create a Password</label>
                        <input type="password" id="reg_password" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="role-group">
                        <label class="role-checkbox">
                            <input type="checkbox" name="is_learner" value="1"> I want to learn
                        </label>
                        <label class="role-checkbox">
                            <input type="checkbox" name="is_mentor" value="1"> I want to mentor
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-primary">Sign up</button>
                    
                    <p class="toggle-auth">
                        Already have an account? <a href="javascript:void(0);" onclick="toggleForms()">Sign in</a>
                    </p>
                </form>
            </div>
            
        </div>
    </div>

    <!-- Script to toggle forms seamlessly -->
    <script>
        function toggleForms() {
            document.getElementById('login-container').classList.toggle('hidden');
            document.getElementById('signup-container').classList.toggle('hidden');
        }
    </script>
</body>
</html>
