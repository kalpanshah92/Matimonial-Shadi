<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'stories';

// Handle approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storyId = intval($_POST['story_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($storyId && $action === 'approve') {
        $pdo->prepare("UPDATE success_stories SET is_approved = 1 WHERE id = ?")->execute([$storyId]);
    } elseif ($storyId && $action === 'reject') {
        $pdo->prepare("DELETE FROM success_stories WHERE id = ?")->execute([$storyId]);
    }
    header('Location: stories.php');
    exit;
}

$stmt = $pdo->query(
    "SELECT ss.*, u.name, u.profile_id FROM success_stories ss 
     JOIN users u ON ss.user_id = u.id 
     ORDER BY ss.is_approved ASC, ss.created_at DESC"
);
$stories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <h4 class="mb-4">Success Stories</h4>

    <div class="row g-4">
        <?php foreach ($stories as $story): ?>
        <div class="col-md-6">
            <div class="card <?= !$story['is_approved'] ? 'border-warning' : '' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><?= htmlspecialchars($story['title']) ?></h5>
                            <p class="text-muted mb-1">
                                By <?= htmlspecialchars($story['name']) ?> (<?= $story['profile_id'] ?>) 
                                & <?= htmlspecialchars($story['partner_name']) ?>
                            </p>
                        </div>
                        <span class="badge bg-<?= $story['is_approved'] ? 'success' : 'warning' ?>">
                            <?= $story['is_approved'] ? 'Approved' : 'Pending' ?>
                        </span>
                    </div>
                    <p class="mt-2"><?= htmlspecialchars(substr($story['story'], 0, 200)) ?>...</p>
                    <small class="text-muted">Submitted: <?= date('d M Y', strtotime($story['created_at'])) ?></small>
                    
                    <?php if (!$story['is_approved']): ?>
                    <div class="mt-3 d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="story_id" value="<?= $story['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm btn-success"><i class="bi bi-check me-1"></i>Approve</button>
                        </form>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this story?')">
                            <input type="hidden" name="story_id" value="<?= $story['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($stories)): ?>
        <div class="col-12 text-center py-5">
            <i class="bi bi-heart" style="font-size: 3rem; color: var(--text-muted);"></i>
            <p class="text-muted mt-2">No success stories submitted yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
