<?php
/**
 * Account Renewal Success Page
 */
$pageTitle = 'Renewal Successful';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/AccountEntitlement.php';

$entitlement = AccountEntitlement::forUser($currentUser['id']);
$newExpiryDate = $entitlement->getFormattedExpiryDate();

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="auth-card text-center">
                    <div class="auth-header">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        <h2 class="mt-3">Renewal Successful!</h2>
                    </div>
                    
                    <div class="py-4">
                        <p class="lead">Your account has been successfully renewed.</p>
                        
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h5 class="card-title">New Expiry Date</h5>
                                <p class="h3 text-primary mb-0"><?= $newExpiryDate ?></p>
                                <small class="text-muted">Your account is now active until this date</small>
                            </div>
                        </div>
                        
                        <p>You now have full access to:</p>
                        <ul class="list-unstyled mb-4">
                            <li><i class="bi bi-check text-success me-2"></i>Search for partners</li>
                            <li><i class="bi bi-check text-success me-2"></i>View detailed profiles</li>
                            <li><i class="bi bi-check text-success me-2"></i>Connect with matches</li>
                            <li><i class="bi bi-check text-success me-2"></i>Chat with connections</li>
                        </ul>
                        
                        <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-house me-2"></i>Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
