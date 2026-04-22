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
    <div class="container-fluid position-relative px-0">
        <div class="row g-0 align-items-stretch min-vh-75">
            <!-- Left Ad Placement -->
            <div class="col-lg-2 d-none d-lg-flex align-items-center justify-content-center">
                <a href="#" class="hero-ad-slot animate__animated animate__fadeInLeft">
                    <img src="<?= SITE_URL ?>/assets/images/ads/ad-left.jpg" alt="Advertisement">
                </a>
            </div>
            
            <!-- Center Content -->
            <div class="col-lg-8 text-white d-flex align-items-center">
                <div class="hero-center-content text-center w-100 py-5 px-3">
                    <h1 class="hero-title animate__animated animate__fadeInUp">
                        Find Your <span class="text-accent">Perfect Match</span><br>
                        Made in Heaven
                    </h1>
                    <p class="hero-subtitle animate__animated animate__fadeInUp animate__delay-1s mx-auto" style="max-width: 600px;">
                        India's most trusted matrimonial platform. Connecting hearts across communities, 
                        cultures, and traditions. Join lakhs of happy couples who found their soulmate here.
                    </p>
                    
                    <!-- Quick Search -->
                    <div class="hero-search animate__animated animate__fadeInUp animate__delay-2s mx-auto" style="max-width: 900px;">
                        <form action="<?= isset($_SESSION['user_id']) ? 'search.php' : 'login.php' ?>" method="GET" class="row g-3 justify-content-center">
                            <div class="col-md">
                                <select name="looking_for" class="form-select">
                                    <option value="Female">Bride</option>
                                    <option value="Male">Groom</option>
                                </select>
                            </div>
                            <div class="col-md">
                                <select name="religion" class="form-select">
                                    <option value="">Religion</option>
                                    <?php foreach ($RELIGIONS as $r): ?>
                                        <option value="<?= $r ?>"><?= $r ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md">
                                <select name="min_age" class="form-select">
                                    <option value="">Min Age</option>
                                    <?php for ($i = 18; $i <= 60; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md">
                                <select name="max_age" class="form-select">
                                    <option value="">Max Age</option>
                                    <?php for ($i = 18; $i <= 60; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-auto">
                                <button type="submit" class="btn btn-accent px-4">
                                    <i class="bi bi-search me-1"></i>Search
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Stats Row -->
                    <div class="hero-stats animate__animated animate__fadeInUp animate__delay-2s mt-4 justify-content-center">
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
            
            <!-- Right Ad Placement -->
            <div class="col-lg-2 d-none d-lg-flex align-items-center justify-content-center">
                <a href="#" class="hero-ad-slot animate__animated animate__fadeInRight">
                    <img src="<?= SITE_URL ?>/assets/images/ads/ad-right.jpg" alt="Advertisement">
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Sponsors Section -->
<section class="py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Our Sponsors</h2>
            <p class="section-subtitle">Join our community of enthusiasts. Engage, connect, and share your passions with like-minded individuals. Welcome to thriving discussions!</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-4 col-md-6">
                <a href="#" class="sponsor-card d-block">
                    <img src="<?= SITE_URL ?>/assets/images/sponsors/sponsor1.jpg" alt="Sponsor 1">
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="#" class="sponsor-card d-block">
                    <img src="<?= SITE_URL ?>/assets/images/sponsors/sponsor2.jpg" alt="Sponsor 2">
                </a>
            </div>
            <div class="col-lg-4 col-md-6">
                <a href="#" class="sponsor-card d-block">
                    <img src="<?= SITE_URL ?>/assets/images/sponsors/sponsor3.jpg" alt="Sponsor 3">
                </a>
            </div>
        </div>
        <p class="text-center text-muted mt-4"><small>Interested in advertising? <a href="mailto:<?= SITE_EMAIL ?>">Contact us</a>.</small></p>
        <div class="text-center mt-3">
            <a href="#" class="btn btn-outline-danger btn-lg px-4">More Community</a>
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
