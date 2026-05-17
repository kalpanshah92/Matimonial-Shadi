<?php
/**
 * Server-side coupon preview.
 *
 * Returns the validated discount + final amount based on the pending
 * registration user's gender-priced plan. Never trust client-side prices.
 *
 * Auth: caller must hold a `registration_payment_user_id` session token
 *       (set by verify-otp.php). The user is not logged in yet.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

requireCSRF();

$pendingUserId = (int)($_SESSION['registration_payment_user_id'] ?? 0);
$expiresAt     = (int)($_SESSION['registration_payment_expires'] ?? 0);
if (!$pendingUserId || time() > $expiresAt) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session expired. Please register again.']);
    exit;
}

// Throttle: a single user shouldn't be able to spray thousands of codes.
if (!rateLimit('coupon:try:' . $pendingUserId, 30, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait a few minutes.']);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id, gender FROM users WHERE id = ?");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

$plan = getRegistrationPlanForGender($user['gender']);
if (!$plan) {
    echo json_encode(['success' => false, 'message' => 'No plan configured.']);
    exit;
}

$result = validateCoupon($_POST['code'] ?? '', $user['gender'], $plan['price']);
if (!$result['ok']) {
    echo json_encode(['success' => false, 'message' => $result['message']]);
    exit;
}

echo json_encode([
    'success'          => true,
    'discount_percent' => (int)$result['coupon']['discount_percent'],
    'discount_amount'  => (float)$result['discount_amount'],
    'final_amount'     => (float)$result['final_amount'],
    'original_amount'  => (float)$plan['price'],
]);
