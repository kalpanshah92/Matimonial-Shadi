<?php
/**
 * Address Proof / Documentation handler for Super Admin only.
 * Supports: view (inline), download (attachment), delete.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Only super admin can access documents
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo 'Access denied. Only Super Admin can access documents.';
    exit;
}

$pdo = getDBConnection();

$userId = intval($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!$userId) {
    header('Location: profiles.php');
    exit;
}

// Lookup the document for this user
$stmt = $pdo->prepare("SELECT address_proof_document, name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo 'User not found.';
    exit;
}

$docPath = $row['address_proof_document'] ?? '';

// Handle delete (POST)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        header('Location: view-profile.php?id=' . $userId . '&doc_error=invalid');
        exit;
    }
    if (!empty($docPath)) {
        $full = realpath(__DIR__ . '/../' . $docPath);
        $base = realpath(UPLOADS_PATH . 'address_proof');
        if ($full && $base && strpos($full, $base) === 0 && file_exists($full)) {
            @unlink($full);
        }
        $upd = $pdo->prepare("UPDATE users SET address_proof_document = NULL, address_proof_uploaded_at = NULL WHERE id = ?");
        $upd->execute([$userId]);
    }
    header('Location: view-profile.php?id=' . $userId . '&address_proof_deleted=1');
    exit;
}

// For view/download we need an existing file
if (empty($docPath)) {
    http_response_code(404);
    echo 'No document uploaded for this user.';
    exit;
}

$fullPath = realpath(__DIR__ . '/../' . $docPath);
$baseDir = realpath(UPLOADS_PATH . 'address_proof');

// Path traversal protection: ensure file is inside uploads/address_proof
if (!$fullPath || !$baseDir || strpos($fullPath, $baseDir) !== 0 || !file_exists($fullPath)) {
    http_response_code(404);
    echo 'Document file not found.';
    exit;
}

if ($action === 'view' || $action === 'download') {
    $disposition = ($action === 'download') ? 'attachment' : 'inline';
    $userName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $row['name'] ?? 'document');
    $downloadName = $userName . '_address_proof.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: no-cache');
    readfile($fullPath);
    exit;
}

http_response_code(400);
echo 'Invalid action.';
