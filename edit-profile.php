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
                    $stmt = $pdo->prepare(
                        "UPDATE users SET name=?, religion=?, caste=?, sub_caste=?, mother_tongue=?, 
                         marital_status=?, state=?, city=?, about_me=?, updated_at=NOW() WHERE id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['name']),
                        sanitize($_POST['religion']),
                        sanitize($_POST['caste']),
                        sanitize($_POST['sub_caste'] ?? ''),
                        sanitize($_POST['mother_tongue']),
                        sanitize($_POST['marital_status']),
                        sanitize($_POST['state']),
                        sanitize($_POST['city']),
                        sanitize($_POST['about_me']),
                        $userId
                    ]);
                    $activeTab = 'basic';
                    break;

                case 'personal':
                    $stmt = $pdo->prepare(
                        "UPDATE profile_details SET height=?, weight=?, complexion=?, body_type=?, 
                         blood_group=?, diet=?, smoking=?, drinking=?, hobbies=?, updated_at=NOW() WHERE user_id=?"
                    );
                    $stmt->execute([
                        intval($_POST['height']),
                        intval($_POST['weight'] ?? 0),
                        sanitize($_POST['complexion'] ?? ''),
                        sanitize($_POST['body_type'] ?? ''),
                        sanitize($_POST['blood_group'] ?? ''),
                        sanitize($_POST['diet'] ?? ''),
                        sanitize($_POST['smoking'] ?? 'No'),
                        sanitize($_POST['drinking'] ?? 'No'),
                        sanitize($_POST['hobbies'] ?? ''),
                        $userId
                    ]);
                    $activeTab = 'personal';
                    break;

                case 'professional':
                    $stmt = $pdo->prepare(
                        "UPDATE profile_details SET education=?, education_detail=?, occupation=?, 
                         occupation_detail=?, company=?, annual_income=?, working_city=?, updated_at=NOW() WHERE user_id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['education']),
                        sanitize($_POST['education_detail'] ?? ''),
                        sanitize($_POST['occupation']),
                        sanitize($_POST['occupation_detail'] ?? ''),
                        sanitize($_POST['company'] ?? ''),
                        sanitize($_POST['annual_income'] ?? ''),
                        sanitize($_POST['working_city'] ?? ''),
                        $userId
                    ]);
                    $activeTab = 'professional';
                    break;

                case 'family':
                    $stmt = $pdo->prepare(
                        "UPDATE family_details SET father_name=?, father_occupation=?, mother_name=?, 
                         mother_occupation=?, brothers=?, brothers_married=?, sisters=?, sisters_married=?,
                         family_type=?, family_status=?, family_values=?, gotra=?, about_family=?, updated_at=NOW() WHERE user_id=?"
                    );
                    $stmt->execute([
                        sanitize($_POST['father_name'] ?? ''),
                        sanitize($_POST['father_occupation'] ?? ''),
                        sanitize($_POST['mother_name'] ?? ''),
                        sanitize($_POST['mother_occupation'] ?? ''),
                        intval($_POST['brothers'] ?? 0),
                        intval($_POST['brothers_married'] ?? 0),
                        intval($_POST['sisters'] ?? 0),
                        intval($_POST['sisters_married'] ?? 0),
                        sanitize($_POST['family_type'] ?? ''),
                        sanitize($_POST['family_status'] ?? ''),
                        sanitize($_POST['family_values'] ?? ''),
                        sanitize($_POST['gotra'] ?? ''),
                        sanitize($_POST['about_family'] ?? ''),
                        $userId
                    ]);
                    $activeTab = 'family';
                    break;

                case 'partner':
                    $stmt = $pdo->prepare(
                        "UPDATE partner_preferences SET min_age=?, max_age=?, min_height=?, max_height=?,
                         marital_status=?, religion=?, caste=?, mother_tongue=?, education=?, occupation=?,
                         min_income=?, max_income=?, state=?, diet=?, smoking=?, drinking=?, about_partner=?, updated_at=NOW() WHERE user_id=?"
                    );
                    $stmt->execute([
                        intval($_POST['pref_min_age'] ?? 18),
                        intval($_POST['pref_max_age'] ?? 60),
                        intval($_POST['pref_min_height'] ?? 0),
                        intval($_POST['pref_max_height'] ?? 0),
                        sanitize($_POST['pref_marital_status'] ?? ''),
                        sanitize($_POST['pref_religion'] ?? ''),
                        sanitize($_POST['pref_caste'] ?? ''),
                        sanitize($_POST['pref_mother_tongue'] ?? ''),
                        sanitize($_POST['pref_education'] ?? ''),
                        sanitize($_POST['pref_occupation'] ?? ''),
                        sanitize($_POST['pref_min_income'] ?? ''),
                        sanitize($_POST['pref_max_income'] ?? ''),
                        sanitize($_POST['pref_state'] ?? ''),
                        sanitize($_POST['pref_diet'] ?? ''),
                        sanitize($_POST['pref_smoking'] ?? "Doesn't Matter"),
                        sanitize($_POST['pref_drinking'] ?? "Doesn't Matter"),
                        sanitize($_POST['pref_about_partner'] ?? ''),
                        $userId
                    ]);
                    $activeTab = 'partner';
                    break;

                case 'photo':
                    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                        $result = uploadPhoto($_FILES['profile_photo'], $userId);
                        if ($result['success']) {
                            // Check photo count
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM photos WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            $photoCount = $stmt->fetch()['count'];
                            
                            $isPrimary = ($photoCount === 0) ? 1 : 0;
                            
                            $stmt = $pdo->prepare("INSERT INTO photos (user_id, photo_path, is_primary) VALUES (?, ?, ?)");
                            $stmt->execute([$userId, $result['path'], $isPrimary]);
                            
                            if ($isPrimary || isset($_POST['set_primary'])) {
                                $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?")->execute([$result['path'], $userId]);
                            }
                        } else {
                            $errors[] = $result['message'];
                        }
                    }
                    $activeTab = 'photos';
                    break;
            }
            
            if (empty($errors)) {
                setFlash('success', 'Profile updated successfully!');
                redirect(SITE_URL . '/edit-profile.php?tab=' . $activeTab);
            }
        } catch (PDOException $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            $errors[] = 'Failed to update profile. Please try again.';
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

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-4 bg-warm">
    <div class="container">
        <h3 class="mb-4"><i class="bi bi-pencil-square me-2"></i>Edit Profile</h3>
        
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
                                    <img src="<?= SITE_URL . '/' . $photo['photo_path'] ?>" class="rounded w-100" style="height: 180px; object-fit: cover;">
                                    <?php if ($photo['is_primary']): ?>
                                        <span class="badge bg-success position-absolute top-0 start-0 m-2">Primary</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (count($photos) < MAX_PHOTOS): ?>
                        <form method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="section" value="photo">
                            <div class="row align-items-end g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Upload Photo</label>
                                    <input type="file" class="form-control" name="profile_photo" accept="image/jpeg,image/png,image/webp" required>
                                    <small class="text-muted">Max 5MB. JPG, PNG, or WebP</small>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="set_primary" id="setPrimary">
                                        <label class="form-check-label" for="setPrimary">Set as primary</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i>Upload</button>
                                </div>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">Maximum photos limit reached.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
