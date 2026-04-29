<?php
$pageTitle = 'Edit Profile';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Fetch all profile data
$stmt = $pdo->prepare("SELECT * FROM profile_details WHERE user_id = ?");
$stmt->execute([$userId]);
$details = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM family_details WHERE user_id = ?");
$stmt->execute([$userId]);
$family = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
$stmt->execute([$userId]);
$partnerPrefs = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM photos WHERE user_id = ? ORDER BY is_primary DESC");
$stmt->execute([$userId]);
$photos = $stmt->fetchAll();

$errors = [];
$activeTab = $_GET['tab'] ?? 'basic';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    }
    
    $section = $_POST['section'] ?? '';
    
    if (empty($errors)) {
        try {
            switch ($section) {
                case 'basic':
                    $oldData = [
                        'name' => $currentUser['name'],
                        'religion' => $currentUser['religion'],
                        'caste' => $currentUser['caste'],
                        'sub_caste' => $currentUser['sub_caste'],
                        'mother_tongue' => $currentUser['mother_tongue'],
                        'marital_status' => $currentUser['marital_status'],
                        'state' => $currentUser['state'],
                        'city' => $currentUser['city'],
                        'about_me' => $currentUser['about_me'],
                    ];
                    $newData = [
                        'name' => sanitize($_POST['name']),
                        'religion' => sanitize($_POST['religion']),
                        'caste' => sanitize($_POST['caste']),
                        'sub_caste' => sanitize($_POST['sub_caste'] ?? ''),
                        'mother_tongue' => sanitize($_POST['mother_tongue']),
                        'marital_status' => sanitize($_POST['marital_status']),
                        'state' => sanitize($_POST['state']),
                        'city' => sanitize($_POST['city']),
                        'about_me' => sanitize($_POST['about_me']),
                    ];
                    $activeTab = 'basic';
                    break;

                case 'personal':
                    $oldData = [
                        'height' => $details['height'] ?? '',
                        'weight' => $details['weight'] ?? '',
                        'complexion' => $details['complexion'] ?? '',
                        'body_type' => $details['body_type'] ?? '',
                        'blood_group' => $details['blood_group'] ?? '',
                        'diet' => $details['diet'] ?? '',
                        'smoking' => $details['smoking'] ?? 'No',
                        'drinking' => $details['drinking'] ?? 'No',
                        'hobbies' => $details['hobbies'] ?? '',
                    ];
                    $newData = [
                        'height' => intval($_POST['height']),
                        'weight' => intval($_POST['weight'] ?? 0),
                        'complexion' => sanitize($_POST['complexion'] ?? ''),
                        'body_type' => sanitize($_POST['body_type'] ?? ''),
                        'blood_group' => sanitize($_POST['blood_group'] ?? ''),
                        'diet' => sanitize($_POST['diet'] ?? ''),
                        'smoking' => sanitize($_POST['smoking'] ?? 'No'),
                        'drinking' => sanitize($_POST['drinking'] ?? 'No'),
                        'hobbies' => sanitize($_POST['hobbies'] ?? ''),
                    ];
                    $activeTab = 'personal';
                    break;

                case 'professional':
                    $oldData = [
                        'education' => $details['education'] ?? '',
                        'education_detail' => $details['education_detail'] ?? '',
                        'occupation' => $details['occupation'] ?? '',
                        'occupation_detail' => $details['occupation_detail'] ?? '',
                        'company' => $details['company'] ?? '',
                        'annual_income' => $details['annual_income'] ?? '',
                        'working_city' => $details['working_city'] ?? '',
                    ];
                    $newData = [
                        'education' => sanitize($_POST['education']),
                        'education_detail' => sanitize($_POST['education_detail'] ?? ''),
                        'occupation' => sanitize($_POST['occupation']),
                        'occupation_detail' => sanitize($_POST['occupation_detail'] ?? ''),
                        'company' => sanitize($_POST['company'] ?? ''),
                        'annual_income' => sanitize($_POST['annual_income'] ?? ''),
                        'working_city' => sanitize($_POST['working_city'] ?? ''),
                    ];
                    $activeTab = 'professional';
                    break;

                case 'family':
                    $oldData = [
                        'father_name' => $family['father_name'] ?? '',
                        'father_occupation' => $family['father_occupation'] ?? '',
                        'mother_name' => $family['mother_name'] ?? '',
                        'mother_occupation' => $family['mother_occupation'] ?? '',
                        'brothers' => $family['brothers'] ?? 0,
                        'brothers_married' => $family['brothers_married'] ?? 0,
                        'sisters' => $family['sisters'] ?? 0,
                        'sisters_married' => $family['sisters_married'] ?? 0,
                        'family_type' => $family['family_type'] ?? '',
                        'family_status' => $family['family_status'] ?? '',
                        'family_values' => $family['family_values'] ?? '',
                        'gotra' => $family['gotra'] ?? '',
                        'about_family' => $family['about_family'] ?? '',
                    ];
                    $newData = [
                        'father_name' => sanitize($_POST['father_name'] ?? ''),
                        'father_occupation' => sanitize($_POST['father_occupation'] ?? ''),
                        'mother_name' => sanitize($_POST['mother_name'] ?? ''),
                        'mother_occupation' => sanitize($_POST['mother_occupation'] ?? ''),
                        'brothers' => intval($_POST['brothers'] ?? 0),
                        'brothers_married' => intval($_POST['brothers_married'] ?? 0),
                        'sisters' => intval($_POST['sisters'] ?? 0),
                        'sisters_married' => intval($_POST['sisters_married'] ?? 0),
                        'family_type' => sanitize($_POST['family_type'] ?? ''),
                        'family_status' => sanitize($_POST['family_status'] ?? ''),
                        'family_values' => sanitize($_POST['family_values'] ?? ''),
                        'gotra' => sanitize($_POST['gotra'] ?? ''),
                        'about_family' => sanitize($_POST['about_family'] ?? ''),
                    ];
                    $activeTab = 'family';
                    break;

                case 'partner':
                    $oldData = [
                        'min_age' => $partnerPrefs['min_age'] ?? 18,
                        'max_age' => $partnerPrefs['max_age'] ?? 60,
                        'min_height' => $partnerPrefs['min_height'] ?? 0,
                        'max_height' => $partnerPrefs['max_height'] ?? 0,
                        'marital_status' => $partnerPrefs['marital_status'] ?? '',
                        'religion' => $partnerPrefs['religion'] ?? '',
                        'caste' => $partnerPrefs['caste'] ?? '',
                        'mother_tongue' => $partnerPrefs['mother_tongue'] ?? '',
                        'education' => $partnerPrefs['education'] ?? '',
                        'occupation' => $partnerPrefs['occupation'] ?? '',
                        'min_income' => $partnerPrefs['min_income'] ?? '',
                        'max_income' => $partnerPrefs['max_income'] ?? '',
                        'state' => $partnerPrefs['state'] ?? '',
                        'diet' => $partnerPrefs['diet'] ?? '',
                        'smoking' => $partnerPrefs['smoking'] ?? "Doesn't Matter",
                        'drinking' => $partnerPrefs['drinking'] ?? "Doesn't Matter",
                        'about_partner' => $partnerPrefs['about_partner'] ?? '',
                    ];
                    $newData = [
                        'min_age' => intval($_POST['pref_min_age'] ?? 18),
                        'max_age' => intval($_POST['pref_max_age'] ?? 60),
                        'min_height' => intval($_POST['pref_min_height'] ?? 0),
                        'max_height' => intval($_POST['pref_max_height'] ?? 0),
                        'marital_status' => sanitize($_POST['pref_marital_status'] ?? ''),
                        'religion' => sanitize($_POST['pref_religion'] ?? ''),
                        'caste' => sanitize($_POST['pref_caste'] ?? ''),
                        'mother_tongue' => sanitize($_POST['pref_mother_tongue'] ?? ''),
                        'education' => sanitize($_POST['pref_education'] ?? ''),
                        'occupation' => sanitize($_POST['pref_occupation'] ?? ''),
                        'min_income' => sanitize($_POST['pref_min_income'] ?? ''),
                        'max_income' => sanitize($_POST['pref_max_income'] ?? ''),
                        'state' => sanitize($_POST['pref_state'] ?? ''),
                        'diet' => sanitize($_POST['pref_diet'] ?? ''),
                        'smoking' => sanitize($_POST['pref_smoking'] ?? "Doesn't Matter"),
                        'drinking' => sanitize($_POST['pref_drinking'] ?? "Doesn't Matter"),
                        'about_partner' => sanitize($_POST['pref_about_partner'] ?? ''),
                    ];
                    $activeTab = 'partner';
                    break;

                case 'delete_photo':
                    $photoId = intval($_POST['photo_id'] ?? 0);
                    if ($photoId) {
                        $stmt = $pdo->prepare("SELECT * FROM photos WHERE id = ? AND user_id = ?");
                        $stmt->execute([$photoId, $userId]);
                        $photo = $stmt->fetch();
                        
                        if ($photo) {
                            // Delete file from disk
                            $filePath = __DIR__ . '/' . $photo['photo_path'];
                            if (file_exists($filePath)) {
                                unlink($filePath);
                            }
                            
                            // If this was the primary approved photo, clear profile_pic on user
                            if ($photo['is_primary'] && $photo['is_approved']) {
                                $pdo->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?")->execute([$userId]);
                            }
                            
                            $pdo->prepare("DELETE FROM photos WHERE id = ?")->execute([$photoId]);
                            setFlash('success', 'Photo deleted successfully.');
                        } else {
                            $errors[] = 'Photo not found.';
                        }
                    }
                    $activeTab = 'photos';
                    if (empty($errors)) {
                        redirect(SITE_URL . '/edit-profile.php?tab=photos');
                    }
                    break;

                case 'set_primary_photo':
                    $photoId = intval($_POST['photo_id'] ?? 0);
                    if ($photoId) {
                        $stmt = $pdo->prepare("SELECT * FROM photos WHERE id = ? AND user_id = ? AND is_approved = 1");
                        $stmt->execute([$photoId, $userId]);
                        $photo = $stmt->fetch();
                        
                        if ($photo) {
                            // Set is_primary = 0 for all photos of this user
                            $pdo->prepare("UPDATE photos SET is_primary = 0 WHERE user_id = ?")->execute([$userId]);
                            
                            // Set is_primary = 1 for the selected photo
                            $pdo->prepare("UPDATE photos SET is_primary = 1 WHERE id = ?")->execute([$photoId]);
                            
                            // Update users.profile_pic to this photo
                            $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?")->execute([$photo['photo_path'], $userId]);
                            
                            setFlash('success', 'Primary photo updated successfully.');
                        } else {
                            $errors[] = 'Photo not found or not approved.';
                        }
                    }
                    $activeTab = 'photos';
                    if (empty($errors)) {
                        redirect(SITE_URL . '/edit-profile.php?tab=photos');
                    }
                    break;

                case 'photo':
                    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                        $result = uploadPhoto($_FILES['profile_photo'], $userId);
                        if ($result['success']) {
                            $setAsPrimary = isset($_POST['set_primary']) ? 1 : 0;
                            
                            $stmt = $pdo->prepare("INSERT INTO photos (user_id, photo_path, is_primary, is_approved) VALUES (?, ?, ?, 0)");
                            $stmt->execute([$userId, $result['path'], $setAsPrimary]);
                        } else {
                            $errors[] = $result['message'];
                        }
                    }
                    $activeTab = 'photos';
                    break;
            }
            
            // For non-photo sections, save as pending change request
            if (empty($errors) && $section !== 'photo' && isset($oldData, $newData)) {
                // Check if there are actual changes
                $hasChanges = false;
                foreach ($newData as $key => $val) {
                    if ((string)$val !== (string)($oldData[$key] ?? '')) {
                        $hasChanges = true;
                        break;
                    }
                }
                
                if ($hasChanges) {
                    // Check if user already has a pending request (any section)
                    $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
                    $stmt->execute([$userId]);
                    $existingRequest = $stmt->fetch();

                    if ($existingRequest) {
                        // Merge new changes into existing request
                        $existingOldData = json_decode($existingRequest['old_data'], true) ?? [];
                        $existingNewData = json_decode($existingRequest['new_data'], true) ?? [];

                        // Merge old_data and new_data with the new changes
                        $mergedOldData = array_merge($existingOldData, $oldData);
                        $mergedNewData = array_merge($existingNewData, $newData);

                        // Update the existing request with merged data
                        $updateStmt = $pdo->prepare(
                            "UPDATE profile_change_requests SET old_data = ?, new_data = ? WHERE id = ?"
                        );
                        $updateStmt->execute([json_encode($mergedOldData), json_encode($mergedNewData), $existingRequest['id']]);
                    } else {
                        // Create new request with this section's changes
                        $insertStmt = $pdo->prepare(
                            "INSERT INTO profile_change_requests (user_id, section, old_data, new_data) VALUES (?, ?, ?, ?)"
                        );
                        $insertStmt->execute([$userId, $section, json_encode($oldData), json_encode($newData)]);
                    }

                    setFlash('info', 'Your changes have been submitted for review. A Super Admin will approve them shortly.');
                    redirect(SITE_URL . '/edit-profile.php?tab=' . $activeTab);
                } else {
                    setFlash('info', 'No changes detected.');
                    redirect(SITE_URL . '/edit-profile.php?tab=' . $activeTab);
                }
            }
            
            if (empty($errors) && $section === 'photo') {
                setFlash('info', 'Photo uploaded and submitted for review. It will be visible after admin approval.');
                redirect(SITE_URL . '/edit-profile.php?tab=' . $activeTab);
            }
        } catch (PDOException $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            if (strpos($e->getMessage(), 'profile_change_requests') !== false) {
                $errors[] = 'Profile change tracking table is missing. Please contact the administrator to run the database migration.';
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// Re-fetch data after update
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM profile_details WHERE user_id = ?");
$stmt->execute([$userId]);
$details = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM family_details WHERE user_id = ?");
$stmt->execute([$userId]);
$family = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
$stmt->execute([$userId]);
$partnerPrefs = $stmt->fetch() ?: [];

$stmt = $pdo->prepare("SELECT * FROM photos WHERE user_id = ? ORDER BY is_primary DESC");
$stmt->execute([$userId]);
$photos = $stmt->fetchAll();

// Fetch pending change requests for this user
$pendingChanges = [];
try {
    $stmt = $pdo->prepare("SELECT section, created_at FROM profile_change_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $pc) {
        $pendingChanges[$pc['section']] = $pc['created_at'];
    }
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <h3 class="mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Profile</h3>
        
        <?php if (!empty($pendingChanges)): ?>
            <div class="alert alert-warning">
                <i class="bi bi-clock-history me-2"></i>
                <strong>Pending Review:</strong> You have changes awaiting admin approval in:
                <?php 
                    $sectionNames = ['basic' => 'Basic Info', 'personal' => 'Personal', 'professional' => 'Professional', 'family' => 'Family', 'partner' => 'Partner Preferences'];
                    $pendingLabels = [];
                    foreach ($pendingChanges as $sec => $date) {
                        $pendingLabels[] = '<strong>' . ($sectionNames[$sec] ?? ucfirst($sec)) . '</strong> (submitted ' . date('d M Y', strtotime($date)) . ')';
                    }
                    echo implode(', ', $pendingLabels);
                ?>
                <br><small class="text-muted">Your current profile will continue to show the old values until the admin approves your changes.</small>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <ul class="nav nav-tabs profile-tabs mb-4" role="tablist">
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'basic' ? 'active' : '' ?>" data-bs-toggle="tab" href="#basic">Basic Info</a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'personal' ? 'active' : '' ?>" data-bs-toggle="tab" href="#personal">Personal</a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'professional' ? 'active' : '' ?>" data-bs-toggle="tab" href="#professional">Professional</a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'family' ? 'active' : '' ?>" data-bs-toggle="tab" href="#family">Family</a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'partner' ? 'active' : '' ?>" data-bs-toggle="tab" href="#partner">Partner Pref</a></li>
            <li class="nav-item"><a class="nav-link <?= $activeTab === 'photos' ? 'active' : '' ?>" data-bs-toggle="tab" href="#photos">Photos</a></li>
        </ul>

        <div class="tab-content">
            <!-- Basic Info Tab -->
            <div class="tab-pane fade <?= $activeTab === 'basic' ? 'show active' : '' ?>" id="basic">
                <div class="dashboard-card">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="basic">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="name" value="<?= sanitize($currentUser['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Religion</label>
                                <select name="religion" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($RELIGIONS as $r): ?>
                                        <option value="<?= $r ?>" <?= ($currentUser['religion'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Samaj Name</label>
                                <input type="text" class="form-control" name="caste" value="<?= sanitize($currentUser['caste'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sub Samaj</label>
                                <input type="text" class="form-control" name="sub_caste" value="<?= sanitize($currentUser['sub_caste'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mother Tongue</label>
                                <select name="mother_tongue" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($MOTHER_TONGUES as $lang): ?>
                                        <option value="<?= $lang ?>" <?= ($currentUser['mother_tongue'] ?? '') === $lang ? 'selected' : '' ?>><?= $lang ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marital Status</label>
                                <select name="marital_status" class="form-select">
                                    <?php foreach ($MARITAL_STATUS as $ms): ?>
                                        <option value="<?= $ms ?>" <?= ($currentUser['marital_status'] ?? '') === $ms ? 'selected' : '' ?>><?= $ms ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <select name="state" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($INDIAN_STATES as $state): ?>
                                        <option value="<?= $state ?>" <?= ($currentUser['state'] ?? '') === $state ? 'selected' : '' ?>><?= $state ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" value="<?= sanitize($currentUser['city'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">About Me</label>
                                <textarea name="about_me" class="form-control" rows="4" placeholder="Write something about yourself..."><?= sanitize($currentUser['about_me'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Personal Tab -->
            <div class="tab-pane fade <?= $activeTab === 'personal' ? 'show active' : '' ?>" id="personal">
                <div class="dashboard-card">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="personal">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Height</label>
                                <select name="height" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($HEIGHT_OPTIONS as $cm => $label): ?>
                                        <option value="<?= $cm ?>" <?= ($details['height'] ?? '') == $cm ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" class="form-control" name="weight" value="<?= $details['weight'] ?? '' ?>" min="30" max="200">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Complexion</label>
                                <select name="complexion" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($COMPLEXION_OPTIONS as $c): ?>
                                        <option value="<?= $c ?>" <?= ($details['complexion'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Body Type</label>
                                <select name="body_type" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($BODY_TYPES as $bt): ?>
                                        <option value="<?= $bt ?>" <?= ($details['body_type'] ?? '') === $bt ? 'selected' : '' ?>><?= $bt ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Blood Group</label>
                                <select name="blood_group" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                                        <option value="<?= $bg ?>" <?= ($details['blood_group'] ?? '') === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Diet</label>
                                <select name="diet" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($DIET_OPTIONS as $d): ?>
                                        <option value="<?= $d ?>" <?= ($details['diet'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Smoking</label>
                                <select name="smoking" class="form-select">
                                    <?php foreach (['No','Yes','Occasionally'] as $s): ?>
                                        <option value="<?= $s ?>" <?= ($details['smoking'] ?? 'No') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Drinking</label>
                                <select name="drinking" class="form-select">
                                    <?php foreach (['No','Yes','Occasionally'] as $d): ?>
                                        <option value="<?= $d ?>" <?= ($details['drinking'] ?? 'No') === $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Hobbies & Interests</label>
                                <textarea name="hobbies" class="form-control" rows="3" placeholder="E.g., Reading, Traveling, Cooking..."><?= sanitize($details['hobbies'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Professional Tab -->
            <div class="tab-pane fade <?= $activeTab === 'professional' ? 'show active' : '' ?>" id="professional">
                <div class="dashboard-card">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="professional">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Highest Education</label>
                                <select name="education" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($EDUCATION_LEVELS as $edu): ?>
                                        <option value="<?= $edu ?>" <?= ($details['education'] ?? '') === $edu ? 'selected' : '' ?>><?= $edu ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Education Detail</label>
                                <input type="text" class="form-control" name="education_detail" value="<?= sanitize($details['education_detail'] ?? '') ?>" placeholder="E.g., IIT Delhi, B.Tech CSE">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Occupation</label>
                                <select name="occupation" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($OCCUPATIONS as $occ): ?>
                                        <option value="<?= $occ ?>" <?= ($details['occupation'] ?? '') === $occ ? 'selected' : '' ?>><?= $occ ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Occupation Detail</label>
                                <input type="text" class="form-control" name="occupation_detail" value="<?= sanitize($details['occupation_detail'] ?? '') ?>" placeholder="E.g., Senior Software Engineer">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Company / Employer</label>
                                <input type="text" class="form-control" name="company" value="<?= sanitize($details['company'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Annual Income</label>
                                <select name="annual_income" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($INCOME_RANGES as $inc): ?>
                                        <option value="<?= $inc ?>" <?= ($details['annual_income'] ?? '') === $inc ? 'selected' : '' ?>><?= $inc ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Working City</label>
                                <input type="text" class="form-control" name="working_city" value="<?= sanitize($details['working_city'] ?? '') ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Family Tab -->
            <div class="tab-pane fade <?= $activeTab === 'family' ? 'show active' : '' ?>" id="family">
                <div class="dashboard-card">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="family">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Father's Name</label>
                                <input type="text" class="form-control" name="father_name" value="<?= sanitize($family['father_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Father's Occupation</label>
                                <input type="text" class="form-control" name="father_occupation" value="<?= sanitize($family['father_occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" class="form-control" name="mother_name" value="<?= sanitize($family['mother_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mother's Occupation</label>
                                <input type="text" class="form-control" name="mother_occupation" value="<?= sanitize($family['mother_occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Brothers</label>
                                <input type="number" class="form-control" name="brothers" value="<?= $family['brothers'] ?? 0 ?>" min="0" max="10">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Married</label>
                                <input type="number" class="form-control" name="brothers_married" value="<?= $family['brothers_married'] ?? 0 ?>" min="0" max="10">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Sisters</label>
                                <input type="number" class="form-control" name="sisters" value="<?= $family['sisters'] ?? 0 ?>" min="0" max="10">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Married</label>
                                <input type="number" class="form-control" name="sisters_married" value="<?= $family['sisters_married'] ?? 0 ?>" min="0" max="10">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Family Type</label>
                                <select name="family_type" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($FAMILY_TYPES as $ft): ?>
                                        <option value="<?= $ft ?>" <?= ($family['family_type'] ?? '') === $ft ? 'selected' : '' ?>><?= $ft ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Family Status</label>
                                <select name="family_status" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($FAMILY_STATUS as $fs): ?>
                                        <option value="<?= $fs ?>" <?= ($family['family_status'] ?? '') === $fs ? 'selected' : '' ?>><?= $fs ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Family Values</label>
                                <select name="family_values" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach ($FAMILY_VALUES as $fv): ?>
                                        <option value="<?= $fv ?>" <?= ($family['family_values'] ?? '') === $fv ? 'selected' : '' ?>><?= $fv ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gotra</label>
                                <input type="text" class="form-control" name="gotra" value="<?= sanitize($family['gotra'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">About Family</label>
                                <textarea name="about_family" class="form-control" rows="3"><?= sanitize($family['about_family'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Partner Preferences Tab -->
            <div class="tab-pane fade <?= $activeTab === 'partner' ? 'show active' : '' ?>" id="partner">
                <div class="dashboard-card">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="section" value="partner">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Min Age</label>
                                <input type="number" class="form-control" name="pref_min_age" value="<?= $partnerPrefs['min_age'] ?? 18 ?>" min="18" max="60">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Max Age</label>
                                <input type="number" class="form-control" name="pref_max_age" value="<?= $partnerPrefs['max_age'] ?? 60 ?>" min="18" max="60">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Min Height</label>
                                <select name="pref_min_height" class="form-select">
                                    <option value="">Any</option>
                                    <?php foreach ($HEIGHT_OPTIONS as $cm => $label): ?>
                                        <option value="<?= $cm ?>" <?= ($partnerPrefs['min_height'] ?? '') == $cm ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Max Height</label>
                                <select name="pref_max_height" class="form-select">
                                    <option value="">Any</option>
                                    <?php foreach ($HEIGHT_OPTIONS as $cm => $label): ?>
                                        <option value="<?= $cm ?>" <?= ($partnerPrefs['max_height'] ?? '') == $cm ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Religion</label>
                                <input type="text" class="form-control" name="pref_religion" value="<?= sanitize($partnerPrefs['religion'] ?? '') ?>" placeholder="E.g., Hindu, Sikh">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Samaj Name</label>
                                <input type="text" class="form-control" name="pref_caste" value="<?= sanitize($partnerPrefs['caste'] ?? '') ?>" placeholder="Any or specific">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mother Tongue</label>
                                <input type="text" class="form-control" name="pref_mother_tongue" value="<?= sanitize($partnerPrefs['mother_tongue'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marital Status</label>
                                <input type="text" class="form-control" name="pref_marital_status" value="<?= sanitize($partnerPrefs['marital_status'] ?? '') ?>" placeholder="E.g., Never Married">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Education</label>
                                <input type="text" class="form-control" name="pref_education" value="<?= sanitize($partnerPrefs['education'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Occupation</label>
                                <input type="text" class="form-control" name="pref_occupation" value="<?= sanitize($partnerPrefs['occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="pref_state" value="<?= sanitize($partnerPrefs['state'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Diet</label>
                                <input type="text" class="form-control" name="pref_diet" value="<?= sanitize($partnerPrefs['diet'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">About Desired Partner</label>
                                <textarea name="pref_about_partner" class="form-control" rows="3"><?= sanitize($partnerPrefs['about_partner'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Preferences</button>
                    </form>
                </div>
            </div>

            <!-- Photos Tab -->
            <div class="tab-pane fade <?= $activeTab === 'photos' ? 'show active' : '' ?>" id="photos">
                <div class="dashboard-card">
                    <h5 class="mb-3">Your Photos (<?= count($photos) ?>/<?= MAX_PHOTOS ?>)</h5>
                    
                    <div class="row g-3 mb-4">
                        <?php foreach ($photos as $photo): ?>
                            <div class="col-md-3 col-6">
                                <div class="position-relative">
                                    <img src="<?= SITE_URL . '/' . $photo['photo_path'] ?>" class="rounded w-100" style="height: 180px; object-fit: cover;<?= !$photo['is_approved'] ? ' opacity: 0.6;' : '' ?>">
                                    <?php if ($photo['is_primary'] && $photo['is_approved']): ?>
                                        <span class="badge bg-success position-absolute top-0 start-0 m-2">Primary</span>
                                    <?php endif; ?>
                                    <?php if (!$photo['is_approved']): ?>
                                        <span class="badge bg-warning text-dark position-absolute top-0 end-0 m-2">Pending Approval</span>
                                    <?php endif; ?>
                                    <div class="position-absolute bottom-0 end-0 m-2 d-flex gap-1">
                                        <?php if ($photo['is_approved'] && !$photo['is_primary']): ?>
                                            <form method="POST" action="" onsubmit="return confirm('Set this photo as primary?');">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <input type="hidden" name="section" value="set_primary_photo">
                                                <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" title="Set as primary"><i class="bi bi-star"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="" class="delete-photo-form" onsubmit="return confirm('Delete this photo? This cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="section" value="delete_photo">
                                            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete photo"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($photos) < MAX_PHOTOS): ?>
                        <form method="POST" action="" enctype="multipart/form-data" id="photoUploadForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="section" value="photo">
                            <div class="row align-items-end g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Upload Photo</label>
                                    <input type="file" class="form-control" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp" required>
                                    <small class="text-danger fw-bold">Maximum file size: 5MB. Files larger than 5MB cannot be uploaded.</small>
                                    <br><small class="text-muted">Allowed formats: JPG, PNG, or WebP. You'll be able to crop before uploading.</small>
                                    <div id="photoSizeError" class="alert alert-danger mt-2 py-1 d-none">
                                        <small><i class="bi bi-exclamation-triangle me-1"></i>Selected file exceeds 5MB. Please choose a smaller image.</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="set_primary" id="setPrimary">
                                        <label class="form-check-label" for="setPrimary">Set as primary</label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">Maximum photos limit reached.</p>
                    <?php endif; ?>

                    <!-- Crop Modal -->
                    <div class="modal fade" id="cropModal" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-crop me-2"></i>Crop Your Photo</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div style="max-height:500px;">
                                        <img id="cropImage" style="max-width:100%;display:block;">
                                    </div>
                                    <small class="text-muted d-block mt-2"><i class="bi bi-info-circle me-1"></i>Drag to reposition, use corners to resize. Aspect ratio is locked to square for best profile display.</small>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="cropUploadBtn"><i class="bi bi-upload me-1"></i>Crop &amp; Upload</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Cropper.js for photo cropping before upload -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('profilePhotoInput');
    var uploadForm = document.getElementById('photoUploadForm');
    var cropImage = document.getElementById('cropImage');
    var cropModalEl = document.getElementById('cropModal');
    var cropUploadBtn = document.getElementById('cropUploadBtn');
    var errDiv = document.getElementById('photoSizeError');
    var cropper = null;
    var cropModal = cropModalEl ? new bootstrap.Modal(cropModalEl) : null;

    if (!input || !uploadForm || !cropModal) return;

    input.addEventListener('change', function() {
        if (!this.files.length) return;
        var file = this.files[0];

        // 5MB client-side validation
        if (file.size > 5 * 1024 * 1024) {
            errDiv.classList.remove('d-none');
            this.value = '';
            return;
        }
        errDiv.classList.add('d-none');

        var reader = new FileReader();
        reader.onload = function(e) {
            cropImage.src = e.target.result;
            cropImage.style.display = 'block';
            cropModal.show();
        };
        reader.onerror = function() {
            alert('Failed to read the image file. Please try another file.');
            input.value = '';
        };
        reader.readAsDataURL(file);
    });

    cropModalEl.addEventListener('shown.bs.modal', function() {
        if (cropper) cropper.destroy();
        try {
            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
                movable: true,
                zoomable: true,
                scalable: false,
                rotatable: false
            });
        } catch (e) {
            console.error('Cropper initialization error:', e);
            alert('Failed to initialize image cropper. Please try again.');
            cropModal.hide();
        }
    });

    cropModalEl.addEventListener('hidden.bs.modal', function() {
        if (cropper) { cropper.destroy(); cropper = null; }
        input.value = '';
    });

    cropUploadBtn.addEventListener('click', function() {
        if (!cropper) return;
        cropUploadBtn.disabled = true;
        cropUploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading...';

        cropper.getCroppedCanvas({
            width: 800,
            height: 800,
            imageSmoothingQuality: 'high'
        }).toBlob(function(blob) {
            var formData = new FormData(uploadForm);
            formData.append('profile_photo', blob, 'cropped.jpg');

            fetch(uploadForm.action || window.location.href, {
                method: 'POST',
                body: formData
            }).then(function(res) {
                window.location.href = '<?= SITE_URL ?>/edit-profile.php?tab=photos';
            }).catch(function() {
                alert('Upload failed. Please try again.');
                cropUploadBtn.disabled = false;
                cropUploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Crop & Upload';
            });
        }, 'image/jpeg', 0.9);
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
