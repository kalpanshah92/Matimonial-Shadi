<?php
$pageTitle = 'Success Stories';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

$stories = [];
try {
    $stmt = $pdo->query(
        "SELECT ss.*, u.name FROM success_stories ss 
         JOIN users u ON ss.user_id = u.id 
         WHERE ss.is_approved = 1 
         ORDER BY ss.created_at DESC"
    );
    $stories = $stmt->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-warm">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Success Stories</h2>
            <p class="section-subtitle">Real love stories that started on <?= SITE_NAME ?></p>
        </div>

        <?php if (!empty($stories)): ?>
            <div class="row g-4">
                <?php foreach ($stories as $story): ?>
                    <div class="col-md-4">
                        <div class="testimonial-card">
                            <?php if ($story['photo']): ?>
                                <img src="<?= SITE_URL . '/' . $story['photo'] ?>" class="testimonial-img" alt="">
                            <?php endif; ?>
                            <h5><?= sanitize($story['title']) ?></h5>
                            <p><?= nl2br(sanitize($story['story'])) ?></p>
                            <div class="testimonial-author">
                                <strong><?= sanitize($story['name']) ?> & <?= sanitize($story['partner_name']) ?></strong>
                                <?php if ($story['marriage_date']): ?>
                                    <small class="d-block text-muted">Married: <?= date('F Y', strtotime($story['marriage_date'])) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-heart" style="font-size: 4rem; color: var(--text-muted);"></i>
                <h5 class="mt-3">Success stories coming soon!</h5>
                <p class="text-muted">Be the first to share your love story.</p>
            </div>
        <?php endif; ?>

        <?php if (isLoggedIn()): ?>
            <div class="text-center mt-5">
                <div class="dashboard-card d-inline-block p-4">
                    <h5>Found your match on <?= SITE_NAME ?>?</h5>
                    <p class="text-muted">Share your success story and inspire others!</p>
                    <a href="<?= SITE_URL ?>/share-story.php" class="btn btn-primary">
                        <i class="bi bi-heart me-1"></i>Share Your Story
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
