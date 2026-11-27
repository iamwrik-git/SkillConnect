<?php
/**
 * actions/message_process.php - Handles in-page messaging between connected mentors and trainees
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$senderId = (int)getCurrentUserId();
$senderName = (string)($_SESSION['name'] ?? 'User');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php');
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if (!csrfValid($csrf)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Your security token expired. Please reload and try again.']);
        exit;
    }
    $_SESSION['error'] = 'Security token expired. Please try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboard.php'));
    exit;
}

$recipientId = (int)($_POST['recipient_id'] ?? 0);
$messageText = trim((string)($_POST['message'] ?? ''));

if ($recipientId <= 0 || $recipientId === $senderId) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid recipient specified.']);
        exit;
    }
    $_SESSION['error'] = 'Invalid recipient.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboard.php'));
    exit;
}

if ($messageText === '' || mb_strlen($messageText) > 1000) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Please enter a message between 1 and 1000 characters.']);
        exit;
    }
    $_SESSION['error'] = 'Please enter a message.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboard.php'));
    exit;
}

try {
    // Verify recipient exists
    $stmt = $pdo->prepare('SELECT user_id, name FROM users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$recipientId]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$recipient) {
        throw new Exception('Recipient account was not found.');
    }

    // Verify accepted mentorship connection exists between sender and recipient
    $connStmt = $pdo->prepare('
        SELECT 1 FROM mentorships 
        WHERE status = "accepted" 
          AND ((trainer_id = ? AND trainee_id = ?) OR (trainee_id = ? AND trainer_id = ?))
        LIMIT 1
    ');
    $connStmt->execute([$senderId, $recipientId, $senderId, $recipientId]);
    if (!$connStmt->fetchColumn()) {
        throw new Exception('You can only message mentors or mentees who have an accepted connection with you.');
    }

    // Insert into notifications with sender_user_id and full message text (0 extra tables needed)
    $insertStmt = $pdo->prepare('INSERT INTO notifications (recipient_user_id, sender_user_id, notification_type, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
    $insertStmt->execute([$recipientId, $senderId, 'message', $messageText]);
    $newMsgId = (int) $pdo->lastInsertId();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Message sent!',
            'notification_id' => $newMsgId,
            'message_text' => $messageText,
            'created_at' => date('Y-m-d H:i:s'),
            'formatted_time' => date('g:i A')
        ]);
        exit;
    }

    header('Location: ../messages.php?user_id=' . $recipientId);
    exit;
} catch (Exception $e) {
    error_log('Message Process Error: ' . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Could not send your message. Please try again.']);
        exit;
    }
    $_SESSION['error'] = 'Could not send message. Please try again.';
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../dashboard.php'));
    exit;
}
