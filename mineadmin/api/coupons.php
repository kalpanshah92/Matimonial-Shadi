<?php
/**
 * Coupon CRUD for super admins. Endpoints:
 *   POST action=create  → fields: code, discount_percent, gender_restriction, max_redemptions, valid_from, valid_until, notes
 *   POST action=toggle  → fields: id, is_active (0|1)
 *   POST action=delete  → fields: id
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || ($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$pdo = getDBConnection();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $pct  = (int)($_POST['discount_percent'] ?? 0);
            $gen  = $_POST['gender_restriction'] ?? 'any';
            $max  = trim($_POST['max_redemptions'] ?? '');
            $from = trim($_POST['valid_from'] ?? '');
            $till = trim($_POST['valid_until'] ?? '');
            $notes= trim($_POST['notes'] ?? '');

            if (!preg_match('/^[A-Z0-9_\-]{3,40}$/', $code)) {
                echo json_encode(['success' => false, 'message' => 'Code must be 3-40 chars, letters/numbers/dash/underscore.']); exit;
            }
            if ($pct < 1 || $pct > 100) {
                echo json_encode(['success' => false, 'message' => 'Discount must be between 1 and 100.']); exit;
            }
            if (!in_array($gen, ['any','Male','Female'], true)) $gen = 'any';
            $maxR = ($max === '' ? null : max(1, (int)$max));
            $vFrom= ($from === '' ? null : $from);
            $vTill= ($till === '' ? null : $till);

            $stmt = $pdo->prepare(
                "INSERT INTO coupons (code, discount_percent, gender_restriction, max_redemptions,
                                       valid_from, valid_until, is_active, created_by_admin, notes)
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)"
            );
            $stmt->execute([$code, $pct, $gen, $maxR, $vFrom, $vTill, (int)$_SESSION['admin_id'], $notes !== '' ? $notes : null]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
            break;

        case 'toggle':
            $id = (int)($_POST['id'] ?? 0);
            $active = (int)($_POST['is_active'] ?? 0) ? 1 : 0;
            $pdo->prepare("UPDATE coupons SET is_active = ? WHERE id = ?")->execute([$active, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            // Redemption history (registration_payments) keeps coupon_id NULL via FK ON DELETE SET NULL.
            $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    if (stripos($e->getMessage(), 'Duplicate') !== false) {
        echo json_encode(['success' => false, 'message' => 'A coupon with that code already exists.']);
    } else {
        error_log('coupons admin api: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()]);
    }
}
