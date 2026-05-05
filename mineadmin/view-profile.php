<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

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
                        <div class="col-md-4"><strong>Location:</strong> <?= htmlspecialchars(($user['city'] ? $user['city'] . ', ' : '') . ($user['state'] ?? '')) ?></div>
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
                        <div class="col-md-6"><strong>Phone:</strong> +91 <?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></div>
                    </div>
                </div>
            </div>

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
                        <div class="col-md-4"><strong>Family Values:</strong> <?= htmlspecialchars($user['family_values'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Brothers:</strong> <?= isset($user['brothers']) ? ($user['brothers'] . ' (' . ($user['brothers_married'] ?? 0) . ' married)') : '-' ?></div>
                        <div class="col-md-4"><strong>Sisters:</strong> <?= isset($user['sisters']) ? ($user['sisters'] . ' (' . ($user['sisters_married'] ?? 0) . ' married)') : '-' ?></div>
                        <div class="col-md-4"><strong>Gotra:</strong> <?= htmlspecialchars($user['gotra'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Family Income:</strong> <?= htmlspecialchars($user['family_income'] ?? '-') ?></div>
                        <div class="col-md-4"><strong>Family Location:</strong> <?= htmlspecialchars($user['family_location'] ?? '-') ?></div>
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
                        <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin' && !$isPremium): ?>
                            <button class="btn btn-warning btn-upgrade-premium" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars($user['name']) ?>">
                                <i class="bi bi-star me-1"></i>Upgrade to Premium
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Account Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Account Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-2"><strong>Active:</strong> <?= $user['is_active'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>' ?></div>
                    <div class="mb-2"><strong>Status:</strong> <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?>"><?= ucfirst($user['status']) ?></span></div>
                    <div class="mb-2"><strong>Verified:</strong> <?= $user['is_verified'] ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>' ?></div>
                    <div><strong>Premium:</strong> <?= $isPremium ? '<span class="text-warning">Yes</span>' : '<span class="text-muted">No</span>' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Premium Upgrade Modal -->
<div class="modal fade" id="premiumUpgradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upgrade to Premium</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="premiumUpgradeForm">
                    <input type="hidden" name="user_id" id="premiumUserId">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <input type="text" class="form-control" id="premiumUserName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Premium Until</label>
                        <input type="date" class="form-control" name="end_date" id="premiumEndDate" required>
                        <small class="text-muted">Select the date when premium access should expire</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Plan</label>
                        <select class="form-select" name="plan_id" id="premiumPlanId" required>
                            <?php
                            $planStmt = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC");
                            $plans = $planStmt->fetchAll();
                            foreach ($plans as $plan):
                            ?>
                                <option value="<?= $plan['id'] ?>" data-duration="<?= $plan['duration_days'] ?>">
                                    <?= htmlspecialchars($plan['name']) ?> - ₹<?= number_format($plan['price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnConfirmPremiumUpgrade">Upgrade to Premium</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit End Date Modal -->
<div class="modal fade" id="editEndDateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Premium End Date</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editEndDateForm">
                    <input type="hidden" name="user_id" id="editEndDateUserId">
                    <div class="mb-3">
                        <label class="form-label">New End Date</label>
                        <input type="date" class="form-control" name="end_date" id="editEndDateValue" required>
                        <small class="text-muted">Select the new expiration date for premium access</small>
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

// Premium upgrade modal
var premiumModal;
$(document).ready(function() {
    premiumModal = new bootstrap.Modal(document.getElementById('premiumUpgradeModal'));
    
    $('.btn-upgrade-premium').click(function() {
        var userId = $(this).data('user-id');
        var userName = $(this).data('user-name');
        
        $('#premiumUserId').val(userId);
        $('#premiumUserName').val(userName);
        
        // Set default end date to 2 years from now
        var defaultDate = new Date();
        defaultDate.setFullYear(defaultDate.getFullYear() + 2);
        $('#premiumEndDate').val(defaultDate.toISOString().split('T')[0]);
        
        premiumModal.show();
    });
    
    $('#premiumPlanId').change(function() {
        var duration = $(this).find(':selected').data('duration');
        var endDate = new Date();
        endDate.setDate(endDate.getDate() + parseInt(duration));
        $('#premiumEndDate').val(endDate.toISOString().split('T')[0]);
    });
    
    $('#btnConfirmPremiumUpgrade').click(function() {
        var formData = $('#premiumUpgradeForm').serialize();
        formData += '&action=upgrade_premium';
        
        $.post('api/profiles.php', formData, function(response) {
            if (response.success) {
                alert('User upgraded to premium successfully!');
                premiumModal.hide();
                location.reload();
            } else {
                alert(response.message || 'Failed to upgrade user to premium.');
            }
        }, 'json').fail(function() {
            alert('Failed to process request.');
        });
    });

    // Edit End Date modal
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
                    alert('End date updated successfully!');
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
});
</script>
</body>
</html>
