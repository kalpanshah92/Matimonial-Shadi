<?php
$pageTitle = 'Shortlisted By';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * RESULTS_PER_PAGE;

// Get profiles that have shortlisted the current user
$stmt = $pdo->prepare("
    SELECT u.*, pd.height, pd.education, pd.occupation, pd.annual_income, s.created_at as shortlisted_at
    FROM users u
    LEFT JOIN profile_details pd ON u.id = pd.user_id
    INNER JOIN shortlisted s ON s.user_id = u.id
    WHERE s.shortlisted_id = ?
    AND u.is_active = 1
    AND u.status = 'approved'
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, RESULTS_PER_PAGE, $offset]);
$shortlisted = $stmt->fetchAll();

// Count total profiles that have shortlisted the current user
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM users u
    INNER JOIN shortlisted s ON s.user_id = u.id
    WHERE s.shortlisted_id = ?
    AND u.is_active = 1
    AND u.status = 'approved'
");
$stmt->execute([$userId]);
$totalShortlisted = $stmt->fetch()['count'];
$totalPages = ceil($totalShortlisted / RESULTS_PER_PAGE);

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-heart me-2 text-danger"></i>Shortlisted By (<?= $totalShortlisted ?>)</h3>
            <a href="<?= SITE_URL ?>/search.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Advanced Search
            </a>
        </div>

        <?php if (!empty($shortlisted)): ?>
            <div class="row g-3">
                <?php foreach ($shortlisted as $profile): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="profile-card">
                            <div class="profile-card-img">
                                <img src="<?= getProfilePic($profile['profile_pic'], $profile['gender']) ?>" alt="">
                                <?php if ($profile['is_verified']): ?>
                                    <span class="verified-badge"><i class="bi bi-patch-check-fill"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="profile-card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1"><?= sanitize($profile['name']) ?></h6>
                                    </div>
                                    <button class="btn btn-sm btn-danger btn-shortlist active" data-profile-id="<?= $profile['id'] ?>">
                                        <i class="bi bi-heart-fill"></i>
                                    </button>
                                </div>
                                <div class="profile-details-mini mt-2">
                                    <span><i class="bi bi-calendar3"></i> <?= calculateAge($profile['dob']) ?> yrs</span>
                                    <?php if (!empty($profile['height'])): ?>
                                        <span><i class="bi bi-rulers"></i> <?= formatHeight($profile['height']) ?></span>
                                    <?php endif; ?>
                                    <span><i class="bi bi-geo-alt"></i> <?= sanitize($profile['country'] ?? '') ?>, <?= sanitize($profile['state'] ?? '') ?>, <?= sanitize($profile['city'] ?? '') ?></span>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $profile['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">View</a>
                                    <button class="btn btn-primary btn-sm btn-connect" data-profile-id="<?= $profile['id'] ?>">
                                        <i class="bi bi-person-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-heart" style="font-size: 4rem; color: var(--text-muted);"></i>
                <h5 class="mt-3">No one has shortlisted you yet</h5>
                <p class="text-muted">Complete your profile to get noticed by other members.</p>
                <a href="<?= SITE_URL ?>/edit-profile.php" class="btn btn-primary">Complete Profile</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
