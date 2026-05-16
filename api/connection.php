<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// F-06 CSRF
requireCSRF();

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

// F-07 Rate limit interest-spam: 30 send/day, 60 accept-or-decline/hour
if ($action === 'send' && !rateLimit('conn:send:' . $userId, 30, 86400)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Daily interest limit reached.']);
    exit;
}
if (in_array($action, ['accept','decline'], true) && !rateLimit('conn:resp:' . $userId, 60, 3600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests.']);
    exit;
}

$pdo = getDBConnection();

switch ($action) {
    case 'send':
        $profileId = intval($_POST['profile_id'] ?? 0);
        
        if (!$profileId || $profileId === $userId) {
            echo json_encode(['success' => false, 'message' => 'Invalid profile']);
            exit;
        }
        
        // Check if request already exists
        $stmt = $pdo->prepare(
            "SELECT id FROM connection_requests WHERE sender_id = ? AND receiver_id = ?"
        );
        $stmt->execute([$userId, $profileId]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Request already sent']);
            exit;
        }
        
        // Check reverse request
        $stmt = $pdo->prepare(
            "SELECT id, status FROM connection_requests WHERE sender_id = ? AND receiver_id = ?"
        );
        $stmt->execute([$profileId, $userId]);
        $reverse = $stmt->fetch();
        
        if ($reverse && $reverse['status'] === 'pending') {
            // Auto-accept mutual interest
            $stmt = $pdo->prepare("UPDATE connection_requests SET status = 'accepted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reverse['id']]);
            
            createNotification($profileId, 'connection', 'Connection Accepted', 'Your connection request has been mutually accepted!', 'chat.php?contact=' . $userId);
            createNotification($userId, 'connection', 'Mutual Match!', 'You have a mutual match! Start chatting now.', 'chat.php?contact=' . $profileId);
            
            echo json_encode(['success' => true, 'message' => 'Mutual match! You can now chat.']);
            exit;
        }
        
        $stmt = $pdo->prepare(
            "INSERT INTO connection_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')"
        );
        $stmt->execute([$userId, $profileId]);
        
        // Get sender name
        $sender = getCurrentUser();
        createNotification($profileId, 'interest', 'New Interest Received', 'Please accept to connect with ' . sanitize($sender['name']), 'dashboard.php');
        
        echo json_encode(['success' => true, 'message' => 'Interest sent successfully']);
        break;

    case 'accept':
        $requestId = intval($_POST['request_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT * FROM connection_requests WHERE id = ? AND receiver_id = ? AND status = 'pending'");
        $stmt->execute([$requestId, $userId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE connection_requests SET status = 'accepted', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$requestId]);
        
        createNotification($request['sender_id'], 'connection', 'Interest Accepted!', 'Your interest has been accepted. Start chatting now!', 'chat.php?contact=' . $userId);
        
        echo json_encode(['success' => true, 'message' => 'Request accepted']);
        break;

    case 'decline':
        $requestId = intval($_POST['request_id'] ?? 0);
        
        $stmt = $pdo->prepare("UPDATE connection_requests SET status = 'declined', updated_at = NOW() WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$requestId, $userId]);
        
        echo json_encode(['success' => true, 'message' => 'Request declined']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
