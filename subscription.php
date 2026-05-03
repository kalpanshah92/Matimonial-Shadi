<?php
$pageTitle = 'Premium Plans';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

// Fetch active plans
$plans = [];
try {
    $stmt = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC");
    $plans = $stmt->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Plans Section -->
<section class="py-5 bg-warm">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Choose Your Plan</h2>
            <p class="section-subtitle">Upgrade to premium for better matchmaking experience</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($plans as $index => $plan): 
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
                        
                        <div class="plan-price">
                            INR <?= number_format($plan['price']) ?>
                            <small>/ 2 Years</small>
                        </div>
                        
                        <ul class="plan-features">
                            <li><i class="bi bi-check-circle-fill"></i> Duration / <?= $plan['duration_days'] ?> days</li>
                            
                            
                        </ul>
                        
                        <?php if (isLoggedIn()): ?>
                            <button class="btn btn-<?= $cardColor ?> w-100" 
                                    onclick="initiatePayment(<?= $plan['id'] ?>, <?= $plan['price'] ?>, '<?= sanitize($plan['name']) ?>')">
                                Choose Plan
                            </button>
                        <?php else: ?>
                            <a href="<?= SITE_URL ?>/register.php" class="btn btn-<?= $cardColor ?> w-100">
                                Register to Subscribe
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- FAQ -->
        <div class="row justify-content-center mt-5">
            <div class="col-lg-8">
                <h3 class="text-center mb-4">Frequently Asked Questions</h3>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                What payment methods are accepted?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We accept UPI, Debit/Credit Cards, Net Banking, Paytm, and all major payment methods through Razorpay.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Can I upgrade my plan later?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, you can upgrade your plan at any time. The remaining days from your current plan will be adjusted.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Is there a refund policy?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                All subscription fees are non-refundable once the subscription period has begun. In case of technical issues or payment errors, please contact our support team. For full details, please refer to our <a href="<?= SITE_URL ?>/refund-policy.php">Refund Policy</a>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (isLoggedIn()): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function initiatePayment(planId, amount, planName) {
    var options = {
        "key": "<?= RAZORPAY_KEY_ID ?>",
        "amount": amount * 100,
        "currency": "INR",
        "name": "<?= SITE_NAME ?>",
        "description": planName + " Subscription",
        "handler": function (response) {
            // Send payment details to server
            $.ajax({
                url: '<?= SITE_URL ?>/api/payment.php',
                method: 'POST',
                data: {
                    plan_id: planId,
                    payment_id: response.razorpay_payment_id,
                    amount: amount
                },
                dataType: 'json',
                success: function (result) {
                    if (result.success) {
                        alert('Payment successful! Your plan has been activated.');
                        window.location.reload();
                    } else {
                        alert('Payment verification failed. Please contact support.');
                    }
                }
            });
        },
        "prefill": {
            "name": "<?= sanitize($currentUser['name'] ?? '') ?>",
            "email": "<?= sanitize($currentUser['email'] ?? '') ?>",
            "contact": "<?= sanitize($currentUser['phone'] ?? '') ?>"
        },
        "theme": {
            "color": "#c0392b"
        }
    };
    var rzp = new Razorpay(options);
    rzp.open();
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
