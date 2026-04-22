<?php
$pageTitle = 'Share Your Story';
require_once __DIR__ . '/includes/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }
    
    $title = sanitize($_POST['title'] ?? '');
    $partnerName = sanitize($_POST['partner_name'] ?? '');
    $story = sanitize($_POST['story'] ?? '');
    $marriageDate = sanitize($_POST['marriage_date'] ?? '');
    
    if (empty($title)) $errors[] = 'Title is required.';
    if (empty($partnerName)) $errors[] = "Partner's name is required.";
    if (empty($story) || strlen($story) < 50) $errors[] = 'Story must be at least 50 characters.';
    
    if (empty($errors)) {
        $pdo = getDBConnection();
        $photoPath = null;
        
        // Handle photo upload
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadPhoto($_FILES['photo'], $currentUser['id']);
            if ($result['success']) {
                $photoPath = $result['path'];
            }
        }
        
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO success_stories (user_id, partner_name, title, story, photo, marriage_date, is_approved) 
                 VALUES (?, ?, ?, ?, ?, ?, 0)"
            );
            $stmt->execute([
                $currentUser['id'],
                $partnerName,
                $title,
                $story,
                $photoPath,
                $marriageDate ?: null
            ]);
            
            setFlash('success', 'Your story has been submitted! It will be displayed after admin approval.');
            redirect(SITE_URL . '/success-stories.php');
        } catch (Exception $e) {
            error_log("Story Submit Error: " . $e->getMessage());
            $errors[] = 'Failed to submit story. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-5 bg-warm">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="dashboard-card">
                    <div class="text-center mb-4">
                        <h3><i class="bi bi-heart text-danger me-2"></i>Share Your Success Story</h3>
                        <p class="text-muted">Inspire others by sharing how you found your life partner</p>
                    </div>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Story Title *</label>
                                <input type="text" class="form-control" name="title" required 
                                       placeholder="E.g., We found love across cities" value="<?= sanitize($_POST['title'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Partner's Name *</label>
                                <input type="text" class="form-control" name="partner_name" required 
                                       placeholder="Your partner's name" value="<?= sanitize($_POST['partner_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Marriage Date</label>
                                <input type="date" class="form-control" name="marriage_date" value="<?= sanitize($_POST['marriage_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Couple Photo</label>
                                <input type="file" class="form-control" name="photo" accept="image/jpeg,image/png,image/webp">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Your Story *</label>
                                <textarea name="story" class="form-control" rows="6" required minlength="50"
                                          placeholder="Share how you met, your journey together, and any message for others looking for their soulmate..."><?= sanitize($_POST['story'] ?? '') ?></textarea>
                                <small class="text-muted">Minimum 50 characters</small>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mt-4 w-100">
                            <i class="bi bi-send me-1"></i>Submit Story
                        </button>
                        <p class="text-muted text-center mt-2"><small>Your story will be reviewed by our team before publishing.</small></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
