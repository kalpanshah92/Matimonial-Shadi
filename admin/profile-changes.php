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
$adminPage = 'profile-changes';

// Filters
$status = $_GET['status'] ?? 'pending';
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * ADMIN_RESULTS_PER_PAGE;

$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "pcr.status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM profile_change_requests pcr WHERE $whereClause");
$countStmt->execute($params);
$totalResults = $countStmt->fetchColumn();
$totalPages = ceil($totalResults / ADMIN_RESULTS_PER_PAGE);

// Fetch requests
$sql = "SELECT pcr.*, u.name, u.profile_id, u.email, u.gender, u.profile_pic
        FROM profile_change_requests pcr
        JOIN users u ON pcr.user_id = u.id
        WHERE $whereClause
        ORDER BY pcr.created_at DESC
        LIMIT " . ADMIN_RESULTS_PER_PAGE . " OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Field labels for human-readable display
$fieldLabels = [
    'name' => 'Full Name',
    'religion' => 'Religion',
    'caste' => 'Samaj Name',
    'sub_caste' => 'Sub Samaj',
    'mother_tongue' => 'Mother Tongue',
    'marital_status' => 'Marital Status',
    'state' => 'State',
    'city' => 'City',
    'about_me' => 'About Me',
    'height' => 'Height (cm)',
    'weight' => 'Weight (kg)',
    'complexion' => 'Complexion',
    'body_type' => 'Body Type',
    'blood_group' => 'Blood Group',
    'diet' => 'Diet',
    'smoking' => 'Smoking',
    'drinking' => 'Drinking',
    'hobbies' => 'Hobbies',
    'education' => 'Education',
    'education_detail' => 'Education Detail',
    'occupation' => 'Occupation',
    'occupation_detail' => 'Occupation Detail',
    'company' => 'Company',
    'annual_income' => 'Annual Income',
    'working_city' => 'Working City',
    'father_name' => 'Father\'s Name',
    'father_occupation' => 'Father\'s Occupation',
    'mother_name' => 'Mother\'s Name',
    'mother_occupation' => 'Mother\'s Occupation',
    'brothers' => 'Brothers',
    'brothers_married' => 'Brothers Married',
    'sisters' => 'Sisters',
    'sisters_married' => 'Sisters Married',
    'family_type' => 'Family Type',
    'family_status' => 'Family Status',
    'family_values' => 'Family Values',
    'gotra' => 'Gotra',
    'about_family' => 'About Family',
    'min_age' => 'Min Age',
    'max_age' => 'Max Age',
    'min_height' => 'Min Height',
    'max_height' => 'Max Height',
    'about_partner' => 'About Partner',
    'min_income' => 'Min Income',
    'max_income' => 'Max Income',
];

$sectionLabels = [
    'basic' => 'Basic Details',
    'personal' => 'Personal Details',
    'professional' => 'Professional Details',
    'family' => 'Family Details',
    'partner' => 'Partner Preferences',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Change Requests | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .diff-table th { width: 25%; background: #f8f9fa; }
        .diff-old { background: #ffeef0; color: #c0392b; }
        .diff-new { background: #e6ffed; color: #1e7e34; }
        .diff-unchanged { color: #999; }
        .change-card { border-left: 4px solid #f0ad4e; }
        .change-card.approved { border-left-color: #28a745; }
        .change-card.rejected { border-left-color: #dc3545; }
    </style>
</head>
<body class="admin-body">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="admin-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4><i class="bi bi-pencil-square me-2"></i>Profile Change Requests</h4>
                    <p class="text-muted mb-0">Review and approve profile changes submitted by users</p>
                </div>
                <span class="badge bg-warning fs-6"><?= $totalResults ?> <?= $status ?> request(s)</span>
            </div>

            <!-- Status Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>" href="?status=pending">
                        <i class="bi bi-clock me-1"></i>Pending
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status === 'approved' ? 'active' : '' ?>" href="?status=approved">
                        <i class="bi bi-check-circle me-1"></i>Approved
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $status === 'rejected' ? 'active' : '' ?>" href="?status=rejected">
                        <i class="bi bi-x-circle me-1"></i>Rejected
                    </a>
                </li>
            </ul>

            <?php
                // Fetch pending photos
                $pendingPhotos = [];
                if ($status === 'pending') {
                    $photoStmt = $pdo->query(
                        "SELECT p.*, u.name, u.profile_id, u.email, u.gender, u.profile_pic 
                         FROM photos p JOIN users u ON p.user_id = u.id 
                         WHERE p.is_approved = 0 ORDER BY p.created_at DESC"
                    );
                    $pendingPhotos = $photoStmt->fetchAll();
                }
            ?>

            <?php if (!empty($pendingPhotos)): ?>
                <div class="card mb-4 change-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-image me-2"></i>Pending Photo Approvals (<?= count($pendingPhotos) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($pendingPhotos as $photo): ?>
                                <div class="col-md-3 col-6" id="photo-card-<?= $photo['id'] ?>">
                                    <div class="card h-100">
                                        <img src="<?= SITE_URL ?>/<?= $photo['photo_path'] ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                                        <div class="card-body p-2 text-center">
                                            <strong class="d-block small"><?= htmlspecialchars($photo['name']) ?></strong>
                                            <small class="text-muted"><?= $photo['profile_id'] ?></small>
                                            <?php if ($photo['is_primary']): ?>
                                                <br><span class="badge bg-info">Wants as Primary</span>
                                            <?php endif; ?>
                                            <div class="mt-2 d-flex gap-1 justify-content-center">
                                                <button class="btn btn-success btn-sm btn-approve-photo" data-id="<?= $photo['id'] ?>">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                                <button class="btn btn-danger btn-sm btn-reject-photo" data-id="<?= $photo['id'] ?>">
                                                    <i class="bi bi-x-lg"></i> Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($requests) && empty($pendingPhotos)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-check-circle text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">No <?= $status ?> change requests</h5>
                </div>
            <?php endif; ?>

            <!-- Change Requests -->
            <?php foreach ($requests as $req): ?>
                <?php
                    $oldData = json_decode($req['old_data'], true) ?: [];
                    $newData = json_decode($req['new_data'], true) ?: [];
                    $changedFields = [];
                    $unchangedFields = [];
                    foreach ($newData as $key => $val) {
                        if ((string)$val !== (string)($oldData[$key] ?? '')) {
                            $changedFields[$key] = true;
                        } else {
                            $unchangedFields[$key] = true;
                        }
                    }
                ?>
                <div class="card mb-4 change-card <?= $req['status'] ?>">
                    <div class="card-header bg-white">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <img src="<?= getProfilePic($req['profile_pic'], $req['gender']) ?>"
                                         class="rounded-circle me-3" width="45" height="45" alt="">
                                    <div>
                                        <strong><?= htmlspecialchars($req['name']) ?></strong>
                                        <small class="d-block text-muted"><?= $req['profile_id'] ?> &middot; <?= htmlspecialchars($req['email']) ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <?php
                                    // Determine which sections have changes in merged data
                                    $newData = json_decode($req['new_data'], true) ?: [];
                                    $sectionsWithChanges = [];
                                    foreach ($newData as $key => $val) {
                                        // Map field to section based on field labels
                                        if (in_array($key, ['name', 'religion', 'caste', 'sub_caste', 'mother_tongue', 'marital_status', 'state', 'city'])) {
                                            $sectionsWithChanges['basic'] = true;
                                        } elseif (in_array($key, ['height', 'weight', 'complexion', 'body_type', 'blood_group', 'diet', 'smoking', 'drinking', 'hobbies', 'about_me'])) {
                                            $sectionsWithChanges['personal'] = true;
                                        } elseif (in_array($key, ['education', 'education_detail', 'occupation', 'occupation_detail', 'company', 'annual_income', 'working_city'])) {
                                            $sectionsWithChanges['professional'] = true;
                                        } elseif (in_array($key, ['father_name', 'father_occupation', 'mother_name', 'mother_occupation', 'brothers', 'brothers_married', 'sisters', 'sisters_married', 'family_type', 'family_status', 'family_values', 'gotra', 'about_family'])) {
                                            $sectionsWithChanges['family'] = true;
                                        } elseif (in_array($key, ['min_age', 'max_age', 'min_height', 'max_height', 'min_income', 'max_income', 'about_partner'])) {
                                            $sectionsWithChanges['partner'] = true;
                                        }
                                    }
                                ?>
                                <?php if (count($sectionsWithChanges) > 1): ?>
                                    <span class="badge bg-primary">Multiple Sections</span>
                                    <small class="d-block text-muted mt-1"><?= implode(', ', array_keys($sectionsWithChanges)) ?></small>
                                <?php else: ?>
                                    <span class="badge bg-primary"><?= $sectionLabels[$req['section']] ?? ucfirst($req['section']) ?></span>
                                <?php endif; ?>
                                <small class="d-block text-muted mt-1"><?= date('d M Y, h:i A', strtotime($req['created_at'])) ?></small>
                            </div>
                            <div class="col-md-3 text-end">
                                <?php if ($req['status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-sm btn-approve-change" data-id="<?= $req['id'] ?>">
                                        <i class="bi bi-check-lg me-1"></i>Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm btn-reject-change" data-id="<?= $req['id'] ?>">
                                        <i class="bi bi-x-lg me-1"></i>Reject
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-<?= $req['status'] === 'approved' ? 'success' : 'danger' ?> fs-6">
                                        <?= ucfirst($req['status']) ?>
                                    </span>
                                    <?php if ($req['reviewed_at']): ?>
                                        <small class="d-block text-muted mt-1"><?= date('d M Y', strtotime($req['reviewed_at'])) ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width:25%;">Field</th>
                                    <th style="width:37.5%;">Old Value</th>
                                    <th style="width:37.5%;">New Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($newData as $key => $newVal): ?>
                                    <?php
                                        $oldVal = $oldData[$key] ?? '';
                                        $isChanged = isset($changedFields[$key]);
                                        $label = $fieldLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
                                    ?>
                                    <?php if ($isChanged): ?>
                                    <tr>
                                        <td><strong><?= $label ?></strong></td>
                                        <td class="diff-old">
                                            <del><?= htmlspecialchars($oldVal ?: '(empty)') ?></del>
                                        </td>
                                        <td class="diff-new">
                                            <strong><?= htmlspecialchars($newVal ?: '(empty)') ?></strong>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (!empty($unchangedFields)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">
                                        <a class="btn btn-link btn-sm" data-bs-toggle="collapse" href="#unchanged-<?= $req['id'] ?>">
                                            Show <?= count($unchangedFields) ?> unchanged field(s)
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($unchangedFields)): ?>
                            <tbody class="collapse" id="unchanged-<?= $req['id'] ?>">
                                <?php foreach ($newData as $key => $newVal): ?>
                                    <?php if (isset($unchangedFields[$key])): ?>
                                    <tr>
                                        <td class="diff-unchanged"><?= $fieldLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)) ?></td>
                                        <td class="diff-unchanged"><?= htmlspecialchars($newVal ?: '(empty)') ?></td>
                                        <td class="diff-unchanged"><?= htmlspecialchars($newVal ?: '(empty)') ?></td>
                                    </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <?php endif; ?>
                        </table>
                    </div>
                    <?php if ($req['admin_note']): ?>
                    <div class="card-footer bg-light">
                        <small><strong>Admin Note:</strong> <?= htmlspecialchars($req['admin_note']) ?></small>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    $(document).ready(function() {
        function handleChangeAction(action, id, btn) {
            let note = '';
            if (action === 'reject') {
                note = prompt('Reason for rejection (optional):') || '';
            }
            if (action === 'reject' && note === null) return; // cancelled prompt

            $.ajax({
                url: 'api/profile-changes.php',
                method: 'POST',
                data: { action: action, request_id: id, admin_note: note },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.message || 'Action failed');
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        }

        $(document).on('click', '.btn-approve-change', function() {
            if (confirm('Approve these changes? They will be applied to the user\'s profile immediately.')) {
                handleChangeAction('approve', $(this).data('id'), $(this));
            }
        });

        $(document).on('click', '.btn-reject-change', function() {
            handleChangeAction('reject', $(this).data('id'), $(this));
        });

        // Photo approve/reject
        function handlePhotoAction(action, photoId) {
            $.ajax({
                url: 'api/profile-changes.php',
                method: 'POST',
                data: { action: 'photo_' + action, photo_id: photoId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#photo-card-' + photoId).fadeOut(300, function() { $(this).remove(); });
                    } else {
                        alert(response.message || 'Action failed');
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        }

        $(document).on('click', '.btn-approve-photo', function() {
            if (confirm('Approve this photo?')) {
                handlePhotoAction('approve', $(this).data('id'));
            }
        });

        $(document).on('click', '.btn-reject-photo', function() {
            if (confirm('Reject and delete this photo?')) {
                handlePhotoAction('reject', $(this).data('id'));
            }
        });
    });
    </script>
</body>
</html>
