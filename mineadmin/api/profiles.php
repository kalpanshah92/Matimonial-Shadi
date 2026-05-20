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

    case 'update_end_date':
        // Only super admin can update end date
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Only super admin can update end date']);
            break;
        }

        $endDate = $_POST['end_date'] ?? '';

        if (empty($endDate)) {
            echo json_encode(['success' => false, 'message' => 'Invalid end date']);
            break;
        }

        try {
            // Update the most recent subscription end date
            $stmt = $pdo->prepare(
                "UPDATE subscriptions SET end_date = ? 
                 WHERE user_id = ? 
                 ORDER BY end_date DESC LIMIT 1"
            );
            $stmt->execute([$endDate, $userId]);

            if ($stmt->rowCount() === 0) {
                // No subscription record exists, create one
                $startDate = date('Y-m-d');
                $stmt = $pdo->prepare(
                    "INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, payment_method, amount, status)
                     VALUES (?, 1, ?, ?, 'admin_update', 0, 'active')"
                );
                $stmt->execute([$userId, $startDate, $endDate]);
            }

            echo json_encode(['success' => true, 'message' => 'End date updated successfully']);
        } catch (Exception $e) {
            error_log("End date update error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update end date']);
        }
        break;

    case 'delete_profile':
        // Permanently delete the user and all linked data.
        // Restricted to super_admin because the operation is irreversible.
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Only super admin can delete profiles']);
            break;
        }

        try {
            // Fetch user first so we know which files to remove on disk
            $stmt = $pdo->prepare("SELECT id, email, phone, profile_pic, id_document, address_proof_document FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User not found']);
                break;
            }

            // Collect photo paths before deleting rows
            $stmt = $pdo->prepare("SELECT photo_path FROM photos WHERE user_id = ?");
            $stmt->execute([$userId]);
            $photoPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->beginTransaction();

            // Wipe every table that references this user. PDO is configured with
            // EMULATE_PREPARES=false, so we use positional `?` placeholders and
            // pass the id once per occurrence (named placeholders can't repeat
            // under native prepares).
            $deletesTwoCols = [
                "DELETE FROM messages            WHERE sender_id   = ? OR receiver_id    = ?",
                "DELETE FROM connection_requests WHERE sender_id   = ? OR receiver_id    = ?",
                "DELETE FROM shortlisted         WHERE user_id     = ? OR shortlisted_id = ?",
                "DELETE FROM profile_visits      WHERE visitor_id  = ? OR visited_id     = ?",
                "DELETE FROM reports             WHERE reporter_id = ? OR reported_id    = ?",
            ];
            foreach ($deletesTwoCols as $sql) {
                $pdo->prepare($sql)->execute([$userId, $userId]);
            }

            $deletesOneCol = [
                "DELETE FROM notifications         WHERE user_id = ?",
                "DELETE FROM profile_change_requests WHERE user_id = ?",
                "DELETE FROM subscriptions         WHERE user_id = ?",
                "DELETE FROM success_stories       WHERE user_id = ?",
                "DELETE FROM privacy_settings      WHERE user_id = ?",
                "DELETE FROM partner_preferences   WHERE user_id = ?",
                "DELETE FROM family_details        WHERE user_id = ?",
                "DELETE FROM profile_details       WHERE user_id = ?",
                "DELETE FROM photos                WHERE user_id = ?",
            ];
            foreach ($deletesOneCol as $sql) {
                $pdo->prepare($sql)->execute([$userId]);
            }

            // Optional tables (may not exist on older deployments — must NOT abort the txn)
            $optional = [
                "DELETE FROM deactivation_requests WHERE user_id = ?",
                "DELETE FROM remember_tokens       WHERE user_id = ?",
            ];
            foreach ($optional as $sql) {
                try { $pdo->prepare($sql)->execute([$userId]); }
                catch (Throwable $e) { error_log('delete_profile optional: ' . $e->getMessage()); }
            }

            // Clean OTPs + login attempts tied to this email/phone
            if (!empty($user['email'])) {
                try { $pdo->prepare("DELETE FROM otp_verifications WHERE identifier = ?")->execute([$user['email']]); } catch (Throwable $e) {}
                try { $pdo->prepare("DELETE FROM login_attempts    WHERE identifier = ? AND scope = 'user'")->execute([$user['email']]); } catch (Throwable $e) {}
            }
            if (!empty($user['phone'])) {
                try { $pdo->prepare("DELETE FROM otp_verifications WHERE identifier = ?")->execute([$user['phone']]); } catch (Throwable $e) {}
            }

            // Finally, the user row itself
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

            $pdo->commit();

            // Best-effort filesystem cleanup AFTER commit — restricted to the user's
            // own folder to prevent any path-traversal mistakes.
            $rootUploads = realpath(__DIR__ . '/../../uploads');
            $userPhotoDir = $rootUploads ? $rootUploads . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR . (int)$userId : null;

            $deleteIfInside = function ($absPath) use ($rootUploads) {
                if (!$rootUploads || !$absPath) return;
                $real = realpath($absPath);
                if ($real && strpos($real, $rootUploads) === 0 && is_file($real)) {
                    @unlink($real);
                }
            };

            // Photo files from photos table
            foreach ($photoPaths as $rel) {
                if (!is_string($rel) || $rel === '') continue;
                $deleteIfInside(__DIR__ . '/../../' . $rel);
            }
            // Profile picture (legacy users.profile_pic — may be a path or basename)
            if (!empty($user['profile_pic'])) {
                $deleteIfInside(__DIR__ . '/../../' . $user['profile_pic']);
                $deleteIfInside(__DIR__ . '/../../uploads/profiles/' . basename($user['profile_pic']));
            }
            // ID doc & address proof
            foreach (['id_document', 'address_proof_document'] as $col) {
                if (!empty($user[$col])) {
                    $deleteIfInside(__DIR__ . '/../../' . $user[$col]);
                }
            }
            // Empty per-user photo dir if it exists
            if ($userPhotoDir && is_dir($userPhotoDir)) {
                foreach ((array)@scandir($userPhotoDir) as $f) {
                    if ($f === '.' || $f === '..') continue;
                    $deleteIfInside($userPhotoDir . DIRECTORY_SEPARATOR . $f);
                }
                @rmdir($userPhotoDir);
            }

            error_log("admin {$_SESSION['admin_id']} deleted user $userId ({$user['email']})");
            echo json_encode(['success' => true, 'message' => 'Profile and all linked data deleted']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("delete_profile error: " . $e->getMessage());
            // Super-admin sees the underlying error to ease debugging in production.
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete profile: ' . $e->getMessage(),
            ]);
        }
        break;

    case 'update_account_expiry':
        // Only super admin can update account expiry
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Only super admin can update account expiry']);
            break;
        }

        $expiryDate = $_POST['expiry_date'] ?? '';
        $adminNote = $_POST['admin_note'] ?? '';

        if (empty($expiryDate)) {
            echo json_encode(['success' => false, 'message' => 'Invalid expiry date']);
            break;
        }

        try {
            require_once __DIR__ . '/../../includes/AccountEntitlement.php';

            $entitlement = AccountEntitlement::forUser($userId);
            $oldExpiry = $entitlement->getExpiryDate();

            // Set the new expiry date with admin audit logging
            $success = $entitlement->setExpiryDate($expiryDate, (int)$_SESSION['admin_id']);

            if (!$success) {
                throw new Exception('Failed to update account expiry');
            }

            // Log additional admin note if provided
            if (!empty($adminNote)) {
                $pdo->prepare(
                    "INSERT INTO admin_audit_log (admin_id, user_id, action, old_value, new_value, details)
                     VALUES (?, ?, 'update_expiry_note', ?, ?, ?)"
                )->execute([
                    (int)$_SESSION['admin_id'],
                    $userId,
                    $oldExpiry,
                    $expiryDate,
                    json_encode(['admin_note' => $adminNote, 'source' => 'manual_update'])
                ]);
            }

            // Create notification for user
            createNotification(
                $userId,
                'account',
                'Account Expiry Updated',
                'Your account expiry date has been updated by admin. New expiry: ' . date('d M Y', strtotime($expiryDate))
            );

            echo json_encode(['success' => true, 'message' => 'Account expiry updated successfully']);
        } catch (Exception $e) {
            error_log("Account expiry update error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update account expiry']);
        }
        break;

    case 'extend_account':
        // Only super admin can extend account
        if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
            echo json_encode(['success' => false, 'message' => 'Only super admin can extend accounts']);
            break;
        }

        $days = intval($_POST['days'] ?? 0);
        $adminNote = $_POST['admin_note'] ?? '';

        if ($days <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid extension duration']);
            break;
        }

        try {
            require_once __DIR__ . '/../../includes/AccountEntitlement.php';

            $entitlement = AccountEntitlement::forUser($userId);

            // Extend the account with admin audit logging
            $success = $entitlement->extendExpiry($days, (int)$_SESSION['admin_id']);

            if (!$success) {
                throw new Exception('Failed to extend account');
            }

            $newExpiry = $entitlement->getExpiryDate();
            $newExpiryFormatted = $entitlement->getFormattedExpiryDate();

            // Log additional admin note if provided
            if (!empty($adminNote)) {
                $pdo->prepare(
                    "UPDATE admin_audit_log SET details = JSON_SET(details, '$.admin_note', ?)
                     WHERE admin_id = ? AND user_id = ? AND action = 'extend_expiry'
                     ORDER BY created_at DESC LIMIT 1"
                )->execute([$adminNote, (int)$_SESSION['admin_id'], $userId]);
            }

            // Create notification for user
            createNotification(
                $userId,
                'account',
                'Account Extended',
                'Your account has been extended by ' . $days . ' days. New expiry: ' . $newExpiryFormatted
            );

            echo json_encode([
                'success' => true,
                'message' => 'Account extended successfully',
                'new_expiry' => $newExpiryFormatted,
                'expiry_date' => $newExpiry
            ]);
        } catch (Exception $e) {
            error_log("Account extend error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to extend account']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
