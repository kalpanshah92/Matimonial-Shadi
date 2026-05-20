<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/AccountEntitlement.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'profiles';

$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    header('Location: profiles.php');
    exit;
}

// Get user profile data
$stmt = $pdo->prepare(
    "SELECT u.*, pd.*, f.* FROM users u 
     LEFT JOIN profile_details pd ON u.id = pd.user_id 
     LEFT JOIN family_details f ON u.id = f.user_id 
     WHERE u.id = ?"
);
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: profiles.php');
    exit;
}

// Check premium status (use is_premium flag from users table for consistency with profiles.php)
$isPremium = $user['is_premium'] ? true : false;

// Get account entitlement info
$userEntitlement = AccountEntitlement::forUser($userId);
$accountExpiryDate = $userEntitlement->getExpiryDate();
$isAccountExpired = $userEntitlement->isExpired();
$isInGracePeriod = $userEntitlement->isInGracePeriod();
$daysUntilExpiry = $userEntitlement->daysUntilExpiry();

// Get partner preferences
$stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
$stmt->execute([$userId]);
$partnerPrefs = $stmt->fetch() ?: [];

// Get privacy settings
$stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$privacy = $stmt->fetch() ?: [];

// Get subscription info (if premium)
if ($isPremium) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
    $stmt->execute([$userId]);
    $subscription = $stmt->fetch();
} else {
    $subscription = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">User Profile</h4>
        <a href="profiles.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Profiles</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Profile Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <?php if (!empty($user['profile_pic'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/profiles/<?= $user['profile_pic'] ?>" class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                    <i class="bi bi-person text-white" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-9">
                            <h4><?= htmlspecialchars($user['name']) ?></h4>
                            <p class="text-muted mb-1">ID: <?= $user['profile_id'] ?></p>
                            <div class="mb-2">
                                <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                                <?php if ($isPremium): ?>
                                    <span class="badge bg-warning">Premium</span>
                                <?php endif; ?>
                                <?php if ($user['is_verified']): ?>
                                    <span class="badge bg-info">Verified</span>
                                <?php endif; ?>
                            </div>
                            <p class="mb-0"><i class="bi bi-calendar me-1"></i>Joined: <?= date('d M Y', strtotime($user['created_at'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Basic Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Basic Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Age:</strong> <?= calculateAge($user['dob']) ?> years</div>
                        <div class="col-md-4"><strong>Date of Birth:</strong> <?= date('d M Y', strtotime($user['dob'])) ?></div>
                        <div class="col-md-4"><strong>Gender:</strong> <?= $user['gender'] ?></div>
                        <div class="col-md-4"><strong>Height:</strong> <?= formatHeight($user['height']) ?></div>
                        <div class="col-md-4"><strong>Religion:</strong> <?= htmlspecialchars($user['religion'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Samaj:</strong> <?= htmlspecialchars($user['caste'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Sub Samaj:</strong> <?= htmlspecialchars($user['sub_caste'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Mother Tongue:</strong> <?= htmlspecialchars($user['mother_tongue'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>City:</strong> <?= htmlspecialchars($user['city'] ?? '-') ?: '-' ?></div>
                        <div class="col-md-4"><strong>State:</strong> <?= htmlspecialchars($user['state'] ?? '-') ?: '-' ?></div>
                        <div class="col-md-4"><strong>Country:</strong> <?= htmlspecialchars($user['country'] ?? '-') ?: '-' ?></div>
                        <div class="col-md-8"><strong>Address:</strong> <?= htmlspecialchars($user['address'] ?? '') ?: '-' ?></div>
                        <div class="col-md-4"><strong>Property Status:</strong> <?= htmlspecialchars($user['address_type'] ?? '') ?: '-' ?></div>
                    </div>
                </div>
            </div>

            <!-- Contact Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-telephone me-2"></i>Contact Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></div>
                        <div class="col-md-6"><strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                    </div>
                </div>
            </div>

            <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
            <!-- Photo ID - Documentation (Super Admin only) -->
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Photo ID - Documentation</h5>
                    <span class="badge bg-dark">Super Admin Only</span>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['doc_deleted'])): ?>
                        <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>Document removed.</div>
                    <?php endif; ?>
                    <?php if (!empty($user['id_document'])): ?>
                        <p class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                            Document uploaded
                            <?php if (!empty($user['id_document_uploaded_at'])): ?>
                                on <?= date('d M Y, h:i A', strtotime($user['id_document_uploaded_at'])) ?>
                            <?php endif; ?>.
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="document.php?action=view&user_id=<?= $userId ?>" target="_blank" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="document.php?action=download&user_id=<?= $userId ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                            <form method="POST" action="document.php" class="d-inline" onsubmit="return confirm('Remove this document permanently?');">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="user_id" value="<?= $userId ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i>Remove
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>No document uploaded by this user.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Address Proof - Documentation (Super Admin only) -->
            <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Address Proof - Documentation</h5>
                    <span class="badge bg-dark">Super Admin Only</span>
                </div>
                <div class="card-body">
                    <?php if (isset($_GET['address_proof_deleted'])): ?>
                        <div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i>Document removed.</div>
                    <?php endif; ?>
                    <?php if (!empty($user['address_proof_document'])): ?>
                        <p class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                            Document uploaded
                            <?php if (!empty($user['address_proof_uploaded_at'])): ?>
                                on <?= date('d M Y, h:i A', strtotime($user['address_proof_uploaded_at'])) ?>
                            <?php endif; ?>.
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="address-proof.php?action=view&user_id=<?= $userId ?>" target="_blank" class="btn btn-primary btn-sm">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="address-proof.php?action=download&user_id=<?= $userId ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                            <form method="POST" action="address-proof.php" class="d-inline" onsubmit="return confirm('Remove this document permanently?');">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="user_id" value="<?= $userId ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i>Remove
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>No document uploaded by this user.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Personal Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Personal Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Marital Status:</strong> <?= htmlspecialchars($user['marital_status'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Complexion:</strong> <?= htmlspecialchars($user['complexion'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Body Type:</strong> <?= htmlspecialchars($user['body_type'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Weight:</strong> <?= $user['weight'] ? $user['weight'] . ' kg' : '-' ?></div>
                        <div class="col-md-4"><strong>Blood Group:</strong> <?= htmlspecialchars($user['blood_group'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Disability:</strong> <?= ($user['disability'] && $user['disability'] !== 'None') ? htmlspecialchars($user['disability']) : 'None' ?></div>
                        <div class="col-md-4"><strong>Diet:</strong> <?= htmlspecialchars($user['diet'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Smoking:</strong> <?= htmlspecialchars($user['smoking'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Drinking:</strong> <?= htmlspecialchars($user['drinking'] ?? '-') ?></div>
                        <div class="col-12"><strong>Hobbies:</strong> <?= nl2br(htmlspecialchars($user['hobbies'] ?? 'Not provided')) ?></div>
                    </div>
                </div>
            </div>

            <!-- Professional Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Professional Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Education:</strong> <?= htmlspecialchars($user['education'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Education Detail:</strong> <?= htmlspecialchars($user['education_detail'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Occupation:</strong> <?= htmlspecialchars($user['occupation'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Occupation Detail:</strong> <?= htmlspecialchars($user['occupation_detail'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Annual Income:</strong> <?= htmlspecialchars($user['annual_income'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Company:</strong> <?= htmlspecialchars($user['company'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Working City:</strong> <?= htmlspecialchars($user['working_city'] ?? '-') ?></div>
                    </div>
                </div>
            </div>

            <!-- Family Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Family Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Father's Name:</strong> <?= htmlspecialchars($user['father_name'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Father's Occupation:</strong> <?= htmlspecialchars($user['father_occupation'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Mother's Name:</strong> <?= htmlspecialchars($user['mother_name'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Mother's Occupation:</strong> <?= htmlspecialchars($user['mother_occupation'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Family Type:</strong> <?= htmlspecialchars($user['family_type'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Family Status:</strong> <?= htmlspecialchars($user['family_status'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Brothers:</strong> <?= isset($user['brothers']) ? ($user['brothers'] . ' (' . ($user['brothers_married'] ?? 0) . ' married)') : '-' ?></div>
                        <div class="col-md-4"><strong>Sisters:</strong> <?= isset($user['sisters']) ? ($user['sisters'] . ' (' . ($user['sisters_married'] ?? 0) . ' married)') : '-' ?></div>
                        <div class="col-md-4"><strong>Gotra:</strong> <?= htmlspecialchars($user['gotra'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Family Income:</strong> <?= htmlspecialchars($user['family_income'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Family Location:</strong> <?= htmlspecialchars($user['family_location'] ?? '-') ?></div>
                        <div class="col-md-8"><strong>Parents Address:</strong> <?= htmlspecialchars($user['parents_address'] ?? '') ?: '-' ?></div>
                        <div class="col-md-4"><strong>Property Status:</strong> <?= htmlspecialchars($user['parents_address_type'] ?? '') ?: '-' ?></div>
                        <div class="col-12"><strong>About Family:</strong> <?= nl2br(htmlspecialchars($user['about_family'] ?? 'Not provided')) ?></div>
                    </div>
                </div>
            </div>

            <!-- About -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">About</h5>
                </div>
                <div class="card-body">
                    <p><?= nl2br(htmlspecialchars($user['about_me'] ?? 'Not provided')) ?></p>
                </div>
            </div>

            <!-- Partner Preferences -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Partner Preferences</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Age:</strong> <?= ($partnerPrefs['min_age'] ?? 18) ?> - <?= ($partnerPrefs['max_age'] ?? 60) ?> years</div>
                        <div class="col-md-4"><strong>Height:</strong> <?= !empty($partnerPrefs['min_height']) || !empty($partnerPrefs['max_height']) ? formatHeight($partnerPrefs['min_height']) . ' - ' . formatHeight($partnerPrefs['max_height']) : '-' ?></div>
                        <div class="col-md-4"><strong>Marital Status:</strong> <?= htmlspecialchars($partnerPrefs['marital_status'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Religion:</strong> <?= htmlspecialchars($partnerPrefs['religion'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Samaj:</strong> <?= htmlspecialchars($partnerPrefs['caste'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Mother Tongue:</strong> <?= htmlspecialchars($partnerPrefs['mother_tongue'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Education:</strong> <?= htmlspecialchars($partnerPrefs['education'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Occupation:</strong> <?= htmlspecialchars($partnerPrefs['occupation'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Income Range:</strong> <?= !empty($partnerPrefs['min_income']) || !empty($partnerPrefs['max_income']) ? htmlspecialchars($partnerPrefs['min_income'] ?? 'Any') . ' - ' . htmlspecialchars($partnerPrefs['max_income'] ?? 'Any') : '-' ?></div>
                        <div class="col-md-4"><strong>State:</strong> <?= htmlspecialchars($partnerPrefs['state'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Diet:</strong> <?= htmlspecialchars($partnerPrefs['diet'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Smoking:</strong> <?= htmlspecialchars($partnerPrefs['smoking'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Drinking:</strong> <?= htmlspecialchars($partnerPrefs['drinking'] ?? '-') ?></div>
                        <div class="col-12"><strong>About Partner:</strong> <?= nl2br(htmlspecialchars($partnerPrefs['about_partner'] ?? 'Not provided')) ?></div>
                    </div>
                </div>
            </div>

            <!-- Privacy Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Privacy Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Show Phone:</strong> <?= ucfirst($privacy['show_phone'] ?? 'connected') ?></div>
                        <div class="col-md-4"><strong>Show Email:</strong> <?= ucfirst($privacy['show_email'] ?? 'everyone') ?></div>
                        <div class="col-md-4"><strong>Show Photo:</strong> <?= ucfirst($privacy['show_photo'] ?? 'everyone') ?></div>
                        <div class="col-md-4"><strong>Show Income:</strong> <?= ucfirst($privacy['show_income'] ?? 'everyone') ?></div>
                        <div class="col-md-4"><strong>Profile Visibility:</strong> <?= ucfirst($privacy['profile_visibility'] ?? 'everyone') ?></div>
                        <div class="col-md-4"><strong>Allow Messages:</strong> <?= ucfirst($privacy['allow_messages'] ?? 'connected') ?></div>
                    </div>
                </div>
            </div>

            <!-- Subscription Info (if premium) -->
            <?php if ($isPremium): ?>
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Premium Subscription</h5>
                    <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                        <button class="btn btn-sm btn-dark" id="btnEditEndDate" data-user-id="<?= $user['id'] ?>" data-current-end-date="<?= $subscription ? $subscription['end_date'] : '' ?>">
                            <i class="bi bi-pencil-square me-1"></i>Edit End Date
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($subscription): ?>
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Plan:</strong> <?= htmlspecialchars($subscription['plan_id']) ?></div>
                        <div class="col-md-4"><strong>Start Date:</strong> <?= date('d M Y', strtotime($subscription['start_date'])) ?></div>
                        <div class="col-md-4">
                            <strong>End Date:</strong>
                            <span id="displayEndDate"><?= date('d M Y', strtotime($subscription['end_date'])) ?></span>
                        </div>
                        <div class="col-md-4"><strong>Status:</strong> <?= ucfirst($subscription['status']) ?></div>
                        <div class="col-md-4"><strong>Payment Method:</strong> <?= ucfirst($subscription['payment_method'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Amount:</strong> ₹<?= number_format($subscription['amount']) ?></div>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0">No subscription record found. Premium status is set via database flag.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="edit-user.php?id=<?= $user['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-pencil-square me-1"></i>Edit User
                        </a>
                        <?php if ($user['status'] !== 'approved'): ?>
                            <button class="btn btn-success btn-approve-profile" data-user-id="<?= $user['id'] ?>">
                                <i class="bi bi-check-lg me-1"></i>Approve Profile
                            </button>
                        <?php endif; ?>
                        <?php if ($user['status'] !== 'rejected'): ?>
                            <button class="btn btn-danger btn-reject-profile" data-user-id="<?= $user['id'] ?>">
                                <i class="bi bi-x-lg me-1"></i>Reject Profile
                            </button>
                        <?php endif; ?>
                        <?php if (!$user['is_verified']): ?>
                            <button class="btn btn-info" onclick="verifyProfile(<?= $user['id'] ?>)">
                                <i class="bi bi-patch-check me-1"></i>Verify Profile
                            </button>
                        <?php endif; ?>
                        <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                            <hr class="my-2">
                            <button type="button"
                                    class="btn btn-outline-danger btn-delete-profile"
                                    data-user-id="<?= (int)$user['id'] ?>"
                                    data-user-name="<?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-profile-id="<?= htmlspecialchars($user['profile_id'], ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-trash3 me-1"></i>Delete Profile
                            </button>
                            <small class="text-muted text-center d-block mt-1">
                                Permanently removes the user and all linked data (matches, chat history, photos, documents).
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Account Status</h5>
                    <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-success" id="btnExtendAccountQuick"
                                    data-user-id="<?= $user['id'] ?>">
                                <i class="bi bi-calendar-plus me-1"></i>Quick Extend
                            </button>
                            <button class="btn btn-outline-primary" id="btnEditAccountExpiry"
                                    data-user-id="<?= $user['id'] ?>"
                                    data-current-expiry="<?= $accountExpiryDate ?>">
                                <i class="bi bi-pencil me-1"></i>Edit Expiry
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Active:</strong> <?= $user['is_active'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>' ?></div>
                    <div class="mb-2"><strong>Status:</strong> <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= ucfirst($user['status']) ?></span></div>
                    <div class="mb-2"><strong>Verified:</strong> <?= $user['is_verified'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>' ?></div>
                    <div class="mb-2"><strong>Premium:</strong> <?= $isPremium ? '<span class="text-warning">Yes</span>' : '<span class="text-muted">No</span>' ?></div>
                    <div class="mb-2">
                        <strong>Account Expiry:</strong>
                        <?php if ($accountExpiryDate): ?>
                            <span class="<?= $isAccountExpired ? 'text-danger fw-bold' : ($isInGracePeriod ? 'text-warning fw-bold' : 'text-success') ?>">
                                <?= date('d M Y', strtotime($accountExpiryDate)) ?>
                                <?php if ($isAccountExpired): ?>
                                    <span class="badge bg-danger ms-1">Expired</span>
                                <?php elseif ($isInGracePeriod): ?>
                                    <span class="badge bg-warning text-dark ms-1">Grace Period</span>
                                <?php elseif ($daysUntilExpiry <= 30): ?>
                                    <span class="badge bg-info ms-1"><?= $daysUntilExpiry ?> days left</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">Not set</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Premium End Date Modal -->
<div class="modal fade" id="editEndDateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Premium Subscription End Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editEndDateForm">
                    <input type="hidden" name="user_id" id="editEndDateUserId">
                    <div class="mb-3">
                        <label class="form-label">New Premium End Date</label>
                        <input type="date" class="form-control" name="end_date" id="editEndDateValue" required>
                        <small class="text-muted">Select the new expiration date for premium subscription</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveEndDate">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Account Expiry Modal -->
<div class="modal fade" id="editAccountExpiryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Account Expiry Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editAccountExpiryForm">
                    <input type="hidden" name="user_id" id="editAccountExpiryUserId">
                    <div class="mb-3">
                        <label class="form-label">New Account Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date" id="editAccountExpiryValue" required>
                        <small class="text-muted">This controls when the user account expires and loses access to search/chat features</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Note (optional)</label>
                        <textarea class="form-control" name="admin_note" id="editAccountExpiryNote" rows="2" placeholder="Reason for change..."></textarea>
                        <small class="text-muted">This will be recorded in the audit log</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveAccountExpiry">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Extend Account Quick Actions -->
<div class="modal fade" id="extendAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Extend Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="extendAccountForm">
                    <input type="hidden" name="user_id" id="extendAccountUserId">
                    <div class="mb-3">
                        <label class="form-label">Extension Duration</label>
                        <select class="form-select" name="days" id="extendAccountDays" required>
                            <option value="365">1 Year (+365 days)</option>
                            <option value="730" selected>2 Years (+730 days)</option>
                            <option value="1095">3 Years (+1095 days)</option>
                        </select>
                        <small class="text-muted">Adds days to current expiry (or from today if expired)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Note (optional)</label>
                        <textarea class="form-control" name="admin_note" id="extendAccountNote" rows="2" placeholder="Reason for extension..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnExtendAccount">
                    <i class="bi bi-calendar-plus me-1"></i>Extend Account
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
function verifyProfile(userId) {
    if (confirm('Verify this profile?')) {
        $.post('api/profiles.php', { action: 'verify', user_id: userId }, function(response) {
            if (response.success) location.reload();
        }, 'json');
    }
}

// Delete profile (super admin) — two-step confirm to avoid accidents.
$(document).on('click', '.btn-delete-profile', function () {
    var btn       = $(this);
    var userId    = btn.attr('data-user-id');
    var userName  = btn.attr('data-user-name') || '';
    // Use .attr() not .data() — jQuery's .data() coerces numeric-looking strings
    // (e.g. "021272") to Numbers and strips leading zeros.
    var profileId = String(btn.attr('data-profile-id') || '').trim();
    // Normalise: strip whitespace and compare case-insensitively.
    var norm = function (s) { return String(s || '').replace(/\s+/g, '').toUpperCase(); };

    if (!confirm('Are you sure?\n\nThis will PERMANENTLY delete "' + userName + '" (' + profileId + ') and ALL linked data:\n  • Profile + photos + documents\n  • Matches / interests / shortlist\n  • Chat history\n  • Notifications & subscriptions\n\nThis action CANNOT be undone.')) {
        return;
    }
    // Second prompt makes the admin retype the profile ID — defence against muscle-memory clicks.
    var typed = prompt('Type the Profile ID exactly to confirm deletion (e.g. ' + profileId + '):');
    if (typed === null) return;
    if (norm(typed) !== norm(profileId)) {
        alert('Profile ID did not match. Deletion cancelled.\n\nExpected: ' + profileId + '\nReceived: ' + typed);
        return;
    }

    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Deleting...');

    $.ajax({
        url: 'api/profiles.php',
        method: 'POST',
        data: { action: 'delete_profile', user_id: userId },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                alert('Profile deleted.');
                window.location.href = 'profiles.php';
            } else {
                alert(response.message || 'Failed to delete profile.');
                btn.prop('disabled', false).html('<i class="bi bi-trash3 me-1"></i>Delete Profile');
            }
        },
        error: function () {
            alert('Request failed. Please try again.');
            btn.prop('disabled', false).html('<i class="bi bi-trash3 me-1"></i>Delete Profile');
        }
    });
});

// Edit Premium End Date modal
var editEndDateModal;
$(document).ready(function() {
    editEndDateModal = new bootstrap.Modal(document.getElementById('editEndDateModal'));

    $('#btnEditEndDate').click(function() {
        var userId = $(this).data('user-id');
        var currentEndDate = $(this).data('current-end-date');

        $('#editEndDateUserId').val(userId);
        $('#editEndDateValue').val(currentEndDate);

        editEndDateModal.show();
    });

    $('#btnSaveEndDate').click(function() {
        var formData = $('#editEndDateForm').serialize();

        $.post('api/profiles.php', formData + '&action=update_end_date', function(response) {
            if (response.success) {
                alert('Premium end date updated successfully!');
                editEndDateModal.hide();
                location.reload();
            } else {
                alert(response.message || 'Failed to update end date.');
            }
        }, 'json').fail(function() {
            alert('Failed to process request.');
        });
    });
});

// Edit Account Expiry modal
var editAccountExpiryModal;
$(document).ready(function() {
    editAccountExpiryModal = new bootstrap.Modal(document.getElementById('editAccountExpiryModal'));

    $('#btnEditAccountExpiry').click(function() {
        var userId = $(this).data('user-id');
        var currentExpiry = $(this).data('current-expiry');

        $('#editAccountExpiryUserId').val(userId);
        $('#editAccountExpiryValue').val(currentExpiry);

        editAccountExpiryModal.show();
    });

    $('#btnSaveAccountExpiry').click(function() {
        var formData = $('#editAccountExpiryForm').serialize();

        $.post('api/profiles.php', formData + '&action=update_account_expiry', function(response) {
            if (response.success) {
                alert('Account expiry updated successfully!');
                editAccountExpiryModal.hide();
                location.reload();
            } else {
                alert(response.message || 'Failed to update account expiry.');
            }
        }, 'json').fail(function() {
            alert('Failed to process request.');
        });
    });
});

// Extend Account Quick modal
var extendAccountModal;
$(document).ready(function() {
    extendAccountModal = new bootstrap.Modal(document.getElementById('extendAccountModal'));

    $('#btnExtendAccountQuick').click(function() {
        var userId = $(this).data('user-id');

        $('#extendAccountUserId').val(userId);

        extendAccountModal.show();
    });

    $('#btnExtendAccount').click(function() {
        var formData = $('#extendAccountForm').serialize();

        $.post('api/profiles.php', formData + '&action=extend_account', function(response) {
            if (response.success) {
                alert('Account extended successfully! New expiry: ' + response.new_expiry);
                extendAccountModal.hide();
                location.reload();
            } else {
                alert(response.message || 'Failed to extend account.');
            }
        }, 'json').fail(function() {
            alert('Failed to process request.');
        });
    });
});
</script>
</body>
</html>
