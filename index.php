<?php
$pageTitle = 'Find Your Perfect Life Partner';
require_once __DIR__ . '/includes/functions.php';

// Fetch featured profiles
$pdo = getDBConnection();
$featuredProfiles = [];
try {
    $stmt = $pdo->query(
        "SELECT u.*, pd.education, pd.occupation, pd.annual_income, pd.height 
         FROM users u 
         LEFT JOIN profile_details pd ON u.id = pd.user_id 
         WHERE u.is_active = 1 AND u.status = 'approved' AND u.profile_pic IS NOT NULL
         ORDER BY u.is_premium DESC, RAND() 
         LIMIT 8"
    );
    $featuredProfiles = $stmt->fetchAll();
} catch (Exception $e) {
    // Table may not exist yet
}

// Fetch approved success stories
$successStories = [];
try {
    $stmt = $pdo->query(
        "SELECT ss.*, u.name FROM success_stories ss 
         JOIN users u ON ss.user_id = u.id 
         WHERE ss.is_approved = 1 
         ORDER BY ss.created_at DESC LIMIT 3"
    );
    $successStories = $stmt->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="hero-overlay"></div>
    <div class="container position-relative">
        <div class="row align-items-center min-vh-75">
            <div class="col-lg-7 text-white">
                <h1 class="hero-title animate__animated animate__fadeInUp">
                    Find Your <span class="text-accent">Perfect Match</span><br>
                    Made in Heaven
                </h1>
                <p class="hero-subtitle animate__animated animate__fadeInUp animate__delay-1s">
                    India's most trusted matrimonial platform. Connecting hearts across communities, 
                    cultures, and traditions. Join lakhs of happy couples who found their soulmate here.
                </p>
                
                <!-- Quick Search -->
                <div class="hero-search animate__animated animate__fadeInUp animate__delay-2s">
                    <form action="<?= isset($_SESSION['user_id']) ? 'search.php' : 'login.php' ?>" method="GET" class="row g-2">
                        <div class="col-md-3">
                            <select name="looking_for" class="form-select">
                                <option value="Female">Looking for Bride</option>
                                <option value="Male">Looking for Groom</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="religion" class="form-select">
                                <option value="">All Religions</option>
                                <?php foreach ($RELIGIONS as $r): ?>
                                    <option value="<?= $r ?>"><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="min_age" class="form-select">
                                <option value="">Min Age</option>
                                <?php for ($i = 18; $i <= 60; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="max_age" class="form-select">
                                <option value="">Max Age</option>
                                <?php for ($i = 18; $i <= 60; $i++): ?>
                                    <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-accent w-100">
                                <i class="bi bi-search me-1"></i>Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <div class="hero-image animate__animated animate__fadeInRight">
                    <div class="hero-stats">
                        <div class="stat-item">
                            <h3>10L+</h3>
                            <p>Active Profiles</p>
                        </div>
                        <div class="stat-item">
                            <h3>50K+</h3>
                            <p>Happy Marriages</p>
                        </div>
                        <div class="stat-item">
                            <h3>100+</h3>
                            <p>Communities</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle">Find your life partner in 4 simple steps</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="step-card text-center">
                    <div class="step-icon">
                        <span class="step-number">1</span>
                        <i class="bi bi-person-plus"></i>
                    </div>
                    <h5>Register Free</h5>
                    <p>Create your profile with personal and professional details</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="step-card text-center">
                    <div class="step-icon">
                        <span class="step-number">2</span>
                        <i class="bi bi-search-heart"></i>
                    </div>
                    <h5>Search Profiles</h5>
                    <p>Find matches by community, religion, location, and preferences</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="step-card text-center">
                    <div class="step-icon">
                        <span class="step-number">3</span>
                        <i class="bi bi-hand-thumbs-up"></i>
                    </div>
                    <h5>Connect</h5>
                    <p>Send interest and connect with profiles you like</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="step-card text-center">
                    <div class="step-icon">
                        <span class="step-number">4</span>
                        <i class="bi bi-heart"></i>
                    </div>
                    <h5>Get Married</h5>
                    <p>Meet your perfect match and start your journey together</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Browse by Community -->
<section class="py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Browse by Community</h2>
            <p class="section-subtitle">Find matches from your community</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php 
            $communityIcons = [
                'Hindu' => 'bi-flower1', 'Jain' => 'bi-gem'
            ];
            foreach ($communityIcons as $community => $icon): 
            ?>
                <div class="col-lg-3 col-md-4 col-6">
                    <a href="<?= SITE_URL ?>/search.php?religion=<?= urlencode($community) ?>" class="community-card text-center d-block">
                        <div class="community-icon">
                            <i class="bi <?= $icon ?>"></i>
                        </div>
                        <h6><?= $community ?></h6>
                        <small>Matrimony</small>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Featured Profiles -->
<?php if (!empty($featuredProfiles)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Featured Profiles</h2>
            <p class="section-subtitle">Verified and premium profiles for you</p>
        </div>
        <div class="row g-4">
            <?php foreach ($featuredProfiles as $profile): ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="profile-card">
                        <div class="profile-card-img">
                            <img src="<?= getProfilePic($profile['profile_pic'], $profile['gender']) ?>" alt="<?= sanitize($profile['name']) ?>">
                            <?php if ($profile['is_verified']): ?>
                                <span class="verified-badge"><i class="bi bi-patch-check-fill"></i></span>
                            <?php endif; ?>
                            <?php if ($profile['is_premium']): ?>
                                <span class="premium-badge"><i class="bi bi-star-fill"></i> Premium</span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-card-body">
                            <h5><?= sanitize($profile['name']) ?></h5>
                            <p class="profile-id"><?= $profile['profile_id'] ?></p>
                            <div class="profile-details-mini">
                                <span><i class="bi bi-calendar3"></i> <?= calculateAge($profile['dob']) ?> yrs</span>
                                <?php if ($profile['height']): ?>
                                    <span><i class="bi bi-rulers"></i> <?= formatHeight($profile['height']) ?></span>
                                <?php endif; ?>
                                <span><i class="bi bi-mortarboard"></i> <?= sanitize($profile['education'] ?? 'Not specified') ?></span>
                                <span><i class="bi bi-geo-alt"></i> <?= sanitize($profile['city'] ?? $profile['state'] ?? 'India') ?></span>
                            </div>
                            <a href="<?= SITE_URL ?>/profile.php?id=<?= $profile['id'] ?>" class="btn btn-outline-primary btn-sm w-100 mt-2">
                                View Profile
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?= SITE_URL ?>/search.php" class="btn btn-primary btn-lg">
                <i class="bi bi-search me-2"></i>View More Profiles
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Browse by Mother Tongue -->
<section class="py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Browse by Mother Tongue</h2>
            <p class="section-subtitle">Connect with someone who speaks your language</p>
        </div>
        <div class="row g-3 justify-content-center">
            <?php 
            $topLanguages = ['Gujarati', 'Hindi', 'Marvadi', 'English'];
            foreach ($topLanguages as $lang): 
            ?>
                <div class="col-auto">
                    <a href="<?= SITE_URL ?>/search.php?mother_tongue=<?= urlencode($lang) ?>" class="btn btn-outline-primary btn-language">
                        <?= $lang ?> Matrimony
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="py-5 bg-primary-gradient text-white">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title text-white">Why Choose <?= SITE_NAME ?>?</h2>
            <p class="section-subtitle text-white-50">Trusted by millions of Indians worldwide</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="feature-card text-center">
                    <i class="bi bi-shield-check feature-icon"></i>
                    <h5>100% Verified</h5>
                    <p>All profiles are manually verified for authenticity and trust</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="feature-card text-center">
                    <i class="bi bi-lock feature-icon"></i>
                    <h5>Privacy Protected</h5>
                    <p>Your personal information is secure with advanced privacy controls</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="feature-card text-center">
                    <i class="bi bi-people feature-icon"></i>
                    <h5>Hindu & Jain</h5>
                    <p>Profiles from Hindu and Jain communities across India</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="feature-card text-center">
                    <i class="bi bi-headset feature-icon"></i>
                    <h5>Dedicated Support</h5>
                    <p>Expert matchmaking assistance and 24/7 customer support</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Success Stories -->
<section class="py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Success Stories</h2>
            <p class="section-subtitle">Real couples who found love on <?= SITE_NAME ?></p>
        </div>
        
        <?php if (!empty($successStories)): ?>
            <div class="row g-4">
                <?php foreach ($successStories as $story): ?>
                    <div class="col-md-4">
                        <div class="testimonial-card">
                            <?php if ($story['photo']): ?>
                                <img src="<?= SITE_URL . '/' . $story['photo'] ?>" class="testimonial-img" alt="Success Story">
                            <?php endif; ?>
                            <h5><?= sanitize($story['title']) ?></h5>
                            <p><?= sanitize(substr($story['story'], 0, 200)) ?>...</p>
                            <div class="testimonial-author">
                                <strong><?= sanitize($story['name']) ?> & <?= sanitize($story['partner_name']) ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars mb-2">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p>"We found each other on <?= SITE_NAME ?> and it was love at first sight. Thank you for connecting us!"</p>
                        <div class="testimonial-author">
                            <strong>Rahul & Priya</strong>
                            <small class="d-block text-muted">Married in 2024</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars mb-2">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p>"The search filters helped us find exactly what we were looking for. Best matrimonial site!"</p>
                        <div class="testimonial-author">
                            <strong>Arjun & Sneha</strong>
                            <small class="d-block text-muted">Married in 2024</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-stars mb-2">
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                            <i class="bi bi-star-fill text-warning"></i>
                        </div>
                        <p>"Our families are so happy. <?= SITE_NAME ?> made our dream wedding possible!"</p>
                        <div class="testimonial-author">
                            <strong>Vikram & Meera</strong>
                            <small class="d-block text-muted">Married in 2023</small>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <a href="<?= SITE_URL ?>/success-stories.php" class="btn btn-outline-primary">
                Read More Stories <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section py-5">
    <div class="container text-center">
        <h2 class="text-white mb-3">Your Perfect Match is Waiting!</h2>
        <p class="text-white-50 mb-4 lead">Join <?= SITE_NAME ?> today and start your journey to a happy married life.</p>
        <a href="<?= SITE_URL ?>/register.php" class="btn btn-accent btn-lg px-5">
            <i class="bi bi-person-plus me-2"></i>Register Free Now
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
