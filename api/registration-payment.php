<?php
/**
 * Registration payment API.
 *
 *   POST action=initiate  → server-side validates plan + coupon, then either
 *                            (a) bypasses Razorpay for a 100% coupon, or
 *                            (b) creates a Razorpay order and returns it.
 *   POST action=verify    → server validates the HMAC signature returned by
 *                            Razorpay's client SDK and finalises the payment.
 *
 * Auth: same as apply-coupon.php (session token only, user not logged in).
 *
 * Security:
 *  - All money math happens here. Client-supplied amounts are ignored.
 *  - 100% coupon path never touches Razorpay.
 *  - Razorpay path verifies HMAC-SHA256(order_id|payment_id, secret) with
 *    hash_equals (mirrors F-01).
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

// Rate-limit both initiate + verify together
if (!rateLimit('regpay:' . $pendingUserId, 20, 600)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait.']);
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id, name, email, gender, registration_payment_status FROM users WHERE id = ?");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}
if (in_array($user['registration_payment_status'], ['completed','bypassed'], true)) {
    echo json_encode(['success' => true, 'bypass' => true, 'message' => 'Already paid.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'initiate') {
    $plan = getRegistrationPlanForGender($user['gender']);
    if (!$plan) {
        echo json_encode(['success' => false, 'message' => 'No plan configured.']);
        exit;
    }

    $original = (float)$plan['price'];
    $discount = 0.00;
    $final    = $original;
    $couponId = null;
    $couponCode = null;

    $code = trim($_POST['coupon'] ?? '');
    if ($code !== '') {
        $v = validateCoupon($code, $user['gender'], $original);
        if (!$v['ok']) {
            echo json_encode(['success' => false, 'message' => $v['message']]);
            exit;
        }
        $discount   = (float)$v['discount_amount'];
        $final      = (float)$v['final_amount'];
        $couponId   = (int)$v['coupon']['id'];
        $couponCode = $v['coupon']['code'];
    }

    // 100% off → record + flip user without touching Razorpay
    if ($final <= 0.00) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                "INSERT INTO registration_payments
                    (user_id, plan_id, original_amount, discount_amount, final_amount,
                     coupon_id, coupon_code, payment_method, status, completed_at)
                 VALUES (?, ?, ?, ?, 0.00, ?, ?, 'coupon_bypass', 'completed', NOW())"
            )->execute([$pendingUserId, $plan['id'], $original, $discount, $couponId, $couponCode]);

            if ($couponId) {
                $pdo->prepare("UPDATE coupons SET redemptions_count = redemptions_count + 1 WHERE id = ?")->execute([$couponId]);
            }

            // Set account expiry to 2 years from today, and activate the account
            $expiryDate = date('Y-m-d', strtotime('+2 years'));
            $pdo->prepare(
                "UPDATE users SET registration_payment_status = 'bypassed', status = 'pending', account_status = 'active', expiry_date = ? WHERE id = ?"
            )->execute([$expiryDate, $pendingUserId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('regpay bypass error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not record free registration. Please retry.']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'bypass'  => true,
            'message' => 'Free registration recorded.',
        ]);
        exit;
    }

    // Paid path — Razorpay must be configured
    if (!isRazorpayConfigured()) {
        echo json_encode(['success' => false, 'message' => 'Razorpay is not configured on this environment. Apply a 100% coupon or contact support.']);
        exit;
    }

    // Create a pending registration_payments row first so we have a stable id
    try {
        $pdo->prepare(
            "INSERT INTO registration_payments
                (user_id, plan_id, original_amount, discount_amount, final_amount,
                 coupon_id, coupon_code, payment_method, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'razorpay', 'pending')"
        )->execute([$pendingUserId, $plan['id'], $original, $discount, $final, $couponId, $couponCode]);
        $registrationPaymentId = (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('regpay insert: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not initiate payment.']);
        exit;
    }

    // Create order with Razorpay
    // Docs: https://razorpay.com/docs/api/orders/
    $amountPaise = (int)round($final * 100);
    $receipt     = 'reg_' . $registrationPaymentId . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode([
            'amount'   => $amountPaise,
            'currency' => 'INR',
            'receipt'  => $receipt,
            'notes'    => [
                'registration_payment_id' => $registrationPaymentId,
                'user_id'                 => $pendingUserId,
                'plan_id'                 => (int)$plan['id'],
            ],
        ]),
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr   = curl_error($ch);
    curl_close($ch);

    if ($status !== 200) {
        error_log('regpay rzp order failed: ' . $status . ' ' . $body . ' ' . $cerr);
        $pdo->prepare("UPDATE registration_payments SET status = 'failed' WHERE id = ?")->execute([$registrationPaymentId]);
        echo json_encode(['success' => false, 'message' => 'Payment gateway unavailable. Please try again.']);
        exit;
    }
    $order = json_decode($body, true);
    if (empty($order['id'])) {
        error_log('regpay rzp order malformed: ' . $body);
        echo json_encode(['success' => false, 'message' => 'Unexpected gateway response.']);
        exit;
    }

    $pdo->prepare("UPDATE registration_payments SET razorpay_order_id = ? WHERE id = ?")
        ->execute([$order['id'], $registrationPaymentId]);

    echo json_encode([
        'success'                  => true,
        'bypass'                   => false,
        'registration_payment_id'  => $registrationPaymentId,
        'razorpay_order_id'        => $order['id'],
        'amount_paise'             => $amountPaise,
        'currency'                 => 'INR',
        'plan_name'                => $plan['name'],
    ]);
    exit;
}

if ($action === 'verify') {
    $registrationPaymentId = (int)($_POST['registration_payment_id'] ?? 0);
    $orderId   = trim($_POST['razorpay_order_id']   ?? '');
    $paymentId = trim($_POST['razorpay_payment_id'] ?? '');
    $signature = trim($_POST['razorpay_signature']  ?? '');

    if (!$registrationPaymentId
        || !preg_match('/^order_[A-Za-z0-9]{8,40}$/', $orderId)
        || !preg_match('/^pay_[A-Za-z0-9]{8,40}$/',   $paymentId)
        || !preg_match('/^[A-Fa-f0-9]{64}$/',         $signature)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid payment data.']);
        exit;
    }
    if (!isRazorpayConfigured()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Payment gateway not configured.']);
        exit;
    }

    $expectedSig = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    if (!hash_equals($expectedSig, $signature)) {
        error_log("regpay verify: bad signature for user $pendingUserId, payment $paymentId");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Signature verification failed.']);
        exit;
    }

    // Load + reconcile the pending registration_payments row
    $stmt = $pdo->prepare(
        "SELECT * FROM registration_payments
         WHERE id = ? AND user_id = ? AND razorpay_order_id = ? LIMIT 1"
    );
    $stmt->execute([$registrationPaymentId, $pendingUserId, $orderId]);
    $rp = $stmt->fetch();
    if (!$rp) {
        echo json_encode(['success' => false, 'message' => 'Payment record not found.']);
        exit;
    }
    if ($rp['status'] === 'completed') {
        // Idempotency: already verified once
        echo json_encode(['success' => true, 'message' => 'Already verified.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            "UPDATE registration_payments
                SET status = 'completed',
                    razorpay_payment_id = ?,
                    razorpay_signature  = ?,
                    completed_at        = NOW()
              WHERE id = ?"
        )->execute([$paymentId, $signature, $registrationPaymentId]);

        if (!empty($rp['coupon_id'])) {
            $pdo->prepare("UPDATE coupons SET redemptions_count = redemptions_count + 1 WHERE id = ?")
                ->execute([(int)$rp['coupon_id']]);
        }

        // Set account expiry to 2 years from today, and activate the account
        $expiryDate = date('Y-m-d', strtotime('+2 years'));
        $pdo->prepare(
            "UPDATE users SET registration_payment_status = 'completed', status = 'pending', account_status = 'active', expiry_date = ? WHERE id = ?"
        )->execute([$expiryDate, $pendingUserId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('regpay verify commit: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Could not finalise payment. Contact support.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Payment verified.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
