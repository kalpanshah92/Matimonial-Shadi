<?php
/**
 * Core Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Generate a unique profile ID
 */
function generateProfileId($gender) {
    $prefix = ($gender === 'Male') ? 'MS' : 'MW';
    $pdo = getDBConnection();
    do {
        $id = $prefix . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE profile_id = ?");
        $stmt->execute([$id]);
    } while ($stmt->fetch());
    return $id;
}

/**
 * Generate OTP
 */
function generateOTP() {
    return str_pad(rand(0, pow(10, OTP_LENGTH) - 1), OTP_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Save OTP to database
 */
function saveOTP($identifier, $otp, $purpose = 'registration') {
    $pdo = getDBConnection();
    // Delete old OTPs for this identifier
    $stmt = $pdo->prepare("DELETE FROM otp_verifications WHERE identifier = ? AND purpose = ?");
    $stmt->execute([$identifier, $purpose]);
    
    // Save new OTP
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    $stmt = $pdo->prepare("INSERT INTO otp_verifications (identifier, otp, purpose, expires_at) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$identifier, $otp, $purpose, $expiresAt]);
}

/**
 * Verify OTP
 */
function verifyOTP($identifier, $otp, $purpose = 'registration') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT * FROM otp_verifications WHERE identifier = ? AND otp = ? AND purpose = ? AND expires_at > NOW() AND is_verified = 0 AND attempts < 5"
    );
    $stmt->execute([$identifier, $otp, $purpose]);
    $record = $stmt->fetch();
    
    if ($record) {
        $stmt = $pdo->prepare("UPDATE otp_verifications SET is_verified = 1 WHERE id = ?");
        $stmt->execute([$record['id']]);
        return true;
    }
    
    // Increment attempts
    $stmt = $pdo->prepare("UPDATE otp_verifications SET attempts = attempts + 1 WHERE identifier = ? AND purpose = ?");
    $stmt->execute([$identifier, $purpose]);
    
    return false;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user data
 */
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Upload a photo
 */
function uploadPhoto($file, $userId) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error'];
    }
    
    if ($file['size'] > MAX_PHOTO_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum 5MB allowed.'];
    }
    
    if (!in_array($file['type'], ALLOWED_PHOTO_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and WebP allowed.'];
    }
    
    $userDir = UPLOADS_PATH . 'photos' . DIRECTORY_SEPARATOR . $userId;
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('photo_') . '.' . $ext;
    $filepath = $userDir . DIRECTORY_SEPARATOR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'path' => 'uploads/photos/' . $userId . '/' . $filename,
            'filename' => $filename
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

/**
 * Calculate age from date of birth
 */
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    return $today->diff($birthDate)->y;
}

/**
 * Format height from cm to feet/inches
 */
function formatHeight($cm) {
    if (!$cm) return 'Not specified';
    $totalInches = $cm / 2.54;
    $feet = floor($totalInches / 12);
    $inches = round($totalInches % 12);
    return "$feet' $inches\" ($cm cm)";
}

/**
 * Get user's profile completion percentage
 */
function getProfileCompletion($userId) {
    $pdo = getDBConnection();
    $completion = 0;
    $totalFields = 0;
    
    // Check user basic info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    $basicFields = ['name', 'email', 'gender', 'dob', 'religion', 'caste', 'mother_tongue', 'state', 'city', 'profile_pic', 'about_me'];
    foreach ($basicFields as $field) {
        $totalFields++;
        if (!empty($user[$field])) $completion++;
    }
    
    // Check profile details
    $stmt = $pdo->prepare("SELECT * FROM profile_details WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    
    $profileFields = ['height', 'education', 'occupation', 'annual_income', 'diet'];
    foreach ($profileFields as $field) {
        $totalFields++;
        if ($profile && !empty($profile[$field])) $completion++;
    }
    
    // Check family details
    $stmt = $pdo->prepare("SELECT * FROM family_details WHERE user_id = ?");
    $stmt->execute([$userId]);
    $family = $stmt->fetch();
    
    $familyFields = ['father_occupation', 'mother_occupation', 'family_type'];
    foreach ($familyFields as $field) {
        $totalFields++;
        if ($family && !empty($family[$field])) $completion++;
    }
    
    // Check partner preferences
    $stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $partner = $stmt->fetch();
    $totalFields++;
    if ($partner) $completion++;
    
    // Check photos
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM photos WHERE user_id = ?");
    $stmt->execute([$userId]);
    $photos = $stmt->fetch();
    $totalFields++;
    if ($photos['count'] > 0) $completion++;
    
    return $totalFields > 0 ? round(($completion / $totalFields) * 100) : 0;
}

/**
 * Get notification count for user
 */
function getUnreadNotificationCount($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Create a notification
 */
function createNotification($userId, $type, $title, $message, $link = null) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $title, $message, $link]);
}

/**
 * Log profile visit
 */
function logProfileVisit($visitorId, $visitedId) {
    if ($visitorId === $visitedId) return;
    
    $pdo = getDBConnection();
    // Check if visited in last hour
    $stmt = $pdo->prepare(
        "SELECT id FROM profile_visits WHERE visitor_id = ? AND visited_id = ? AND visited_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $stmt->execute([$visitorId, $visitedId]);
    
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO profile_visits (visitor_id, visited_id) VALUES (?, ?)");
        $stmt->execute([$visitorId, $visitedId]);
        createNotification($visitedId, 'visit', 'Profile Viewed', 'Someone viewed your profile', 'profile.php?id=' . $visitorId);
    }
}

/**
 * Check if user has active premium subscription
 */
function isPremium($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE() ORDER BY end_date DESC LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ? true : false;
}

/**
 * Get matched profiles based on preferences
 */
function getMatchedProfiles($userId, $limit = 12, $offset = 0) {
    $pdo = getDBConnection();
    
    $user = getCurrentUser();
    if (!$user) return [];
    
    // Get partner preferences
    $stmt = $pdo->prepare("SELECT * FROM partner_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    
    $oppositeGender = ($user['gender'] === 'Male') ? 'Female' : 'Male';
    
    $query = "SELECT u.*, pd.height, pd.education, pd.occupation, pd.annual_income 
              FROM users u 
              LEFT JOIN profile_details pd ON u.id = pd.user_id
              WHERE u.gender = ? AND u.id != ? AND u.is_active = 1 AND u.status = 'approved'";
    $params = [$oppositeGender, $userId];
    
    if ($prefs) {
        if (!empty($prefs['religion'])) {
            $religions = explode(',', $prefs['religion']);
            $placeholders = implode(',', array_fill(0, count($religions), '?'));
            $query .= " AND u.religion IN ($placeholders)";
            $params = array_merge($params, $religions);
        }
        if ($prefs['min_age'] && $prefs['max_age']) {
            $query .= " AND TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) BETWEEN ? AND ?";
            $params[] = $prefs['min_age'];
            $params[] = $prefs['max_age'];
        }
        if (!empty($prefs['state'])) {
            $states = explode(',', $prefs['state']);
            $placeholders = implode(',', array_fill(0, count($states), '?'));
            $query .= " AND u.state IN ($placeholders)";
            $params = array_merge($params, $states);
        }
    }
    
    $query .= " ORDER BY u.is_premium DESC, u.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * CSRF Token Generation
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get profile picture URL
 */
function getProfilePic($profilePic, $gender = null) {
    if ($profilePic && file_exists(ROOT_PATH . $profilePic)) {
        return SITE_URL . '/' . $profilePic;
    }
    // Gender-based placeholder
    if ($gender && in_array($gender, ['Male', 'Female'])) {
        $placeholder = 'default-' . strtolower($gender);
    } else {
        $placeholder = 'default-male'; // neutral fallback
    }
    // Try PNG first, then SVG
    if (file_exists(ROOT_PATH . 'assets/images/' . $placeholder . '.png') && filesize(ROOT_PATH . 'assets/images/' . $placeholder . '.png') > 0) {
        return SITE_URL . '/assets/images/' . $placeholder . '.png';
    }
    return SITE_URL . '/assets/images/' . $placeholder . '.svg';
}

/**
 * Time ago format
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Send email using SMTP
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    try {
        // Use PHPMailer if available, otherwise fallback to mail()
        $usePHPMailer = class_exists('PHPMailer\PHPMailer\PHPMailer');
        
        if ($usePHPMailer) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return true;
        } else {
            // Fallback to PHP mail() function
            $headers = [
                'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: PHP/' . phpversion()
            ];
            
            if ($isHtml) {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            }
            
            return mail($to, $subject, $body, implode("\r\n", $headers));
        }
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}
