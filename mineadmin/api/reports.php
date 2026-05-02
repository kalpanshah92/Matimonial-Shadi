<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$reportId = intval($_POST['report_id'] ?? 0);
$status = $_POST['status'] ?? '';

if ($reportId && in_array($status, ['reviewed', 'resolved', 'dismissed'])) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE reports SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $reportId]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
