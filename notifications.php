<?php
$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * 20;

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20 OFFSET ?");
$stmt->execute([$userId, $offset]);
$notifications = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?");
$stmt->execute([$userId]);
$totalNotifs = $stmt->fetch()['count'];

// Mark all as read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
    redirect(SITE_URL . '/notifications.php');
}

$typeIcons = [
    'visit' => 'bi-eye text-info',
    'interest' => 'bi-heart text-danger',
    'connection' => 'bi-person-check text-success',
    'message' => 'bi-chat-dots text-primary',
    'subscription' => 'bi-star text-warning',
    'system' => 'bi-bell text-secondary'
];

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3><i class="bi bi-bell me-2"></i>Notifications</h3>
                    <a href="?mark_read=1" class="btn btn-outline-primary btn-sm">Mark All as Read</a>
                </div>

                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="dashboard-card mb-2 notification-item <?= !$notif['is_read'] ? 'border-start border-3 border-primary' : '' ?>" 
                             data-notif-id="<?= $notif['id'] ?>">
                            <div class="d-flex align-items-start gap-3">
                                <i class="bi <?= $typeIcons[$notif['type']] ?? 'bi-bell text-secondary' ?>" style="font-size: 1.3rem; margin-top: 3px;"></i>
                                <div class="flex-grow-1">
                                    <strong><?= sanitize($notif['title']) ?></strong>
                                    <p class="mb-1 text-muted"><?= sanitize($notif['message']) ?></p>
                                    <small class="text-muted"><?= timeAgo($notif['created_at']) ?></small>
                                </div>
                                <?php if ($notif['link']): ?>
                                    <a href="<?= SITE_URL . '/' . $notif['link'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-bell-slash" style="font-size: 4rem; color: var(--text-muted);"></i>
                        <h5 class="mt-3">No notifications yet</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
