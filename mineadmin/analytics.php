<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'analytics';

// Gender distribution
$stmt = $pdo->query("SELECT gender, COUNT(*) as count FROM users GROUP BY gender");
$genderStats = $stmt->fetchAll();

// Religion distribution
$stmt = $pdo->query("SELECT religion, COUNT(*) as count FROM users WHERE religion IS NOT NULL GROUP BY religion ORDER BY count DESC LIMIT 10");
$religionStats = $stmt->fetchAll();

// Registration trend (last 12 months)
$stmt = $pdo->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
     FROM users 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) 
     GROUP BY month ORDER BY month"
);
$monthlyRegistrations = $stmt->fetchAll();

// State distribution
$stmt = $pdo->query("SELECT state, COUNT(*) as count FROM users WHERE state IS NOT NULL GROUP BY state ORDER BY count DESC LIMIT 10");
$stateStats = $stmt->fetchAll();

// Active vs Inactive
$stmt = $pdo->query("SELECT is_active, COUNT(*) as count FROM users GROUP BY is_active");
$activityStats = $stmt->fetchAll();

// Popular searches (from profile visits)
$stmt = $pdo->query("SELECT COUNT(*) as total_visits FROM profile_visits");
$totalVisits = $stmt->fetch()['total_visits'];

$stmt = $pdo->query("SELECT COUNT(*) as total_messages FROM messages");
$totalMessages = $stmt->fetch()['total_messages'];

$stmt = $pdo->query("SELECT COUNT(*) as total_connections FROM connection_requests WHERE status = 'accepted'");
$totalConnections = $stmt->fetch()['total_connections'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <h4 class="mb-4">Analytics & Reports</h4>

    <!-- Key Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-primary"><i class="bi bi-eye"></i></div>
                <div>
                    <h4 class="mb-0"><?= number_format($totalVisits) ?></h4>
                    <small class="text-muted">Total Profile Views</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-success"><i class="bi bi-chat-dots"></i></div>
                <div>
                    <h4 class="mb-0"><?= number_format($totalMessages) ?></h4>
                    <small class="text-muted">Total Messages</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="admin-stat-card">
                <div class="stat-icon stat-warning"><i class="bi bi-heart"></i></div>
                <div>
                    <h4 class="mb-0"><?= number_format($totalConnections) ?></h4>
                    <small class="text-muted">Successful Connections</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Gender Distribution -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Gender Distribution</h5></div>
                <div class="card-body">
                    <table class="table">
                        <?php foreach ($genderStats as $gs): ?>
                        <tr>
                            <td><strong><?= $gs['gender'] ?></strong></td>
                            <td><?= number_format($gs['count']) ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <?php $total = array_sum(array_column($genderStats, 'count')); $pct = $total > 0 ? round(($gs['count'] / $total) * 100) : 0; ?>
                                    <div class="progress-bar bg-<?= $gs['gender'] === 'Male' ? 'primary' : 'danger' ?>" style="width: <?= $pct ?>%"><?= $pct ?>%</div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Religion Distribution -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Religion Distribution</h5></div>
                <div class="card-body">
                    <table class="table">
                        <?php $relTotal = array_sum(array_column($religionStats, 'count')); ?>
                        <?php foreach ($religionStats as $rs): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($rs['religion']) ?></strong></td>
                            <td><?= number_format($rs['count']) ?></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <?php $pct = $relTotal > 0 ? round(($rs['count'] / $relTotal) * 100) : 0; ?>
                                    <div class="progress-bar bg-success" style="width: <?= $pct ?>%"><?= $pct ?>%</div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- Monthly Registrations -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Monthly Registrations (12 months)</h5></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>Month</th><th>Registrations</th></tr></thead>
                        <tbody>
                            <?php foreach ($monthlyRegistrations as $mr): ?>
                            <tr>
                                <td><?= date('M Y', strtotime($mr['month'] . '-01')) ?></td>
                                <td><strong><?= $mr['count'] ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($monthlyRegistrations)): ?>
                            <tr><td colspan="2" class="text-center text-muted">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top States -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Top States</h5></div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead><tr><th>#</th><th>State</th><th>Users</th></tr></thead>
                        <tbody>
                            <?php foreach ($stateStats as $i => $ss): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($ss['state']) ?></td>
                                <td><strong><?= number_format($ss['count']) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($stateStats)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
