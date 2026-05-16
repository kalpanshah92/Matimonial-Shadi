<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    setFlash('error', 'Please login to report a profile.');
    redirect(SITE_URL . '/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(SITE_URL);
}

// F-06 CSRF
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid form submission.');
    redirect(SITE_URL . '/dashboard.php');
}

$userId      = (int)$_SESSION['user_id'];
$reportedId  = intval($_POST['reported_id'] ?? 0);
$reason      = sanitize($_POST['reason'] ?? '');
$description = sanitize(substr($_POST['description'] ?? '', 0, 1000)); // F-18 length cap

if (!$reportedId || $reportedId === $userId || empty($reason)) {
    setFlash('error', 'Please provide a valid reason.');
    redirect(SITE_URL . '/dashboard.php');
}

// F-07 Rate limit + F-18 dedupe: max 5 reports/day, max 1 against same target/24h
if (!rateLimit('report:' . $userId, 5, 86400)) {
    setFlash('error', 'You have reached the daily report limit.');
    redirect(SITE_URL . '/dashboard.php');
}

$pdo = getDBConnection();

try {
    $stmt = $pdo->prepare(
        "SELECT id FROM reports WHERE reporter_id = ? AND reported_id = ?
         AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1"
    );
    $stmt->execute([$userId, $reportedId]);
    if ($stmt->fetch()) {
        setFlash('error', 'You have already reported this profile recently.');
        redirect(SITE_URL . '/dashboard.php');
    }

    $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, reported_id, reason, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $reportedId, $reason, $description]);

    setFlash('success', 'Report submitted successfully. Our team will review it.');
} catch (Exception $e) {
    error_log('Report error: ' . $e->getMessage());
    setFlash('error', 'Failed to submit report. Please try again.');
}

// Redirect using encoded id (consistent with rest of site)
redirect(SITE_URL . '/profile.php?id=' . encodeProfileId($reportedId));
