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

// Dashboard Stats
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'pending'");
$stats['pending_profiles'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_premium = 1");
$stats['premium_users'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['new_users_30d'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM connection_requests WHERE status = 'accepted'");
$stats['successful_connections'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM subscriptions WHERE status = 'active'");
$stats['total_revenue'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'");
$stats['pending_reports'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['active_users_7d'] = $stmt->fetch()['count'];

// Recent registrations
$stmt = $pdo->query("SELECT id, profile_id, name, email, gender, religion, status, created_at FROM users ORDER BY created_at DESC LIMIT 10");
$recentUsers = $stmt->fetchAll();

// Pending reports
$stmt = $pdo->query(
    "SELECT r.*, u1.name as reporter_name, u2.name as reported_name 
     FROM reports r 
     JOIN users u1 ON r.reporter_id = u1.id 
     JOIN users u2 ON r.reported_id = u2.id 
     WHERE r.status = 'pending' 
     ORDER BY r.created_at DESC LIMIT 5"
);
$pendingReports = $stmt->fetchAll();

$adminPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <!-- Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Dashboard</h4>
        <div>
            <span class="text-muted me-3">Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-primary"><i class="bi bi-people"></i></div>
                <div>
                    <h4 class="mb-0"><?= number_format($stats['total_users']) ?></h4>
                    <small class="text-muted">Total Users</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-warning"><i class="bi bi-clock-history"></i></div>
                <div>
                    <h4 class="mb-0"><?= $stats['pending_profiles'] ?></h4>
                    <small class="text-muted">Pending Approvals</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-success"><i class="bi bi-star"></i></div>
                <div>
                    <h4 class="mb-0"><?= $stats['premium_users'] ?></h4>
                    <small class="text-muted">Premium Users</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-info"><i class="bi bi-currency-rupee"></i></div>
                <div>
                    <h4 class="mb-0">₹<?= number_format($stats['total_revenue']) ?></h4>
                    <small class="text-muted">Total Revenue</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;"><i class="bi bi-person-plus"></i></div>
                <div>
                    <h4 class="mb-0"><?= $stats['new_users_30d'] ?></h4>
                    <small class="text-muted">New Users (30d)</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-success"><i class="bi bi-heart"></i></div>
                <div>
                    <h4 class="mb-0"><?= $stats['successful_connections'] ?></h4>
                    <small class="text-muted">Connections Made</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-warning"><i class="bi bi-flag"></i></div>
                <div>
                    <h4 class="mb-0"><?= $stats['pending_reports'] ?></h4>
                    <small class="text-muted">Pending Reports</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-info"><i class="bi bi-activity"></i></div>
                <div>
                    <h4 class="mb-0"><?= $stats['active_users_7d'] ?></h4>
                    <small class="text-muted">Active Users (7d)</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Registrations -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent Registrations</h5>
            <a href="profiles.php" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Profile ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Gender</th>
                            <th>Religion</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><strong><?= $user['profile_id'] ?></strong></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= $user['gender'] ?></td>
                            <td><?= htmlspecialchars($user['religion'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?> status-badge">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?php if ($user['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success btn-approve-profile" data-user-id="<?= $user['id'] ?>"><i class="bi bi-check"></i></button>
                                    <button class="btn btn-sm btn-danger btn-reject-profile" data-user-id="<?= $user['id'] ?>"><i class="bi bi-x"></i></button>
                                <?php endif; ?>
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pending Reports -->
    <?php if (!empty($pendingReports)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Pending Reports</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reporter</th>
                            <th>Reported User</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingReports as $report): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['reporter_name']) ?></td>
                            <td><?= htmlspecialchars($report['reported_name']) ?></td>
                            <td><?= htmlspecialchars($report['reason']) ?></td>
                            <td><?= date('d M Y', strtotime($report['created_at'])) ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $report['reported_id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">View Profile</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
