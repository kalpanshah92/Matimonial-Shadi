<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } elseif ($currentPassword === $newPassword) {
        $error = 'New password must be different from current password.';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($currentPassword, $admin['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            if ($stmt->execute([$newHash, $_SESSION['admin_id']])) {
                $success = 'Password changed successfully. Please use your new password next time you log in.';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}

$adminPage = 'change-password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | <?= SITE_NAME ?> Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-key me-2"></i>Change Password</h2>
            <p class="text-muted mb-0">Update your admin account password</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" autocomplete="off">
                        <div class="mb-3">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_password" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_password" id="newPassword" required minlength="8">
                            <small class="text-muted">Must be 8+ characters with uppercase, lowercase, and number.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required minlength="8">
                            <small id="matchMessage" class="text-muted"></small>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Update Password
                        </button>
                        <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                        </a>
                    </form>
                </div>
            </div>

            <div class="card mt-3 border-info">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-info-circle me-1"></i>Password Requirements</h6>
                    <ul class="mb-0 small">
                        <li>Minimum 8 characters</li>
                        <li>At least one uppercase letter (A-Z)</li>
                        <li>At least one lowercase letter (a-z)</li>
                        <li>At least one number (0-9)</li>
                        <li>Must be different from current password</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const newPwd = document.getElementById('newPassword');
    const confirmPwd = document.getElementById('confirmPassword');
    const matchMsg = document.getElementById('matchMessage');

    function checkMatch() {
        if (confirmPwd.value === '') {
            matchMsg.textContent = '';
            matchMsg.className = 'text-muted';
            return;
        }
        if (newPwd.value === confirmPwd.value) {
            matchMsg.textContent = '✓ Passwords match';
            matchMsg.className = 'text-success';
        } else {
            matchMsg.textContent = '✗ Passwords do not match';
            matchMsg.className = 'text-danger';
        }
    }

    newPwd.addEventListener('input', checkMatch);
    confirmPwd.addEventListener('input', checkMatch);
</script>
</body>
</html>
