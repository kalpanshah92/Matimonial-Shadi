<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/AccountEntitlement.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Get account entitlement info
$entitlement = AccountEntitlement::forUser($userId);
$accountStatus = $entitlement->isExpired() ? 'expired' : ($entitlement->isInGracePeriod() ? 'grace' : 'active');
$daysUntilExpiry = $entitlement->daysUntilExpiry();

// Get stats
$profileCompletion = getProfileCompletion($userId);
$isPremiumUser = isPremium($userId);

// Account expiry info from entitlement system (primary source)
$accountExpiryDate = $entitlement->getExpiryDate();

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

// My shortlisted profiles
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM shortlisted WHERE user_id = ?");
$stmt->execute([$userId]);
$shortlistedByCount = $stmt->fetch()['count'];

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
                    <p class="mb-2 opacity-75">
                        <?php if ($isPremiumUser): ?>
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-star-fill"></i> Premium</span>
                        <?php endif; ?>
                        <?php if ($currentUser['is_verified']): ?>
                            <span class="badge bg-info ms-1"><i class="bi bi-patch-check-fill"></i> Verified</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($accountExpiryDate): ?>
                        <p class="mb-2 opacity-90">
                            <i class="bi bi-calendar-check me-1"></i>
                            <strong>Account Valid Till:</strong>
                            <?= date('d M Y', strtotime($accountExpiryDate)) ?>
                            <?php if ($entitlement->isExpired()): ?>
                                <span class="badge bg-danger ms-1">Expired</span>
                            <?php elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= 30): ?>
                                <span class="badge bg-warning text-dark ms-1">Expires in <?= $daysUntilExpiry ?> days</span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
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

        <!-- Account Expiry Alerts -->
        <?php if ($accountStatus === 'expired'): ?>
            <div class="alert alert-danger mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h5 class="alert-heading mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Your account has expired</h5>
                        <p class="mb-0">Your membership expired on <strong><?= $entitlement->getFormattedExpiryDate() ?></strong>. You can still access your dashboard and profile, but cannot search for partners or use chat.</p>
                    </div>
                    <a href="<?= SITE_URL ?>/renew.php" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Renew Your Account
                    </a>
                </div>
            </div>
        <?php elseif ($accountStatus === 'grace'): ?>
            <div class="alert alert-warning mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h5 class="alert-heading mb-1"><i class="bi bi-clock-history me-2"></i>Your account has expired</h5>
                        <p class="mb-0">Your membership expired on <strong><?= $entitlement->getFormattedExpiryDate() ?></strong>. Grace period ends on <strong><?= $entitlement->getGracePeriodEndDate() ?></strong>. Please renew now to avoid service interruption.</p>
                    </div>
                    <a href="<?= SITE_URL ?>/renew.php" class="btn btn-warning">
                        <i class="bi bi-arrow-clockwise me-1"></i>Renew Now
                    </a>
                </div>
            </div>
        <?php elseif ($daysUntilExpiry !== null && $daysUntilExpiry <= 30 && $daysUntilExpiry > 0): ?>
            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div>
                        <h5 class="alert-heading mb-1"><i class="bi bi-info-circle-fill me-2"></i>Your account will expire soon</h5>
                        <p class="mb-0">Your membership expires in <strong><?= $daysUntilExpiry ?> days</strong> (<?= $entitlement->getFormattedExpiryDate() ?>). Renew now to ensure uninterrupted access.</p>
                    </div>
                    <a href="<?= SITE_URL ?>/subscription.php" class="btn btn-info text-white">
                        <i class="bi bi-arrow-clockwise me-1"></i>Extend Membership
                    </a>
                </div>
            </div>
        <?php endif; ?>

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
                <div class="dashboard-card dashboard-stat" style="cursor: pointer;" onclick="window.location.href='<?= SITE_URL ?>/profile-views.php'">
                    <div class="stat-icon stat-warning"><i class="bi bi-eye"></i></div>
                    <h3><?= $recentVisits ?></h3>
                    <p>Profile Views (30d)</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="dashboard-card dashboard-stat" style="cursor: pointer;" onclick="window.location.href='<?= SITE_URL ?>/shortlist.php'">
                    <div class="stat-icon stat-info"><i class="bi bi-bookmark-heart"></i></div>
                    <h3><?= $shortlistedByCount ?></h3>
                    <p>My Shortlist</p>
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
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= encodeProfileId($req['sender_id']) ?>">
                                    <img src="<?= getProfilePic($req['profile_pic'], $req['gender']) ?>" 
                                         class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                                </a>
                            </div>
                            <div class="flex-grow-1">
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= encodeProfileId($req['sender_id']) ?>" class="fw-bold text-dark text-decoration-none">
                                    <?= sanitize($req['name']) ?>
                                </a>
                                    
                                <small class="d-block text-muted">
                                    <?= calculateAge($req['dob']) ?> yrs | <?= sanitize($req['religion'] ?? '') ?> | <?= sanitize($req['city'] ?? '') ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= encodeProfileId($req['sender_id']) ?>" class="btn btn-sm btn-outline-primary">
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
                        <a href="<?= SITE_URL ?>/matches.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-heart me-2"></i>View Matches
                        </a>
                        <a href="<?= SITE_URL ?>/settings.php" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-shield-lock me-2"></i>Privacy Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
