<?php
$pageTitle = 'View Profile';
require_once __DIR__ . '/includes/functions.php';

$profileId = intval($_GET['id'] ?? 0);
if (!$profileId) {
    setFlash('error', 'Invalid profile.');
    redirect(SITE_URL . '/search.php');
}

$pdo = getDBConnection();

// Fetch user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    setFlash('error', 'Profile not found.');
    redirect(SITE_URL . '/search.php');
}

// Fetch profile details
$stmt = $pdo->prepare("SELECT * FROM profile_details WHERE user_id = ?");
$stmt->execute([$profileId]);
$details = $stmt->fetch();

// Fetch family details
$stmt = $pdo->prepare("SELECT * FROM family_details WHERE user_id = ?");
$stmt->execute([$profileId]);
$family = $stmt->fetch();

// Fetch partner preferences
$stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
$stmt->execute([$profileId]);
$partnerPrefs = $stmt->fetch();

// Fetch photos
$stmt = $pdo->prepare("SELECT * FROM photos WHERE user_id = ? AND is_approved = 1 ORDER BY is_primary DESC");
$stmt->execute([$profileId]);
$photos = $stmt->fetchAll();

// Fetch privacy settings
$stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = ?");
$stmt->execute([$profileId]);
$privacy = $stmt->fetch();

// Log visit if logged in
$isOwner = false;
$connectionStatus = null;
$isShortlisted = false;

if (isLoggedIn()) {
    $currentUserId = $_SESSION['user_id'];
    $isOwner = ($currentUserId == $profileId);
    
    if (!$isOwner) {
        logProfileVisit($currentUserId, $profileId);
        
        // Check connection status
        $stmt = $pdo->prepare(
            "SELECT * FROM connection_requests 
             WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$currentUserId, $profileId, $profileId, $currentUserId]);
        $connection = $stmt->fetch();
        $connectionStatus = $connection ? $connection['status'] : null;
        
        // Check if shortlisted
        $stmt = $pdo->prepare("SELECT id FROM shortlisted WHERE user_id = ? AND shortlisted_id = ?");
        $stmt->execute([$currentUserId, $profileId]);
        $isShortlisted = $stmt->fetch() ? true : false;
    }
}

$isConnected = ($connectionStatus === 'accepted');
$canViewContact = $isOwner || $isConnected;

$pageTitle = sanitize($profile['name']) . "'s Profile";
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <div class="row g-4">
            <!-- Profile Main -->
            <div class="col-lg-8">
                <!-- Profile Header -->
                <div class="dashboard-card mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <img src="<?= getProfilePic($profile['profile_pic'], $profile['gender']) ?>" 
                                 class="rounded-circle border border-3" width="180" height="180" 
                                 style="object-fit: cover; border-color: var(--primary) !important;" alt="<?= sanitize($profile['name']) ?>">
                            
                            <?php if (!empty($photos) && count($photos) > 1): ?>
                                <div class="d-flex gap-2 justify-content-center mt-2 flex-wrap">
                                    <?php foreach (array_slice($photos, 0, 4) as $photo): ?>
                                        <img src="<?= SITE_URL . '/' . $photo['photo_path'] ?>" 
                                             class="rounded" width="50" height="50" style="object-fit: cover; cursor: pointer;" 
                                             onclick="document.querySelector('.rounded-circle').src=this.src">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <h3 class="mb-1">
                                        <?= sanitize($profile['name']) ?>
                                        <?php if ($profile['is_verified']): ?>
                                            <i class="bi bi-patch-check-fill text-primary" title="Verified"></i>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-muted mb-2"><?= $profile['profile_id'] ?>
                                        <?php if ($profile['is_premium']): ?>
                                            <span class="badge bg-warning text-dark ms-1"><i class="bi bi-star-fill"></i> Premium</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if (isLoggedIn() && !$isOwner): ?>
                                    <button class="btn btn-sm <?= $isShortlisted ? 'btn-danger' : 'btn-outline-danger' ?> btn-shortlist <?= $isShortlisted ? 'active' : '' ?>" 
                                            data-profile-id="<?= $profile['id'] ?>">
                                        <i class="bi <?= $isShortlisted ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row g-2 mt-2">
                                <div class="col-6"><small class="text-muted">Age:</small><br><strong><?= calculateAge($profile['dob']) ?> years</strong></div>
                                <div class="col-6"><small class="text-muted">Height:</small><br><strong><?= $details ? formatHeight($details['height']) : 'Not specified' ?></strong></div>
                                <div class="col-6"><small class="text-muted">Religion:</small><br><strong><?= sanitize($profile['religion'] ?? 'Not specified') ?></strong></div>
                                <div class="col-6"><small class="text-muted">Caste:</small><br><strong><?= sanitize($profile['caste'] ?? 'Not specified') ?></strong></div>
                                <div class="col-6"><small class="text-muted">Mother Tongue:</small><br><strong><?= sanitize($profile['mother_tongue'] ?? 'Not specified') ?></strong></div>
                                <div class="col-6"><small class="text-muted">Location:</small><br><strong><?= sanitize(($profile['city'] ? $profile['city'] . ', ' : '') . ($profile['state'] ?? 'India')) ?></strong></div>
                            </div>

                            <?php if (isLoggedIn() && !$isOwner): ?>
                                <div class="mt-3 d-flex gap-2">
                                    <?php if (!$connectionStatus): ?>
                                        <button class="btn btn-primary btn-sm btn-connect" data-profile-id="<?= $profile['id'] ?>">
                                            <i class="bi bi-person-plus me-1"></i>Send Interest
                                        </button>
                                    <?php elseif ($connectionStatus === 'pending'): ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="bi bi-clock me-1"></i>Interest Sent
                                        </button>
                                    <?php elseif ($connectionStatus === 'accepted'): ?>
                                        <a href="<?= SITE_URL ?>/chat.php?contact=<?= $profile['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="bi bi-chat-dots me-1"></i>Chat Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- About -->
                <?php if (!empty($profile['about_me'])): ?>
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-person-lines-fill me-2 text-primary"></i>About</h5>
                    <p class="mt-2"><?= nl2br(sanitize($profile['about_me'])) ?></p>
                </div>
                <?php endif; ?>

                <!-- Personal Details -->
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-info-circle me-2 text-primary"></i>Personal Details</h5>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4"><small class="text-muted">Marital Status</small><br><?= sanitize($profile['marital_status'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Complexion</small><br><?= sanitize($details['complexion'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Body Type</small><br><?= sanitize($details['body_type'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Diet</small><br><?= sanitize($details['diet'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Smoking</small><br><?= sanitize($details['smoking'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Drinking</small><br><?= sanitize($details['drinking'] ?? 'Not specified') ?></div>
                    </div>
                </div>

                <!-- Professional Details -->
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-briefcase me-2 text-primary"></i>Professional Details</h5>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4"><small class="text-muted">Education</small><br><?= sanitize($details['education'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Occupation</small><br><?= sanitize($details['occupation'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Annual Income</small><br>
                            <?php if ($canViewContact): ?>
                                <?= sanitize($details['annual_income'] ?? 'Not specified') ?>
                            <?php else: ?>
                                <span class="text-muted"><i class="bi bi-lock"></i> Connect to view</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4"><small class="text-muted">Company</small><br><?= sanitize($details['company'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Working City</small><br><?= sanitize($details['working_city'] ?? 'Not specified') ?></div>
                    </div>
                </div>

                <!-- Family Details -->
                <?php if ($family): ?>
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-people me-2 text-primary"></i>Family Details</h5>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4"><small class="text-muted">Father's Occupation</small><br><?= sanitize($family['father_occupation'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Mother's Occupation</small><br><?= sanitize($family['mother_occupation'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Family Type</small><br><?= sanitize($family['family_type'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Family Status</small><br><?= sanitize($family['family_status'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Family Values</small><br><?= sanitize($family['family_values'] ?? 'Not specified') ?></div>
                        <div class="col-md-4"><small class="text-muted">Siblings</small><br>
                            <?= ($family['brothers'] ?? 0) ?> Brother(s), <?= ($family['sisters'] ?? 0) ?> Sister(s)
                        </div>
                        <?php if (!empty($family['about_family'])): ?>
                            <div class="col-12"><small class="text-muted">About Family</small><br><?= nl2br(sanitize($family['about_family'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Partner Preferences -->
                <?php if ($partnerPrefs): ?>
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-heart me-2 text-primary"></i>Partner Preferences</h5>
                    <div class="row g-3 mt-2">
                        <div class="col-md-4"><small class="text-muted">Age</small><br><?= $partnerPrefs['min_age'] ?? 18 ?> - <?= $partnerPrefs['max_age'] ?? 60 ?> years</div>
                        <?php if ($partnerPrefs['min_height'] || $partnerPrefs['max_height']): ?>
                            <div class="col-md-4"><small class="text-muted">Height</small><br><?= formatHeight($partnerPrefs['min_height']) ?> - <?= formatHeight($partnerPrefs['max_height']) ?></div>
                        <?php endif; ?>
                        <?php if ($partnerPrefs['religion']): ?>
                            <div class="col-md-4"><small class="text-muted">Religion</small><br><?= sanitize($partnerPrefs['religion']) ?></div>
                        <?php endif; ?>
                        <?php if ($partnerPrefs['caste']): ?>
                            <div class="col-md-4"><small class="text-muted">Caste</small><br><?= sanitize($partnerPrefs['caste']) ?></div>
                        <?php endif; ?>
                        <?php if ($partnerPrefs['education']): ?>
                            <div class="col-md-4"><small class="text-muted">Education</small><br><?= sanitize($partnerPrefs['education']) ?></div>
                        <?php endif; ?>
                        <?php if ($partnerPrefs['occupation']): ?>
                            <div class="col-md-4"><small class="text-muted">Occupation</small><br><?= sanitize($partnerPrefs['occupation']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($partnerPrefs['about_partner'])): ?>
                            <div class="col-12"><small class="text-muted">About Partner</small><br><?= nl2br(sanitize($partnerPrefs['about_partner'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Contact Details -->
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-telephone me-2 text-primary"></i>Contact Details</h5>
                    <?php if ($canViewContact): ?>
                        <div class="mt-3">
                            <p><i class="bi bi-envelope me-2"></i><?= sanitize($profile['email']) ?></p>
                            <p><i class="bi bi-phone me-2"></i>+91 <?= sanitize($profile['phone'] ?? 'Not provided') ?></p>
                        </div>
                    <?php elseif (isLoggedIn()): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-lock" style="font-size: 2rem; color: var(--text-muted);"></i>
                            <p class="text-muted mt-2">Send interest & get accepted to view contact details</p>
                            <?php if (!$connectionStatus): ?>
                                <button class="btn btn-primary btn-sm btn-connect" data-profile-id="<?= $profile['id'] ?>">
                                    <i class="bi bi-person-plus me-1"></i>Send Interest
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <p class="text-muted">Login to view contact details</p>
                            <a href="<?= SITE_URL ?>/login.php" class="btn btn-primary btn-sm">Login</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Profile Actions -->
                <?php if (isLoggedIn() && !$isOwner): ?>
                <div class="dashboard-card mb-4">
                    <h5><i class="bi bi-gear me-2 text-primary"></i>Actions</h5>
                    <div class="d-grid gap-2 mt-3">
                        <button class="btn btn-outline-danger btn-sm btn-shortlist <?= $isShortlisted ? 'active' : '' ?>" 
                                data-profile-id="<?= $profile['id'] ?>">
                            <i class="bi <?= $isShortlisted ? 'bi-heart-fill' : 'bi-heart' ?> me-1"></i>
                            <?= $isShortlisted ? 'Shortlisted' : 'Shortlist' ?>
                        </button>
                        <a href="#" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="bi bi-flag me-1"></i>Report Profile
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Similar Profiles -->
                <div class="dashboard-card">
                    <h5><i class="bi bi-people me-2 text-primary"></i>Similar Profiles</h5>
                    <?php
                    $stmt = $pdo->prepare(
                        "SELECT id, name, profile_id, profile_pic, gender, dob, city FROM users 
                         WHERE gender = ? AND religion = ? AND id != ? AND is_active = 1 AND status = 'approved'
                         ORDER BY RAND() LIMIT 3"
                    );
                    $stmt->execute([$profile['gender'], $profile['religion'], $profile['id']]);
                    $similar = $stmt->fetchAll();
                    ?>
                    <?php foreach ($similar as $sim): ?>
                        <div class="d-flex align-items-center gap-3 mb-3 mt-3">
                            <img src="<?= getProfilePic($sim['profile_pic'], $sim['gender']) ?>" 
                                 class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                            <div>
                                <a href="<?= SITE_URL ?>/profile.php?id=<?= $sim['id'] ?>" class="fw-semibold text-dark d-block">
                                    <?= sanitize($sim['name']) ?>
                                </a>
                                <small class="text-muted"><?= calculateAge($sim['dob']) ?> yrs | <?= sanitize($sim['city'] ?? '') ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Report Modal -->
<?php if (isLoggedIn() && !$isOwner): ?>
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Report Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= SITE_URL ?>/api/report.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="reported_id" value="<?= $profile['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <select name="reason" class="form-select" required>
                            <option value="">Select reason</option>
                            <option value="Fake Profile">Fake Profile</option>
                            <option value="Inappropriate Content">Inappropriate Content</option>
                            <option value="Harassment">Harassment</option>
                            <option value="Already Married">Already Married</option>
                            <option value="Spam">Spam</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Provide additional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
