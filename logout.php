<?php
// logout.php - Securely terminates the user session

// Start the session if it hasn't been started yet
require_once __DIR__ . '/includes/session.php';

// Unset all session variables to remove user data instantly from memory
$_SESSION = [];

// Destroy the session cookie in the user's browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, // Expire the cookie immediately in the past
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the actual session file data on the server
session_destroy();

// FIXED: Capitalized "Location" and targeted your standard file "login.php"
header("Location: login.php");
exit();
