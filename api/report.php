<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    setFlash('error', 'Please login to report a profile.');
    redirect(SITE_URL . '/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL);
}

$userId = $_SESSION['user_id'];
$reportedId = intval($_POST['reported_id'] ?? 0);
$reason = sanitize($_POST['reason'] ?? '');
$description = sanitize($_POST['description'] ?? '');

if (!$reportedId || empty($reason)) {
    setFlash('error', 'Please provide a valid reason.');
    redirect(SITE_URL . '/profile.php?id=' . $reportedId);
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, reported_id, reason, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $reportedId, $reason, $description]);
    
    setFlash('success', 'Report submitted successfully. Our team will review it.');
} catch (Exception $e) {
    setFlash('error', 'Failed to submit report. Please try again.');
}

redirect(SITE_URL . '/profile.php?id=' . $reportedId);
