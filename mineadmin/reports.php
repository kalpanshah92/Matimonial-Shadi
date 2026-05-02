<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'reports';

$stmt = $pdo->query(
    "SELECT r.*, u1.name as reporter_name, u1.profile_id as reporter_pid,
     u2.name as reported_name, u2.profile_id as reported_pid
     FROM reports r 
     JOIN users u1 ON r.reporter_id = u1.id 
     JOIN users u2 ON r.reported_id = u2.id 
     ORDER BY FIELD(r.status, 'pending', 'reviewed', 'resolved', 'dismissed'), r.created_at DESC 
     LIMIT 50"
);
$reports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <h4 class="mb-4">User Reports</h4>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Reporter</th>
                            <th>Reported User</th>
                            <th>Reason</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?= $report['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($report['reporter_name']) ?>
                                <small class="d-block text-muted"><?= $report['reporter_pid'] ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($report['reported_name']) ?>
                                <small class="d-block text-muted"><?= $report['reported_pid'] ?></small>
                            </td>
                            <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($report['reason']) ?></span></td>
                            <td><small><?= htmlspecialchars(substr($report['description'] ?? '', 0, 100)) ?></small></td>
                            <td>
                                <span class="badge bg-<?= $report['status'] === 'pending' ? 'danger' : ($report['status'] === 'resolved' ? 'success' : 'secondary') ?>">
                                    <?= ucfirst($report['status']) ?>
                                </span>
                            </td>
                            <td><small><?= date('d M Y', strtotime($report['created_at'])) ?></small></td>
                            <td>
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $report['reported_id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($report['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-success" onclick="updateReport(<?= $report['id'] ?>, 'resolved')">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="updateReport(<?= $report['id'] ?>, 'dismissed')">
                                        <i class="bi bi-x"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($reports)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No reports found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
function updateReport(reportId, status) {
    $.post('api/reports.php', { report_id: reportId, status: status }, function() {
        location.reload();
    });
}
</script>
</body>
</html>
