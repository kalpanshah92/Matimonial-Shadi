<?php
/**
 * Account Renewal API
 * 
 * Handles renewal payment initiation and verification.
 * Similar to payment.php but specifically for account renewal.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/AccountEntitlement.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// F-06 CSRF + F-07 rate limit
requireCSRF();
if (!rateLimit('renew:' . $_SESSION['user_id'], 10, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

$pdo = getDBConnection();

if ($action === 'initiate') {
    $planId = intval($_POST['plan_id'] ?? 0);
    
    if (!$planId) {
        echo json_encode(['success' => false, 'message' => 'Invalid plan']);
        exit;
    }
    
    // Verify plan exists and is active
    $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ? AND is_active = 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        echo json_encode(['success' => false, 'message' => 'Plan not found']);
        exit;
    }
    
    // Get user's gender for plan validation
    $stmt = $pdo->prepare("SELECT gender FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userGender = strtolower($stmt->fetchColumn() ?? '');
    
    // Validate plan matches user's gender
    $planNameLower = strtolower($plan['name']);
    $isFemalePlan = strpos($planNameLower, 'female') !== false;
    $isMalePlan = !$isFemalePlan && strpos($planNameLower, 'male') !== false;
    
    if ($userGender === 'female' && !$isFemalePlan) {
        echo json_encode(['success' => false, 'message' => 'Invalid plan for your profile']);
        exit;
    }
    if ($userGender === 'male' && !$isMalePlan) {
        echo json_encode(['success' => false, 'message' => 'Invalid plan for your profile']);
        exit;
    }
    
    // Create renewal record
    try {
        $pdo->beginTransaction();
        
        $original = (float)$plan['price'];
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+' . (int)$plan['duration_days'] . ' days'));
        
        // Create subscription record (for renewal)
        $stmt = $pdo->prepare(
            "INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, payment_method, amount, status)
             VALUES (?, ?, ?, ?, 'razorpay', ?, 'pending')"
        );
        $stmt->execute([$userId, $planId, $startDate, $endDate, $plan['price']]);
        $subscriptionId = (int)$pdo->lastInsertId();
        
        // Check if Razorpay is configured
        if (!isRazorpayConfigured()) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Payment gateway not configured']);
            exit;
        }
        
        // Create Razorpay order
        $amountPaise = (int)round($original * 100);
        $receipt = 'renew_' . $subscriptionId . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERPWD => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'amount' => $amountPaise,
                'currency' => 'INR',
                'receipt' => $receipt,
                'notes' => [
                    'subscription_id' => $subscriptionId,
                    'user_id' => $userId,
                    'plan_id' => $planId,
                    'type' => 'account_renewal'
                ]
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        
        if ($status !== 200) {
            $pdo->rollBack();
            error_log('renew rzp order failed: ' . $status . ' ' . $body . ' ' . $cerr);
            $pdo->prepare("UPDATE subscriptions SET status = 'failed' WHERE id = ?")
                ->execute([$subscriptionId]);
            echo json_encode(['success' => false, 'message' => 'Payment gateway unavailable']);
            exit;
        }
        
        $order = json_decode($body, true);
        if (empty($order['id'])) {
            $pdo->rollBack();
            error_log('renew rzp order malformed: ' . $body);
            echo json_encode(['success' => false, 'message' => 'Unexpected gateway response']);
            exit;
        }
        
        // Update subscription with order ID
        $pdo->prepare("UPDATE subscriptions SET razorpay_order_id = ? WHERE id = ?")
            ->execute([$order['id'], $subscriptionId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'renewal_id' => $subscriptionId,
            'razorpay_order_id' => $order['id'],
            'amount_paise' => $amountPaise,
            'currency' => 'INR',
            'plan_name' => $plan['name']
        ]);
        exit;
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('renew initiate error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not initiate renewal']);
        exit;
    }
}

if ($action === 'verify') {
    $subscriptionId = intval($_POST['renewal_id'] ?? 0);
    $orderId = trim($_POST['razorpay_order_id'] ?? '');
    $paymentId = trim($_POST['razorpay_payment_id'] ?? '');
    $signature = trim($_POST['razorpay_signature'] ?? '');
    
    // Validate inputs
    if (!$subscriptionId
        || !preg_match('/^order_[A-Za-z0-9]{8,40}$/', $orderId)
        || !preg_match('/^pay_[A-Za-z0-9]{8,40}$/', $paymentId)
        || !preg_match('/^[A-Fa-f0-9]{64}$/', $signature)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid payment data']);
        exit;
    }
    
    if (!isRazorpayConfigured()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured']);
        exit;
    }
    
    // Verify HMAC signature
    $expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    if (!hash_equals($expectedSig, $signature)) {
        error_log("renew verify: bad signature for user $userId, payment $paymentId");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Signature verification failed']);
        exit;
    }
    
    // Load subscription record
    $stmt = $pdo->prepare(
        "SELECT * FROM subscriptions WHERE id = ? AND user_id = ? AND razorpay_order_id = ? LIMIT 1"
    );
    $stmt->execute([$subscriptionId, $userId, $orderId]);
    $subscription = $stmt->fetch();
    
    if (!$subscription) {
        echo json_encode(['success' => false, 'message' => 'Renewal record not found']);
        exit;
    }
    
    if ($subscription['status'] === 'active') {
        // Already verified
        echo json_encode(['success' => true, 'message' => 'Already verified']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update subscription to active
        $stmt = $pdo->prepare(
            "UPDATE subscriptions 
             SET status = 'active', payment_id = ?, razorpay_signature = ?, completed_at = NOW() 
             WHERE id = ?"
        );
        $stmt->execute([$paymentId, $signature, $subscriptionId]);
        
        // Update user's expiry date and account status using AccountEntitlement
        $entitlement = AccountEntitlement::forUser($userId);
        $daysToAdd = (int)$subscription['duration_days'] ?? 730; // Default 2 years
        
        // Extend expiry using the entitlement system
        $success = $entitlement->extendExpiry($daysToAdd);
        
        if (!$success) {
            throw new Exception('Failed to update account expiry');
        }
        
        // Update user to premium
        $pdo->prepare("UPDATE users SET is_premium = 1 WHERE id = ?")
            ->execute([$userId]);
        
        // Create notification
        createNotification(
            $userId, 
            'renewal', 
            'Account Renewed', 
            'Your account has been successfully renewed until ' . $entitlement->getExpiryDate()
        );
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Renewal successful']);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('renew verify error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not finalize renewal']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action']);
