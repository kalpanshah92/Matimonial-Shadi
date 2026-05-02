<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$adminPage = 'plans';

// Handle plan updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $planId = intval($_POST['plan_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $duration = intval($_POST['duration_days'] ?? 0);
    $maxContacts = intval($_POST['max_contacts'] ?? 0);
    $maxMessages = intval($_POST['max_messages'] ?? 0);
    $features = $_POST['features'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    $featuresArray = array_filter(array_map('trim', explode("\n", $features)));
    $featuresJson = json_encode(array_values($featuresArray));
    
    if ($planId) {
        $stmt = $pdo->prepare(
            "UPDATE plans SET name=?, price=?, duration_days=?, features=?, max_contacts=?, max_messages=?, is_active=? WHERE id=?"
        );
        $stmt->execute([$name, $price, $duration, $featuresJson, $maxContacts, $maxMessages, $isActive, $planId]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO plans (name, price, duration_days, features, max_contacts, max_messages, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $price, $duration, $featuresJson, $maxContacts, $maxMessages, $isActive]);
    }
    
    header('Location: plans.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM plans ORDER BY price ASC");
$plans = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plans & Pricing | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="admin-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Plans & Pricing</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#planModal">
            <i class="bi bi-plus me-1"></i>Add Plan
        </button>
    </div>

    <div class="row g-4">
        <?php foreach ($plans as $plan): 
            $features = json_decode($plan['features'], true) ?: [];
        ?>
        <div class="col-md-3">
            <div class="card h-100 <?= !$plan['is_active'] ? 'opacity-50' : '' ?>">
                <div class="card-body text-center">
                    <h5><?= htmlspecialchars($plan['name']) ?></h5>
                    <h3 class="text-primary">
                        <?= $plan['price'] > 0 ? '₹' . number_format($plan['price']) : 'Free' ?>
                    </h3>
                    <small class="text-muted"><?= $plan['duration_days'] ?> days</small>
                    <hr>
                    <ul class="list-unstyled text-start">
                        <?php foreach ($features as $f): ?>
                            <li class="mb-1"><i class="bi bi-check text-success me-1"></i><?= htmlspecialchars($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="text-muted mt-2">
                        <small>
                            Contacts/day: <?= $plan['max_contacts'] == 999 ? '∞' : $plan['max_contacts'] ?> | 
                            Messages/day: <?= $plan['max_messages'] == 999 ? '∞' : $plan['max_messages'] ?>
                        </small>
                    </p>
                    <span class="badge bg-<?= $plan['is_active'] ? 'success' : 'secondary' ?>"><?= $plan['is_active'] ? 'Active' : 'Inactive' ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add/Edit Plan Modal -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="plan_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Price (₹)</label>
                            <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Duration (days)</label>
                            <input type="number" class="form-control" name="duration_days" min="1" required>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6">
                            <label class="form-label">Max Contacts/day</label>
                            <input type="number" class="form-control" name="max_contacts" min="0" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Max Messages/day</label>
                            <input type="number" class="form-control" name="max_messages" min="0" value="0">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Features (one per line)</label>
                        <textarea class="form-control" name="features" rows="5" placeholder="Browse Profiles&#10;Send Interests&#10;Advanced Search"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
