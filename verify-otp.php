<?php
$pageTitle = 'Verify Email';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(SITE_URL . '/dashboard.php');
}

// Ensure user came from registration
if (empty($_SESSION['pending_registration'])) {
    setFlash('error', 'Please complete the registration form first.');
    redirect(SITE_URL . '/register.php');
}

$pending = $_SESSION['pending_registration'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    }

    $action = $_POST['action'] ?? 'verify';

    if ($action === 'resend' && empty($errors)) {
        $otp = generateOTP();
        if (saveOTP($pending['email'], $otp, 'registration')) {
            $subject = 'Email Verification OTP - ' . SITE_NAME;
            $body = "<p>Dear {$pending['name']},</p><p>Your new OTP is:</p><h2 style='color:#C0392B;letter-spacing:5px;'>$otp</h2><p>Valid for " . OTP_EXPIRY_MINUTES . " minutes.</p>";
            if (sendEmail($pending['email'], $subject, $body)) {
                setFlash('success', 'A new OTP has been sent to your email.');
            } else {
                $errors[] = 'Failed to resend OTP. Please try again.';
            }
        } else {
            $errors[] = 'You have reached the maximum limit for OTP resend. Please try again after 30 minutes.';
        }
    } elseif (empty($errors)) {
        $otp = sanitize($_POST['otp'] ?? '');

        if (empty($otp)) {
            $errors[] = 'Please enter the OTP.';
        } elseif (!verifyOTP($pending['email'], $otp, 'registration')) {
            $errors[] = 'Invalid OTP. You can resend OTP ' . (2 - getOTPResendCount($pending['email'], 'registration')) . ' more times.';
        } else {
            // OTP verified - complete registration
            try {
                $pdo = getDBConnection();
                $profileId = generateProfileId($pending['gender']);

                $stmt = $pdo->prepare(
                    "INSERT INTO users (profile_id, name, email, phone, password, gender, dob, religion, caste, mother_tongue, country, state, city, email_verified, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'pending')"
                );

                $stmt->execute([
                    $profileId,
                    $pending['name'],
                    $pending['email'],
                    $pending['phone'],
                    $pending['password'],
                    $pending['gender'],
                    $pending['dob'],
                    $pending['religion'],
                    $pending['caste'],
                    $pending['mother_tongue'],
                    $pending['country'],
                    $pending['state'],
                    $pending['city']
                ]);

                $userId = $pdo->lastInsertId();

                // Create empty profile records
                $pdo->prepare("INSERT INTO profile_details (user_id) VALUES (?)")->execute([$userId]);
                $pdo->prepare("INSERT INTO family_details (user_id) VALUES (?)")->execute([$userId]);
                $pdo->prepare("INSERT INTO partner_preferences (user_id) VALUES (?)")->execute([$userId]);
                $pdo->prepare("INSERT INTO privacy_settings (user_id) VALUES (?)")->execute([$userId]);

                // Handle profile photo if uploaded during registration
                if (!empty($pending['photo_temp'])) {
                    $tempPhotoPath = UPLOADS_PATH . 'pending_photos' . DIRECTORY_SEPARATOR . $pending['photo_temp'];
                    if (file_exists($tempPhotoPath)) {
                        // Move to user's photos folder
                        $userPhotoDir = UPLOADS_PATH . 'photos' . DIRECTORY_SEPARATOR . $userId;
                        if (!is_dir($userPhotoDir)) {
                            mkdir($userPhotoDir, 0755, true);
                        }
                        $finalPhotoPath = 'uploads/photos/' . $userId . '/' . $pending['photo_temp'];
                        $finalFilePath = $userPhotoDir . DIRECTORY_SEPARATOR . $pending['photo_temp'];
                        
                        if (rename($tempPhotoPath, $finalFilePath)) {
                            // Insert into photos table with is_approved=0, is_primary=1
                            $stmt = $pdo->prepare("INSERT INTO photos (user_id, photo_path, is_primary, is_approved) VALUES (?, ?, 1, 0)");
                            $stmt->execute([$userId, $finalPhotoPath]);
                        }
                    }
                }

                unset($_SESSION['pending_registration']);

                setFlash('success', 'Your account is pending for admin approval. You will be able to login once approved.');
                redirect(SITE_URL . '/login.php');
            } catch (PDOException $e) {
                error_log("Registration Error: " . $e->getMessage());
                $errors[] = 'Registration failed. Please try again.';
            }
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
                        <h2><i class="bi bi-envelope-check"></i> Verify Your Email</h2>
                        <p>Enter the OTP sent to <strong><?= sanitize($pending['email']) ?></strong></p>
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
                        <input type="hidden" name="action" value="verify">

                        <div class="mb-3">
                            <label for="otp" class="form-label">Enter OTP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-lg text-center" id="otp" name="otp"
                                   required maxlength="<?= OTP_LENGTH ?>" pattern="[0-9]{<?= OTP_LENGTH ?>}"
                                   placeholder="Enter <?= OTP_LENGTH ?>-digit OTP" autocomplete="off"
                                   style="letter-spacing: 8px; font-size: 1.5rem;">
                            <small class="text-muted">OTP is valid for <?= OTP_EXPIRY_MINUTES ?> minutes.</small>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-check-circle me-2"></i>Verify & Complete Registration
                        </button>
                    </form>

                    <form method="POST" action="" class="mt-3 text-center">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="resend">
                        <button type="submit" class="btn btn-link">Didn't receive OTP? Resend</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
