<?php
$pageTitle = 'My Matches';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * RESULTS_PER_PAGE;

$matches = getMatchedProfiles($userId, RESULTS_PER_PAGE, $offset);

// Count total matches
$oppositeGender = ($currentUser['gender'] === 'Male') ? 'Female' : 'Male';
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE gender = ? AND id != ? AND is_active = 1 AND status = 'approved'");
$stmt->execute([$oppositeGender, $userId]);
$totalMatches = $stmt->fetch()['count'];
$totalPages = ceil($totalMatches / RESULTS_PER_PAGE);

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-heart me-2 text-danger"></i>Your Matches (<?= $totalMatches ?>)</h3>
            <a href="<?= SITE_URL ?>/search.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-funnel me-1"></i>Advanced Search
            </a>
        </div>

        <?php if (!empty($matches)): ?>
            <div class="row g-3">
                <?php foreach ($matches as $match): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <div class="profile-card">
                            <div class="profile-card-img">
                                <img src="<?= getProfilePic($match['profile_pic'], $match['gender']) ?>" alt="">
                                <?php if ($match['is_verified']): ?>
                                    <span class="verified-badge"><i class="bi bi-patch-check-fill"></i></span>
                                <?php endif; ?>
                            </div>
                            <div class="profile-card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1"><?= sanitize($match['name']) ?></h6>
                                        <small class="text-muted"><?= $match['profile_id'] ?? '' ?></small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-danger btn-shortlist" data-profile-id="<?= $match['id'] ?>">
                                        <i class="bi bi-heart"></i>
                                    </button>
                                </div>
                                <div class="profile-details-mini mt-2">
                                    <span><i class="bi bi-calendar3"></i> <?= calculateAge($match['dob']) ?> yrs</span>
                                    <?php if (!empty($match['height'])): ?>
                                        <span><i class="bi bi-rulers"></i> <?= formatHeight($match['height']) ?></span>
                                    <?php endif; ?>
                                    <span><i class="bi bi-geo-alt"></i> <?= sanitize($match['city'] ?? $match['state'] ?? 'India') ?></span>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $match['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">View</a>
                                    <button class="btn btn-primary btn-sm btn-connect" data-profile-id="<?= $match['id'] ?>">
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
                <i class="bi bi-search-heart" style="font-size: 4rem; color: var(--text-muted);"></i>
                <h5 class="mt-3">No matches found yet</h5>
                <p class="text-muted">Complete your profile and partner preferences to get better matches.</p>
                <a href="<?= SITE_URL ?>/edit-profile.php?tab=partner" class="btn btn-primary">Set Preferences</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
