<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$pdo = getDBConnection();

switch ($action) {
    case 'photo_approve':
        $photoId = intval($_POST['photo_id'] ?? 0);
        if (!$photoId) {
            echo json_encode(['success' => false, 'message' => 'Invalid photo']);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch();
        
        if (!$photo) {
            echo json_encode(['success' => false, 'message' => 'Photo not found']);
            break;
        }
        
        $pdo->prepare("UPDATE photos SET is_approved = 1 WHERE id = ?")->execute([$photoId]);
        
        // If user wanted this as primary, set it
        if ($photo['is_primary']) {
            $pdo->prepare("UPDATE photos SET is_primary = 0 WHERE user_id = ? AND id != ?")->execute([$photo['user_id'], $photoId]);
            $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?")->execute([$photo['photo_path'], $photo['user_id']]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Photo approved']);
        break;

    case 'photo_reject':
        $photoId = intval($_POST['photo_id'] ?? 0);
        if (!$photoId) {
            echo json_encode(['success' => false, 'message' => 'Invalid photo']);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch();
        
        if ($photo) {
            $filePath = __DIR__ . '/../../' . $photo['photo_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $pdo->prepare("DELETE FROM photos WHERE id = ?")->execute([$photoId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Photo rejected and deleted']);
        break;

    case 'approve':
    case 'reject':
        $requestId = intval($_POST['request_id'] ?? 0);
        $adminNote = $_POST['admin_note'] ?? '';

        if (!$requestId) {
            echo json_encode(['success' => false, 'message' => 'Invalid request']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Change request not found']);
            break;
        }

        if ($request['status'] !== 'pending') {
            echo json_encode(['success' => false, 'message' => 'This request has already been ' . $request['status']]);
            break;
        }

        if ($action === 'reject') {
            $stmt = $pdo->prepare(
                "UPDATE profile_change_requests SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
            );
            $stmt->execute([$adminNote, $_SESSION['admin_id'], $requestId]);
            echo json_encode(['success' => true, 'message' => 'Changes rejected']);
            break;
        }

        // Approve: apply changes
        $newData = json_decode($request['new_data'], true);
        $userId = $request['user_id'];

        try {
            $pdo->beginTransaction();

            // Apply changes to users table (basic fields)
            $basicFields = ['name', 'religion', 'caste', 'sub_caste', 'mother_tongue', 'marital_status', 'state', 'city', 'about_me'];
            $basicUpdates = [];
            $basicParams = [];
            foreach ($basicFields as $field) {
                if (isset($newData[$field])) {
                    $basicUpdates[] = "$field = ?";
                    $basicParams[] = $newData[$field];
                }
            }
            if (!empty($basicUpdates)) {
                $basicUpdates[] = 'updated_at = NOW()';
                $basicParams[] = $userId;
                $sql = "UPDATE users SET " . implode(', ', $basicUpdates) . " WHERE id = ?";
                $pdo->prepare($sql)->execute($basicParams);
            }

            // Apply changes to profile_details table (personal and professional fields)
            $profileFields = ['height', 'weight', 'complexion', 'body_type', 'blood_group', 'diet', 'smoking', 'drinking', 'hobbies', 'about_me', 'education', 'education_detail', 'occupation', 'occupation_detail', 'company', 'annual_income', 'working_city'];
            $profileUpdates = [];
            $profileParams = [];
            foreach ($profileFields as $field) {
                if (isset($newData[$field])) {
                    $profileUpdates[] = "$field = ?";
                    $profileParams[] = $newData[$field];
                }
            }
            if (!empty($profileUpdates)) {
                $profileUpdates[] = 'updated_at = NOW()';
                $profileParams[] = $userId;
                $sql = "UPDATE profile_details SET " . implode(', ', $profileUpdates) . " WHERE user_id = ?";
                $pdo->prepare($sql)->execute($profileParams);
            }

            // Apply changes to family_details table
            $familyFields = ['father_name', 'father_occupation', 'mother_name', 'mother_occupation', 'brothers', 'brothers_married', 'sisters', 'sisters_married', 'family_type', 'family_status', 'family_values', 'gotra', 'about_family'];
            $familyUpdates = [];
            $familyParams = [];
            foreach ($familyFields as $field) {
                if (isset($newData[$field])) {
                    $familyUpdates[] = "$field = ?";
                    $familyParams[] = $newData[$field];
                }
            }
            if (!empty($familyUpdates)) {
                $familyUpdates[] = 'updated_at = NOW()';
                $familyParams[] = $userId;
                $sql = "UPDATE family_details SET " . implode(', ', $familyUpdates) . " WHERE user_id = ?";
                $pdo->prepare($sql)->execute($familyParams);
            }

            // Apply changes to partner_preferences table
            $partnerFields = ['min_age', 'max_age', 'min_height', 'max_height', 'marital_status', 'religion', 'caste', 'mother_tongue', 'education', 'occupation', 'min_income', 'max_income', 'state', 'diet', 'smoking', 'drinking', 'about_partner'];
            $partnerUpdates = [];
            $partnerParams = [];
            foreach ($partnerFields as $field) {
                if (isset($newData[$field])) {
                    $partnerUpdates[] = "$field = ?";
                    $partnerParams[] = $newData[$field];
                }
            }
            if (!empty($partnerUpdates)) {
                $partnerUpdates[] = 'updated_at = NOW()';
                $partnerParams[] = $userId;
                $sql = "UPDATE partner_preferences SET " . implode(', ', $partnerUpdates) . " WHERE user_id = ?";
                $pdo->prepare($sql)->execute($partnerParams);
            }

            $stmt = $pdo->prepare(
                "UPDATE profile_change_requests SET status='approved', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
            );
            $stmt->execute([$adminNote, $_SESSION['admin_id'], $requestId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Changes approved and applied']);

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Approve Change Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to apply changes']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
