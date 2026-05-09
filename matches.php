<?php
$pageTitle = 'My Matches';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * RESULTS_PER_PAGE;

// Get connected profiles only
$stmt = $pdo->prepare("
    SELECT u.*, pd.height, pd.education, pd.occupation, pd.annual_income
    FROM users u
    LEFT JOIN profile_details pd ON u.id = pd.user_id
    INNER JOIN connection_requests cr ON (
        (cr.sender_id = u.id AND cr.receiver_id = ?) OR
        (cr.receiver_id = u.id AND cr.sender_id = ?)
    )
    WHERE cr.status = 'accepted'
    AND u.id != ?
    AND u.is_active = 1
    AND u.status = 'approved'
    ORDER BY cr.updated_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $userId, $userId, RESULTS_PER_PAGE, $offset]);
$matches = $stmt->fetchAll();

// Check shortlist status for each match
foreach ($matches as &$match) {
    $stmt = $pdo->prepare("SELECT id FROM shortlisted WHERE user_id = ? AND shortlisted_id = ?");
    $stmt->execute([$userId, $match['id']]);
    $match['is_shortlisted'] = $stmt->fetch() ? true : false;
}
unset($match);

// Count total connected profiles
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM users u
    INNER JOIN connection_requests cr ON (
        (cr.sender_id = u.id AND cr.receiver_id = ?) OR
        (cr.receiver_id = u.id AND cr.sender_id = ?)
    )
    WHERE cr.status = 'accepted'
    AND u.id != ?
    AND u.is_active = 1
    AND u.status = 'approved'
");
$stmt->execute([$userId, $userId, $userId]);
$totalMatches = $stmt->fetch()['count'];
$totalPages = ceil($totalMatches / RESULTS_PER_PAGE);

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3><i class="bi bi-heart me-2 text-danger"></i>Your Connections (<?= $totalMatches ?>)</h3>
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
                                    </div>
                                    <button class="btn btn-sm <?= $match['is_shortlisted'] ? 'btn-danger' : 'btn-outline-danger' ?> btn-shortlist <?= $match['is_shortlisted'] ? 'active' : '' ?>" data-profile-id="<?= $match['id'] ?>">
                                        <i class="bi <?= $match['is_shortlisted'] ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                    </button>
                                </div>
                                <div class="profile-details-mini mt-2">
                                    <span><i class="bi bi-calendar3"></i> <?= calculateAge($match['dob']) ?> yrs</span>
                                    <?php if (!empty($match['height'])): ?>
                                        <span><i class="bi bi-rulers"></i> <?= formatHeight($match['height']) ?></span>
                                    <?php endif; ?>
                                    <span><i class="bi bi-geo-alt"></i> <?= sanitize($match['country'] ?? '') ?>, <?= sanitize($match['state'] ?? '') ?>, <?= sanitize($match['city'] ?? '') ?></span>
                                </div>
                                <div class="d-flex gap-2 mt-3">
                                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $match['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">View</a>
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
