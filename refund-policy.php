<?php
$pageTitle = 'Refund Policy';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-5">
                        <h1 class="mb-4">Refund Policy</h1>
                        <p class="text-muted mb-4">Last updated: <?= date('F j, Y') ?></p>
                        
                        <h4 class="mt-4 mb-3">1. General Refund Policy</h4>
                        <p>At <?= SITE_NAME ?>, we strive to provide the best matrimonial services to our users. However, due to the nature of our services, all subscription fees are non-refundable once the subscription period has begun.</p>
                        
                        <h4 class="mt-4 mb-3">2. Subscription Refunds</h4>
                        <p>Once you have purchased a premium subscription and the subscription period has started, we do not offer refunds. Please review your subscription details carefully before making a purchase.</p>
                        
                        <h4 class="mt-4 mb-3">3. Technical Issues</h4>
                        <p>In the unlikely event that you experience technical issues that prevent you from accessing our service, please contact our support team immediately. We will work to resolve the issue promptly. If we cannot resolve the issue within a reasonable timeframe, we may offer a prorated refund at our sole discretion.</p>
                        
                        <h4 class="mt-4 mb-3">4. Account Termination</h4>
                        <p>If you choose to terminate your account, you will not be entitled to a refund of any unused portion of your subscription. Your access to premium features will continue until the end of your current subscription period.</p>
                        
                        <h4 class="mt-4 mb-3">5. Fraudulent Activity</h4>
                        <p>We reserve the right to refuse refunds or cancel subscriptions if we detect fraudulent activity or violation of our Terms of Service. In such cases, we may also suspend or terminate your account.</p>
                        
                        <h4 class="mt-4 mb-3">6. Payment Processing Errors</h4>
                        <p>If you were charged in error (e.g., duplicate charges, incorrect amount), please contact us within 30 days of the charge. We will investigate and issue a refund if the error is confirmed.</p>
                        
                        <h4 class="mt-4 mb-3">7. Requesting a Refund</h4>
                        <p>To request a refund, please contact our support team at <?= SITE_EMAIL ?> with your account details and the reason for your refund request. All refund requests are reviewed on a case-by-case basis.</p>
                        
                        <h4 class="mt-4 mb-3">8. Refund Processing Time</h4>
                        <p>If a refund is approved, it will be processed within 7-10 business days. The refund will be credited to the original payment method used for the purchase.</p>
                        
                        <h4 class="mt-4 mb-3">9. Changes to This Policy</h4>
                        <p>We reserve the right to modify this refund policy at any time. Any changes will be posted on this page with an updated revision date.</p>
                        
                        <h4 class="mt-4 mb-3">10. Contact Us</h4>
                        <p>If you have any questions about our refund policy, please contact us at <?= SITE_EMAIL ?>.</p>
                        
                        <div class="mt-5">
                            <a href="<?= SITE_URL ?>" class="btn btn-primary">Back to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
