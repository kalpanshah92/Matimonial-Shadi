<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$planId = intval($_POST['plan_id'] ?? 0);
$paymentId = sanitize($_POST['payment_id'] ?? '');
$amount = floatval($_POST['amount'] ?? 0);

if (!$planId || empty($paymentId)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment data']);
    exit;
}

$pdo = getDBConnection();

// Verify plan
$stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan || $plan['price'] != $amount) {
    echo json_encode(['success' => false, 'message' => 'Plan verification failed']);
    exit;
}

try {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+' . $plan['duration_days'] . ' days'));
    
    // Create subscription
    $stmt = $pdo->prepare(
        "INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, payment_id, payment_method, amount, status) 
         VALUES (?, ?, ?, ?, ?, 'razorpay', ?, 'active')"
    );
    $stmt->execute([$userId, $planId, $startDate, $endDate, $paymentId, $amount]);
    
    // Update user premium status
    $stmt = $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
    $stmt->execute([$userId]);
    
    createNotification($userId, 'subscription', 'Plan Activated', 'Your ' . $plan['name'] . ' plan has been activated until ' . $endDate);
    
    echo json_encode(['success' => true, 'message' => 'Subscription activated']);
} catch (Exception $e) {
    error_log("Payment Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process payment']);
}
