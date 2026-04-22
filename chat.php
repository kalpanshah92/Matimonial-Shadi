<?php
$pageTitle = 'Chat';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Get accepted connections (chat contacts)
$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.profile_id, u.profile_pic, u.gender, u.last_login,
     (SELECT message FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
     (SELECT created_at FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message_time,
     (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
     FROM users u
     WHERE u.id IN (
         SELECT CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END
         FROM connection_requests
         WHERE (sender_id = ? OR receiver_id = ?) AND status = 'accepted'
     ) AND u.is_active = 1
     ORDER BY last_message_time DESC"
);
$stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
$contacts = $stmt->fetchAll();

$selectedContact = intval($_GET['contact'] ?? 0);

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <div class="chat-container">
            <!-- Chat Sidebar -->
            <div class="chat-sidebar">
                <div class="p-3 border-bottom">
                    <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>Messages</h5>
                </div>
                <?php if (!empty($contacts)): ?>
                    <?php foreach ($contacts as $contact): ?>
                        <div class="chat-contact <?= $selectedContact == $contact['id'] ? 'active' : '' ?>" 
                             data-contact-id="<?= $contact['id'] ?>" data-contact-name="<?= sanitize($contact['name']) ?>">
                            <img src="<?= getProfilePic($contact['profile_pic'], $contact['gender']) ?>" alt="">
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between">
                                    <strong style="font-size: 0.9rem;"><?= sanitize($contact['name']) ?></strong>
                                    <?php if ($contact['unread_count'] > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?= $contact['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted text-truncate d-block"><?= sanitize($contact['last_message'] ?? 'Start a conversation') ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 px-3">
                        <i class="bi bi-chat-square-text" style="font-size: 3rem; color: var(--text-muted);"></i>
                        <p class="text-muted mt-2">No connections yet. Send interests to start chatting!</p>
                        <a href="<?= SITE_URL ?>/search.php" class="btn btn-primary btn-sm">Find Matches</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Chat Main Area -->
            <div class="chat-main">
                <div class="p-3 bg-white border-bottom d-flex align-items-center gap-3">
                    <h6 class="mb-0" id="chatContactName">Select a contact to start chatting</h6>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <div class="text-center py-5">
                        <i class="bi bi-chat-heart" style="font-size: 4rem; color: var(--text-muted);"></i>
                        <p class="text-muted mt-2">Select a conversation from the left panel</p>
                    </div>
                </div>
                <div class="chat-input-area" id="chatInputArea" style="display: none;">
                    <form id="chatForm" class="d-flex gap-2">
                        <input type="hidden" id="currentContactId" value="<?= $selectedContact ?>">
                        <input type="text" class="form-control" id="chatInput" placeholder="Type a message..." autocomplete="off">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
