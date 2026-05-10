<?php
$pageTitle = 'Profile Views';
require_once __DIR__ . '/includes/auth.php';

// Check if premium user
if (!isPremium($currentUser['id'])) {
    setFlash('error', 'Profile Views is a premium feature. Please upgrade to view who visited your profile.');
    redirect(SITE_URL . '/subscription.php');
}

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Get profile visitors with details
$stmt = $pdo->prepare(
    "SELECT pv.*, u.name, u.profile_id, u.profile_pic, u.gender, u.dob, u.city, u.religion
     FROM profile_visits pv
     JOIN users u ON pv.visitor_id = u.id
     WHERE pv.visited_id = ?
     ORDER BY pv.visited_at DESC
     LIMIT 50"
);
$stmt->execute([$userId]);
$visitors = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Profile Views -->
<section class="py-4 bg-warm">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0"><i class="bi bi-eye me-2"></i>Who Viewed Your Profile</h3>
            <a href="<?= SITE_URL ?>/dashboard.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>

        <?php if (empty($visitors)): ?>
            <div class="dashboard-card text-center py-5">
                <i class="bi bi-eye-slash display-4 text-muted mb-3"></i>
                <h5 class="text-muted">No profile views yet</h5>
                <p class="text-muted">Your profile views will appear here once other users visit your profile.</p>
                <a href="<?= SITE_URL ?>/search.php" class="btn btn-primary mt-3">
                    <i class="bi bi-search me-2"></i>Search for Profiles
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($visitors as $visitor): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="dashboard-card">
                            <div class="position-relative">
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $visitor['visitor_id'] ?>">
                                    <img src="<?= getProfilePic($visitor['profile_pic'], $visitor['gender']) ?>" 
                                         class="w-100" 
                                         style="height: 200px; object-fit: cover; border-radius: 8px;"
                                         alt="<?= sanitize($visitor['name']) ?>">
                                </a>
                                <span class="badge bg-primary position-absolute top-0 end-0 m-2">
                                    <?= calculateAge($visitor['dob']) ?> yrs
                                </span>
                            </div>
                            <div class="mt-3">
                                <h5 class="mb-2">
                                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $visitor['visitor_id'] ?>" 
                                       class="text-decoration-none text-dark">
                                        <?= sanitize($visitor['name']) ?>
                                    </a>
                                </h5>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-geo-alt me-1"></i><?= sanitize($visitor['city'] ?? 'Not specified') ?>
                                </p>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-calendar me-1"></i>Viewed: <?= date('d M Y, g:i A', strtotime($visitor['visited_at'])) ?>
                                </p>
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $visitor['visitor_id'] ?>" 
                                   class="btn btn-primary btn-sm w-100">
                                    <i class="bi bi-eye me-1"></i>View Profile
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
