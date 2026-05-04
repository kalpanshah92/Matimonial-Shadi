<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Fetch privacy settings
$stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$privacy = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }
    
    $section = $_POST['section'] ?? '';
    
    if (empty($errors)) {
        try {
            switch ($section) {
                case 'privacy':
                    $stmt = $pdo->prepare(
                        "UPDATE privacy_settings SET show_phone=?, show_email=?, show_photo=?, 
                         show_income=?, profile_visibility=?, allow_messages=?, updated_at=NOW() WHERE user_id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['show_phone']),
                        sanitize($_POST['show_email']),
                        sanitize($_POST['show_photo']),
                        sanitize($_POST['show_income']),
                        sanitize($_POST['profile_visibility']),
                        sanitize($_POST['allow_messages']),
                        $userId
                    ]);
                    setFlash('success', 'Privacy settings updated.');
                    break;

                case 'password':
                    $currentPassword = $_POST['current_password'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';
                    
                    if (!password_verify($currentPassword, $currentUser['password'])) {
                        $errors[] = 'Current password is incorrect.';
                    } elseif (strlen($newPassword) < 8) {
                        $errors[] = 'New password must be at least 8 characters.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $errors[] = 'New passwords do not match.';
                    } else {
                        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed, $userId]);
                        setFlash('success', 'Password changed successfully.');
                    }
                    break;

                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$userId]);
                    session_destroy();
                    header('Location: ' . SITE_URL . '/login.php');
                    exit;
            }
            
            if (empty($errors)) {
                redirect(SITE_URL . '/settings.php');
            }
        } catch (Exception $e) {
            $errors[] = 'Failed to update settings.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <h3 class="mb-4"><i class="bi bi-gear me-2"></i>Settings</h3>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Privacy Settings -->
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-shield-lock me-2 text-primary"></i>Privacy Settings</h5>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="privacy">
                        
                        <?php
                        $visibilityOptions = ['everyone' => 'Everyone', 'connected' => 'Connected Only'];
                        $fields = [
                            'show_phone' => 'Phone Number Visibility',
                            'show_email' => 'Email Visibility',
                            'show_photo' => 'Photo Visibility',
                            'show_income' => 'Income Visibility',
                        ];
                        ?>
                        
                        <?php foreach ($fields as $field => $label): ?>
                            <div class="privacy-option">
                                <label><?= $label ?></label>
                                <select name="<?= $field ?>" class="form-select form-select-sm" style="width: auto;">
                                    <?php foreach ($visibilityOptions as $val => $text): ?>
                                        <option value="<?= $val ?>" <?= ($privacy[$field] ?? ($field === 'show_phone' ? 'connected' : 'everyone')) === $val ? 'selected' : '' ?>><?= $text ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="privacy-option">
                            <label>Profile Visibility</label>
                            <select name="profile_visibility" class="form-select form-select-sm" style="width: auto;">
                                <option value="everyone" <?= ($privacy['profile_visibility'] ?? 'everyone') === 'everyone' ? 'selected' : '' ?>>Everyone</option>
                                <option value="connected" <?= ($privacy['profile_visibility'] ?? '') === 'connected' ? 'selected' : '' ?>>Connected Only</option>
                            </select>
                        </div>
                        
                        <div class="privacy-option">
                            <label>Who Can Message Me</label>
                            <select name="allow_messages" class="form-select form-select-sm" style="width: auto;">
                                <option value="everyone" <?= ($privacy['allow_messages'] ?? 'connected') === 'everyone' ? 'selected' : '' ?>>Everyone</option>
                                <option value="connected" <?= ($privacy['allow_messages'] ?? 'connected') === 'connected' ? 'selected' : '' ?>>Connected Only</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Privacy Settings</button>
                    </form>
                </div>

                <!-- Change Password -->
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-key me-2 text-primary"></i>Change Password</h5>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="password">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required minlength="8">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Change Password</button>
                    </form>
                </div>

                <!-- Deactivate Account -->
                <div class="dashboard-card border-danger">
                    <h5 class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Deactivate Account</h5>
                    <p class="text-muted mt-2">Once deactivated, your profile will be hidden from search results. You can reactivate by contacting support.</p>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate your account?')">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="deactivate">
                        <button type="submit" class="btn btn-outline-danger">Deactivate My Account</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="dashboard-card">
                    <h5><i class="bi bi-info-circle me-2 text-primary"></i>Account Info</h5>
                    <div class="mt-3">
                        <p><strong>Email:</strong> <?= sanitize($currentUser['email']) ?></p>
                        <p><strong>Phone:</strong> +91 <?= sanitize($currentUser['phone'] ?? 'Not set') ?></p>
                        <p><strong>Member Since:</strong> <?= date('d M Y', strtotime($currentUser['created_at'])) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge bg-<?= $currentUser['status'] === 'approved' ? 'success' : 'warning' ?>">
                                <?= ucfirst($currentUser['status']) ?>
                            </span>
                        </p>
                        <p><strong>Verified:</strong> 
                            <?= $currentUser['is_verified'] ? '<span class="text-success"><i class="bi bi-patch-check-fill"></i> Yes</span>' : '<span class="text-muted">No</span>' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
