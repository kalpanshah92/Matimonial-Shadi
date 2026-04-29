<?php
$pageTitle = 'Success Stories';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

$stories = [];
try {
    $stmt = $pdo->query(
        "SELECT ss.*, u.name, u.gender FROM success_stories ss 
         JOIN users u ON ss.user_id = u.id 
         WHERE ss.is_approved = 1 
         ORDER BY ss.created_at DESC"
    );
    $stories = $stmt->fetchAll();
} catch (Exception $e) {}

// Sample/demo stories shown when no real stories exist yet
$sampleStories = [
    [
        'name' => 'Priya',
        'partner_name' => 'Rahul',
        'title' => 'A Match Made in Heaven',
        'story' => 'We connected on ' . SITE_NAME . ' in early 2023. What started as a simple "hello" turned into endless conversations about life, dreams, and family. Our families met within a month and instantly bonded. Today we are blessed with a beautiful marriage and cherish every moment together. Thank you for making our dreams come true!',
        'marriage_date' => '2024-02-14',
        'photo' => 'https://image.pollinations.ai/prompt/ghibli%20style%20anime%20couple%20holding%20hands%20under%20cherry%20blossom%20tree%20soft%20pastel%20colors%20romantic%20illustration?width=600&height=400&nologo=true',
        'location' => 'Ahmedabad, Gujarat',
    ],
    [
        'name' => 'Ankita',
        'partner_name' => 'Vivek',
        'title' => 'Found My Soulmate',
        'story' => 'After months of searching, I almost gave up. Then I came across Vivek\'s profile and something just clicked. We share the same values, love for family, and passion for travel. Our wedding was a dream come true with both families blessing us. ' . SITE_NAME . ' truly changed our lives forever.',
        'marriage_date' => '2024-05-22',
        'photo' => 'https://image.pollinations.ai/prompt/ghibli%20style%20anime%20couple%20walking%20on%20beach%20sunset%20soft%20warm%20colors%20romantic%20illustration?width=600&height=400&nologo=true',
        'location' => 'Surat, Gujarat',
    ],
    [
        'name' => 'Neha',
        'partner_name' => 'Karan',
        'title' => 'Tradition Meets Love',
        'story' => 'Both our families were looking for someone from the same Samaj. When our parents connected through ' . SITE_NAME . ', they knew it was a perfect match. Karan and I met and instantly felt a deep connection. Our traditional wedding was blessed with love from everyone. We are forever grateful!',
        'marriage_date' => '2023-11-10',
        'photo' => 'https://image.pollinations.ai/prompt/ghibli%20style%20anime%20wedding%20couple%20traditional%20ceremony%20soft%20elegant%20colors%20illustration?width=600&height=400&nologo=true',
        'location' => 'Vadodara, Gujarat',
    ],
    [
        'name' => 'Riya',
        'partner_name' => 'Amit',
        'title' => 'From Strangers to Soulmates',
        'story' => 'Our story began with a simple "interest" sent through ' . SITE_NAME . '. What followed was weeks of beautiful conversations that grew into deep love and understanding. Our families loved each other instantly. The wedding was a magical celebration of our journey together.',
        'marriage_date' => '2024-01-18',
        'photo' => 'https://image.pollinations.ai/prompt/ghibli%20style%20anime%20couple%20in%20flower%20field%20spring%20soft%20pink%20and%20green%20colors%20romantic%20illustration?width=600&height=400&nologo=true',
        'location' => 'Rajkot, Gujarat',
    ],
    [
        'name' => 'Meera',
        'partner_name' => 'Rohan',
        'title' => 'Destiny Through Destiny',
        'story' => 'I was skeptical about online matrimony until I met Rohan. Our values aligned perfectly and we both wanted similar things in life. Our parents met, blessed our union, and we got married within 6 months. Every day feels like a blessing. Thank you ' . SITE_NAME . '!',
        'marriage_date' => '2024-06-15',
        'photo' => 'https://image.pollinations.ai/prompt/ghibli%20style%20anime%20couple%20stargazing%20night%20sky%20soft%20dreamy%20colors%20romantic%20illustration?width=600&height=400&nologo=true',
        'location' => 'Mumbai, Maharashtra',
    ],
    [
        'name' => 'Pooja',
        'partner_name' => 'Sagar',
        'title' => 'Happily Ever After',
        'story' => 'Sagar\'s profile stood out from the rest. His kindness, ambition, and family values matched exactly what I was looking for. Our engagement happened within 3 months and we celebrated our wedding surrounded by loved ones. We recommend ' . SITE_NAME . ' to everyone searching for true love.',
        'marriage_date' => '2023-12-05',
        'photo' => 'https://image.pollinations.ai/prompt/ghibli%20style%20anime%20couple%20in%20garden%20surrounded%20by%20butterflies%20soft%20pastel%20colors%20romantic%20illustration?width=600&height=400&nologo=true',
        'location' => 'Bhavnagar, Gujarat',
    ],
];

// Stats
$totalSuccess = count($stories) > 0 ? count($stories) : 5000;
$totalProfiles = 0;
$totalMatches = 0;
try {
    $totalProfiles = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();
    $totalMatches = (int)$pdo->query("SELECT COUNT(*) FROM connections WHERE status = 'accepted'")->fetchColumn();
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="success-hero text-white text-center py-5">
    <div class="container py-4">
        <i class="bi bi-hearts mb-3" style="font-size: 3.5rem;"></i>
        <h1 class="display-4 fw-bold mb-3">Love Stories That Inspire</h1>
        <p class="lead mb-4 mx-auto" style="max-width: 700px;">
            Thousands of couples have found their soulmates on <?= SITE_NAME ?>.
            Read their beautiful stories and start your own journey today.
        </p>
        <div class="row g-3 justify-content-center mt-3">
            <div class="col-auto">
                <div class="stat-chip">
                    <i class="bi bi-heart-fill"></i>
                    <strong><?= number_format($totalSuccess) ?>+</strong>
                    <span>Happy Couples</span>
                </div>
            </div>
            <div class="col-auto">
                <div class="stat-chip">
                    <i class="bi bi-people-fill"></i>
                    <strong><?= number_format(max($totalProfiles, 10000)) ?>+</strong>
                    <span>Verified Profiles</span>
                </div>
            </div>
            <div class="col-auto">
                <div class="stat-chip">
                    <i class="bi bi-link-45deg"></i>
                    <strong><?= number_format(max($totalMatches, 25000)) ?>+</strong>
                    <span>Matches Made</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Success Stories Grid -->
<section class="py-5 bg-warm">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title">Real Stories, Real Love</h2>
            <p class="section-subtitle">Couples who found their perfect match on <?= SITE_NAME ?></p>
        </div>

        <?php if (!empty($stories)): ?>
            <div class="row g-4">
                <?php foreach ($stories as $story): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="story-card h-100">
                            <div class="story-img-wrap">
                                <?php if ($story['photo']): ?>
                                    <img src="<?= SITE_URL . '/' . $story['photo'] ?>" class="story-img" alt="<?= sanitize($story['title']) ?>">
                                <?php else: ?>
                                    <img src="https://placehold.co/600x400/C0392B/FFFFFF?text=<?= urlencode($story['name'] . ' %26 ' . $story['partner_name']) ?>" class="story-img" alt="">
                                <?php endif; ?>
                                <div class="story-heart"><i class="bi bi-heart-fill"></i></div>
                            </div>
                            <div class="story-body">
                                <h5 class="story-title"><?= sanitize($story['title']) ?></h5>
                                <p class="story-text"><?= nl2br(sanitize(mb_strimwidth($story['story'], 0, 220, '...'))) ?></p>
                                <div class="story-footer">
                                    <div class="story-couple">
                                        <i class="bi bi-person-hearts me-1"></i>
                                        <strong><?= sanitize($story['name']) ?> &amp; <?= sanitize($story['partner_name']) ?></strong>
                                    </div>
                                    <?php if ($story['marriage_date']): ?>
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-calendar-heart me-1"></i>
                                            Married: <?= date('F Y', strtotime($story['marriage_date'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mb-4">
                <i class="bi bi-info-circle me-2"></i>
                Be the first real couple to share your story! Below are sample stories showcasing the joy of finding love on <?= SITE_NAME ?>.
            </div>
            <div class="row g-4">
                <?php foreach ($sampleStories as $story): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="story-card h-100">
                            <div class="story-img-wrap">
                                <img src="<?= $story['photo'] ?>" class="story-img" alt="<?= sanitize($story['title']) ?>">
                                <div class="story-heart"><i class="bi bi-heart-fill"></i></div>
                            </div>
                            <div class="story-body">
                                <h5 class="story-title"><?= sanitize($story['title']) ?></h5>
                                <p class="story-text"><?= nl2br(sanitize(mb_strimwidth($story['story'], 0, 220, '...'))) ?></p>
                                <div class="story-footer">
                                    <div class="story-couple">
                                        <i class="bi bi-person-hearts me-1"></i>
                                        <strong><?= sanitize($story['name']) ?> &amp; <?= sanitize($story['partner_name']) ?></strong>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-calendar-heart me-1"></i>
                                        Married: <?= date('F Y', strtotime($story['marriage_date'])) ?>
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?= sanitize($story['location']) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Share Your Story CTA -->
<section class="py-5 bg-primary-gradient text-white text-center">
    <div class="container">
        <i class="bi bi-heart-fill mb-3" style="font-size: 3rem;"></i>
        <h2 class="mb-3">Found Your Match on <?= SITE_NAME ?>?</h2>
        <p class="lead mb-4 mx-auto" style="max-width: 650px;">
            Your love story could inspire thousands of singles searching for their soulmate.
            Share your journey and spread the joy!
        </p>
        <?php if (isLoggedIn()): ?>
            <a href="<?= SITE_URL ?>/share-story.php" class="btn btn-light btn-lg px-4">
                <i class="bi bi-pencil-square me-2"></i>Share Your Story
            </a>
        <?php else: ?>
            <a href="<?= SITE_URL ?>/register.php" class="btn btn-light btn-lg px-4 me-2">
                <i class="bi bi-person-plus me-2"></i>Register Free
            </a>
            <a href="<?= SITE_URL ?>/login.php" class="btn btn-outline-light btn-lg px-4">
                <i class="bi bi-box-arrow-in-right me-2"></i>Login
            </a>
        <?php endif; ?>
    </div>
</section>

<style>
.success-hero {
    background: linear-gradient(135deg, #C0392B 0%, #E67E22 100%);
    position: relative;
}
.stat-chip {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border-radius: 50px;
    padding: 12px 24px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: 1px solid rgba(255,255,255,0.25);
}
.stat-chip i { font-size: 1.3rem; }
.stat-chip strong { font-size: 1.2rem; }
.stat-chip span { opacity: 0.9; font-size: 0.95rem; }

.story-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transition: transform 0.3s, box-shadow 0.3s;
    display: flex;
    flex-direction: column;
}
.story-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 30px rgba(0,0,0,0.15);
}
.story-img-wrap {
    position: relative;
    overflow: hidden;
    height: 240px;
}
.story-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s;
}
.story-card:hover .story-img { transform: scale(1.06); }
.story-heart {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #fff;
    color: #C0392B;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
}
.story-body { padding: 22px; flex: 1; display: flex; flex-direction: column; }
.story-title {
    color: #C0392B;
    font-weight: 700;
    margin-bottom: 12px;
}
.story-text {
    color: #555;
    line-height: 1.6;
    font-size: 0.95rem;
    flex: 1;
}
.story-footer {
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
    margin-top: 15px;
}
.story-couple { color: #2c3e50; font-size: 1rem; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
