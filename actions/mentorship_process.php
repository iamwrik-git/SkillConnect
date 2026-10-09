<?php  // Need Review
session_start();

require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure user is logged in
requireLogin();

// Get the authenticated user ID
$current_user_id = getCurrentUserId();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard.php");
    exit();
}

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if ($action === 'send_request') {
    
    $trainer_id = filter_input(INPUT_POST, 'trainer_id', FILTER_VALIDATE_INT);
    
    if (!$trainer_id || $trainer_id <= 0) {
        $_SESSION['error'] = "Invalid trainer ID.";
        header("Location: ../dashboard.php");
        exit();
    }
    
    $redirect_url = "../profile.php?user_id=" . $trainer_id;

    // Prevent self-mentorship
    if ($current_user_id === $trainer_id) {
        $_SESSION['error'] = "You cannot send a mentorship request to yourself.";
        header("Location: " . $redirect_url);
        exit();
    }

    try {
        // Validate that current user is a trainee and target user is a trainer
        $stmt = $pdo->prepare("SELECT user_id, role FROM users WHERE user_id IN (?, ?)");
        $stmt->execute([$current_user_id, $trainer_id]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $is_trainee = false;
        $is_trainer = false;

        foreach ($users as $user) {
            if ($user['user_id'] == $current_user_id && $user['role'] === 'trainee') {
                $is_trainee = true;
            }
            if ($user['user_id'] == $trainer_id && $user['role'] === 'trainer') {
                $is_trainer = true;
            }
        }

        if (!$is_trainee) {
            $_SESSION['error'] = "Only trainees can send mentorship requests.";
            header("Location: " . $redirect_url);
            exit();
        }

        if (!$is_trainer) {
            $_SESSION['error'] = "The requested user is not a valid trainer.";
            header("Location: " . $redirect_url);
            exit();
        }

        // Check the ACTIVE request rule (only one pending or accepted request allowed)
        $stmt = $pdo->prepare("
            SELECT request_id 
            FROM mentorships 
            WHERE trainee_id = ? 
            AND trainer_id = ? 
            AND status IN ('pending', 'accepted')
        ");
        $stmt->execute([$current_user_id, $trainer_id]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "You already have an active or pending mentorship request with this trainer.";
            header("Location: " . $redirect_url);
            exit();
        }

        // Insert new pending request (rejected requests are historical and do not block this)
        $stmt = $pdo->prepare("INSERT INTO mentorships (trainer_id, trainee_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$trainer_id, $current_user_id]);

        $_SESSION['success'] = "Mentorship request sent successfully.";
        header("Location: " . $redirect_url);
        exit();

    } catch (PDOException $e) {
        error_log("Database Error in send_request: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while sending your request. Please try again later.";
        header("Location: " . $redirect_url);
        exit();
    }

} elseif ($action === 'accept_request' || $action === 'reject_request') {
    
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    
    if (!$request_id || $request_id <= 0) {
        $_SESSION['error'] = "Invalid request ID.";
        header("Location: ../dashboard.php");
        exit();
    }

    $new_status = ($action === 'accept_request') ? 'accepted' : 'rejected';
    $success_message = ($action === 'accept_request') ? "Mentorship request accepted." : "Mentorship request rejected.";

    try {
        // Validate that the authenticated user is actually a trainer
        $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$current_user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_user || $current_user['role'] !== 'trainer') {
            $_SESSION['error'] = "Only trainers can accept or reject requests.";
            header("Location: ../dashboard.php");
            exit();
        }

        // Fetch the request and join with users to verify the trainee's role
        $stmt = $pdo->prepare("
            SELECT m.status, trainee.role AS trainee_role 
            FROM mentorships m
            JOIN users trainee ON m.trainee_id = trainee.user_id
            WHERE m.request_id = ? 
            AND m.trainer_id = ?
        ");
        $stmt->execute([$request_id, $current_user_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify request exists and is owned by the current trainer
        if (!$request) {
            $_SESSION['error'] = "Mentorship request not found or access denied.";
            header("Location: ../dashboard.php");
            exit();
        }

        // Verify status is pending
        if ($request['status'] !== 'pending') {
            $_SESSION['error'] = "Only pending requests can be modified.";
            header("Location: ../dashboard.php");
            exit();
        }

        // Verify the trainee is still a valid trainee
        if ($request['trainee_role'] !== 'trainee') {
            $_SESSION['error'] = "The sender of this request is no longer a valid trainee.";
            header("Location: ../dashboard.php");
            exit();
        }

        // Update the request status
        $update_stmt = $pdo->prepare("
            UPDATE mentorships 
            SET status = ? 
            WHERE request_id = ? 
            AND trainer_id = ? 
            AND status = 'pending'
        ");
        $update_stmt->execute([$new_status, $request_id, $current_user_id]);

        if ($update_stmt->rowCount() > 0) {
            $_SESSION['success'] = $success_message;
        } else {
            $_SESSION['error'] = "Could not update the request. It may have already been processed.";
        }

        header("Location: ../dashboard.php");
        exit();

    } catch (PDOException $e) {
        error_log("Database Error in " . $action . ": " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while processing the request. Please try again later.";
        header("Location: ../dashboard.php");
        exit();
    }

} else {
    // Unrecognized action
    $_SESSION['error'] = "Invalid action.";
    header("Location: ../dashboard.php");
    exit();
}
