<?php
// messages.php - Direct messaging between accepted mentors and mentees
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

requireLogin();

$current_user_id = (int) getCurrentUserId();
$current_user_role = (string) ($_SESSION['role'] ?? 'trainee');
$csrf_token = csrfToken();

// 1. Fetch all accepted mentorship contacts (mentors and mentees)
$connected_contacts = [];
try {
    $contactsStmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.name,
            u.email,
            u.role,
            u.profile_photo,
            u.exp_level,
            m.request_id,
            (
                SELECT message 
                FROM notifications 
                WHERE notification_type = 'message'
                  AND ((sender_user_id = :me1 AND recipient_user_id = u.user_id) 
                    OR (sender_user_id = u.user_id AND recipient_user_id = :me2))
                ORDER BY created_at DESC, notification_id DESC
                LIMIT 1
            ) AS last_message,
            (
                SELECT created_at 
                FROM notifications 
                WHERE notification_type = 'message'
                  AND ((sender_user_id = :me3 AND recipient_user_id = u.user_id) 
                    OR (sender_user_id = u.user_id AND recipient_user_id = :me4))
                ORDER BY created_at DESC, notification_id DESC
                LIMIT 1
            ) AS last_message_time,
            (
                SELECT COUNT(*) 
                FROM notifications 
                WHERE notification_type = 'message'
                  AND sender_user_id = u.user_id 
                  AND recipient_user_id = :me5 
                  AND is_read = 0
            ) AS unread_count
        FROM mentorships m
        JOIN users u ON (
            (m.trainer_id = :me6 AND m.trainee_id = u.user_id)
            OR 
            (m.trainee_id = :me7 AND m.trainer_id = u.user_id)
        )
        WHERE m.status = 'accepted'
        ORDER BY 
            (last_message_time IS NOT NULL) DESC,
            last_message_time DESC,
            u.name ASC
    ");
    $contactsStmt->execute([
        'me1' => $current_user_id,
        'me2' => $current_user_id,
        'me3' => $current_user_id,
        'me4' => $current_user_id,
        'me5' => $current_user_id,
        'me6' => $current_user_id,
        'me7' => $current_user_id,
    ]);
    $connected_contacts = $contactsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Messages Contacts Fetch Error: " . $e->getMessage());
}

$contacts_by_id = [];
foreach ($connected_contacts as $c) {
    $contacts_by_id[(int)$c['user_id']] = $c;
}

// 2. Determine active contact
$active_contact = null;
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $requested_id = (int) $_GET['user_id'];
    if (isset($contacts_by_id[$requested_id])) {
        $active_contact = $contacts_by_id[$requested_id];
    } else {
        // User requested a chat with someone not accepted
        try {
            $userCheck = $pdo->prepare("SELECT name FROM users WHERE user_id = ? LIMIT 1");
            $userCheck->execute([$requested_id]);
            $targetUser = $userCheck->fetch(PDO::FETCH_ASSOC);
            if ($targetUser) {
                $_SESSION['error'] = "You can only message mentors or mentees who have an accepted connection with you.";
            } else {
                $_SESSION['error'] = "User was not found.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Could not open conversation.";
        }
        header("Location: messages.php");
        exit;
    }
} else {
    // Default to the first contact if available
    if (!empty($connected_contacts)) {
        $active_contact = $connected_contacts[0];
    }
}

// 3. Mark active conversation's unread messages as read & fetch message history
$chat_messages = [];
if ($active_contact) {
    $active_id = (int) $active_contact['user_id'];
    try {
        // Mark as read
        $markStmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE notification_type = 'message' 
              AND sender_user_id = ? 
              AND recipient_user_id = ? 
              AND is_read = 0
        ");
        $markStmt->execute([$active_id, $current_user_id]);

        // Fetch chronological conversation messages
        $messagesStmt = $pdo->prepare("
            SELECT 
                n.notification_id,
                n.sender_user_id,
                n.recipient_user_id,
                n.message,
                n.created_at,
                u.name AS sender_name,
                u.profile_photo AS sender_photo
            FROM notifications n
            JOIN users u ON n.sender_user_id = u.user_id
            WHERE n.notification_type = 'message'
              AND (
                (n.sender_user_id = :me1 AND n.recipient_user_id = :other1)
                OR
                (n.sender_user_id = :other2 AND n.recipient_user_id = :me2)
              )
            ORDER BY n.created_at ASC, n.notification_id ASC
        ");
        $messagesStmt->execute([
            'me1' => $current_user_id,
            'other1' => $active_id,
            'other2' => $active_id,
            'me2' => $current_user_id,
        ]);
        $chat_messages = $messagesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Messages Fetch Error: " . $e->getMessage());
    }
}

$page_title = 'Messages - SkillConnect';
if (file_exists('includes/header.php')) {
    require_once 'includes/header.php';
}
?>

<style>
/* Full-stretch edge-to-edge layout for messages without side boundaries or header */
.main-content {
    padding: 0 !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    height: 100% !important;
    max-height: 100% !important;
    flex: 1 !important;
}
</style>

<div class="messages-page-wrapper">

    <!-- Flash message alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="profile-alert alert-success js-toast-target" style="display:none;" role="status">
            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="profile-alert alert-danger js-toast-target" style="display:none;" role="alert">
            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="messages-layout-container">
        
        <?php if (empty($connected_contacts)): ?>
            <!-- Empty State: No accepted mentorships yet -->
            <div class="messages-empty-view">
                <div class="messages-empty-icon-wrap">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </div>
                <h3>No Connected Conversations Yet</h3>
                <p>Direct messaging is only available between connected mentors and mentees. Once a mentorship request is accepted, your chat will unlock automatically!</p>
                <?php if ($current_user_role === 'trainee'): ?>
                    <a href="search.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        Find a Mentor
                    </a>
                <?php else: ?>
                    <a href="mentorships.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>
                        View Mentorship Requests
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <!-- LEFT: Conversations List Pane -->
            <aside class="chat-sidebar <?= !empty($active_contact) ? 'has-active' : '' ?>">
                <div class="chat-sidebar-header">
                    <h2 class="chat-sidebar-title">Conversations</h2>
                    <span class="chat-connections-badge"><?= count($connected_contacts) ?> connection<?= count($connected_contacts) === 1 ? '' : 's' ?></span>
                </div>

                <div class="chat-contacts-list" role="tablist" aria-label="Connected contacts">
                    <?php foreach ($connected_contacts as $contact): 
                        $is_active = $active_contact && ((int)$active_contact['user_id'] === (int)$contact['user_id']);
                        $avatar_url = getProfilePhotoUrl($contact['profile_photo'] ?? '', $contact['name'] ?? 'User');
                        $role_label = $contact['role'] === 'trainer' ? 'Mentor' : 'Mentee';
                        $unread = (int)($contact['unread_count'] ?? 0);
                        
                        $snippet = !empty($contact['last_message']) ? $contact['last_message'] : 'No messages yet';
                        $time_label = '';
                        if (!empty($contact['last_message_time'])) {
                            $ts = strtotime($contact['last_message_time']);
                            if (date('Y-m-d', $ts) === date('Y-m-d')) {
                                $time_label = date('g:i A', $ts);
                            } else {
                                $time_label = date('M j', $ts);
                            }
                        }
                    ?>
                        <a href="messages.php?user_id=<?= (int)$contact['user_id'] ?>" 
                           class="chat-contact-item <?= $is_active ? 'active' : '' ?>" 
                           role="tab" 
                           aria-selected="<?= $is_active ? 'true' : 'false' ?>">
                            <div class="chat-avatar-wrap">
                                <img src="<?= $avatar_url ?>" alt="<?= htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8') ?>" class="chat-avatar">
                                <?php if ($unread > 0): ?>
                                    <span class="chat-unread-dot" aria-label="<?= $unread ?> unread message<?= $unread === 1 ? '' : 's' ?>"></span>
                                <?php endif; ?>
                            </div>
                            <div class="chat-contact-info">
                                <div class="chat-contact-top">
                                    <strong class="chat-contact-name"><?= htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($time_label): ?>
                                        <span class="chat-contact-time"><?= htmlspecialchars($time_label, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-contact-bottom">
                                    <span class="chat-contact-snippet <?= empty($contact['last_message']) ? 'is-empty' : '' ?>">
                                        <?= htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <span class="chat-role-pill role-<?= strtolower($role_label) ?>"><?= $role_label ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- RIGHT: Active Chat Conversation Pane -->
            <main class="chat-main-area">
                <?php if ($active_contact): 
                    $active_avatar = getProfilePhotoUrl($active_contact['profile_photo'] ?? '', $active_contact['name'] ?? 'User');
                    $active_role = $active_contact['role'] === 'trainer' ? 'Mentor' : 'Mentee';
                ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-header-user">
                            <a href="messages.php" class="chat-back-mobile" aria-label="Back to conversations list">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </a>
                            <img src="<?= $active_avatar ?>" alt="<?= htmlspecialchars($active_contact['name'], ENT_QUOTES, 'UTF-8') ?>" class="chat-header-avatar">
                            <div>
                                <h3 class="chat-header-name"><?= htmlspecialchars($active_contact['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <div class="chat-header-meta">
                                    <span class="chat-role-pill role-<?= strtolower($active_role) ?>"><?= $active_role ?></span>
                                    <?php if (!empty($active_contact['exp_level']) && $active_contact['role'] === 'trainer'): ?>
                                        <span class="chat-meta-sep">•</span>
                                        <span class="chat-meta-exp"><?= htmlspecialchars(ucfirst($active_contact['exp_level']), ENT_QUOTES, 'UTF-8') ?> experience</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="chat-header-actions">
                            <a href="profile.php?user_id=<?= (int)$active_contact['user_id'] ?>" class="btn-card-outline" title="View Profile">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <span>Profile</span>
                            </a>
                        </div>
                    </div>

                    <!-- Chat Message Stream -->
                    <div class="chat-stream" id="chat-stream">
                        <?php if (empty($chat_messages)): ?>
                            <div class="chat-welcome-box">
                                <div class="chat-welcome-icon">
                                    <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                    </svg>
                                </div>
                                <h4>Start your conversation with <?= htmlspecialchars($active_contact['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                                <p>This is the beginning of your direct mentorship chat. Send your first message below!</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $last_date = '';
                            foreach ($chat_messages as $msg): 
                                $is_outgoing = ((int)$msg['sender_user_id'] === $current_user_id);
                                $msg_ts = strtotime($msg['created_at']);
                                $msg_date = date('Y-m-d', $msg_ts);

                                if ($msg_date !== $last_date):
                                    $last_date = $msg_date;
                                    $date_label = date('F j, Y', $msg_ts);
                                    if ($msg_date === date('Y-m-d')) {
                                        $date_label = 'Today';
                                    } elseif ($msg_date === date('Y-m-d', strtotime('-1 day'))) {
                                        $date_label = 'Yesterday';
                                    }
                            ?>
                                    <div class="chat-date-divider">
                                        <span><?= htmlspecialchars($date_label, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                            <?php endif; ?>

                                <div class="chat-message-row <?= $is_outgoing ? 'outgoing' : 'incoming' ?>">
                                    <div class="chat-bubble">
                                        <div class="chat-bubble-text"><?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?></div>
                                        <div class="chat-bubble-time"><?= date('g:i A', $msg_ts) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Chat Message Input Bar -->
                    <div class="chat-input-container">
                        <form action="actions/message_process.php" method="POST" class="chat-input-form" id="chat-send-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="recipient_id" value="<?= (int)$active_contact['user_id'] ?>">
                            <div class="chat-input-wrapper">
                                <textarea name="message" 
                                          id="chat-message-input" 
                                          class="chat-textarea" 
                                          rows="1" 
                                          placeholder="Type a message to <?= htmlspecialchars($active_contact['name'], ENT_QUOTES, 'UTF-8') ?>..." 
                                          maxlength="1000" 
                                          required 
                                          autocomplete="off"></textarea>
                                <button type="submit" class="btn btn-primary chat-send-btn" id="chat-submit-btn" aria-label="Send message">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                    </svg>
                                </button>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="chat-empty-select">
                        <p>Select a connected conversation from the list to start messaging.</p>
                    </div>
                <?php endif; ?>
            </main>

        <?php endif; ?>

    </div>

</div>

<script>
// Auto-scroll chat to latest message on page load and handle smooth typing
document.addEventListener('DOMContentLoaded', () => {
    const stream = document.getElementById('chat-stream');
    if (stream) {
        stream.scrollTop = stream.scrollHeight;
    }

    const textarea = document.getElementById('chat-message-input');
    const form = document.getElementById('chat-send-form');

    if (textarea) {
        // Auto-expand textarea slightly on multi-line input
        textarea.addEventListener('input', () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        });

        // Submit on Enter key (without Shift)
        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (textarea.value.trim().length > 0) {
                    form.submit();
                }
            }
        });
    }

    // AJAX form submission for seamless, instant message delivery
    if (form && stream) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const messageText = textarea.value.trim();
            if (!messageText) return;

            const submitBtn = document.getElementById('chat-submit-btn');
            submitBtn.disabled = true;

            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    // Remove welcome box if present
                    const welcome = stream.querySelector('.chat-welcome-box');
                    if (welcome) welcome.remove();

                    // Create and append outgoing bubble
                    const row = document.createElement('div');
                    row.className = 'chat-message-row outgoing';
                    
                    const bubble = document.createElement('div');
                    bubble.className = 'chat-bubble';

                    const textDiv = document.createElement('div');
                    textDiv.className = 'chat-bubble-text';
                    textDiv.textContent = messageText;

                    const timeDiv = document.createElement('div');
                    timeDiv.className = 'chat-bubble-time';
                    timeDiv.textContent = data.formatted_time || 'Just now';

                    bubble.appendChild(textDiv);
                    bubble.appendChild(timeDiv);
                    row.appendChild(bubble);
                    stream.appendChild(row);

                    // Clear textarea and scroll
                    textarea.value = '';
                    textarea.style.height = 'auto';
                    stream.scrollTop = stream.scrollHeight;
                } else {
                    alert(data.error || 'Failed to send message.');
                }
            } catch (err) {
                // If network or parsing error, fallback to normal submission
                form.submit();
            } finally {
                submitBtn.disabled = false;
                textarea.focus();
            }
        });
    }
});
</script>

<?php
if (file_exists('includes/footer.php')) {
    require_once 'includes/footer.php';
}
?>
