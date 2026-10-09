<?php
// Start a session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If request method is other than post, send back to login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit();
}

require_once(__DIR__ . '/../includes/db_connect.php');

$action = $_POST['action'] ?? '';

if ($action === 'register') {
    // Sanitize the inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Server-side validation
    $errors = [];

    if (empty($name) || strlen($name) > 255) {
        $errors[] = "A valid name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors[] = "A valid email address is required.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($role !== 'trainee' && $role !== 'trainer') {
        $errors[] = "Please select a valid role.";
    }

    // Validation Failure: Redirect preserving 'signup' state and safe form data
    if (!empty($errors)) {
        $_SESSION['error'] = implode(" ", $errors);
        $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'role' => $role];
        header("Location: ../login.php?mode=signup");
        exit();
    }

    try {
        // Check for existing email
        $checkstmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkstmt->execute([$email]);

        if ($checkstmt->fetch()) {
            $_SESSION['error'] = "Account already registered. Please sign in.";
            $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'role' => $role];
            header("Location: ../login.php?mode=signup");
            exit();
        }

        // Hash and insert
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $insertstmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $insertstmt->execute([$name, $email, $password_hash, $role]);

        $new_user_id = $pdo->lastInsertId();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['name'] = $name;
        $_SESSION['role'] = $role;
        $_SESSION['is_admin'] = 0;

        header('Location: ../dashboard.php');
        exit();
    } catch (PDOException $e) {
        error_log("Registration Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An unexpected error occurred. Please try again.";
        $_SESSION['form_data'] = ['name' => $name, 'email' => $email, 'role' => $role];
        header("Location: ../login.php?mode=signup");
        exit();
    }
} elseif ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please provide both email & password.";
        $_SESSION['form_data'] = ['email' => $email];
        header("Location: ../login.php?mode=login");
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, name, password_hash, role, is_admin FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_admin'] = $user['is_admin'];

            header("Location: ../dashboard.php");
            exit();
        } else {
            // Invalid credentials: Redirect preserving 'login' state
            $_SESSION['error'] = "Sign-in failed. Invalid email or password.";
            $_SESSION['form_data'] = ['email' => $email];
            header("Location: ../login.php?mode=login");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Login Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An unexpected error occurred during login. Please try again.";
        $_SESSION['form_data'] = ['email' => $email];
        header("Location: ../login.php?mode=login");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid action requested.";
    header("Location: ../login.php");
    exit();
}
