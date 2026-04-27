<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Get stats
$profileCompletion = getProfileCompletion($userId);
$isPremiumUser = isPremium($userId);

// Matches count
$oppositeGender = ($currentUser['gender'] === 'Male') ? 'Female' : 'Male';
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE gender = ? AND id != ? AND is_active = 1 AND status = 'approved'");
$stmt->execute([$oppositeGender, $userId]);
$matchesCount = $stmt->fetch()['count'];

// Connection requests received
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM connection_requests WHERE receiver_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingRequests = $stmt->fetch()['count'];

// Profile visits
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM profile_visits WHERE visited_id = ? AND visited_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$userId]);
$recentVisits = $stmt->fetch()['count'];

// Shortlisted by others
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM shortlisted WHERE shortlisted_id = ?");
$stmt->execute([$userId]);
$shortlistedByCount = $stmt->fetch()['count'];

// Recent profile visits
$stmt = $pdo->prepare(
    "SELECT u.id, u.name, u.profile_id, u.profile_pic, u.gender, u.dob, u.city, u.state, pv.visited_at 
     FROM profile_visits pv 
     JOIN users u ON pv.visitor_id = u.id 
     WHERE pv.visited_id = ? 
     ORDER BY pv.visited_at DESC LIMIT 5"
);
$stmt->execute([$userId]);
$recentVisitors = $stmt->fetchAll();

// Recent matches
$recentMatches = getMatchedProfiles($userId, 4, 0);

// Pending connection requests
$stmt = $pdo->prepare(
    "SELECT cr.*, u.name, u.profile_id, u.profile_pic, u.gender, u.dob, u.city, u.religion 
     FROM connection_requests cr 
     JOIN users u ON cr.sender_id = u.id 
     WHERE cr.receiver_id = ? AND cr.status = 'pending' 
     ORDER BY cr.created_at DESC LIMIT 5"
);
$stmt->execute([$userId]);
$connectionRequests = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Dashboard -->
<section class="py-4 bg-warm">
    <div class="container">
        <!-- Welcome Banner -->
        <div class="dashboard-card mb-4" style="background: linear-gradient(135deg, var(--primary), var(--maroon)); color: white;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3>Welcome, <?= sanitize($currentUser['name']) ?>!</h3>
                    <p class="mb-2 opacity-75">Profile ID: <?= $currentUser['profile_id'] ?> 
                        <?php if ($isPremiumUser): ?>
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-star-fill"></i> Premium</span>
                        <?php endif; ?>
                        <?php if ($currentUser['is_verified']): ?>
                            <span class="badge bg-info ms-1"><i class="bi bi-patch-check-fill"></i> Verified</span>
                        <?php endif; ?>
                    </p>
                    <div class="mt-3">
                        <small>Profile Completion: <?= $profileCompletion ?>%</small>
                        <div class="completion-bar mt-1" style="max-width: 300px;">
                            <div class="completion-fill" style="width: <?= $profileCompletion ?>%"></div>
                        </div>
                        <?php if ($profileCompletion < 80): ?>
                            <a href="<?= SITE_URL ?>/edit-profile.php" class="btn btn-accent btn-sm mt-2">Complete Profile</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <img src="<?= getProfilePic($currentUser['profile_pic'], $currentUser['gender']) ?>" 
                         class="rounded-circle border border-3 border-white" width="100" height="100" 
                         style="object-fit: cover;" alt="Profile">
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="dashboard-card dashboard-stat">
                    <div class="stat-icon stat-primary"><i class="bi bi-heart"></i></div>
                    <h3><?= $matchesCount ?></h3>
                    <p>Potential Matches</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="dashboard-card dashboard-stat">
                    <div class="stat-icon stat-success"><i class="bi bi-person-check"></i></div>
                    <h3><?= $pendingRequests ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="dashboard-card dashboard-stat">
                    <div class="stat-icon stat-warning"><i class="bi bi-eye"></i></div>
                    <h3><?= $recentVisits ?></h3>
                    <p>Profile Views (30d)</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="dashboard-card dashboard-stat">
                    <div class="stat-icon stat-info"><i class="bi bi-bookmark-heart"></i></div>
                    <h3><?= $shortlistedByCount ?></h3>
                    <p>Shortlisted By</p>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Connection Requests (shown first when pending) -->
                <?php if (!empty($connectionRequests)): ?>
                <div class="dashboard-card mb-4" style="border-left: 4px solid var(--success);">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-person-plus text-success me-2"></i>Pending Connection Requests
                            <span class="badge bg-success ms-1"><?= count($connectionRequests) ?></span>
                        </h5>
                    </div>
                    <?php foreach ($connectionRequests as $req): ?>
                        <div class="request-item d-flex align-items-center justify-content-between p-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $req['sender_id'] ?>">
                                    <img src="<?= getProfilePic($req['profile_pic'], $req['gender']) ?>" 
                                         class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                                </a>
                                <div>
                                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $req['sender_id'] ?>" class="fw-bold text-dark text-decoration-none">
                                        <?= sanitize($req['name']) ?>
                                    </a>
                                    <small class="d-block text-muted"><?= $req['profile_id'] ?></small>
                                    <small class="d-block text-muted">
                                        <?= calculateAge($req['dob']) ?> yrs | <?= sanitize($req['religion'] ?? '') ?> | <?= sanitize($req['city'] ?? '') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $req['sender_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                                <button class="btn btn-sm btn-success btn-accept-request" data-request-id="<?= $req['id'] ?>">
                                    <i class="bi bi-check-lg"></i> Accept
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-decline-request" data-request-id="<?= $req['id'] ?>">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Recommended Matches -->
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-heart text-danger me-2"></i>Recommended Matches</h5>
                        <a href="<?= SITE_URL ?>/matches.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="row g-3">
                        <?php if (!empty($recentMatches)): ?>
                            <?php foreach ($recentMatches as $match): ?>
                                <div class="col-md-6 col-lg-3">
                                    <div class="profile-card">
                                        <div class="profile-card-img" style="height: 160px;">
                                            <img src="<?= getProfilePic($match['profile_pic'], $match['gender']) ?>" alt="">
                                        </div>
                                        <div class="profile-card-body p-2">
                                            <h6 class="mb-1" style="font-size: 0.85rem;"><?= sanitize($match['name']) ?></h6>
                                            <small class="text-muted d-block"><?= calculateAge($match['dob']) ?> yrs, <?= sanitize($match['city'] ?? $match['state'] ?? '') ?></small>
                                            <a href="<?= SITE_URL ?>/profile.php?id=<?= $match['id'] ?>" class="btn btn-outline-primary btn-sm w-100 mt-2" style="font-size: 0.75rem;">View</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center py-4">
                                <i class="bi bi-search-heart" style="font-size: 3rem; color: var(--text-muted);"></i>
                                <p class="text-muted mt-2">Complete your profile to get better matches!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="dashboard-card mb-4">
                    <h5 class="mb-3"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="<?= SITE_URL ?>/edit-profile.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-pencil-square me-2"></i>Edit Profile
                        </a>
                        <a href="<?= SITE_URL ?>/search.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-search me-2"></i>Search Profiles
                        </a>
                        <a href="<?= SITE_URL ?>/settings.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-shield-lock me-2"></i>Privacy Settings
                        </a>
                        <?php if (!$isPremiumUser): ?>
                            <a href="<?= SITE_URL ?>/subscription.php" class="btn btn-accent btn-sm text-start">
                                <i class="bi bi-star me-2"></i>Upgrade to Premium
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Visitors -->
                <div class="dashboard-card">
                    <h5 class="mb-3"><i class="bi bi-eye me-2"></i>Recent Visitors</h5>
                    <?php if (!empty($recentVisitors)): ?>
                        <?php foreach ($recentVisitors as $visitor): ?>
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <img src="<?= getProfilePic($visitor['profile_pic'], $visitor['gender']) ?>" 
                                     class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                <div class="flex-grow-1">
                                    <a href="<?= SITE_URL ?>/profile.php?id=<?= $visitor['id'] ?>" class="fw-semibold text-dark d-block" style="font-size: 0.9rem;">
                                        <?= sanitize($visitor['name']) ?>
                                    </a>
                                    <small class="text-muted"><?= timeAgo($visitor['visited_at']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No recent visitors yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
