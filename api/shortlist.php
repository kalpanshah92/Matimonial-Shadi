<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

requireCSRF(); // F-06
if (!rateLimit('shortlist:' . $_SESSION['user_id'], 120, 3600)) { // F-07
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many actions.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$profileId = intval($_POST['profile_id'] ?? 0);

if (!$profileId || $profileId === $userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid profile']);
    exit;
}

$pdo = getDBConnection();

// Check if user is connected to the profile
$stmt = $pdo->prepare("
    SELECT id FROM connection_requests
    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    AND status = 'accepted'
");
$stmt->execute([$userId, $profileId, $profileId, $userId]);
$connection = $stmt->fetch();

if (!$connection) {
    echo json_encode(['success' => false, 'message' => 'You can only shortlist profiles you are connected to']);
    exit;
}

// Check if already shortlisted
$stmt = $pdo->prepare("SELECT id FROM shortlisted WHERE user_id = ? AND shortlisted_id = ?");
$stmt->execute([$userId, $profileId]);
$existing = $stmt->fetch();

if ($existing) {
    // Remove from shortlist
    $stmt = $pdo->prepare("DELETE FROM shortlisted WHERE id = ?");
    $stmt->execute([$existing['id']]);
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    // Add to shortlist
    $stmt = $pdo->prepare("INSERT INTO shortlisted (user_id, shortlisted_id) VALUES (?, ?)");
    $stmt->execute([$userId, $profileId]);
    echo json_encode(['success' => true, 'action' => 'added']);
}
