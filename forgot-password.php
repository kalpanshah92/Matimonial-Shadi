<?php
$pageTitle = 'Forgot Password';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) redirect(SITE_URL . '/dashboard.php');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }
    
    $email = sanitize($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($errors)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $otp = generateOTP();
            saveOTP($email, $otp, 'password_reset');
            
            // Send email with OTP
            $subject = 'Password Reset OTP - ' . SITE_NAME;
            $body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #C0392B; color: #fff; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 5px 5px; }
                        .otp { font-size: 32px; font-weight: bold; color: #C0392B; text-align: center; margin: 20px 0; letter-spacing: 5px; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Password Reset</h2>
                        </div>
                        <div class='content'>
                            <p>Dear {$user['name']},</p>
                            <p>We received a request to reset your password for your " . SITE_NAME . " account.</p>
                            <p>Your One-Time Password (OTP) is:</p>
                            <div class='otp'>$otp</div>
                            <p><strong>This OTP is valid for " . OTP_EXPIRY_MINUTES . " minutes.</strong></p>
                            <p>If you did not request this password reset, please ignore this email.</p>
                            <p>For security reasons, do not share this OTP with anyone.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $emailSent = sendEmail($email, $subject, $body);
            
            if ($emailSent) {
                $success = true;
                setFlash('success', 'Password reset OTP has been sent to your email. Please check your inbox.');
            } else {
                $errors[] = 'Failed to send email. Please try again later.';
            }
        } else {
            // Don't reveal if email exists
            $success = true;
            setFlash('success', 'If this email is registered, you will receive a password reset OTP.');
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
                        <h2><i class="bi bi-shield-lock"></i> Reset Password</h2>
                        <p>Enter your registered email to receive OTP</p>
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
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="mb-4">
                            <label for="email" class="form-label">Registered Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="your@email.com">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-send me-2"></i>Send OTP
                        </button>
                        
                        <div class="text-center mt-3">
                            <a href="<?= SITE_URL ?>/login.php"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
