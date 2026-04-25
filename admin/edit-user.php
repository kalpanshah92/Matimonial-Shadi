<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Super admin only
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header('Location: profiles.php?error=permission');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'profiles';
$userId = intval($_GET['id'] ?? 0);

if (!$userId) {
    header('Location: profiles.php');
    exit;
}

$errors = [];
$success = '';

// Load user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: profiles.php?error=notfound');
    exit;
}

// Load related tables (auto-create empty rows if missing)
foreach (['profile_details', 'family_details', 'partner_preferences'] as $tbl) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM $tbl WHERE user_id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        $pdo->prepare("INSERT INTO $tbl (user_id) VALUES (?)")->execute([$userId]);
    }
}

$stmt = $pdo->prepare("SELECT * FROM profile_details WHERE user_id = ?");
$stmt->execute([$userId]);
$details = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM family_details WHERE user_id = ?");
$stmt->execute([$userId]);
$family = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
$stmt->execute([$userId]);
$partner = $stmt->fetch() ?: [];

$activeTab = $_GET['tab'] ?? 'account';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }

    $section = $_POST['section'] ?? '';

    if (empty($errors)) {
        try {
            switch ($section) {
                case 'account':
                    $stmt = $pdo->prepare(
                        "UPDATE users SET name=?, email=?, phone=?, gender=?, dob=?, status=?, 
                         is_active=?, is_verified=?, is_premium=?, email_verified=?, phone_verified=? WHERE id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['name']),
                        sanitize($_POST['email']),
                        sanitize($_POST['phone']),
                        $_POST['gender'],
                        $_POST['dob'],
                        $_POST['status'],
                        isset($_POST['is_active']) ? 1 : 0,
                        isset($_POST['is_verified']) ? 1 : 0,
                        isset($_POST['is_premium']) ? 1 : 0,
                        isset($_POST['email_verified']) ? 1 : 0,
                        isset($_POST['phone_verified']) ? 1 : 0,
                        $userId
                    ]);
                    $success = 'Account information updated.';
                    $activeTab = 'account';
                    break;

                case 'basic':
                    $stmt = $pdo->prepare(
                        "UPDATE users SET religion=?, caste=?, sub_caste=?, mother_tongue=?, 
                         marital_status=?, state=?, city=?, about_me=? WHERE id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['religion']),
                        sanitize($_POST['caste']),
                        sanitize($_POST['sub_caste']),
                        sanitize($_POST['mother_tongue']),
                        sanitize($_POST['marital_status']),
                        sanitize($_POST['state']),
                        sanitize($_POST['city']),
                        sanitize($_POST['about_me']),
                        $userId
                    ]);
                    $success = 'Basic information updated.';
                    $activeTab = 'basic';
                    break;

                case 'personal':
                    $stmt = $pdo->prepare(
                        "UPDATE profile_details SET height=?, weight=?, complexion=?, body_type=?, 
                         blood_group=?, diet=?, smoking=?, drinking=?, hobbies=? WHERE user_id=?"
                    );
                    $stmt->execute([
                        intval($_POST['height']) ?: null,
                        intval($_POST['weight']) ?: null,
                        sanitize($_POST['complexion']),
                        sanitize($_POST['body_type']),
                        sanitize($_POST['blood_group']),
                        sanitize($_POST['diet']),
                        $_POST['smoking'] ?: 'No',
                        $_POST['drinking'] ?: 'No',
                        sanitize($_POST['hobbies']),
                        $userId
                    ]);
                    $success = 'Personal details updated.';
                    $activeTab = 'personal';
                    break;

                case 'professional':
                    $stmt = $pdo->prepare(
                        "UPDATE profile_details SET education=?, education_detail=?, occupation=?, 
                         occupation_detail=?, company=?, annual_income=?, working_city=? WHERE user_id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['education']),
                        sanitize($_POST['education_detail']),
                        sanitize($_POST['occupation']),
                        sanitize($_POST['occupation_detail']),
                        sanitize($_POST['company']),
                        sanitize($_POST['annual_income']),
                        sanitize($_POST['working_city']),
                        $userId
                    ]);
                    $success = 'Professional details updated.';
                    $activeTab = 'professional';
                    break;

                case 'family':
                    $stmt = $pdo->prepare(
                        "UPDATE family_details SET father_name=?, father_occupation=?, mother_name=?, 
                         mother_occupation=?, brothers=?, brothers_married=?, sisters=?, sisters_married=?,
                         family_type=?, family_status=?, family_values=?, gotra=?, about_family=? WHERE user_id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['father_name']),
                        sanitize($_POST['father_occupation']),
                        sanitize($_POST['mother_name']),
                        sanitize($_POST['mother_occupation']),
                        intval($_POST['brothers']),
                        intval($_POST['brothers_married']),
                        intval($_POST['sisters']),
                        intval($_POST['sisters_married']),
                        sanitize($_POST['family_type']),
                        sanitize($_POST['family_status']),
                        sanitize($_POST['family_values']),
                        sanitize($_POST['gotra']),
                        sanitize($_POST['about_family']),
                        $userId
                    ]);
                    $success = 'Family details updated.';
                    $activeTab = 'family';
                    break;

                case 'password':
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';

                    if (strlen($newPassword) < 6) {
                        $errors[] = 'Password must be at least 6 characters.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $errors[] = 'Passwords do not match.';
                    } else {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
                        $success = 'Password reset successfully.';
                    }
                    $activeTab = 'password';
                    break;
            }

            // Reload user data after update
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT * FROM profile_details WHERE user_id = ?");
            $stmt->execute([$userId]);
            $details = $stmt->fetch() ?: [];

            $stmt = $pdo->prepare("SELECT * FROM family_details WHERE user_id = ?");
            $stmt->execute([$userId]);
            $family = $stmt->fetch() ?: [];

        } catch (PDOException $e) {
            error_log("Admin Edit User Error: " . $e->getMessage());
            $errors[] = 'Failed to update. ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User: <?= htmlspecialchars($user['name']) ?> | Admin</title>
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
            <h4 class="mb-1">Edit User: <?= htmlspecialchars($user['name']) ?></h4>
            <small class="text-muted">Profile ID: <strong><?= $user['profile_id'] ?></strong> &middot; <?= htmlspecialchars($user['email']) ?></small>
        </div>
        <div>
            <a href="<?= SITE_URL ?>/profile.php?id=<?= $user['id'] ?>" target="_blank" class="btn btn-outline-primary"><i class="bi bi-eye me-1"></i>View Public Profile</a>
            <a href="profiles.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item"><a class="nav-link <?= $activeTab === 'account' ? 'active' : '' ?>" href="?id=<?= $userId ?>&tab=account">Account</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeTab === 'basic' ? 'active' : '' ?>" href="?id=<?= $userId ?>&tab=basic">Basic Info</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeTab === 'personal' ? 'active' : '' ?>" href="?id=<?= $userId ?>&tab=personal">Personal</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeTab === 'professional' ? 'active' : '' ?>" href="?id=<?= $userId ?>&tab=professional">Professional</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeTab === 'family' ? 'active' : '' ?>" href="?id=<?= $userId ?>&tab=family">Family</a></li>
                <li class="nav-item"><a class="nav-link text-danger <?= $activeTab === 'password' ? 'active' : '' ?>" href="?id=<?= $userId ?>&tab=password"><i class="bi bi-key me-1"></i>Reset Password</a></li>
            </ul>

            <?php if ($activeTab === 'account'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="section" value="account">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><option value="Male" <?= $user['gender']==='Male'?'selected':'' ?>>Male</option><option value="Female" <?= $user['gender']==='Female'?'selected':'' ?>>Female</option></select></div>
                        <div class="col-md-3"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="dob" value="<?= $user['dob'] ?>"></div>
                        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status">
                            <?php foreach (['pending','approved','rejected','suspended'] as $s): ?>
                                <option value="<?= $s ?>" <?= $user['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                        <div class="col-md-8 d-flex flex-wrap gap-3 align-items-end">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $user['is_active']?'checked':'' ?>><label class="form-check-label" for="isActive">Active</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_verified" id="isVerified" <?= $user['is_verified']?'checked':'' ?>><label class="form-check-label" for="isVerified">Verified</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_premium" id="isPremium" <?= $user['is_premium']?'checked':'' ?>><label class="form-check-label" for="isPremium">Premium</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="email_verified" id="emailVerified" <?= $user['email_verified']?'checked':'' ?>><label class="form-check-label" for="emailVerified">Email Verified</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="phone_verified" id="phoneVerified" <?= $user['phone_verified']?'checked':'' ?>><label class="form-check-label" for="phoneVerified">Phone Verified</label></div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i>Save Account</button>
                </form>

            <?php elseif ($activeTab === 'basic'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="section" value="basic">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Religion</label><input class="form-control" name="religion" value="<?= htmlspecialchars($user['religion'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Caste</label><input class="form-control" name="caste" value="<?= htmlspecialchars($user['caste'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Sub Caste</label><input class="form-control" name="sub_caste" value="<?= htmlspecialchars($user['sub_caste'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Mother Tongue</label><input class="form-control" name="mother_tongue" value="<?= htmlspecialchars($user['mother_tongue'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Marital Status</label><select class="form-select" name="marital_status">
                            <?php foreach (['Never Married','Divorced','Widowed','Separated'] as $m): ?>
                                <option <?= ($user['marital_status']??'')===$m?'selected':'' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select></div>
                        <div class="col-md-4"><label class="form-label">State</label><input class="form-control" name="state" value="<?= htmlspecialchars($user['state'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">About Me</label><textarea class="form-control" name="about_me" rows="4"><?= htmlspecialchars($user['about_me'] ?? '') ?></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i>Save Basic Info</button>
                </form>

            <?php elseif ($activeTab === 'personal'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="section" value="personal">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Height (cm)</label><input type="number" class="form-control" name="height" value="<?= $details['height'] ?? '' ?>"></div>
                        <div class="col-md-3"><label class="form-label">Weight (kg)</label><input type="number" class="form-control" name="weight" value="<?= $details['weight'] ?? '' ?>"></div>
                        <div class="col-md-3"><label class="form-label">Complexion</label><input class="form-control" name="complexion" value="<?= htmlspecialchars($details['complexion'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Body Type</label><input class="form-control" name="body_type" value="<?= htmlspecialchars($details['body_type'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Blood Group</label><input class="form-control" name="blood_group" value="<?= htmlspecialchars($details['blood_group'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Diet</label><input class="form-control" name="diet" value="<?= htmlspecialchars($details['diet'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Smoking</label><select class="form-select" name="smoking"><?php foreach (['No','Yes','Occasionally'] as $o): ?><option <?= ($details['smoking']??'')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3"><label class="form-label">Drinking</label><select class="form-select" name="drinking"><?php foreach (['No','Yes','Occasionally'] as $o): ?><option <?= ($details['drinking']??'')===$o?'selected':'' ?>><?= $o ?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label">Hobbies</label><textarea class="form-control" name="hobbies" rows="3"><?= htmlspecialchars($details['hobbies'] ?? '') ?></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i>Save Personal Details</button>
                </form>

            <?php elseif ($activeTab === 'professional'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="section" value="professional">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Education</label><input class="form-control" name="education" value="<?= htmlspecialchars($details['education'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Education Detail</label><input class="form-control" name="education_detail" value="<?= htmlspecialchars($details['education_detail'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Occupation</label><input class="form-control" name="occupation" value="<?= htmlspecialchars($details['occupation'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Occupation Detail</label><input class="form-control" name="occupation_detail" value="<?= htmlspecialchars($details['occupation_detail'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Company</label><input class="form-control" name="company" value="<?= htmlspecialchars($details['company'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Annual Income</label><input class="form-control" name="annual_income" value="<?= htmlspecialchars($details['annual_income'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Working City</label><input class="form-control" name="working_city" value="<?= htmlspecialchars($details['working_city'] ?? '') ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i>Save Professional</button>
                </form>

            <?php elseif ($activeTab === 'family'): ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="section" value="family">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Father's Name</label><input class="form-control" name="father_name" value="<?= htmlspecialchars($family['father_name'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Father's Occupation</label><input class="form-control" name="father_occupation" value="<?= htmlspecialchars($family['father_occupation'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Mother's Name</label><input class="form-control" name="mother_name" value="<?= htmlspecialchars($family['mother_name'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Mother's Occupation</label><input class="form-control" name="mother_occupation" value="<?= htmlspecialchars($family['mother_occupation'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Brothers</label><input type="number" min="0" class="form-control" name="brothers" value="<?= $family['brothers'] ?? 0 ?>"></div>
                        <div class="col-md-3"><label class="form-label">Brothers Married</label><input type="number" min="0" class="form-control" name="brothers_married" value="<?= $family['brothers_married'] ?? 0 ?>"></div>
                        <div class="col-md-3"><label class="form-label">Sisters</label><input type="number" min="0" class="form-control" name="sisters" value="<?= $family['sisters'] ?? 0 ?>"></div>
                        <div class="col-md-3"><label class="form-label">Sisters Married</label><input type="number" min="0" class="form-control" name="sisters_married" value="<?= $family['sisters_married'] ?? 0 ?>"></div>
                        <div class="col-md-4"><label class="form-label">Family Type</label><input class="form-control" name="family_type" value="<?= htmlspecialchars($family['family_type'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Family Status</label><input class="form-control" name="family_status" value="<?= htmlspecialchars($family['family_status'] ?? '') ?>"></div>
                        <div class="col-md-4"><label class="form-label">Family Values</label><input class="form-control" name="family_values" value="<?= htmlspecialchars($family['family_values'] ?? '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Gotra</label><input class="form-control" name="gotra" value="<?= htmlspecialchars($family['gotra'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">About Family</label><textarea class="form-control" name="about_family" rows="3"><?= htmlspecialchars($family['about_family'] ?? '') ?></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-save me-1"></i>Save Family Details</button>
                </form>

            <?php elseif ($activeTab === 'password'): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <strong>Caution:</strong> Setting a new password will immediately replace this user's current password. The user will need to use the new password to login. Consider sending it to them via a secure channel.
                </div>
                <form method="POST" autocomplete="off" onsubmit="return confirm('Reset password for <?= htmlspecialchars($user['name']) ?>?');">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="section" value="password">
                    <div class="row g-3" style="max-width:500px;">
                        <div class="col-12">
                            <label class="form-label">New Password</label>
                            <input type="text" class="form-control" name="new_password" id="newPassword" minlength="6" required>
                            <small class="text-muted">Minimum 6 characters.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Confirm New Password</label>
                            <input type="text" class="form-control" name="confirm_password" minlength="6" required>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="generatePwd()"><i class="bi bi-shuffle me-1"></i>Generate Random Password</button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger mt-4"><i class="bi bi-key me-1"></i>Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function generatePwd() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$';
    var pwd = '';
    for (var i = 0; i < 12; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    document.querySelector('input[name=new_password]').value = pwd;
    document.querySelector('input[name=confirm_password]').value = pwd;
}
</script>
</body>
</html>
