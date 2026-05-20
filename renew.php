<?php
/**
 * Account Renewal Page
 * 
 * For expired or expiring accounts to renew their membership.
 * Shows plan options and handles payment to extend account expiry.
 */
$pageTitle = 'Renew Your Account';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/AccountEntitlement.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Get entitlement info
$entitlement = AccountEntitlement::forUser($userId);
$isExpired = $entitlement->isExpired();
$isInGrace = $entitlement->isInGracePeriod();
$daysRemaining = $entitlement->daysUntilExpiry();

// Fetch active plans (gender-based)
$plans = [];
try {
    $stmt = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC");
    $plans = $stmt->fetchAll();
    
    // Filter plans by user's gender
    if (!empty($currentUser['gender'])) {
        $userGender = strtolower($currentUser['gender']);
        $plans = array_values(array_filter($plans, function ($plan) use ($userGender) {
            $isFemalePlan = stripos($plan['name'], 'Female') !== false;
            $isMalePlan = !$isFemalePlan && stripos($plan['name'], 'Male') !== false;
            if ($userGender === 'female') return $isFemalePlan;
            if ($userGender === 'male') return $isMalePlan;
            return true;
        }));
    }
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Renewal Section -->
<section class="py-5 bg-warm">
    <div class="container">
        <!-- Status Banner -->
        <div class="row justify-content-center mb-4">
            <div class="col-lg-8">
                <?php if ($isExpired && !$isInGrace): ?>
                    <div class="alert alert-danger">
                        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Your account has expired</h5>
                        <p class="mb-0">Your membership expired on <strong><?= $entitlement->getFormattedExpiryDate() ?></strong>. Renew now to restore full access to search, chat, and all features.</p>
                    </div>
                <?php elseif ($isExpired && $isInGrace): ?>
                    <div class="alert alert-warning">
                        <h5 class="alert-heading"><i class="bi bi-clock-history me-2"></i>Grace Period Active</h5>
                        <p class="mb-0">Your membership expired on <strong><?= $entitlement->getFormattedExpiryDate() ?></strong>. Grace period ends on <strong><?= $entitlement->getGracePeriodEndDate() ?></strong>. Renew now to avoid permanent account suspension.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i>Account Renewal</h5>
                        <p class="mb-0">Your account is active until <strong><?= $entitlement->getFormattedExpiryDate() ?></strong> (<?= $daysRemaining ?> days remaining). You can renew early to extend your membership.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Plan Selection -->
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="section-header text-center mb-4">
                    <h2 class="section-title">Renew Your Account</h2>
                    <p class="section-subtitle">Select a plan to extend your membership for 2 years</p>
                </div>

                <div class="row g-4 justify-content-center">
                    <?php foreach ($plans as $plan): 
                        $features = json_decode($plan['features'], true) ?: [];
                        $isMalePlan = stripos($plan['name'], 'Male') !== false && stripos($plan['name'], 'Female') === false;
                        $isFemalePlan = stripos($plan['name'], 'Female') !== false;
                        $cardColor = $isMalePlan ? 'primary' : 'danger';
                    ?>
                        <div class="col-lg-5 col-md-6">
                            <div class="plan-card">
                                <h4 class="plan-name">
                                    <i class="bi <?= $isMalePlan ? 'bi-gender-male' : 'bi-gender-female' ?> me-2"></i>
                                    <?= sanitize($plan['name']) ?>
                                </h4>
                                <div class="plan-price">₹<?= number_format($plan['price']) ?></div>
                                <div class="plan-duration">for <?= (int)$plan['duration_days'] / 365 ?> years</div>
                                <ul class="plan-features">
                                    <?php foreach ($features as $feature): ?>
                                        <li><i class="bi bi-check2 text-success me-2"></i><?= sanitize($feature) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <button class="btn btn-<?= $cardColor ?> btn-lg w-100 btn-renew" 
                                        data-plan-id="<?= $plan['id'] ?>"
                                        data-plan-name="<?= htmlspecialchars($plan['name']) ?>"
                                        data-plan-price="<?= $plan['price'] ?>"
                                        data-duration="<?= $plan['duration_days'] ?>">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Renew Now
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (isRazorpayConfigured()): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.jQuery === 'undefined') {
        console.error('renew: jQuery not loaded');
        return;
    }
    var $ = window.jQuery;
    var csrfToken = <?= json_encode(generateCSRFToken()) ?>;
    
    $.ajaxSetup({
        headers: { 'X-CSRF-Token': csrfToken }
    });
    
    var razorpayEnabled = <?= isRazorpayConfigured() ? 'true' : 'false' ?>;
    var razorpayKey = <?= json_encode(defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : '') ?>;
    var siteName = <?= json_encode(SITE_NAME) ?>;
    var userEmail = <?= json_encode($currentUser['email']) ?>;
    var userName = <?= json_encode($currentUser['name']) ?>;
    
    // Renew button click handler
    $('.btn-renew').on('click', function() {
        var planId = $(this).data('plan-id');
        var planName = $(this).data('plan-name');
        var planPrice = parseFloat($(this).data('plan-price'));
        var duration = parseInt($(this).data('duration'));
        
        if (!razorpayEnabled) {
            alert('Payment gateway is not configured. Please contact support.');
            return;
        }
        
        // Initiate payment
        $.post('api/renew.php', {
            plan_id: planId,
            action: 'initiate'
        }, function(response) {
            if (!response.success) {
                alert(response.message || 'Could not initiate renewal. Please try again.');
                return;
            }
            
            if (response.bypass) {
                // Free renewal (shouldn't happen normally)
                window.location.href = '<?= SITE_URL ?>/renewal-success.php';
                return;
            }
            
            // Launch Razorpay checkout
            var options = {
                key: razorpayKey,
                amount: response.amount_paise,
                currency: response.currency,
                name: siteName,
                description: 'Account Renewal - ' + planName,
                order_id: response.razorpay_order_id,
                handler: function(paymentResult) {
                    // Verify payment
                    $.post('api/renew.php', {
                        action: 'verify',
                        renewal_id: response.renewal_id,
                        razorpay_order_id: paymentResult.razorpay_order_id,
                        razorpay_payment_id: paymentResult.razorpay_payment_id,
                        razorpay_signature: paymentResult.razorpay_signature
                    }, function(verifyResponse) {
                        if (verifyResponse.success) {
                            window.location.href = '<?= SITE_URL ?>/renewal-success.php';
                        } else {
                            alert(verifyResponse.message || 'Payment verification failed. Please contact support.');
                        }
                    }, 'json').fail(function() {
                        alert('Failed to verify payment. Please contact support if amount was deducted.');
                    });
                },
                prefill: {
                    name: userName,
                    email: userEmail
                },
                theme: {
                    color: '#C0392B'
                },
                modal: {
                    escape: false,
                    backdropclose: false
                }
            };
            
            var rzp = new Razorpay(options);
            rzp.open();
        }, 'json').fail(function() {
            alert('Failed to initiate renewal. Please try again.');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
