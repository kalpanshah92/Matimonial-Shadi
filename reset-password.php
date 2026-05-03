<?php
$pageTitle = 'Reset Password';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$errors = [];
$step = 'verify_otp'; // Step 1: verify OTP, Step 2: change password

// Get email from session if available
$email = $_SESSION['reset_email'] ?? '';

// Check if OTP was already verified in this session
if (!empty($_SESSION['otp_verified'])) {
    $step = 'change_password';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }
    
    $action = $_POST['action'] ?? '';
    $email = $_SESSION['reset_email'] ?? '';
    
    if (empty($email)) {
        $errors[] = 'Email is missing. Please request a new password reset.';
    }
    
    if (empty($errors) && $action === 'verify_otp') {
        // Step 1: Verify OTP
        $otp = sanitize($_POST['otp'] ?? '');
        
        if (empty($otp)) {
            $errors[] = 'Please enter the OTP.';
        }
        
        if (empty($errors)) {
            if (verifyOTP($email, $otp, 'password_reset')) {
                $_SESSION['otp_verified'] = true;
                $step = 'change_password';
            } else {
                $errors[] = 'Invalid or expired OTP. Please request a new OTP.';
            }
        }
    } elseif (empty($errors) && $action === 'change_password') {
        // Step 2: Change password (only if OTP was verified)
        if (empty($_SESSION['otp_verified'])) {
            $errors[] = 'OTP not verified. Please verify your OTP first.';
        } else {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
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
                $pdo = getDBConnection();
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                if ($stmt->execute([$hashedPassword, $email])) {
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['otp_verified']);
                    setFlash('success', 'Your password has been reset successfully. You can now login with your new password.');
                    redirect(SITE_URL . '/login.php');
                } else {
                    $errors[] = 'Failed to update password. Please try again.';
                }
            }
            
            $step = 'change_password';
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
                        <p><?= $step === 'verify_otp' ? 'Enter the OTP sent to your email' : 'Set your new password' ?></p>
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
                    
                    <?php if ($step === 'verify_otp'): ?>
                        <!-- Step 1: Verify OTP -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="verify_otp">
                            
                            <div class="mb-3">
                                <label for="otp" class="form-label">OTP (One-Time Password)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                                    <input type="text" class="form-control" id="otp" name="otp" required placeholder="Enter 6-digit OTP" maxlength="6" pattern="[0-9]{6}">
                                </div>
                                <small class="text-muted">OTP is valid for <?= OTP_EXPIRY_MINUTES ?> minutes</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-shield-check me-2"></i>Verify OTP
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="<?= SITE_URL ?>/forgot-password.php"><i class="bi bi-arrow-left me-1"></i>Request New OTP</a>
                                <span class="mx-2">|</span>
                                <a href="<?= SITE_URL ?>/login.php">Back to Login</a>
                            </div>
                        </form>
                    <?php elseif ($step === 'change_password'): ?>
                        <!-- Step 2: Change Password -->
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-check-circle me-2"></i>OTP verified successfully. Please set your new password.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="change_password">
                            
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
