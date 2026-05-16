<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// F-06 CSRF + F-07 rate limit
requireCSRF();
if (!rateLimit('payment:' . $_SESSION['user_id'], 10, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait.']);
    exit;
}

$userId           = (int)$_SESSION['user_id'];
$planId           = intval($_POST['plan_id'] ?? 0);
$razorpayOrderId  = trim($_POST['razorpay_order_id']   ?? '');
$razorpayPaymentId= trim($_POST['razorpay_payment_id'] ?? '');
$razorpaySignature= trim($_POST['razorpay_signature']  ?? '');

// Basic input shape checks
if (!$planId
    || !preg_match('/^order_[A-Za-z0-9]{8,40}$/', $razorpayOrderId)
    || !preg_match('/^pay_[A-Za-z0-9]{8,40}$/',   $razorpayPaymentId)
    || !preg_match('/^[A-Fa-f0-9]{64}$/',         $razorpaySignature)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid payment data']);
    exit;
}

// F-01 Verify HMAC signature against server-side secret
if (!defined('RAZORPAY_KEY_SECRET') || RAZORPAY_KEY_SECRET === '' || RAZORPAY_KEY_SECRET === 'your_razorpay_key_secret') {
    http_response_code(500);
    error_log('payment: RAZORPAY_KEY_SECRET not configured');
    echo json_encode(['success' => false, 'message' => 'Payment gateway not configured']);
    exit;
}
$expectedSig = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, RAZORPAY_KEY_SECRET);
if (!hash_equals($expectedSig, $razorpaySignature)) {
    recordLoginAttempt('payment:' . $userId, false, 'user'); // reuse table to flag abuse
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Signature verification failed']);
    exit;
}

$pdo = getDBConnection();

// Verify plan exists and is active
$stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
$stmt->execute([$planId]);
$plan = $stmt->fetch();
if (!$plan) {
    echo json_encode(['success' => false, 'message' => 'Plan not found']);
    exit;
}

// F-01 Idempotency: reject duplicate payment_id
$stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE payment_id = ? LIMIT 1");
$stmt->execute([$razorpayPaymentId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Payment already processed']);
    exit;
}

try {
    $startDate = date('Y-m-d');
    $endDate   = date('Y-m-d', strtotime('+' . (int)$plan['duration_days'] . ' days'));

    $stmt = $pdo->prepare(
        "INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, payment_id, payment_method, amount, status)
         VALUES (?, ?, ?, ?, ?, 'razorpay', ?, 'active')"
    );
    $stmt->execute([$userId, $planId, $startDate, $endDate, $razorpayPaymentId, $plan['price']]);

    $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?")->execute([$userId]);

    createNotification($userId, 'subscription', 'Plan Activated', 'Your ' . $plan['name'] . ' plan has been activated until ' . $endDate);

    echo json_encode(['success' => true, 'message' => 'Subscription activated']);
} catch (Exception $e) {
    error_log("Payment Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to process payment']);
}
