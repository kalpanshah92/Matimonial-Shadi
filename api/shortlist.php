<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$profileId = intval($_POST['profile_id'] ?? 0);

if (!$profileId || $profileId === $userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid profile']);
    exit;
}

$pdo = getDBConnection();

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
