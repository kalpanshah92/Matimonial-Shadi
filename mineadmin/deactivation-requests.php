<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'deactivation-requests';

// Only super admin can access
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header('Location: index.php');
    exit;
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = intval($_POST['request_id'] ?? 0);
    
    if ($requestId && ($action === 'approve' || $action === 'reject')) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM deactivation_requests WHERE id = ?");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            
            if ($request && $request['status'] === 'pending') {
                if ($action === 'approve') {
                    // Deactivate the user account
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$request['user_id']]);
                    
                    // Update request status
                    $stmt = $pdo->prepare("UPDATE deactivation_requests SET status = 'approved', processed_by = ?, processed_at = NOW() WHERE id = ?");
                    $stmt->execute([$_SESSION['admin_id'], $requestId]);
                    
                    // Notify user
                    createNotification($request['user_id'], 'deactivation', 'Account Deactivated', 'Your account has been deactivated as per your request.');
                } else {
                    // Reject the request
                    $stmt = $pdo->prepare("UPDATE deactivation_requests SET status = 'rejected', processed_by = ?, processed_at = NOW() WHERE id = ?");
                    $stmt->execute([$_SESSION['admin_id'], $requestId]);
                    
                    // Notify user
                    createNotification($request['user_id'], 'deactivation', 'Deactivation Request Rejected', 'Your account deactivation request has been rejected. Your account remains active.');
                }
                
                $pdo->commit();
                setFlash('success', 'Request processed successfully.');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Failed to process request.');
        }
        header('Location: deactivation-requests.php');
        exit;
    }
}

// Fetch all deactivation requests
$stmt = $pdo->prepare(
    "SELECT dr.*, u.name, u.email, u.profile_id, a.name as admin_name 
     FROM deactivation_requests dr 
     JOIN users u ON dr.user_id = u.id 
     LEFT JOIN admin_users a ON dr.processed_by = a.id 
     ORDER BY dr.created_at DESC"
);
$stmt->execute();
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deactivation Requests | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <h4 class="mb-4">Account Deactivation Requests</h4>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Pending Requests</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Reason</th>
                            <th>Requested On</th>
                            <th>Status</th>
                            <th>Processed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($req['name']) ?></strong>
                                <small class="d-block text-muted"><?= $req['profile_id'] ?> | <?= htmlspecialchars($req['email']) ?></small>
                            </td>
                            <td><?= !empty($req['reason']) ? nl2br(htmlspecialchars($req['reason'])) : '<span class="text-muted">No reason provided</span>' ?></td>
                            <td><?= date('d M Y, g:i A', strtotime($req['created_at'])) ?></td>
                            <td>
                                <span class="badge bg-<?= $req['status'] === 'approved' ? 'success' : ($req['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                    <?= ucfirst($req['status']) ?>
                                </span>
                            </td>
                            <td><?= $req['admin_name'] ? htmlspecialchars($req['admin_name']) : '-' ?></td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this deactivation request? This will deactivate the user account.')">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this deactivation request?')">
                                            <i class="bi bi-x-lg"></i> Reject
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No deactivation requests found.</td></tr>
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
