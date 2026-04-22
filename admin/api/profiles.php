<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = intval($_POST['user_id'] ?? 0);

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

$pdo = getDBConnection();

switch ($action) {
    case 'approve':
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Profile approved']);
        break;

    case 'reject':
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Profile rejected']);
        break;

    case 'suspend':
        $stmt = $pdo->prepare("UPDATE users SET status = 'suspended', is_active = 0 WHERE id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'User suspended']);
        break;

    case 'verify':
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Profile verified']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
