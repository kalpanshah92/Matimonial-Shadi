<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

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

function sendStatusEmail($pdo, $userId, $status) {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || empty($user['email'])) return;

    $siteName = SITE_NAME;
    $siteUrl = SITE_URL;
    $loginUrl = $siteUrl . '/login.php';

    if ($status === 'approved') {
        $subject = "Account Approved - $siteName";
        $body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
                <h2 style='color:#C0392B;'>Welcome to $siteName!</h2>
                <p>Dear <strong>{$user['name']}</strong>,</p>
                <p>Congratulations! Your account has been <strong style='color:green;'>approved</strong> by our admin team.</p>
                <p>You can now login and start finding your perfect match.</p>
                <p style='text-align:center;margin:30px 0;'>
                    <a href='$loginUrl' style='background:#C0392B;color:#fff;padding:12px 30px;text-decoration:none;border-radius:5px;font-size:16px;'>Login Now</a>
                </p>
                <p>Best wishes,<br>$siteName Team</p>
            </div>";
    } elseif ($status === 'rejected') {
        $subject = "Account Update - $siteName";
        $body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>
                <h2 style='color:#C0392B;'>$siteName</h2>
                <p>Dear <strong>{$user['name']}</strong>,</p>
                <p>We regret to inform you that your registration has been <strong style='color:red;'>not approved</strong> at this time.</p>
                <p>If you believe this is an error, please contact our support team at <a href='mailto:" . SITE_EMAIL . "'>" . SITE_EMAIL . "</a>.</p>
                <p>Regards,<br>$siteName Team</p>
            </div>";
    } else {
        return;
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $siteName <" . SITE_EMAIL . ">\r\n";

    @mail($user['email'], $subject, $body, $headers);
}

switch ($action) {
    case 'approve':
        $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
        $stmt->execute([$userId]);
        sendStatusEmail($pdo, $userId, 'approved');
        echo json_encode(['success' => true, 'message' => 'Profile approved']);
        break;

    case 'reject':
        $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$userId]);
        sendStatusEmail($pdo, $userId, 'rejected');
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
