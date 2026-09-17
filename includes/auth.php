<?php

// Check for user log in
// Start session if it has not been started yet.
// Return True if Logged in, false otherwise
function isLoggedIn()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

// Ensure the user must be logged in
// Redirect to login page & stop code execution
function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit();
    }
}

// Retrieve current user's id, null if not logged in.
function getCurrentUserId()
{
    if (isLoggedIn()) {
        return $_SESSION['user_id'];
    }
    return null; // Explicitly return null if not logged in
}

// Restrict user to a specific role
// Require login first
function requireRole(string $role)
{
    requireLogin(); //Ensures user is logged in before checking roles

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header("Location: ../dashboard.php");
        exit();
    }
}

// Restrict admin log in only
// Requires the user to be logged in first
function requireAdmin()
{
    requireLogin();
    if (empty($_SESSION['is_admin'])) {
        header("Location: ../dashboard.php");
        exit();
    }
}
