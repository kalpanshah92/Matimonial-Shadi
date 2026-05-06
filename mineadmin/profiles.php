<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'profiles';

// Filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$gender = $_GET['gender'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ADMIN_RESULTS_PER_PAGE;

$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "u.status = ?";
    $params[] = $status;
}
if ($gender) {
    $where[] = "u.gender = ?";
    $params[] = $gender;
}
if ($search) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.profile_id LIKE ? OR u.phone LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users u WHERE $whereClause");
$stmt->execute($params);
$totalUsers = $stmt->fetch()['count'];
$totalPages = ceil($totalUsers / ADMIN_RESULTS_PER_PAGE);

$stmt = $pdo->prepare(
    "SELECT u.*, pd.education, pd.occupation FROM users u 
     LEFT JOIN profile_details pd ON u.id = pd.user_id 
     WHERE $whereClause ORDER BY u.status = 'pending' DESC, u.created_at DESC 
     LIMIT " . ADMIN_RESULTS_PER_PAGE . " OFFSET $offset"
);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profiles | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Manage Profiles (<?= number_format($totalUsers) ?>)</h4>
    </div>

    <?php if (($_GET['error'] ?? '') === 'permission'): ?>
        <div class="alert alert-warning"><i class="bi bi-shield-exclamation me-1"></i>Only Super Admins can edit user profiles or reset passwords.</div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, email, ID, phone...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="">All</option>
                        <option value="Male" <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="profiles.php" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Profile</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Gender</th>
                            <th>Religion</th>
                            <th>Education</th>
                            <th>Premium</th>
                            <th>Verified</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?= $user['profile_id'] ?></strong></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td>
                                <small><?= htmlspecialchars($user['email']) ?></small><br>
                                <small class="text-muted"><?= $user['phone'] ?? '-' ?></small>
                            </td>
                            <td><?= $user['gender'] ?></td>
                            <td><?= htmlspecialchars($user['religion'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['education'] ?? '-') ?></td>
                            <td><?= $user['is_premium'] ? '<span class="badge bg-warning">Yes</span>' : '-' ?></td>
                            <td><?= $user['is_verified'] ? '<span class="badge bg-info">Yes</span>' : '-' ?></td>
                            <td>
                                <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?> status-badge">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </td>
                            <td><small><?= date('d M Y', strtotime($user['created_at'])) ?></small></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($user['status'] !== 'approved'): ?>
                                        <button class="btn btn-success btn-approve-profile" data-user-id="<?= $user['id'] ?>" title="Approve">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($user['status'] !== 'rejected'): ?>
                                        <button class="btn btn-danger btn-reject-profile" data-user-id="<?= $user['id'] ?>" title="Reject">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!$user['is_verified']): ?>
                                        <button class="btn btn-info" onclick="verifyProfile(<?= $user['id'] ?>)" title="Verify">
                                            <i class="bi bi-patch-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="view-profile.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
                                        <button class="btn btn-warning btn-upgrade-premium" data-user-id="<?= $user['id'] ?>" data-user-name="<?= htmlspecialchars($user['name']) ?>" title="Upgrade to Premium">
                                            <i class="bi bi-star"></i>
                                        </button>
                                        <a href="edit-user.php?id=<?= $user['id'] ?>" class="btn btn-outline-dark" title="Edit user">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
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
});
</script>
</body>
</html>
