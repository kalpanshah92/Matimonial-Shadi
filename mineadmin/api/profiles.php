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

    case 'upgrade_premium':
        // Only super admin can upgrade to premium
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Only super admin can upgrade users to premium']);
            break;
        }

        $planId = intval($_POST['plan_id'] ?? 0);
        $endDate = $_POST['end_date'] ?? '';

        if (!$planId || empty($endDate)) {
            echo json_encode(['success' => false, 'message' => 'Invalid plan or end date']);
            break;
        }

        try {
            $pdo->beginTransaction();

            // Get plan details
            $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
            $stmt->execute([$planId]);
            $plan = $stmt->fetch();

            if (!$plan) {
                throw new Exception('Invalid plan');
            }

            // Create subscription record
            $startDate = date('Y-m-d');
            $stmt = $pdo->prepare(
                "INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, payment_method, amount, status)
                 VALUES (?, ?, ?, ?, 'admin_upgrade', ?, 'active')"
            );
            $stmt->execute([$userId, $planId, $startDate, $endDate, $plan['price']]);

            // Update user premium status
            $stmt = $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
            $stmt->execute([$userId]);

            // Get user details for notification
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            // Notify user
            require_once __DIR__ . '/../../includes/functions.php';
            createNotification($userId, 'premium', 'Premium Upgrade', 'You have been upgraded to Premium membership until ' . $endDate);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'User upgraded to premium successfully']);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Premium upgrade error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to upgrade user to premium']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
