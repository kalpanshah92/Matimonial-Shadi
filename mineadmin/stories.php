<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'stories';

$editingStory = null;
$errors = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $storyId = intval($_POST['story_id'] ?? 0);
        $userId = intval($_POST['user_id'] ?? 0);
        $partnerName = sanitize($_POST['partner_name'] ?? '');
        $title = sanitize($_POST['title'] ?? '');
        $story = sanitize($_POST['story'] ?? '');
        $marriageDate = $_POST['marriage_date'] ?? null;
        $location = sanitize($_POST['location'] ?? '');
        $isApproved = isset($_POST['is_approved']) ? 1 : 0;
        
        // Validation
        if (empty($title)) $errors[] = 'Title is required';
        if (empty($story)) $errors[] = 'Story is required';
        
        // Handle photo upload
        $photoPath = $_POST['existing_photo'] ?? '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/stories/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $filename = 'story_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetPath)) {
                $photoPath = 'uploads/stories/' . $filename;
            } else {
                $errors[] = 'Failed to upload photo';
            }
        }
        
        if (empty($errors)) {
            if ($action === 'add') {
                // Admin can add stories without user_id (use a dummy user or null)
                $stmt = $pdo->prepare(
                    "INSERT INTO success_stories (user_id, partner_name, title, story, photo, marriage_date, location, is_approved) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$userId ?: 0, $partnerName, $title, $story, $photoPath, $marriageDate, $location, $isApproved]);
            } else {
                // Edit existing
                $stmt = $pdo->prepare(
                    "UPDATE success_stories SET partner_name = ?, title = ?, story = ?, photo = ?, marriage_date = ?, location = ?, is_approved = ? WHERE id = ?"
                );
                $stmt->execute([$partnerName, $title, $story, $photoPath, $marriageDate, $location, $isApproved, $storyId]);
            }
            header('Location: stories.php');
            exit;
        }
    } elseif ($action === 'delete') {
        $storyId = intval($_POST['story_id'] ?? 0);
        if ($storyId) {
            // Delete photo file if exists
            $stmt = $pdo->prepare("SELECT photo FROM success_stories WHERE id = ?");
            $stmt->execute([$storyId]);
            $story = $stmt->fetch();
            if ($story && $story['photo']) {
                $filePath = __DIR__ . '/../' . $story['photo'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            $pdo->prepare("DELETE FROM success_stories WHERE id = ?")->execute([$storyId]);
        }
        header('Location: stories.php');
        exit;
    }
}

// Handle edit mode
if (isset($_GET['edit'])) {
    $storyId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM success_stories WHERE id = ?");
    $stmt->execute([$storyId]);
    $editingStory = $stmt->fetch();
}

// Fetch all stories
$stmt = $pdo->query(
    "SELECT ss.*, u.name, u.profile_id FROM success_stories ss 
     LEFT JOIN users u ON ss.user_id = u.id 
     ORDER BY ss.is_approved ASC, ss.created_at DESC"
);
$stories = $stmt->fetchAll();

// Fetch users for dropdown
$users = $pdo->query("SELECT id, name, profile_id FROM users WHERE status = 'approved' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Success Stories</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#storyModal" onclick="resetForm()">
            <i class="bi bi-plus-lg me-1"></i>Add Story
        </button>
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

    <div class="row g-4">
        <?php foreach ($stories as $story): ?>
        <div class="col-md-6">
            <div class="card <?= !$story['is_approved'] ? 'border-warning' : '' ?>">
                <?php if ($story['photo']): ?>
                    <img src="<?= SITE_URL . '/' . $story['photo'] ?>" class="card-img-top" style="height: 200px; object-fit: cover;" alt="">
                <?php endif; ?>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><?= htmlspecialchars($story['title']) ?></h5>
                            <p class="text-muted mb-1">
                                <?php if ($story['name']): ?>
                                    By <?= htmlspecialchars($story['name']) ?> (<?= $story['profile_id'] ?>) 
                                <?php else: ?>
                                    Added by Admin
                                <?php endif; ?>
                                & <?= htmlspecialchars($story['partner_name']) ?>
                            </p>
                        </div>
                        <span class="badge bg-<?= $story['is_approved'] ? 'success' : 'warning' ?>">
                            <?= $story['is_approved'] ? 'Approved' : 'Pending' ?>
                        </span>
                    </div>
                    <p class="mt-2"><?= htmlspecialchars(substr($story['story'], 0, 200)) ?>...</p>
                    <?php if ($story['location']): ?>
                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($story['location']) ?></small><br>
                    <?php endif; ?>
                    <?php if ($story['marriage_date']): ?>
                        <small class="text-muted"><i class="bi bi-calendar-heart me-1"></i><?= date('F Y', strtotime($story['marriage_date'])) ?></small><br>
                    <?php endif; ?>
                    <small class="text-muted">Submitted: <?= date('d M Y', strtotime($story['created_at'])) ?></small>
                    
                    <div class="mt-3 d-flex gap-2">
                        <?php if (!$story['is_approved']): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="story_id" value="<?= $story['id'] ?>">
                                <input type="hidden" name="form_action" value="edit">
                                <input type="hidden" name="is_approved" value="1">
                                <input type="hidden" name="partner_name" value="<?= htmlspecialchars($story['partner_name']) ?>">
                                <input type="hidden" name="title" value="<?= htmlspecialchars($story['title']) ?>">
                                <input type="hidden" name="story" value="<?= htmlspecialchars($story['story']) ?>">
                                <input type="hidden" name="existing_photo" value="<?= $story['photo'] ?>">
                                <input type="hidden" name="marriage_date" value="<?= $story['marriage_date'] ?>">
                                <input type="hidden" name="location" value="<?= htmlspecialchars($story['location'] ?? '') ?>">
                                <input type="hidden" name="user_id" value="<?= $story['user_id'] ?>">
                                <button class="btn btn-sm btn-success"><i class="bi bi-check me-1"></i>Approve</button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-info" onclick="editStory(<?= htmlspecialchars(json_encode($story)) ?>)">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this story?')">
                            <input type="hidden" name="story_id" value="<?= $story['id'] ?>">
                            <input type="hidden" name="form_action" value="delete">
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($stories)): ?>
        <div class="col-12 text-center py-5">
            <i class="bi bi-heart" style="font-size: 3rem; color: var(--text-muted);"></i>
            <p class="text-muted mt-2">No success stories yet.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Story Modal -->
<div class="modal fade" id="storyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Success Story</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="form_action" id="formAction" value="add">
                    <input type="hidden" name="story_id" id="editStoryId" value="">
                    <input type="hidden" name="existing_photo" id="existingPhoto" value="">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Couple Name (User)</label>
                            <select class="form-select" name="user_id" id="userId">
                                <option value="">Admin Added (No User)</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= $u['profile_id'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Partner Name *</label>
                            <input type="text" class="form-control" name="partner_name" id="partnerName" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" id="storyTitle" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Story *</label>
                            <textarea class="form-control" name="story" id="storyText" rows="5" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Marriage Date</label>
                            <input type="date" class="form-control" name="marriage_date" id="marriageDate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="storyLocation" placeholder="e.g., Ahmedabad, Gujarat">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="is_approved" id="isApproved">
                                <option value="0">Pending</option>
                                <option value="1">Approved</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/*" id="photoInput">
                            <?php if ($editingStory && $editingStory['photo']): ?>
                                <img src="<?= SITE_URL . '/' . $editingStory['photo'] ?>" class="mt-2" style="max-height: 150px;" alt="">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Story</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function resetForm() {
    document.getElementById('modalTitle').textContent = 'Add Success Story';
    document.getElementById('formAction').value = 'add';
    document.getElementById('editStoryId').value = '';
    document.getElementById('existingPhoto').value = '';
    document.getElementById('userId').value = '';
    document.getElementById('partnerName').value = '';
    document.getElementById('storyTitle').value = '';
    document.getElementById('storyText').value = '';
    document.getElementById('marriageDate').value = '';
    document.getElementById('storyLocation').value = '';
    document.getElementById('isApproved').value = '0';
    document.getElementById('photoInput').value = '';
}

function editStory(story) {
    document.getElementById('modalTitle').textContent = 'Edit Success Story';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('editStoryId').value = story.id;
    document.getElementById('existingPhoto').value = story.photo || '';
    document.getElementById('userId').value = story.user_id || '';
    document.getElementById('partnerName').value = story.partner_name || '';
    document.getElementById('storyTitle').value = story.title || '';
    document.getElementById('storyText').value = story.story || '';
    document.getElementById('marriageDate').value = story.marriage_date || '';
    document.getElementById('storyLocation').value = story.location || '';
    document.getElementById('isApproved').value = story.is_approved ? '1' : '0';
    
    var modal = new bootstrap.Modal(document.getElementById('storyModal'));
    modal.show();
}
</script>
</body>
</html>
