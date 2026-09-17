<?php
//Page for authentication

//Start a session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//if request method is other than post sent back to login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit();
}

require_once 'includes/db_connect.php'; //include db connection page

//determine the action requested
$action = $_POST['action'] ?? '';

if ($action === 'register') {

    //sanitize the inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // 2. Server-side validation
    $errors = [];

    if (empty($name) || strlen($name) > 255) {
        $errors[] = "A valid name is required (max 255 characters).";
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

    // Redirect back to login with an error message if validation fails
    if (!empty($errors)) {
        $_SESSION['error'] = implode(" ", $errors);
        header("Location: ../login.php");
        exit();
    }

    //Database exception handling
    try {
        //prepare a statement to find already registerd email
        $checkstmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkstmt->execute([$email]);  //Swap ? placeholder with $email

        //if fetch return any value means email already exists
        if ($checkstmt->fetch()) {
            $_SESSION['error'] = "This email is already registered.";
            header("Location: ../login.php");
            exit();
        }

        //hasing the user password securely
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $insertstmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $insertstmt->execute([$name, $email, $password_hash, $role]);

        $new_user_id = $pdo->lastInsertId();

        //Session set up for new users
        session_regenerate_id(true);

        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['name'] = $name;
        $_SESSION['role'] = $role;
        $_SESSION['is_admin'] = 0; //Forced default for all new registration

        header('Location: ../dashboard.php');
        exit();
    } catch (PDOException $e) {
        //log the system error
        error_log("Registration Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An unexpected error occurred during registration. Please try again.";
        header("Location: ../login.php");
        exit();
    }
} elseif ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    //if any of email or password missing redirect straight to login
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Please provide both email & password.";
        header("Location: ../login.php");
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
            // Invalid credentials
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: ../login.php");
            exit();
        }
    } catch (PDOException $e) {
        // Log the actual system error and show a generic message to the user
        error_log("Login Database Error: " . $e->getMessage());
        $_SESSION['error'] = "An unexpected error occurred during login. Please try again.";
        header("Location: ../login.php");
        exit();
    }
} else {
    // catch all unidentified actions
    $_SESSION['error'] = "Invalid action requested.";
    header("Location: ../login.php");
    exit();
}
