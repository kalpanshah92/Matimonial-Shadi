<?php
$pageTitle = 'Edit Profile';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDBConnection();
$userId = $currentUser['id'];

// Helpers: direct UPSERT-style updates for child tables
function applyTableUpdate(PDO $pdo, $table, $userId, array $data) {
    if (empty($data)) return;
    // Check if row exists
    $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE user_id = ?");
    $stmt->execute([$userId]);
    $exists = (bool) $stmt->fetchColumn();

    if ($exists) {
        $sets = [];
        $params = [];
        foreach ($data as $f => $v) {
            $sets[] = "$f = ?";
            $params[] = $v;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $userId;
        $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE user_id = ?";
        $pdo->prepare($sql)->execute($params);
    } else {
        $cols = ['user_id'];
        $placeholders = ['?'];
        $params = [$userId];
        foreach ($data as $f => $v) {
            $cols[] = $f;
            $placeholders[] = '?';
            $params[] = $v;
        }
        $sql = "INSERT INTO $table (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $pdo->prepare($sql)->execute($params);
    }
}

function applyProfileDetailsUpdate(PDO $pdo, $userId, array $data) {
    applyTableUpdate($pdo, 'profile_details', $userId, $data);
}

function applyFamilyDetailsUpdate(PDO $pdo, $userId, array $data) {
    applyTableUpdate($pdo, 'family_details', $userId, $data);
}

function applyPartnerPrefsUpdate(PDO $pdo, $userId, array $data) {
    applyTableUpdate($pdo, 'partner_preferences', $userId, $data);
}

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

$stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$privacy = $stmt->fetch() ?: ['show_phone' => 'connected', 'show_email' => 'connected', 'show_photo' => 'everyone', 'show_income' => 'premium', 'profile_visibility' => 'everyone'];

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
                    $submittedAddress = sanitize($_POST['address'] ?? '');
                    $submittedAddressType = sanitize($_POST['address_type'] ?? '');
                    if (empty($submittedAddress) || !in_array($submittedAddressType, ['Own', 'Rent'], true)) {
                        $submittedAddressType = null;
                    }
                    $submittedMaritalStatus = sanitize($_POST['marital_status'] ?? '');
                    if (empty($submittedMaritalStatus)) {
                        $submittedMaritalStatus = $currentUser['marital_status'] ?? '';
                    }
                    $submittedFirst  = normalizeNamePart($_POST['first_name']  ?? '');
                    $submittedMiddle = normalizeNamePart($_POST['middle_name'] ?? '');
                    $submittedLast   = normalizeNamePart($_POST['last_name']   ?? '');
                    if ($err = validateNamePart($submittedFirst,  'First Name', true))  { $errors[] = $err; }
                    if ($err = validateNamePart($submittedMiddle, 'Middle Name', false)) { $errors[] = $err; }
                    if ($err = validateNamePart($submittedLast,   'Last Name',  true))  { $errors[] = $err; }
                    $submittedName = trim(implode(' ', array_filter([$submittedFirst, $submittedMiddle, $submittedLast], 'strlen')));

                    // Auto-update everything except name directly on users table
                    $autoFields = [
                        'religion' => sanitize($_POST['religion']),
                        'caste' => sanitize($_POST['caste']),
                        'sub_caste' => sanitize($_POST['sub_caste'] ?? ''),
                        'mother_tongue' => sanitize($_POST['mother_tongue']),
                        'marital_status' => $submittedMaritalStatus,
                        'address' => $submittedAddress,
                        'address_type' => $submittedAddressType,
                        'country' => sanitize($_POST['country'] ?? ''),
                        'state' => sanitize($_POST['state']),
                        'city' => sanitize($_POST['city']),
                        'about_me' => sanitize($_POST['about_me']),
                    ];
                    $sets = [];
                    $params = [];
                    foreach ($autoFields as $f => $v) {
                        $sets[] = "$f = ?";
                        $params[] = $v;
                    }
                    $sets[] = 'updated_at = NOW()';
                    $params[] = $userId;
                    $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

                    // Name change requires admin approval. Track each part
                    // individually so the approval flow can apply them granularly,
                    // and include `name` so any legacy reads stay coherent.
                    $nameChanged = (
                        $submittedFirst  !== ($currentUser['first_name']  ?? '') ||
                        $submittedMiddle !== ($currentUser['middle_name'] ?? '') ||
                        $submittedLast   !== ($currentUser['last_name']   ?? '')
                    );
                    if (empty($errors) && $nameChanged) {
                        $oldData = [
                            'first_name'  => $currentUser['first_name']  ?? '',
                            'middle_name' => $currentUser['middle_name'] ?? '',
                            'last_name'   => $currentUser['last_name']   ?? '',
                            'name'        => $currentUser['name']        ?? '',
                        ];
                        $newData = [
                            'first_name'  => $submittedFirst,
                            'middle_name' => $submittedMiddle,
                            'last_name'   => $submittedLast,
                            'name'        => $submittedName,
                        ];
                    } elseif (empty($errors)) {
                        setFlash('success', 'Profile Updated Successfully');
                        redirect(SITE_URL . '/edit-profile.php?tab=basic');
                    }
                    $activeTab = 'basic';
                    break;

                case 'personal':
                    applyProfileDetailsUpdate($pdo, $userId, [
                        'height' => intval($_POST['height']),
                        'weight' => intval($_POST['weight'] ?? 0),
                        'complexion' => sanitize($_POST['complexion'] ?? ''),
                        'body_type' => sanitize($_POST['body_type'] ?? ''),
                        'blood_group' => sanitize($_POST['blood_group'] ?? ''),
                        'diet' => sanitize($_POST['diet'] ?? ''),
                        'smoking' => sanitize($_POST['smoking'] ?? ''),
                        'drinking' => sanitize($_POST['drinking'] ?? ''),
                        'hobbies' => sanitize($_POST['hobbies'] ?? ''),
                    ]);
                    setFlash('success', 'Profile Updated Successfully');
                    redirect(SITE_URL . '/edit-profile.php?tab=personal');
                    break;

                case 'professional':
                    applyProfileDetailsUpdate($pdo, $userId, [
                        'education' => sanitize($_POST['education']),
                        'education_detail' => sanitize($_POST['education_detail'] ?? ''),
                        'occupation' => sanitize($_POST['occupation']),
                        'occupation_detail' => sanitize($_POST['occupation_detail'] ?? ''),
                        'employment_status' => sanitize($_POST['employment_status'] ?? ''),
                        'job_description' => sanitize($_POST['job_description'] ?? ''),
                        'business_description' => sanitize($_POST['business_description'] ?? ''),
                        'company' => sanitize($_POST['company'] ?? ''),
                        'annual_income' => sanitize($_POST['annual_income'] ?? ''),
                        'working_city' => sanitize($_POST['working_city'] ?? ''),
                    ]);
                    setFlash('success', 'Profile Updated Successfully');
                    redirect(SITE_URL . '/edit-profile.php?tab=professional');
                    break;

                case 'family':
                    $parentsAddress = sanitize($_POST['parents_address'] ?? '');
                    $parentsAddressType = sanitize($_POST['parents_address_type'] ?? '');
                    if (empty($parentsAddress) || !in_array($parentsAddressType, ['Own', 'Rent'], true)) {
                        $parentsAddressType = null;
                    }
                    applyFamilyDetailsUpdate($pdo, $userId, [
                        'father_name' => sanitize($_POST['father_name'] ?? ''),
                        'father_mobile' => sanitize($_POST['father_mobile'] ?? ''),
                        'father_occupation' => sanitize($_POST['father_occupation'] ?? ''),
                        'mother_name' => sanitize($_POST['mother_name'] ?? ''),
                        'mother_mobile' => sanitize($_POST['mother_mobile'] ?? ''),
                        'mother_occupation' => sanitize($_POST['mother_occupation'] ?? ''),
                        'brothers' => intval($_POST['brothers'] ?? 0),
                        'brothers_married' => intval($_POST['brothers_married'] ?? 0),
                        'sisters' => intval($_POST['sisters'] ?? 0),
                        'sisters_married' => intval($_POST['sisters_married'] ?? 0),
                        'family_type' => sanitize($_POST['family_type'] ?? ''),
                        'family_status' => sanitize($_POST['family_status'] ?? ''),
                        'gotra' => sanitize($_POST['gotra'] ?? ''),
                        'parents_address' => $parentsAddress,
                        'parents_address_type' => $parentsAddressType,
                        'about_family' => sanitize($_POST['about_family'] ?? ''),
                    ]);
                    setFlash('success', 'Profile Updated Successfully');
                    redirect(SITE_URL . '/edit-profile.php?tab=family');
                    break;

                case 'horoscope':
                    applyProfileDetailsUpdate($pdo, $userId, [
                        'birth_time' => sanitize($_POST['birth_time'] ?? ''),
                        'place_of_birth' => sanitize($_POST['place_of_birth'] ?? ''),
                    ]);
                    setFlash('success', 'Profile Updated Successfully');
                    redirect(SITE_URL . '/edit-profile.php?tab=horoscope');
                    break;

                case 'partner':
                    applyPartnerPrefsUpdate($pdo, $userId, [
                        'min_age' => intval($_POST['pref_min_age'] ?? 18),
                        'max_age' => intval($_POST['pref_max_age'] ?? 60),
                        'min_height' => intval($_POST['pref_min_height'] ?? 0),
                        'max_height' => intval($_POST['pref_max_height'] ?? 0),
                        'marital_status' => sanitize($_POST['pref_marital_status'] ?? ''),
                        'religion' => sanitize($_POST['pref_religion'] ?? ''),
                        'education' => sanitize($_POST['pref_education'] ?? ''),
                        'occupation' => sanitize($_POST['pref_occupation'] ?? ''),
                        'min_income' => sanitize($_POST['pref_min_income'] ?? ''),
                        'max_income' => sanitize($_POST['pref_max_income'] ?? ''),
                        'state' => sanitize($_POST['pref_state'] ?? ''),
                        'diet' => sanitize($_POST['pref_diet'] ?? ''),
                        'smoking' => trim($_POST['pref_smoking'] ?? "Doesn't Matter"),
                        'drinking' => trim($_POST['pref_drinking'] ?? "Doesn't Matter"),
                        'about_partner' => sanitize($_POST['pref_about_partner'] ?? ''),
                    ]);
                    setFlash('success', 'Profile Updated Successfully');
                    redirect(SITE_URL . '/edit-profile.php?tab=partner');
                    break;

                case 'contact':
                    $countryCode = sanitize($_POST['country_code'] ?? '+91');
                    $cleanCountryCode = preg_replace('/-.*$/', '', $countryCode);
                    $rawPhone = sanitize($_POST['phone'] ?? '');

                    if (empty($rawPhone) || !preg_match('/^[0-9]+$/', $rawPhone)) {
                        $errors[] = 'Valid mobile number is required.';
                        $activeTab = 'contact';
                        break;
                    }

                    $fullPhone = $cleanCountryCode . ' ' . $rawPhone;

                    // Contact details require admin approval
                    if ($fullPhone !== ($currentUser['phone'] ?? '')) {
                        $oldData = ['phone' => $currentUser['phone'] ?? ''];
                        $newData = ['phone' => $fullPhone];
                    } else {
                        setFlash('info', 'No changes detected.');
                        redirect(SITE_URL . '/edit-profile.php?tab=contact');
                    }
                    $activeTab = 'contact';
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
                            setFlash('success', 'Profile Updated Successfully');
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
                            
                            setFlash('success', 'Profile Updated Successfully');
                        } else {
                            $errors[] = 'Photo not found or not approved.';
                        }
                    }
                    $activeTab = 'photos';
                    if (empty($errors)) {
                        redirect(SITE_URL . '/edit-profile.php?tab=photos');
                    }
                    break;

                case 'id_document':
                    if (isset($_FILES['id_document']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
                        $result = uploadIdDocument($_FILES['id_document'], $userId);
                        if ($result['success']) {
                            // Remove old physical file from previous DB record (in case folder cleanup missed it)
                            $oldStmt = $pdo->prepare("SELECT id_document FROM users WHERE id = ?");
                            $oldStmt->execute([$userId]);
                            $oldDoc = $oldStmt->fetchColumn();
                            if ($oldDoc && $oldDoc !== $result['path']) {
                                $oldFull = __DIR__ . '/' . $oldDoc;
                                if (file_exists($oldFull)) @unlink($oldFull);
                            }
                            $upd = $pdo->prepare("UPDATE users SET id_document = ?, id_document_uploaded_at = NOW() WHERE id = ?");
                            $upd->execute([$result['path'], $userId]);
                            setFlash('success', 'Document uploaded successfully.');
                        } else {
                            $errors[] = $result['message'];
                        }
                    } else {
                        $errors[] = 'Please select a PDF file to upload.';
                    }
                    redirect(SITE_URL . '/edit-profile.php?tab=id_document');
                    break;

                case 'address_proof':
                    if (isset($_FILES['address_proof_document']) && $_FILES['address_proof_document']['error'] === UPLOAD_ERR_OK) {
                        $result = uploadAddressProof($_FILES['address_proof_document'], $userId);
                        if ($result['success']) {
                            // Remove old physical file from previous DB record (in case folder cleanup missed it)
                            $oldStmt = $pdo->prepare("SELECT address_proof_document FROM users WHERE id = ?");
                            $oldStmt->execute([$userId]);
                            $oldDoc = $oldStmt->fetchColumn();
                            if ($oldDoc && $oldDoc !== $result['path']) {
                                $oldFull = __DIR__ . '/' . $oldDoc;
                                if (file_exists($oldFull)) @unlink($oldFull);
                            }
                            $upd = $pdo->prepare("UPDATE users SET address_proof_document = ?, address_proof_uploaded_at = NOW() WHERE id = ?");
                            $upd->execute([$result['path'], $userId]);
                            setFlash('success', 'Document uploaded successfully.');
                        } else {
                            $errors[] = $result['message'];
                        }
                    } else {
                        $errors[] = 'Please select a PDF file to upload.';
                    }
                    redirect(SITE_URL . '/edit-profile.php?tab=address_proof');
                    break;

                case 'photo':
                    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                        $result = uploadPhoto($_FILES['profile_photo'], $userId);
                        if ($result['success']) {
                            // Check if this is the user's first photo
                            $photoCountStmt = $pdo->prepare("SELECT COUNT(*) as count FROM photos WHERE user_id = ?");
                            $photoCountStmt->execute([$userId]);
                            $photoCount = $photoCountStmt->fetch()['count'];
                            
                            // First photo is always set as primary
                            $setAsPrimary = ($photoCount === 0) ? 1 : (isset($_POST['set_primary']) ? 1 : 0);
                            
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
                    redirect(SITE_URL . '/edit-profile.php?tab=' . $activeTab . '&saved=' . urlencode($section));
                } else {
                    setFlash('info', 'No changes detected.');
                    redirect(SITE_URL . '/edit-profile.php?tab=' . $activeTab . '&saved=' . urlencode($section));
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
                $errors[] = 'Failed to update profile. Error: ' . $e->getMessage();
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

$stmt = $pdo->prepare("SELECT * FROM privacy_settings WHERE user_id = ?");
$stmt->execute([$userId]);
$privacy = $stmt->fetch() ?: ['show_phone' => 'connected', 'show_email' => 'connected', 'show_photo' => 'everyone', 'show_income' => 'premium', 'profile_visibility' => 'everyone'];

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

        <div class="alert alert-info sticky-note" style="position: sticky; top: 10px; z-index: 1000;">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Note:</strong> Make sure to save all your edits. Change of Name and Phone number would require admin permission.
        </div>

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

        <style>
            .accordion-button:not(.collapsed) {
                background-color: var(--bs-primary, #C0392B);
                color: #fff;
            }
            .accordion-button:focus {
                box-shadow: 0 0 0 0.25rem rgba(192, 57, 43, 0.25);
            }
            .accordion-item {
                border: 1px solid #dee2e6;
                margin-bottom: 1rem;
                border-radius: 0.5rem !important;
                overflow: hidden;
            }
            .accordion-button {
                font-weight: 600;
                font-size: 1.1rem;
            }
            /* Save buttons are hidden by default and shown via JS when changes detected */
            .section-form .btn-save-section {
                display: none;
            }
            .section-form.has-changes .btn-save-section {
                display: inline-block;
            }
        </style>

        <div class="accordion" id="profileAccordion">
            <!-- Basic Info Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBasic" aria-expanded="true" aria-controls="collapseBasic">
                        <i class="bi bi-person-circle me-2"></i>Basic Info
                    </button>
                </h2>
                <div id="collapseBasic" class="accordion-collapse collapse show" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <form class="section-form" method="POST" action="" data-section="basic">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="section" value="basic">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name"
                                       value="<?= htmlspecialchars($currentUser['first_name'] ?? firstNameOf($currentUser)) ?>"
                                       required maxlength="60" pattern="^\p{L}[\p{L}\s'\-]*$">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Middle Name</label>
                                <input type="text" class="form-control" name="middle_name"
                                       value="<?= htmlspecialchars($currentUser['middle_name'] ?? '') ?>"
                                       maxlength="60" pattern="^\p{L}[\p{L}\s'\-]*$" placeholder="Optional">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name"
                                       value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>"
                                       required maxlength="60" pattern="^\p{L}[\p{L}\s'\-]*$">
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
                                <label class="form-label">Native Village</label>
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
                                    <option value="">Select</option>
                                    <?php foreach ($MARITAL_STATUS as $ms): ?>
                                        <option value="<?= $ms ?>" <?= ($currentUser['marital_status'] ?? '') === $ms ? 'selected' : '' ?>><?= $ms ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Residential Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?= sanitize($currentUser['address'] ?? '') ?>" placeholder="Enter your address">
                            </div>
                            <div class="col-md-4" id="address_type_wrapper" style="display: <?= !empty($currentUser['address']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Do You Own The Property</label>
                                <select name="address_type" id="address_type" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Yes" <?= ($currentUser['address_type'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="No" <?= ($currentUser['address_type'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Country</label>
                                <select class="form-select" id="country_select" data-selected-name="<?= htmlspecialchars($currentUser['country'] ?? 'India') ?>">
                                    <option value="">Loading countries...</option>
                                </select>
                                <input type="hidden" name="country" id="country" value="<?= htmlspecialchars($currentUser['country'] ?? 'India') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <select class="form-select" id="state" name="state" data-selected="<?= htmlspecialchars($currentUser['state'] ?? '') ?>" disabled>
                                    <option value="">Select Country First</option>
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
                        <button type="submit" class="btn btn-primary mt-3 btn-save-section" id="saveBasic" disabled><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Personal Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePersonal" aria-expanded="false" aria-controls="collapsePersonal">
                        <i class="bi bi-heart-fill me-2"></i>Personal Details
                    </button>
                </h2>
                <div id="collapsePersonal" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <form class="section-form" method="POST" action="" data-section="personal">
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
                                    <option value="">Select</option>
                                    <?php foreach (['No','Yes','Occasionally'] as $s): ?>
                                        <option value="<?= $s ?>" <?= ($details['smoking'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Drinking</label>
                                <select name="drinking" class="form-select">
                                    <option value="">Select</option>
                                    <?php foreach (['No','Yes','Occasionally'] as $d): ?>
                                        <option value="<?= $d ?>" <?= ($details['drinking'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Hobbies & Interests</label>
                                <textarea name="hobbies" class="form-control" rows="3" placeholder="E.g., Reading, Traveling, Cooking..."><?= sanitize($details['hobbies'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3 btn-save-section" id="savePersonal" disabled><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Professional Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProfessional" aria-expanded="false" aria-controls="collapseProfessional">
                        <i class="bi bi-briefcase-fill me-2"></i>Professional Details
                    </button>
                </h2>
                <div id="collapseProfessional" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <form class="section-form" method="POST" action="" data-section="professional">
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
                                <label class="form-label">Employment Type</label>
                                <select name="employment_status" class="form-select" id="employmentStatus">
                                    <option value="">Select Employment Type</option>
                                    <option value="Job" <?= ($details['employment_status'] ?? '') === 'Job' ? 'selected' : '' ?>>Job</option>
                                    <option value="Business" <?= ($details['employment_status'] ?? '') === 'Business' ? 'selected' : '' ?>>Business</option>
                                </select>
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
                            <div class="col-md-6" id="jobDescriptionContainer" style="display: none;">
                                <label class="form-label">Job Description</label>
                                <select name="job_description" class="form-select">
                                    <option value="">Select Job Description</option>
                                    <option value="Government" <?= ($details['job_description'] ?? '') === 'Government' ? 'selected' : '' ?>>Government</option>
                                    <option value="Private" <?= ($details['job_description'] ?? '') === 'Private' ? 'selected' : '' ?>>Private</option>
                                    <option value="Semi Government" <?= ($details['job_description'] ?? '') === 'Semi Government' ? 'selected' : '' ?>>Semi Government</option>
                                    <option value="Bank" <?= ($details['job_description'] ?? '') === 'Bank' ? 'selected' : '' ?>>Bank</option>
                                    <option value="Others" <?= ($details['job_description'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="businessDescriptionContainer" style="display: none;">
                                <label class="form-label">Business Description</label>
                                <select name="business_description" class="form-select">
                                    <option value="">Select Business Description</option>
                                    <option value="Manufacturing" <?= ($details['business_description'] ?? '') === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                                    <option value="Agency" <?= ($details['business_description'] ?? '') === 'Agency' ? 'selected' : '' ?>>Agency</option>
                                    <option value="Trading" <?= ($details['business_description'] ?? '') === 'Trading' ? 'selected' : '' ?>>Trading</option>
                                    <option value="Service Business" <?= ($details['business_description'] ?? '') === 'Service Business' ? 'selected' : '' ?>>Service Business</option>
                                    <option value="Others" <?= ($details['business_description'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
                                </select>
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
                        <button type="submit" class="btn btn-primary mt-3 btn-save-section" id="saveProfessional" disabled><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Family Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFamily" aria-expanded="false" aria-controls="collapseFamily">
                        <i class="bi bi-people-fill me-2"></i>Family Details
                    </button>
                </h2>
                <div id="collapseFamily" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <form class="section-form" method="POST" action="" data-section="family">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="section" value="family">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Father's Name</label>
                                <input type="text" class="form-control" name="father_name" value="<?= sanitize($family['father_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Father's Mobile Number</label>
                                <input type="text" class="form-control" name="father_mobile" value="<?= sanitize($family['father_mobile'] ?? '') ?>" placeholder="+91 XXXXX XXXXX">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Father's Occupation</label>
                                <input type="text" class="form-control" name="father_occupation" value="<?= sanitize($family['father_occupation'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mother's Name</label>
                                <input type="text" class="form-control" name="mother_name" value="<?= sanitize($family['mother_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mother's Mobile Number</label>
                                <input type="text" class="form-control" name="mother_mobile" value="<?= sanitize($family['mother_mobile'] ?? '') ?>" placeholder="+91 XXXXX XXXXX">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Mother's Occupation</label>
                                <input type="text" class="form-control" name="mother_occupation" value="<?= sanitize($family['mother_occupation'] ?? '') ?>">
                            </div>
                            <?php
                            $parentsAddr = $family['parents_address'] ?? '';
                            $parentsAddrType = $family['parents_address_type'] ?? '';
                            ?>
                            <div class="col-md-8">
                                <label class="form-label">Parents Address</label>
                                <input type="text" class="form-control" id="parents_address" name="parents_address" value="<?= sanitize($parentsAddr) ?>" placeholder="Enter parents address">
                            </div>
                            <div class="col-md-4" id="parents_address_type_wrapper" style="display: <?= !empty($parentsAddr) ? 'block' : 'none' ?>;">
                                <label class="form-label">Do You Own The Property</label>
                                <select name="parents_address_type" id="parents_address_type" class="form-select">
                                    <option value="">Select</option>
                                    <option value="Yes" <?= $parentsAddrType === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="No" <?= $parentsAddrType === 'No' ? 'selected' : '' ?>>No</option>
                                </select>
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
                            <div class="col-md-6">
                                <label class="form-label">Gotra</label>
                                <input type="text" class="form-control" name="gotra" value="<?= sanitize($family['gotra'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">About Family</label>
                                <textarea name="about_family" class="form-control" rows="3"><?= sanitize($family['about_family'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3 btn-save-section" id="saveFamily" disabled><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Horoscope Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHoroscope" aria-expanded="false" aria-controls="collapseHoroscope">
                        <i class="bi bi-stars me-2"></i>Horoscope
                    </button>
                </h2>
                <div id="collapseHoroscope" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <form class="section-form" method="POST" action="" data-section="horoscope">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="section" value="horoscope">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Time of Birth</label>
                                <input type="time" class="form-control" name="birth_time" value="<?= sanitize($details['birth_time'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Place of Birth</label>
                                <input type="text" class="form-control" name="place_of_birth" value="<?= sanitize($details['place_of_birth'] ?? '') ?>" placeholder="Enter place of birth">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3 btn-save-section" id="saveHoroscope" disabled><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Partner Preferences Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePartner" aria-expanded="false" aria-controls="collapsePartner">
                        <i class="bi bi-search-heart me-2"></i>Partner Preferences
                    </button>
                </h2>
                <div id="collapsePartner" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <form class="section-form" method="POST" action="" data-section="partner">
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
                                <input type="text" class="form-control" name="pref_religion" value="<?= sanitize($partnerPrefs['religion'] ?? '') ?>" placeholder="E.g., Hindu, Jain">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Marital Status</label>
                                <select name="pref_marital_status" class="form-select">
                                    <option value="">Any</option>
                                    <?php foreach ($MARITAL_STATUS as $ms): ?>
                                        <option value="<?= $ms ?>" <?= ($partnerPrefs['marital_status'] ?? '') === $ms ? 'selected' : '' ?>><?= $ms ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                            <div class="col-md-4">
                                <label class="form-label">Smoking</label>
                                <select name="pref_smoking" class="form-select">
                                    <option value="No" <?= ($partnerPrefs['smoking'] ?? "Doesn't Matter") === 'No' ? 'selected' : '' ?>>No</option>
                                    <option value="Yes" <?= ($partnerPrefs['smoking'] ?? "Doesn't Matter") === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="Doesn't Matter" <?= ($partnerPrefs['smoking'] ?? "Doesn't Matter") === "Doesn't Matter" ? 'selected' : '' ?>>Doesn't Matter</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Drinking</label>
                                <select name="pref_drinking" class="form-select">
                                    <option value="No" <?= ($partnerPrefs['drinking'] ?? "Doesn't Matter") === 'No' ? 'selected' : '' ?>>No</option>
                                    <option value="Yes" <?= ($partnerPrefs['drinking'] ?? "Doesn't Matter") === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                    <option value="Doesn't Matter" <?= ($partnerPrefs['drinking'] ?? "Doesn't Matter") === "Doesn't Matter" ? 'selected' : '' ?>>Doesn't Matter</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">About Desired Partner</label>
                                <textarea name="pref_about_partner" class="form-control" rows="3"><?= sanitize($partnerPrefs['about_partner'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3 btn-save-section" id="savePartner" disabled><i class="bi bi-check-lg me-1"></i>Save Preferences</button>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Contact Details Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseContact" aria-expanded="false" aria-controls="collapseContact">
                        <i class="bi bi-telephone-fill me-2"></i>Contact Details
                    </button>
                </h2>
                <div id="collapseContact" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <form class="section-form" method="POST" action="" data-section="contact">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="section" value="contact">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Mobile Number</label>
                                <?php
                                $contactCountryCodes = [
                                    '+91' => 'India (+91)',
                                    '+1-us' => 'USA (+1)',
                                    '+1-ca' => 'Canada (+1)',
                                    '+61' => 'Australia (+61)',
                                    '+64' => 'New Zealand (+64)',
                                    '+44' => 'UK (+44)',
                                    '+355' => 'Albania (+355)',
                                    '+376' => 'Andorra (+376)',
                                    '+43' => 'Austria (+43)',
                                    '+375' => 'Belarus (+375)',
                                    '+32' => 'Belgium (+32)',
                                    '+387' => 'Bosnia and Herzegovina (+387)',
                                    '+359' => 'Bulgaria (+359)',
                                    '+385' => 'Croatia (+385)',
                                    '+357' => 'Cyprus (+357)',
                                    '+420' => 'Czech Republic (+420)',
                                    '+45' => 'Denmark (+45)',
                                    '+372' => 'Estonia (+372)',
                                    '+358' => 'Finland (+358)',
                                    '+33' => 'France (+33)',
                                    '+49' => 'Germany (+49)',
                                    '+30' => 'Greece (+30)',
                                    '+36' => 'Hungary (+36)',
                                    '+354' => 'Iceland (+354)',
                                    '+353' => 'Ireland (+353)',
                                    '+39' => 'Italy (+39)',
                                    '+383' => 'Kosovo (+383)',
                                    '+371' => 'Latvia (+371)',
                                    '+423' => 'Liechtenstein (+423)',
                                    '+370' => 'Lithuania (+370)',
                                    '+352' => 'Luxembourg (+352)',
                                    '+356' => 'Malta (+356)',
                                    '+373' => 'Moldova (+373)',
                                    '+377' => 'Monaco (+377)',
                                    '+382' => 'Montenegro (+382)',
                                    '+31' => 'Netherlands (+31)',
                                    '+389' => 'North Macedonia (+389)',
                                    '+47' => 'Norway (+47)',
                                    '+48' => 'Poland (+48)',
                                    '+351' => 'Portugal (+351)',
                                    '+40' => 'Romania (+40)',
                                    '+7' => 'Russia (+7)',
                                    '+378' => 'San Marino (+378)',
                                    '+381' => 'Serbia (+381)',
                                    '+421' => 'Slovakia (+421)',
                                    '+386' => 'Slovenia (+386)',
                                    '+34' => 'Spain (+34)',
                                    '+46' => 'Sweden (+46)',
                                    '+41' => 'Switzerland (+41)',
                                    '+90' => 'Turkey (+90)',
                                    '+380' => 'Ukraine (+380)',
                                    '+379' => 'Vatican City (+379)',
                                ];
                                // Parse stored phone (format: "+CC NNNNNN")
                                $storedPhone = $currentUser['phone'] ?? '';
                                $existingCode = '+91';
                                $existingNumber = $storedPhone;
                                if (preg_match('/^(\+\d+)\s+(.+)$/', $storedPhone, $m)) {
                                    $existingCode = $m[1];
                                    $existingNumber = $m[2];
                                } else {
                                    // Legacy: no country code prefix
                                    $existingNumber = preg_replace('/[^0-9]/', '', $storedPhone);
                                }
                                ?>
                                <div class="input-group">
                                    <select name="country_code" class="form-select" style="max-width: 150px;">
                                        <?php foreach ($contactCountryCodes as $val => $label): $codeOnly = preg_replace('/-.*$/', '', $val); ?>
                                            <option value="<?= $val ?>" <?= $codeOnly === $existingCode ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($existingNumber) ?>" placeholder="Mobile number" required>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3 btn-save-section" id="saveContact" disabled><i class="bi bi-check-lg me-1"></i>Save Contact Details</button>
                    </form>
                    </div>
                </div>
            </div>

            <!-- Photos Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePhotos" aria-expanded="false" aria-controls="collapsePhotos">
                        <i class="bi bi-camera-fill me-2"></i>Photos
                    </button>
                </h2>
                <div id="collapsePhotos" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <div class="dashboard-card">
                    <h5 class="mb-3">Your Photos (<?= count($photos) ?>/<?= MAX_PHOTOS ?>)</h5>
                    
                    <div class="row g-3 mb-4">
                        <?php foreach ($photos as $photo): ?>
                            <div class="col-md-3 col-6">
                                <div class="position-relative">
                                    <img src="<?= htmlspecialchars(photoUrl($photo['photo_path']), ENT_QUOTES, 'UTF-8') ?>" class="rounded w-100" style="height: 180px; object-fit: cover;<?= !$photo['is_approved'] ? ' opacity: 0.6;' : '' ?>">
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
                    <div class="modal fade" id="cropModal" tabindex="-1" data-bs-backdrop="false">
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

            <!-- Photo ID - Documentation Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIdDoc" aria-expanded="false" aria-controls="collapseIdDoc">
                        <i class="bi bi-file-earmark-pdf-fill me-2"></i>Photo ID - Documentation
                    </button>
                </h2>
                <div id="collapseIdDoc" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <div class="dashboard-card">
                            <p class="text-muted small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Upload a single PDF document <b>(Only One Required)</b> (e.g., Aadhaar, Passport, Driver's License) for identity verification.
                                Your document is private and can only be reviewed by authorized administrators.
                            </p>

                            <?php if (!empty($currentUser['id_document'])): ?>
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <div>
                                        Document uploaded
                                        <?php if (!empty($currentUser['id_document_uploaded_at'])): ?>
                                            on <?= date('d M Y, h:i A', strtotime($currentUser['id_document_uploaded_at'])) ?>
                                        <?php endif; ?>.
                                        Uploading a new file will replace the existing document.
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div>No document uploaded yet.</div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" enctype="multipart/form-data" id="idDocumentUploadForm">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="section" value="id_document">
                                <div class="row align-items-end g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Upload Document (PDF only)</label>
                                        <input type="file" class="form-control" id="idDocumentInput" name="id_document" accept="application/pdf,.pdf" required>
                                        <small class="text-muted d-block mt-1">Only PDF files are allowed. Maximum file size: 5MB.</small>
                                        <div id="idDocError" class="alert alert-danger mt-2 py-1 d-none">
                                            <small><i class="bi bi-exclamation-triangle me-1"></i><span id="idDocErrorMsg"></span></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary w-100" id="idDocSubmitBtn">
                                            <i class="bi bi-upload me-1"></i><?= !empty($currentUser['id_document']) ? 'Replace Document' : 'Upload Document' ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Proof - Documentation Section -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAddressProof" aria-expanded="false" aria-controls="collapseAddressProof">
                        <i class="bi bi-file-earmark-pdf-fill me-2"></i>Address Proof - Documentation
                    </button>
                </h2>
                <div id="collapseAddressProof" class="accordion-collapse collapse" data-bs-parent="#profileAccordion">
                    <div class="accordion-body">
                        <div class="dashboard-card">
                            <p class="text-muted small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Upload a single PDF document <b>(Only One Required)</b>(e.g., Passport, Electricity Bill, Telephone Bill) for address verification.
                                Your document is private and can only be reviewed by authorized administrators.
                            </p>

                            <?php if (!empty($currentUser['address_proof_document'])): ?>
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <div>
                                        Document uploaded
                                        <?php if (!empty($currentUser['address_proof_uploaded_at'])): ?>
                                            on <?= date('d M Y, h:i A', strtotime($currentUser['address_proof_uploaded_at'])) ?>
                                        <?php endif; ?>.
                                        Uploading a new file will replace the existing document.
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning d-flex align-items-center" role="alert">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <div>No document uploaded yet.</div>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" enctype="multipart/form-data" id="addressProofUploadForm">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="section" value="address_proof">
                                <div class="row align-items-end g-3">
                                    <div class="col-md-8">
                                        <label class="form-label">Upload Document (PDF only)</label>
                                        <input type="file" class="form-control" id="addressProofInput" name="address_proof_document" accept="application/pdf,.pdf" required>
                                        <small class="text-muted d-block mt-1">Only PDF files are allowed. Maximum file size: 5MB.</small>
                                        <div id="addressProofError" class="alert alert-danger mt-2 py-1 d-none">
                                            <small><i class="bi bi-exclamation-triangle me-1"></i><span id="addressProofErrorMsg"></span></small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary w-100" id="addressProofSubmitBtn">
                                            <i class="bi bi-upload me-1"></i><?= !empty($currentUser['address_proof_document']) ? 'Replace Document' : 'Upload Document' ?>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- Auto-save form data across tabs and reloads -->
<script>
(function() {
    'use strict';
    var STORAGE_PREFIX = 'editProfile_user<?= (int)$userId ?>_';
    var SAVED_SECTION = <?php echo json_encode($_GET['saved'] ?? ''); ?>;

    // Map of section name -> tab pane id. MUST include every form's
    // <input name="section"> value, otherwise change detection skips it.
    var SECTION_TABS = {
        'basic': 'basic',
        'personal': 'personal',
        'professional': 'professional',
        'family': 'family',
        'horoscope': 'horoscope',
        'partner': 'partner',
        'contact': 'contact'
    };

    function getFieldKey(section, name) {
        return STORAGE_PREFIX + section + '_' + name;
    }

    function shouldSkip(field) {
        if (!field.name) return true;
        if (field.type === 'hidden') return true;
        if (field.type === 'file') return true;
        if (field.type === 'submit' || field.type === 'button') return true;
        if (field.name === 'csrf_token' || field.name === 'section' || field.name === 'photo_id') return true;
        return false;
    }

    function getSectionFromForm(form) {
        var sectionInput = form.querySelector('input[name="section"]');
        if (!sectionInput) return null;
        var s = sectionInput.value;
        return SECTION_TABS[s] ? s : null;
    }

    // Get field value for comparison
    function getFieldValue(field) {
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked ? field.value : '';
        }
        return field.value;
    }

    // Check if form has changes compared to initial state
    function hasFormChanges(form, initialData) {
        var hasChanges = false;
        form.querySelectorAll('input, select, textarea').forEach(function(field) {
            if (shouldSkip(field)) return;
            var currentVal = getFieldValue(field);
            var initialVal = initialData[field.name] || '';
            if (String(currentVal) !== String(initialVal)) {
                hasChanges = true;
            }
        });
        return hasChanges;
    }

    // Restore saved values for all forms on page load
    function restoreAll() {
        document.querySelectorAll('.section-form').forEach(function(form) {
            var section = getSectionFromForm(form);
            if (!section) return;
            form.querySelectorAll('input, select, textarea').forEach(function(field) {
                if (shouldSkip(field)) return;
                var saved = sessionStorage.getItem(getFieldKey(section, field.name));
                if (saved === null) return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    field.checked = (saved === field.value || saved === '1');
                } else {
                    field.value = saved;
                }
            });
        });
    }

    // Save field on change
    function attachAutoSave() {
        document.querySelectorAll('.section-form').forEach(function(form) {
            var section = getSectionFromForm(form);
            if (!section) return;
            form.addEventListener('input', function(e) {
                var f = e.target;
                if (shouldSkip(f)) return;
                var val = (f.type === 'checkbox' || f.type === 'radio') ? (f.checked ? f.value : '') : f.value;
                try {
                    sessionStorage.setItem(getFieldKey(section, f.name), val);
                } catch (err) { /* quota etc. - ignore */ }
            });
            form.addEventListener('change', function(e) {
                var f = e.target;
                if (shouldSkip(f)) return;
                var val = (f.type === 'checkbox' || f.type === 'radio') ? (f.checked ? f.value : '') : f.value;
                try {
                    sessionStorage.setItem(getFieldKey(section, f.name), val);
                } catch (err) {}
            });
        });
    }

    // Clear a section's saved data (called after successful submit redirect)
    function clearSection(section) {
        if (!section) return;
        var keysToRemove = [];
        for (var i = 0; i < sessionStorage.length; i++) {
            var key = sessionStorage.key(i);
            if (key && key.indexOf(STORAGE_PREFIX + section + '_') === 0) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(function(k) { sessionStorage.removeItem(k); });
    }

    // Attach change detection and button enable/disable logic
    function attachChangeDetection() {
        document.querySelectorAll('.section-form').forEach(function(form) {
            var section = getSectionFromForm(form);
            if (!section) return;

            // Store initial values on page load
            var initialData = {};
            form.querySelectorAll('input, select, textarea').forEach(function(field) {
                if (shouldSkip(field)) return;
                initialData[field.name] = getFieldValue(field);
            });

            // Check for changes and update button state
            function updateButtonState() {
                var hasChanges = hasFormChanges(form, initialData);

                // Add/remove has-changes class to trigger CSS display of save button
                if (hasChanges) {
                    form.classList.add('has-changes');
                } else {
                    form.classList.remove('has-changes');
                }

                // Enable/disable the specific section's save button
                var saveBtn = form.querySelector('.btn-save-section');
                if (saveBtn) {
                    saveBtn.disabled = !hasChanges;
                }
            }

            // Listen for input and change events
            form.addEventListener('input', updateButtonState);
            form.addEventListener('change', updateButtonState);

            // Initial check
            updateButtonState();
        });
    }

    // Warn the user if they try to leave with unsaved edits in any section.
    // sessionStorage drafts survive reloads within the same tab, but closing
    // the tab/window or navigating off-site loses them — hence this guard.
    function attachUnloadGuard() {
        window.addEventListener('beforeunload', function(e) {
            // Skip the warning if the user just clicked a section's Save submit button.
            if (window.__skipUnloadGuard) return;
            var dirty = document.querySelector('.section-form.has-changes');
            if (dirty) {
                e.preventDefault();
                e.returnValue = ''; // Required for Chromium to show the prompt
                return '';
            }
        });

        // When any section form is submitted, allow navigation without prompt.
        document.querySelectorAll('.section-form').forEach(function(form) {
            form.addEventListener('submit', function() {
                window.__skipUnloadGuard = true;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        // If server signaled successful save for a section, clear its draft first
        if (SAVED_SECTION) clearSection(SAVED_SECTION);
        restoreAll();
        attachAutoSave();
        attachChangeDetection();
        attachUnloadGuard();

        // Employment Type dynamic dropdown behavior
        var employmentStatus = document.getElementById('employmentStatus');
        var jobDescriptionContainer = document.getElementById('jobDescriptionContainer');
        var businessDescriptionContainer = document.getElementById('businessDescriptionContainer');
        var jobDescriptionSelect = document.querySelector('select[name="job_description"]');
        var businessDescriptionSelect = document.querySelector('select[name="business_description"]');

        if (employmentStatus && jobDescriptionContainer && businessDescriptionContainer) {
            function updateDynamicDropdowns() {
                var status = employmentStatus.value;
                // Hide both first
                jobDescriptionContainer.style.display = 'none';
                businessDescriptionContainer.style.display = 'none';

                if (status === 'Job') {
                    jobDescriptionContainer.style.display = 'block';
                    businessDescriptionSelect.value = '';
                } else if (status === 'Business') {
                    businessDescriptionContainer.style.display = 'block';
                    jobDescriptionSelect.value = '';
                }
            }

            // Initialize on page load
            updateDynamicDropdowns();

            // Listen for changes
            employmentStatus.addEventListener('change', updateDynamicDropdowns);
        }

        // Show subtle "draft restored" indicator if any unsaved data exists
        document.querySelectorAll('.section-form').forEach(function(form) {
            var section = getSectionFromForm(form);
            if (!section) return;
            var hasDraft = false;
            for (var i = 0; i < sessionStorage.length; i++) {
                var key = sessionStorage.key(i);
                if (key && key.indexOf(STORAGE_PREFIX + section + '_') === 0) {
                    hasDraft = true; break;
                }
            }
        });
    });
})();
</script>

<!-- Cropper.js for photo cropping before upload -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>
<style>
#cropModal .modal-dialog {
    z-index: 1060 !important;
}
#cropModal .modal-content {
    z-index: 1061 !important;
}
#cropModal .modal-footer {
    position: relative;
    z-index: 1062 !important;
}
#cropModal .cropper-container {
    z-index: 1;
}
</style>
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

        // Wait for image to load before initializing cropper
        if (cropImage.complete) {
            initCropper();
        } else {
            cropImage.onload = initCropper;
            cropImage.onerror = function() {
                console.error('Image failed to load');
                alert('Failed to load the image. Please try another file.');
                cropModal.hide();
            };
        }

        function initCropper() {
            try {
                cropper = new Cropper(cropImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                    movable: true,
                    zoomable: true,
                    scalable: false,
                    rotatable: false,
                    background: false
                });
            } catch (e) {
                console.error('Cropper initialization error:', e);
                alert('Failed to initialize image cropper. Please try again.');
                cropModal.hide();
            }
        }
    });

    cropModalEl.addEventListener('hidden.bs.modal', function() {
        if (cropper) { cropper.destroy(); cropper = null; }
        input.value = '';
    });

    cropUploadBtn.addEventListener('click', function() {
        if (!cropper) {
            alert('Cropper not initialized. Please try selecting the image again.');
            return;
        }
        cropUploadBtn.disabled = true;
        cropUploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading...';

        try {
            cropper.getCroppedCanvas({
                width: 800,
                height: 800,
                imageSmoothingQuality: 'high'
            }).toBlob(function(blob) {
                if (!blob) {
                    alert('Failed to crop the image. Please try again.');
                    cropUploadBtn.disabled = false;
                    cropUploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Crop & Upload';
                    return;
                }
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
        } catch (e) {
            console.error('Cropping error:', e);
            alert('Failed to crop the image. Please try again.');
            cropUploadBtn.disabled = false;
            cropUploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Crop & Upload';
        }
    });
});

// Toggle Property Status visibility based on Address input
(function() {
    function bindToggle(addressId, wrapperId, selectId) {
        var input = document.getElementById(addressId);
        var wrapper = document.getElementById(wrapperId);
        var select = document.getElementById(selectId);
        if (!input || !wrapper) return;
        input.addEventListener('input', function() {
            if (input.value.trim() !== '') {
                wrapper.style.display = 'block';
            } else {
                wrapper.style.display = 'none';
                if (select) select.value = '';
            }
        });
    }
    bindToggle('address', 'address_type_wrapper', 'address_type');
    bindToggle('parents_address', 'parents_address_type_wrapper', 'parents_address_type');
})();

// Country/State cascading dropdown
(function() {
    var countrySelect = document.getElementById('country_select');
    var countryHidden = document.getElementById('country');
    var stateSelect = document.getElementById('state');
    var selectedCountryName = countrySelect.dataset.selectedName || 'India';
    var selectedState = stateSelect.dataset.selected || '';
    var countriesData = {};

    function populateStates(countryCode) {
        stateSelect.innerHTML = '';
        if (!countryCode || !countriesData[countryCode]) {
            stateSelect.innerHTML = '<option value="">Select Country First</option>';
            stateSelect.disabled = true;
            return;
        }
        var divisions = countriesData[countryCode].divisions || {};
        var keys = Object.keys(divisions).sort(function(a, b) {
            return divisions[a].localeCompare(divisions[b]);
        });
        var defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Select State';
        stateSelect.appendChild(defaultOpt);
        keys.forEach(function(k) {
            var opt = document.createElement('option');
            opt.value = divisions[k];
            opt.textContent = divisions[k];
            if (divisions[k] === selectedState) opt.selected = true;
            stateSelect.appendChild(opt);
        });
        stateSelect.disabled = false;
    }

    fetch('<?= SITE_URL ?>/assets/data/iso-3166-2.json')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            countriesData = data;
            // Populate countries sorted by name
            var codes = Object.keys(data).sort(function(a, b) {
                return data[a].name.localeCompare(data[b].name);
            });
            countrySelect.innerHTML = '<option value="">Select Country</option>';
            var matchedCode = '';
            codes.forEach(function(code) {
                var opt = document.createElement('option');
                opt.value = code;
                opt.textContent = data[code].name;
                if (data[code].name === selectedCountryName) {
                    opt.selected = true;
                    matchedCode = code;
                }
                countrySelect.appendChild(opt);
            });
            // Auto-populate state if a country is preselected
            if (matchedCode) {
                countryHidden.value = data[matchedCode].name;
                populateStates(matchedCode);
            }
        })
        .catch(function() {
            countrySelect.innerHTML = '<option value="">Failed to load countries</option>';
        });

    countrySelect.addEventListener('change', function() {
        selectedState = '';
        var code = this.value;
        countryHidden.value = (code && countriesData[code]) ? countriesData[code].name : '';
        populateStates(code);
    });
})();

// ID Document (PDF only) client-side validation
(function() {
    var input = document.getElementById('idDocumentInput');
    var form = document.getElementById('idDocumentUploadForm');
    var errBox = document.getElementById('idDocError');
    var errMsg = document.getElementById('idDocErrorMsg');
    if (!input || !form) return;

    function showError(msg) {
        if (errBox && errMsg) {
            errMsg.textContent = msg;
            errBox.classList.remove('d-none');
        }
    }
    function hideError() {
        if (errBox) errBox.classList.add('d-none');
    }

    input.addEventListener('change', function() {
        hideError();
        if (!this.files || !this.files.length) return;
        var file = this.files[0];
        var name = (file.name || '').toLowerCase();
        if (!name.endsWith('.pdf') || (file.type && file.type !== 'application/pdf')) {
            showError('Only PDF files are allowed.');
            this.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            showError('File too large. Maximum 5MB allowed.');
            this.value = '';
            return;
        }
    });

    form.addEventListener('submit', function(e) {
        if (!input.files || !input.files.length) {
            e.preventDefault();
            showError('Please select a PDF file to upload.');
            return;
        }
        var file = input.files[0];
        var name = (file.name || '').toLowerCase();
        if (!name.endsWith('.pdf') || (file.type && file.type !== 'application/pdf')) {
            e.preventDefault();
            showError('Only PDF files are allowed.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            e.preventDefault();
            showError('File too large. Maximum 5MB allowed.');
            return;
        }
    });

    // Address Proof upload validation
    var addressProofInput = document.getElementById('addressProofInput');
    var addressProofForm = document.getElementById('addressProofUploadForm');
    var addressProofErrBox = document.getElementById('addressProofError');
    var addressProofErrMsg = document.getElementById('addressProofErrorMsg');
    if (addressProofInput && addressProofForm) {
        function showAddressProofError(msg) {
            if (addressProofErrBox && addressProofErrMsg) {
                addressProofErrMsg.textContent = msg;
                addressProofErrBox.classList.remove('d-none');
            }
        }
        function hideAddressProofError() {
            if (addressProofErrBox) addressProofErrBox.classList.add('d-none');
        }

        addressProofInput.addEventListener('change', function() {
            hideAddressProofError();
            if (!this.files || !this.files.length) return;
            var file = this.files[0];
            var name = (file.name || '').toLowerCase();
            if (!name.endsWith('.pdf') || (file.type && file.type !== 'application/pdf')) {
                showAddressProofError('Only PDF files are allowed.');
                this.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                showAddressProofError('File too large. Maximum 5MB allowed.');
                this.value = '';
                return;
            }
        });

        addressProofForm.addEventListener('submit', function(e) {
            if (!addressProofInput.files || !addressProofInput.files.length) {
                e.preventDefault();
                showAddressProofError('Please select a PDF file to upload.');
                return;
            }
            var file = addressProofInput.files[0];
            var name = (file.name || '').toLowerCase();
            if (!name.endsWith('.pdf') || (file.type && file.type !== 'application/pdf')) {
                e.preventDefault();
                showAddressProofError('Only PDF files are allowed.');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                showAddressProofError('File too large. Maximum 5MB allowed.');
                return;
            }
        });
    }
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
