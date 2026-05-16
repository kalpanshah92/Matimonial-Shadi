<?php
/**
 * F-02 Authenticated photo proxy.
 * Serves files under uploads/photos/<user_id>/ ONLY after enforcing the photo
 * owner's privacy settings (`privacy_settings.show_photo`) via canViewContent().
 *
 * Direct access to /uploads/photos/* must be blocked at the web server
 * (see uploads/photos/.htaccess).
 *
 * Accepts either:
 *   /photo.php?t=<access_token>     (preferred, opaque token)
 *   /photo.php?id=<photos.id>       (legacy, still ACL-checked)
 */
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['t'] ?? '';
$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$path  = $_GET['path'] ?? '';

if (!$token && !$id && !$path) { http_response_code(400); exit('Bad request'); }

$pdo = getDBConnection();
if ($token) {
    $stmt = $pdo->prepare("SELECT * FROM photos WHERE access_token = ? LIMIT 1");
    $stmt->execute([$token]);
} elseif ($id) {
    $stmt = $pdo->prepare("SELECT * FROM photos WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
} else {
    // Legacy: look up by stored relative path (e.g. users.profile_pic)
    if (!preg_match('#^uploads/photos/[0-9]+/[A-Za-z0-9_\-.]+$#', $path)) {
        http_response_code(400); exit('Bad path');
    }
    $stmt = $pdo->prepare("SELECT * FROM photos WHERE photo_path = ? LIMIT 1");
    $stmt->execute([$path]);
}
$photo = $stmt->fetch();
if (!$photo) { http_response_code(404); exit('Not found'); }

$ownerId = (int)$photo['user_id'];

// Owner can always see own photos
$viewerId        = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$isOwner         = ($viewerId === $ownerId);
$isConnected     = false;
$viewerIsPremium = false;
$profileIsPremium= false;
$viewerIsAdmin   = !empty($_SESSION['admin_id']);

if (!$isOwner && $viewerId) {
    $s = $pdo->prepare(
        "SELECT 1 FROM connection_requests
         WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
           AND status = 'accepted' LIMIT 1"
    );
    $s->execute([$viewerId, $ownerId, $ownerId, $viewerId]);
    $isConnected = (bool)$s->fetch();
    $viewerIsPremium = isPremium($viewerId);
}
$s = $pdo->prepare("SELECT is_premium FROM users WHERE id = ?");
$s->execute([$ownerId]);
$profileIsPremium = (bool)($s->fetchColumn());

// Privacy lookup
$s = $pdo->prepare("SELECT show_photo FROM privacy_settings WHERE user_id = ?");
$s->execute([$ownerId]);
$privacy = $s->fetch();
$showPhoto = $privacy['show_photo'] ?? 'everyone';

$allowed = $viewerIsAdmin
    || canViewContent($showPhoto, $isOwner, $isConnected, $viewerIsPremium, $profileIsPremium);

// Unapproved photos: only owner + admin
if (!$photo['is_approved'] && !$isOwner && !$viewerIsAdmin) { $allowed = false; }

if (!$allowed) { http_response_code(403); exit('Forbidden'); }

$file = ROOT_PATH . $photo['photo_path'];
$real = realpath($file);
$base = realpath(UPLOADS_PATH . 'photos');
if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
    http_response_code(404); exit('Not found');
}

// Determine MIME from extension (we control extension at upload time)
$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$mime = $mimes[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($real));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($real);
exit;
