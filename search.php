<?php
$pageTitle = 'Search Profiles';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$results = [];
$totalResults = 0;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * RESULTS_PER_PAGE;

// Build search query
$where = ["u.is_active = 1", "u.status = 'approved'"];
$params = [];

// Gender / Looking for
if (!empty($_GET['looking_for'])) {
    $where[] = "u.gender = ?";
    $params[] = sanitize($_GET['looking_for']);
}

// Religion
if (!empty($_GET['religion'])) {
    $where[] = "u.religion = ?";
    $params[] = sanitize($_GET['religion']);
}

// Caste
if (!empty($_GET['caste'])) {
    $where[] = "u.caste LIKE ?";
    $params[] = '%' . sanitize($_GET['caste']) . '%';
}

// Age range
if (!empty($_GET['min_age'])) {
    $where[] = "TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) >= ?";
    $params[] = intval($_GET['min_age']);
}
if (!empty($_GET['max_age'])) {
    $where[] = "TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) <= ?";
    $params[] = intval($_GET['max_age']);
}

// Marital Status
if (!empty($_GET['marital_status'])) {
    $where[] = "u.marital_status = ?";
    $params[] = sanitize($_GET['marital_status']);
}

// Mother Tongue
if (!empty($_GET['mother_tongue'])) {
    $where[] = "u.mother_tongue = ?";
    $params[] = sanitize($_GET['mother_tongue']);
}

// State
if (!empty($_GET['state'])) {
    $where[] = "u.state = ?";
    $params[] = sanitize($_GET['state']);
}

// City
if (!empty($_GET['city'])) {
    $where[] = "u.city LIKE ?";
    $params[] = '%' . sanitize($_GET['city']) . '%';
}

// Education
if (!empty($_GET['education'])) {
    $where[] = "pd.education = ?";
    $params[] = sanitize($_GET['education']);
}

// Occupation
if (!empty($_GET['occupation'])) {
    $where[] = "pd.occupation = ?";
    $params[] = sanitize($_GET['occupation']);
}

// Exclude current user
if (isLoggedIn()) {
    $where[] = "u.id != ?";
    $params[] = $_SESSION['user_id'];
}

$whereClause = implode(' AND ', $where);

// Count total results
try {
    $countQuery = "SELECT COUNT(*) as total FROM users u LEFT JOIN profile_details pd ON u.id = pd.user_id WHERE $whereClause";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalResults = $stmt->fetch()['total'];

    // Fetch results
    $query = "SELECT u.*, pd.height, pd.education, pd.occupation, pd.annual_income 
              FROM users u 
              LEFT JOIN profile_details pd ON u.id = pd.user_id 
              WHERE $whereClause 
              ORDER BY u.is_premium DESC, u.is_verified DESC, u.created_at DESC 
              LIMIT " . RESULTS_PER_PAGE . " OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Search Error: " . $e->getMessage());
}

$totalPages = ceil($totalResults / RESULTS_PER_PAGE);

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <div class="row">
            <!-- Search Filters Sidebar -->
            <div class="col-lg-3">
                <div class="search-filters">
                    <h5 class="mb-3"><i class="bi bi-funnel me-2"></i>Search Filters</h5>
                    <form method="GET" action="" id="searchForm">
                        <!-- Looking For -->
                        <div class="filter-group">
                            <label>Looking For</label>
                            <select name="looking_for" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="Female" <?= ($_GET['looking_for'] ?? '') === 'Female' ? 'selected' : '' ?>>Bride</option>
                                <option value="Male" <?= ($_GET['looking_for'] ?? '') === 'Male' ? 'selected' : '' ?>>Groom</option>
                            </select>
                        </div>

                        <!-- Age Range -->
                        <div class="filter-group">
                            <label>Age Range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <select name="min_age" id="minAge" class="form-select form-select-sm">
                                        <option value="">Min</option>
                                        <?php for ($i = 18; $i <= 60; $i++): ?>
                                            <option value="<?= $i ?>" <?= ($_GET['min_age'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select name="max_age" id="maxAge" class="form-select form-select-sm">
                                        <option value="">Max</option>
                                        <?php for ($i = 18; $i <= 60; $i++): ?>
                                            <option value="<?= $i ?>" <?= ($_GET['max_age'] ?? '') == $i ? 'selected' : '' ?>><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Religion -->
                        <div class="filter-group">
                            <label>Religion</label>
                            <select name="religion" class="form-select form-select-sm">
                                <option value="">All Religions</option>
                                <?php foreach ($RELIGIONS as $r): ?>
                                    <option value="<?= $r ?>" <?= ($_GET['religion'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Caste -->
                        <div class="filter-group">
                            <label>Caste / Community</label>
                            <input type="text" name="caste" class="form-control form-control-sm" 
                                   value="<?= sanitize($_GET['caste'] ?? '') ?>" placeholder="Enter caste">
                        </div>

                        <!-- Marital Status -->
                        <div class="filter-group">
                            <label>Marital Status</label>
                            <select name="marital_status" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <?php foreach ($MARITAL_STATUS as $ms): ?>
                                    <option value="<?= $ms ?>" <?= ($_GET['marital_status'] ?? '') === $ms ? 'selected' : '' ?>><?= $ms ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Mother Tongue -->
                        <div class="filter-group">
                            <label>Mother Tongue</label>
                            <select name="mother_tongue" class="form-select form-select-sm">
                                <option value="">All Languages</option>
                                <?php foreach ($MOTHER_TONGUES as $lang): ?>
                                    <option value="<?= $lang ?>" <?= ($_GET['mother_tongue'] ?? '') === $lang ? 'selected' : '' ?>><?= $lang ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- State -->
                        <div class="filter-group">
                            <label>State</label>
                            <select name="state" class="form-select form-select-sm">
                                <option value="">All States</option>
                                <?php foreach ($INDIAN_STATES as $state): ?>
                                    <option value="<?= $state ?>" <?= ($_GET['state'] ?? '') === $state ? 'selected' : '' ?>><?= $state ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- City -->
                        <div class="filter-group">
                            <label>City</label>
                            <input type="text" name="city" class="form-control form-control-sm" 
                                   value="<?= sanitize($_GET['city'] ?? '') ?>" placeholder="Enter city">
                        </div>

                        <!-- Education -->
                        <div class="filter-group">
                            <label>Education</label>
                            <select name="education" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <?php foreach ($EDUCATION_LEVELS as $edu): ?>
                                    <option value="<?= $edu ?>" <?= ($_GET['education'] ?? '') === $edu ? 'selected' : '' ?>><?= $edu ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Occupation -->
                        <div class="filter-group">
                            <label>Occupation</label>
                            <select name="occupation" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <?php foreach ($OCCUPATIONS as $occ): ?>
                                    <option value="<?= $occ ?>" <?= ($_GET['occupation'] ?? '') === $occ ? 'selected' : '' ?>><?= $occ ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-2">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                        <a href="<?= SITE_URL ?>/search.php" class="btn btn-outline-secondary w-100 mt-2 btn-sm">Clear Filters</a>
                    </form>
                </div>
            </div>

            <!-- Results -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>
                        <?= number_format($totalResults) ?> Profile<?= $totalResults !== 1 ? 's' : '' ?> Found
                    </h5>
                </div>

                <?php if (!empty($results)): ?>
                    <div class="row g-3">
                        <?php foreach ($results as $profile): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="profile-card">
                                    <div class="profile-card-img" style="height: 220px;">
                                        <img src="<?= getProfilePic($profile['profile_pic'], $profile['gender']) ?>" 
                                             alt="<?= sanitize($profile['name']) ?>">
                                        <?php if ($profile['is_verified']): ?>
                                            <span class="verified-badge"><i class="bi bi-patch-check-fill"></i></span>
                                        <?php endif; ?>
                                        <?php if ($profile['is_premium']): ?>
                                            <span class="premium-badge"><i class="bi bi-star-fill"></i> Premium</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="profile-card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?= sanitize($profile['name']) ?></h6>
                                                <small class="text-muted"><?= $profile['profile_id'] ?></small>
                                            </div>
                                            <?php if (isLoggedIn()): ?>
                                                <button class="btn btn-sm btn-outline-danger btn-shortlist" data-profile-id="<?= $profile['id'] ?>" title="Shortlist">
                                                    <i class="bi bi-heart"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div class="profile-details-mini mt-2">
                                            <span><i class="bi bi-calendar3"></i> <?= calculateAge($profile['dob']) ?> yrs</span>
                                            <?php if ($profile['height']): ?>
                                                <span><i class="bi bi-rulers"></i> <?= formatHeight($profile['height']) ?></span>
                                            <?php endif; ?>
                                            <span><i class="bi bi-book"></i> <?= sanitize($profile['religion'] ?? 'Not specified') ?></span>
                                            <span><i class="bi bi-geo-alt"></i> <?= sanitize($profile['city'] ?? $profile['state'] ?? 'India') ?></span>
                                            <?php if ($profile['education']): ?>
                                                <span><i class="bi bi-mortarboard"></i> <?= sanitize($profile['education']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-2 mt-3">
                                            <a href="<?= SITE_URL ?>/profile.php?id=<?= $profile['id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">View Profile</a>
                                            <?php if (isLoggedIn()): ?>
                                                <button class="btn btn-primary btn-sm btn-connect" data-profile-id="<?= $profile['id'] ?>">
                                                    <i class="bi bi-person-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-search" style="font-size: 4rem; color: var(--text-muted);"></i>
                        <h5 class="mt-3">No profiles found</h5>
                        <p class="text-muted">Try adjusting your search filters to find more profiles.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
