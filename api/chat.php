<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$pdo = getDBConnection();

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_messages':
        $contactId = intval($_GET['contact_id'] ?? 0);
        
        if (!$contactId) {
            echo json_encode(['success' => false, 'message' => 'Invalid contact']);
            exit;
        }
        
        // Verify connection exists
        $stmt = $pdo->prepare(
            "SELECT id FROM connection_requests 
             WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
             AND status = 'accepted'"
        );
        $stmt->execute([$userId, $contactId, $contactId, $userId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Not connected']);
            exit;
        }
        
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->execute([$contactId, $userId]);
        
        // Get messages
        $stmt = $pdo->prepare(
            "SELECT id, sender_id, message, created_at FROM messages 
             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
             ORDER BY created_at ASC LIMIT 100"
        );
        $stmt->execute([$userId, $contactId, $contactId, $userId]);
        $messages = $stmt->fetchAll();
        
        $formatted = [];
        foreach ($messages as $msg) {
            $formatted[] = [
                'id' => $msg['id'],
                'message' => htmlspecialchars($msg['message']),
                'is_mine' => ($msg['sender_id'] == $userId),
                'time' => date('h:i A', strtotime($msg['created_at']))
            ];
        }
        
        echo json_encode(['success' => true, 'messages' => $formatted]);
        break;

    case 'send':
        $contactId = intval($_POST['contact_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        
        if (!$contactId || empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }
        
        // Verify connection
        $stmt = $pdo->prepare(
            "SELECT id FROM connection_requests 
             WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
             AND status = 'accepted'"
        );
        $stmt->execute([$userId, $contactId, $contactId, $userId]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Not connected']);
            exit;
        }
        
        // Sanitize and limit message
        $message = substr(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'), 0, 1000);
        
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $contactId, $message]);
        
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
