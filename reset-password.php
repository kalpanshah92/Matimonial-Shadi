<?php
$pageTitle = 'Reset Password';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$errors = [];
$success = false;

// Get email from session if available
$email = $_SESSION['reset_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }
    
    // Use email from session, not from form
    $email = $_SESSION['reset_email'] ?? '';
    $otp = sanitize($_POST['otp'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($email)) {
        $errors[] = 'Email is missing. Please request a new password reset.';
    }
    if (empty($otp)) {
        $errors[] = 'Please enter the OTP.';
    }
    if (strlen($newPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[0-9]/', $newPassword)) {
        $errors[] = 'Password must contain at least 1 number.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        // Verify OTP
        if (verifyOTP($email, $otp, 'password_reset')) {
            // Update password
            $pdo = getDBConnection();
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            if ($stmt->execute([$hashedPassword, $email])) {
                // Clear session email after successful reset
                unset($_SESSION['reset_email']);
                setFlash('success', 'Your password has been reset successfully. You can now login with your new password.');
                redirect(SITE_URL . '/login.php');
            } else {
                $errors[] = 'Failed to update password. Please try again.';
            }
        } else {
            $errors[] = 'Invalid or expired OTP. Please request a new OTP.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="auth-section py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5 col-md-7">
                <div class="auth-card">
                    <div class="auth-header text-center">
                        <h2><i class="bi bi-key"></i> Reset Password</h2>
                        <p>Enter the OTP sent to your email</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>Your password has been reset successfully.
                        </div>
                        <div class="text-center mt-3">
                            <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly required placeholder="your@email.com">
                                </div>
                                <small class="text-muted">Email is locked and cannot be changed</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="otp" class="form-label">OTP (One-Time Password)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                    <input type="text" class="form-control" id="otp" name="otp" required placeholder="Enter 6-digit OTP" maxlength="6" pattern="[0-9]{6}">
                                </div>
                                <small class="text-muted">OTP is valid for <?= OTP_EXPIRY_MINUTES ?> minutes</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           required minlength="8" placeholder="Min 8 characters, include a number">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required placeholder="Confirm new password">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-check-lg me-2"></i>Reset Password
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="<?= SITE_URL ?>/forgot-password.php"><i class="bi bi-arrow-left me-1"></i>Request New OTP</a>
                                <span class="mx-2">|</span>
                                <a href="<?= SITE_URL ?>/login.php">Back to Login</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
