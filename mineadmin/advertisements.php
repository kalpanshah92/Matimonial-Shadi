<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Super admin only
if (($_SESSION['admin_role'] ?? '') !== 'super_admin') {
    header('Location: index.php?error=permission');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'advertisements';

$errors = [];
$success = '';

$AD_UPLOAD_DIR = ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'ads';
$AD_UPLOAD_URL_PREFIX = 'assets/images/ads';

if (!is_dir($AD_UPLOAD_DIR)) {
    @mkdir($AD_UPLOAD_DIR, 0775, true);
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }

    $action = $_POST['action'] ?? '';

    if (empty($errors) && $action === 'upload') {
        $position = $_POST['position'] ?? '';
        $linkUrl = trim($_POST['link_url'] ?? '#');
        $altText = trim($_POST['alt_text'] ?? 'Advertisement');
        $displayOrder = intval($_POST['display_order'] ?? 0);

        if (!in_array($position, ['hero_left', 'hero_right', 'sponsor'])) {
            $errors[] = 'Invalid position.';
        } elseif (empty($_FILES['ad_image']) || $_FILES['ad_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a valid image file.';
        } elseif ($_FILES['ad_image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image exceeds 5MB limit.';
        } else {
            $ext = strtolower(pathinfo($_FILES['ad_image']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $errors[] = 'Allowed formats: JPG, PNG, WebP, GIF.';
            } else {
                $filename = 'ad_' . $position . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destPath = $AD_UPLOAD_DIR . DIRECTORY_SEPARATOR . $filename;

                if (move_uploaded_file($_FILES['ad_image']['tmp_name'], $destPath)) {
                    $relPath = $AD_UPLOAD_URL_PREFIX . '/' . $filename;
                    $stmt = $pdo->prepare(
                        "INSERT INTO advertisements (position, image_path, link_url, alt_text, display_order, is_active) 
                         VALUES (?, ?, ?, ?, ?, 1)"
                    );
                    $stmt->execute([$position, $relPath, $linkUrl ?: '#', $altText ?: 'Advertisement', $displayOrder]);
                    $success = 'Advertisement added successfully.';
                } else {
                    $errors[] = 'Failed to save uploaded file.';
                }
            }
        }
    }

    if (empty($errors) && $action === 'toggle') {
        $adId = intval($_POST['ad_id'] ?? 0);
        $pdo->prepare("UPDATE advertisements SET is_active = 1 - is_active WHERE id = ?")->execute([$adId]);
        $success = 'Status updated.';
    }

    if (empty($errors) && $action === 'delete') {
        $adId = intval($_POST['ad_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM advertisements WHERE id = ?");
        $stmt->execute([$adId]);
        $ad = $stmt->fetch();
        if ($ad) {
            $filePath = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $ad['image_path']);
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
            $pdo->prepare("DELETE FROM advertisements WHERE id = ?")->execute([$adId]);
            $success = 'Advertisement deleted.';
        }
    }
}

// Fetch all ads grouped by position
$ads = [];
try {
    $stmt = $pdo->query("SELECT * FROM advertisements ORDER BY position, display_order, created_at DESC");
    foreach ($stmt->fetchAll() as $row) {
        $ads[$row['position']][] = $row;
    }
} catch (Exception $e) {
    $errors[] = 'Advertisements table not found. Please run the database migration.';
}

$positions = [
    'hero_left'  => ['label' => 'Hero Left Banner', 'desc' => 'Vertical banner shown on the left side of the homepage hero section.'],
    'hero_right' => ['label' => 'Hero Right Banner', 'desc' => 'Vertical banner shown on the right side of the homepage hero section.'],
    'sponsor'    => ['label' => 'Sponsor Logos', 'desc' => 'Logos shown in the "Our Sponsors" section. Multiple supported.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Advertisements | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-image me-2"></i>Manage Advertisements</h4>
            <small class="text-muted">Add or remove homepage banner ads and sponsor logos.</small>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo htmlspecialchars($e) . '<br>'; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Upload form -->
    <div class="card mb-4">
        <div class="card-header bg-white"><strong><i class="bi bi-upload me-2"></i>Add New Advertisement</strong></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="upload">

                <div class="col-md-3">
                    <label class="form-label">Position</label>
                    <select class="form-select" name="position" required>
                        <?php foreach ($positions as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Image File</label>
                    <input type="file" class="form-control" name="ad_image" accept="image/*" required>
                    <small class="text-muted">Max 5MB. JPG/PNG/WebP/GIF.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Link URL</label>
                    <input type="text" class="form-control" name="link_url" placeholder="https://example.com or #">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Display Order</label>
                    <input type="number" class="form-control" name="display_order" value="0">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alt Text</label>
                    <input type="text" class="form-control" name="alt_text" placeholder="Advertisement" maxlength="150">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Advertisement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing ads grouped by position -->
    <?php foreach ($positions as $key => $info): ?>
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= $info['label'] ?></strong>
                    <small class="text-muted d-block"><?= $info['desc'] ?></small>
                </div>
                <span class="badge bg-secondary"><?= count($ads[$key] ?? []) ?> total</span>
            </div>
            <div class="card-body">
                <?php if (empty($ads[$key])): ?>
                    <p class="text-muted mb-0"><i class="bi bi-info-circle me-1"></i>No advertisements in this slot. Default fallback image will be used (if it exists).</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($ads[$key] as $ad): ?>
                            <div class="col-md-3 col-6">
                                <div class="card h-100<?= !$ad['is_active'] ? ' opacity-50' : '' ?>">
                                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars($ad['image_path']) ?>" class="card-img-top" style="height: 180px; object-fit: contain; background: #f8f9fa;">
                                    <div class="card-body p-2 small">
                                        <div class="text-truncate"><strong>Order:</strong> <?= $ad['display_order'] ?></div>
                                        <div class="text-truncate text-muted"><strong>Link:</strong> <?= htmlspecialchars($ad['link_url']) ?></div>
                                        <div class="mt-1">
                                            <?php if ($ad['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex gap-1 mt-2">
                                            <form method="POST" class="flex-grow-1">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary w-100" title="Toggle active">
                                                    <i class="bi bi-toggle-<?= $ad['is_active'] ? 'on' : 'off' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" class="flex-grow-1" onsubmit="return confirm('Delete this advertisement? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="ad_id" value="<?= $ad['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger w-100" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
