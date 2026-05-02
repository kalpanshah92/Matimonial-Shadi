<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'subscriptions';

// Stats
$stmt = $pdo->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM subscriptions WHERE status = 'active'");
$subStats = $stmt->fetch();

$stmt = $pdo->query(
    "SELECT s.*, u.name, u.profile_id, u.email, p.name as plan_name 
     FROM subscriptions s 
     JOIN users u ON s.user_id = u.id 
     JOIN plans p ON s.plan_id = p.id 
     ORDER BY s.created_at DESC LIMIT 50"
);
$subscriptions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <h4 class="mb-4">Subscriptions & Payments</h4>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="admin-stat-card">
                <div class="stat-icon stat-success"><i class="bi bi-credit-card"></i></div>
                <div>
                    <h4 class="mb-0"><?= $subStats['count'] ?></h4>
                    <small class="text-muted">Active Subscriptions</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-stat-card">
                <div class="stat-icon stat-info"><i class="bi bi-currency-rupee"></i></div>
                <div>
                    <h4 class="mb-0">₹<?= number_format($subStats['total']) ?></h4>
                    <small class="text-muted">Total Revenue</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Recent Subscriptions</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Payment ID</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subscriptions as $sub): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($sub['name']) ?></strong>
                                <small class="d-block text-muted"><?= $sub['profile_id'] ?></small>
                            </td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($sub['plan_name']) ?></span></td>
                            <td>₹<?= number_format($sub['amount']) ?></td>
                            <td><small><?= htmlspecialchars($sub['payment_id'] ?? '-') ?></small></td>
                            <td><?= date('d M Y', strtotime($sub['start_date'])) ?></td>
                            <td><?= date('d M Y', strtotime($sub['end_date'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'expired' ? 'secondary' : 'danger') ?>">
                                    <?= ucfirst($sub['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($subscriptions)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No subscriptions yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
