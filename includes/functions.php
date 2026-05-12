<?php
/**
 * Core Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

// Load Composer autoloader (for PHPMailer etc.)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

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
 * Encode profile ID to non-predictable hash (reversible)
 */
function encodeProfileId($id) {
    $salt = 'matrimonial-secret-salt-2024-secure';
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $base62 = '';

    // XOR with salt to obscure the ID
    $obfuscated = $id ^ crc32($salt);

    // Convert to base62
    $base = 62;
    while ($obfuscated > 0) {
        $remainder = $obfuscated % $base;
        $base62 = $alphabet[$remainder] . $base62;
        $obfuscated = floor($obfuscated / $base);
    }

    // Ensure minimum length of 8
    while (strlen($base62) < 8) {
        $base62 = $alphabet[0] . $base62;
    }

    // Add checksum
    $checksum = substr(md5($salt . $base62), 0, 2);
    return $base62 . $checksum;
}

/**
 * Decode profile ID from hash (reversible)
 */
function decodeProfileId($hash) {
    $salt = 'matrimonial-secret-salt-2024-secure';
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    if (strlen($hash) < 10) {
        return null;
    }

    // Extract base62 and checksum
    $base62 = substr($hash, 0, -2);
    $checksum = substr($hash, -2);

    // Verify checksum
    if (substr(md5($salt . $base62), 0, 2) !== $checksum) {
        return null;
    }

    // Convert from base62
    $base = 62;
    $obfuscated = 0;
    for ($i = 0; $i < strlen($base62); $i++) {
        $char = $base62[$i];
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            return null;
        }
        $obfuscated = $obfuscated * $base + $pos;
    }

    // Reverse XOR to get original ID
    $id = $obfuscated ^ crc32($salt);

    return $id > 0 ? $id : null;
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

    // Check if there's an existing OTP for this identifier and purpose
    $stmt = $pdo->prepare("SELECT id, resend_count, expires_at FROM otp_verifications WHERE identifier = ? AND purpose = ? AND is_verified = 0 AND expires_at > NOW()");
    $stmt->execute([$identifier, $purpose]);
    $existingOTP = $stmt->fetch();

    // If existing OTP found and resend count is already 2, don't allow resend
    if ($existingOTP && $existingOTP['resend_count'] >= 2) {
        return false;
    }

    if ($existingOTP) {
        // Update existing OTP with new code and increment resend count
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $stmt = $pdo->prepare("UPDATE otp_verifications SET otp = ?, expires_at = ?, resend_count = resend_count + 1, attempts = 0 WHERE id = ?");
        return $stmt->execute([$otp, $expiresAt, $existingOTP['id']]);
    } else {
        // Save new OTP
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $stmt = $pdo->prepare("INSERT INTO otp_verifications (identifier, otp, purpose, expires_at, resend_count) VALUES (?, ?, ?, ?, 0)");
        return $stmt->execute([$identifier, $otp, $purpose, $expiresAt]);
    }
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

function getOTPResendCount($identifier, $purpose = 'registration') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT resend_count FROM otp_verifications WHERE identifier = ? AND purpose = ? AND expires_at > NOW() AND is_verified = 0");
    $stmt->execute([$identifier, $purpose]);
    $record = $stmt->fetch();
    return $record ? $record['resend_count'] : 0;
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
 * Upload an ID document (PDF only). Replaces any existing document for the user.
 */
function uploadIdDocument($file, $userId) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error.'];
    }

    if ($file['size'] > MAX_PHOTO_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum 5MB allowed.'];
    }

    // Validate PDF strictly: extension + MIME (browser) + magic bytes
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['success' => false, 'message' => 'Invalid file type. Only PDF files are allowed.'];
    }

    $mime = $file['type'] ?? '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected) $mime = $detected;
        }
    }
    if ($mime !== 'application/pdf') {
        return ['success' => false, 'message' => 'Invalid file type. Only PDF files are allowed.'];
    }

    // Magic bytes check
    $fh = @fopen($file['tmp_name'], 'rb');
    if ($fh) {
        $head = fread($fh, 5);
        fclose($fh);
        if (strpos($head, '%PDF-') !== 0) {
            return ['success' => false, 'message' => 'Invalid PDF file.'];
        }
    }

    $userDir = UPLOADS_PATH . 'documents' . DIRECTORY_SEPARATOR . $userId;
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }

    // Remove any existing PDFs for this user (replace behavior)
    foreach (glob($userDir . DIRECTORY_SEPARATOR . '*.pdf') ?: [] as $old) {
        @unlink($old);
    }

    $filename = 'iddoc_' . uniqid() . '.pdf';
    $filepath = $userDir . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'path' => 'uploads/documents/' . $userId . '/' . $filename,
            'filename' => $filename
        ];
    }

    return ['success' => false, 'message' => 'Failed to save file.'];
}

/**
 * Upload Address Proof document (PDF only)
 */
function uploadAddressProof($file, $userId) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error.'];
    }

    if ($file['size'] > MAX_PHOTO_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum 5MB allowed.'];
    }

    // Validate PDF strictly: extension + MIME (browser) + magic bytes
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['success' => false, 'message' => 'Invalid file type. Only PDF files are allowed.'];
    }

    $mime = $file['type'] ?? '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if ($detected) $mime = $detected;
        }
    }
    if ($mime !== 'application/pdf') {
        return ['success' => false, 'message' => 'Invalid file type. Only PDF files are allowed.'];
    }

    // Magic bytes check
    $fh = @fopen($file['tmp_name'], 'rb');
    if ($fh) {
        $head = fread($fh, 5);
        fclose($fh);
        if (strpos($head, '%PDF-') !== 0) {
            return ['success' => false, 'message' => 'Invalid PDF file.'];
        }
    }

    $userDir = UPLOADS_PATH . 'address_proof' . DIRECTORY_SEPARATOR . $userId;
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }

    // Remove any existing PDFs for this user (replace behavior)
    foreach (glob($userDir . DIRECTORY_SEPARATOR . '*.pdf') ?: [] as $old) {
        @unlink($old);
    }

    $filename = 'addressproof_' . uniqid() . '.pdf';
    $filepath = $userDir . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'path' => 'uploads/address_proof/' . $userId . '/' . $filename,
            'filename' => $filename
        ];
    }

    return ['success' => false, 'message' => 'Failed to save file.'];
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

function getTopNotifications($userId, $limit = 3) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
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
 * Check if a viewer can access a specific privacy-controlled field of a profile.
 * 
 * Access Rules:
 * - Premium viewers can see all content from non-premium profiles (bypass their privacy).
 * - Non-premium viewers must respect non-premium profile privacy settings.
 * - ALL viewers must respect premium profile privacy settings.
 * - Owner can always see their own content.
 * 
 * @param string $privacySetting  The privacy setting value ('everyone', 'connected')
 * @param bool   $isOwner         Whether the viewer is the profile owner
 * @param bool   $isConnected     Whether viewer and profile are connected
 * @param bool   $viewerIsPremium Whether the viewer is a premium user
 * @param bool   $profileIsPremium Whether the profile being viewed is a premium user
 * @return bool
 */
function canViewContent($privacySetting, $isOwner, $isConnected, $viewerIsPremium, $profileIsPremium) {
    if ($isOwner) return true;
    
    // If profile is NOT premium, premium viewers bypass their privacy settings
    if (!$profileIsPremium && $viewerIsPremium) return true;
    
    // Enforce privacy settings (for premium profiles always, for non-premium when viewer is non-premium)
    switch ($privacySetting) {
        case 'everyone':
            return true;
        case 'connected':
            return $isConnected;
        default:
            return false;
    }
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

    // Tier-based sorting:
    // Tier 1: Verified + Premium + Photo
    // Tier 2: Verified + Premium (no photo)
    // Tier 3: Premium (not verified or no photo)
    // Tier 4: All other profiles
    // Within each tier, sort by age descending
    $query .= " ORDER BY
        CASE
            WHEN u.is_verified = 1 AND u.is_premium = 1 AND u.profile_pic IS NOT NULL AND u.profile_pic != '' THEN 1
            WHEN u.is_verified = 1 AND u.is_premium = 1 THEN 2
            WHEN u.is_premium = 1 THEN 3
            ELSE 4
        END ASC,
        TIMESTAMPDIFF(YEAR, u.dob, CURDATE()) DESC
        LIMIT ? OFFSET ?";
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
        
        error_log("sendEmail: PHPMailer available=" . ($usePHPMailer ? 'yes' : 'no') . ", to=$to, host=" . SMTP_HOST . ", user=" . SMTP_USERNAME);
        
        if ($usePHPMailer) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = (int)SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
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
            error_log("sendEmail: PHPMailer not found. Check vendor/autoload.php exists.");
            // Fallback to PHP mail() function
            $headers = [
                'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
                'MIME-Version: 1.0',
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
