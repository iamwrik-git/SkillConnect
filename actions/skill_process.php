<?php 

//Correctly check session status before starting it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Add both auth_process & db_connect securely
require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../includes/db_connect.php');

// Enforce login strictly
requireLogin();

// Get the user id sourced from auth_process
$user_id = getCurrentUserId();

// No other method rather than 'POST' is allowed.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../edit_profile.php");
    exit();
}

// Get the user action 
$action = $_POST['action'] ?? '';
$skill_id = filter_input(INPUT_POST, 'skill_id', FILTER_VALIDATE_INT);

// Optional: Add CSRF token verification here if applicable
// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { ... }

// Skill add logic
if ($action === 'add_skill') {
    // For no skill_id or invalid skill
    if (!$skill_id || $skill_id <= 0) { 
        $_SESSION['error'] = "Invalid Skill selected.";
        header("Location: ../edit_profile.php");
        exit();
    }

    try {
        // Step 1: Check if skill exists in the master skill table
        $checkstmt = $pdo->prepare("SELECT 1 FROM skills WHERE skill_id = ?");
        $checkstmt->execute([$skill_id]);
        if (!$checkstmt->fetch()) {
            $_SESSION['error'] = "Skill not found.";
            header("Location: ../edit_profile.php");
            exit();
        }

        // Step 2: Check user skill count if it is >= 3 (max count reached)
        $countstmt = $pdo->prepare("SELECT COUNT(*) FROM user_skills WHERE user_id = ?");
        $countstmt->execute([$user_id]);
        $current_skill_count = (int) $countstmt->fetchColumn();

        if ($current_skill_count >= 3) {
            $_SESSION['error'] = "You can add a maximum of three skills.";
            header("Location: ../edit_profile.php");
            exit();
        }

        // Step 3: Check if chosen skill already exists for this user
        $duplicatestmt = $pdo->prepare("SELECT 1 FROM user_skills WHERE user_id = ? AND skill_id = ?");
        $duplicatestmt->execute([$user_id, $skill_id]);
        if ($duplicatestmt->fetch()) {
            $_SESSION['error'] = "This skill is already added.";
            header("Location: ../edit_profile.php");
            exit();
        }

        // Final step: Insert the new skill
        $insertstmt = $pdo->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?,?)");
        $insertstmt->execute([$user_id, $skill_id]);
        $_SESSION['success'] = "Skill added successfully."; // Fixed typo

    } catch (PDOException $pe) {
        // Catch all db errors while adding skill
        error_log("Database Error(Add Skill): " . $pe->getMessage());
        $_SESSION['error'] = "An unexpected error occurred while adding the skill.";
    } 
    header("Location: ../edit_profile.php");
    exit();
}
elseif ($action === 'remove_skill') {
    // If invalid skill selected
    if (!$skill_id || $skill_id <= 0) {
        $_SESSION['error'] = "Invalid skill selected.";
        header("Location: ../edit_profile.php");
        exit();
    }

    try {
        // Delete statement
        $deletestmt = $pdo->prepare("DELETE FROM user_skills WHERE user_id = ? AND skill_id = ?");
        $deletestmt->execute([$user_id, $skill_id]);

        // If deleted successfully else removing skill is not valid.
        if ($deletestmt->rowCount() > 0) {
            $_SESSION['success'] = "Skill removed successfully."; // Fixed typo
        } else {
            $_SESSION['error'] = "Skill not found in your profile.";
        }
    } catch (PDOException $pe) {
        error_log("Database Error(Remove skill): " . $pe->getMessage());
        $_SESSION['error'] = "An unexpected error occurred while removing the skill.";
    }
    header("Location: ../edit_profile.php");
    exit();
} 
else {
    // All unrecognized fallbacks
    header("Location: ../edit_profile.php");
    exit();
}
