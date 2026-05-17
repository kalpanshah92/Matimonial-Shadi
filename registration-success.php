<?php
/**
 * Shown once the registration payment is settled (paid or 100%-coupon bypass).
 * The user is still NOT logged in here; they must wait for admin approval.
 */
$pageTitle = 'Registration Submitted';
require_once __DIR__ . '/includes/functions.php';

$pendingUserId = (int)($_SESSION['registration_payment_user_id'] ?? 0);
if (!$pendingUserId) {
    redirect(SITE_URL . '/login.php');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT name, email, registration_payment_status FROM users WHERE id = ?");
$stmt->execute([$pendingUserId]);
$user = $stmt->fetch();

if (!$user || !in_array($user['registration_payment_status'], ['completed','bypassed'], true)) {
    setFlash('error', 'Registration payment was not completed.');
    redirect(SITE_URL . '/registration-payment.php');
}

// Once the success page is rendered we end the registration-payment session window.
unset($_SESSION['registration_payment_user_id'], $_SESSION['registration_payment_expires']);

require_once __DIR__ . '/includes/header.php';
?>
<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-9">
                <div class="auth-card text-center">
                    <div class="mb-3" style="font-size:4rem; color:var(--primary,#C0392B);">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h2 class="mb-2">Registration Submitted</h2>
                    <p class="mb-3">
                        Thank you, <strong><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></strong>!
                        Your profile is now <strong>Pending Admin Review</strong>.
                    </p>
                    <p class="text-muted small mb-4">
                        We have emailed a confirmation to <strong><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></strong>.
                        You will be able to log in once an administrator approves your profile.
                    </p>
                    <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Go to Login</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
