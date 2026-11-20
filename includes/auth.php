<?php

// Check for user log in
// Start session if it has not been started yet.
// Return True if Logged in, false otherwise
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        require_once __DIR__ . '/session.php';
        return isset($_SESSION['user_id']);
    }
}

// Ensure the user must be logged in
// Redirect to login page & stop code execution
if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        if (!isLoggedIn()) {
            $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
            $prefix = in_array(basename($scriptDirectory), ['actions', 'admin'], true) ? '../' : '';
            header("Location: " . $prefix . "login.php");
            exit();
        }

        // Newly registered users must finish onboarding before opening other pages/actions.
        // Existing sessions without this flag are left alone; migrated accounts default to complete.
        if (
            array_key_exists('onboarding_completed', $_SESSION)
            && (int) $_SESSION['onboarding_completed'] === 0
            && empty($_SESSION['is_admin'])
        ) {
            $currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
            if (!in_array($currentScript, ['dashboard.php', 'onboarding_process.php', 'logout.php'], true)) {
                $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
                if (in_array(basename($scriptDirectory), ['actions', 'admin'], true)) {
                    $scriptDirectory = dirname($scriptDirectory);
                }
                $dashboardPath = rtrim($scriptDirectory, '/');
                header('Location: ' . ($dashboardPath === '' ? '' : $dashboardPath) . '/dashboard.php');
                exit();
            }
        }
    }
}

// Retrieve current user's id, null if not logged in.
if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId()
    {
        if (isLoggedIn()) {
            return $_SESSION['user_id'];
        }
        return null; // Explicitly return null if not logged in
    }
}

// Restrict admin log in only
// Requires the user to be logged in first
if (!function_exists('requireAdmin')) {
    function requireAdmin()
    {
        requireLogin();
        if (empty($_SESSION['is_admin'])) {
            $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
            $prefix = in_array(basename($scriptDirectory), ['actions', 'admin'], true) ? '../' : '';
            header("Location: " . $prefix . "dashboard.php");
            exit();
        }
    }
}
