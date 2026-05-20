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

// Start session if not started (with hardened cookie params: F-08, F-14)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
    }
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.use_strict_mode', '1');
    session_start();
}

/**
 * Get client IP (best-effort; trust only first hop from a known proxy in prod).
 */
function clientIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

/**
 * F-07 Generic DB-backed rate limiter. Returns true when under the cap.
 * Each successful check records a hit. Call BEFORE the protected action.
 */
function rateLimit($key, $maxRequests, $windowSeconds) {
    try {
        $pdo = getDBConnection();
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL ? SECOND)")
            ->execute([(int)$windowSeconds]);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE rl_key = ? AND created_at > (NOW() - INTERVAL ? SECOND)");
        $stmt->execute([$key, (int)$windowSeconds]);
        if ((int)$stmt->fetchColumn() >= $maxRequests) {
            return false;
        }
        $pdo->prepare("INSERT INTO rate_limits (rl_key) VALUES (?)")->execute([$key]);
        return true;
    } catch (Throwable $e) {
        error_log('rateLimit error: ' . $e->getMessage());
        return true; // fail-open to avoid DoS on infra error; alarms should catch this
    }
}

/**
 * F-07 / F-16 Track login attempts and check lockout.
 */
function recordLoginAttempt($identifier, $success, $scope = 'user') {
    try {
        $pdo = getDBConnection();
        $pdo->prepare("INSERT INTO login_attempts (identifier, ip_address, scope, success) VALUES (?, ?, ?, ?)")
            ->execute([substr((string)$identifier, 0, 191), clientIp(), $scope, $success ? 1 : 0]);
    } catch (Throwable $e) { error_log('recordLoginAttempt: '.$e->getMessage()); }
}

function isLoginLocked($identifier, $scope = 'user') {
    try {
        $pdo = getDBConnection();
        // 10 failed attempts in 15 min from same identifier OR same IP => locked
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE scope = ? AND success = 0
               AND created_at > (NOW() - INTERVAL 15 MINUTE)
               AND (identifier = ? OR ip_address = ?)"
        );
        $stmt->execute([$scope, substr((string)$identifier, 0, 191), clientIp()]);
        return ((int)$stmt->fetchColumn()) >= 10;
    } catch (Throwable $e) { return false; }
}

/**
 * F-10 Restrict redirects to local paths only.
 */
function safeRedirectTarget($url, $fallback = '/dashboard.php') {
    if (!is_string($url) || $url === '') return SITE_URL . $fallback;
    // Reject scheme://, protocol-relative, backslash tricks
    if (preg_match('~^([a-z][a-z0-9+\-.]*:|//|\\\\)~i', $url)) return SITE_URL . $fallback;
    // Only allow path/query/fragment
    if ($url[0] !== '/') $url = '/' . $url;
    if (!preg_match('~^/[A-Za-z0-9_\-./?&=%#]*$~', $url)) return SITE_URL . $fallback;
    return SITE_URL . $url;
}

/**
 * F-06 Enforce CSRF on JSON API endpoints. Looks for token in header or POST body.
 */
function requireCSRF() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!verifyCSRFToken($token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

/**
 * F-09 Remember-me: create selector/validator cookie scheme.
 */
function issueRememberToken($userId) {
    $pdo = getDBConnection();
    $selector  = bin2hex(random_bytes(12));   // 24 hex chars
    $validator = bin2hex(random_bytes(32));   // 64 hex chars
    $hash      = hash('sha256', $validator);
    $expires   = date('Y-m-d H:i:s', time() + 30*24*3600);
    $pdo->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$userId, $selector, $hash, $expires]);
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie('remember_me', $selector . ':' . $validator, [
        'expires'  => time() + 30*24*3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function consumeRememberToken() {
    if (empty($_COOKIE['remember_me']) || isLoggedIn()) return false;
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) !== 2) return false;
    [$selector, $validator] = $parts;
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW() LIMIT 1");
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        if (!$row) return false;
        if (!hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            // Possible token theft: invalidate all tokens for that user
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$row['user_id']]);
            return false;
        }
        // Rotate token
        $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['id']]);
        $stmt = $pdo->prepare("SELECT id, status, is_active FROM users WHERE id = ?");
        $stmt->execute([$row['user_id']]);
        $user = $stmt->fetch();
        if (!$user || !$user['is_active'] || $user['status'] !== 'approved') return false;
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        issueRememberToken($user['id']);
        return true;
    } catch (Throwable $e) { return false; }
}

function clearRememberToken() {
    if (!empty($_COOKIE['remember_me'])) {
        $parts = explode(':', $_COOKIE['remember_me'], 2);
        if (count($parts) === 2) {
            try {
                getDBConnection()->prepare("DELETE FROM remember_tokens WHERE selector = ?")
                    ->execute([$parts[0]]);
            } catch (Throwable $e) {}
        }
        setcookie('remember_me', '', time() - 3600, '/');
    }
}

// Attempt auto-login from remember cookie at every request load
if (!isLoggedIn()) { @consumeRememberToken(); }


/**
 * Sanitize input data
 */
function sanitize($data) {
    if ($data === null) {
        return '';
    }
    $data = trim($data ?? '');
    $data = stripslashes($data ?? '');
    $data = htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Encode profile ID to non-predictable hash (reversible)
 */
function _profileIdSalt() {
    static $salt = null;
    if ($salt !== null) return $salt;
    $env = getenv('PROFILE_ID_SALT');
    if ($env && strlen($env) >= 16) { $salt = $env; return $salt; }
    if (defined('PROFILE_ID_SALT') && strlen(PROFILE_ID_SALT) >= 16) {
        $salt = PROFILE_ID_SALT; return $salt;
    }
    // Last-resort fallback (still better than a public constant): derive from DB creds + site URL
    $salt = hash('sha256', 'profile-id|' . SITE_URL . '|' . DB_NAME . '|' . DB_USER);
    return $salt;
}

function encodeProfileId($id) {
    $salt = _profileIdSalt();
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
    $salt = _profileIdSalt();
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
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error'];
    }
    if ($file['size'] > MAX_PHOTO_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum 5MB allowed.'];
    }

    // F-03 Validate by content (finfo), NOT by client-provided Content-Type / extension
    $real = null;
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        if ($f) { $real = finfo_file($f, $file['tmp_name']); finfo_close($f); }
    }
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$real || !isset($map[$real])) {
        return ['success' => false, 'message' => 'Invalid image. Only JPG, PNG, or WebP allowed.'];
    }
    if (!in_array($real, ALLOWED_PHOTO_TYPES)) {
        return ['success' => false, 'message' => 'Invalid image type.'];
    }

    // Re-decode and re-encode to strip EXIF, polyglots, embedded scripts
    if (!function_exists('imagecreatefromstring')) {
        return ['success' => false, 'message' => 'Server image library unavailable.'];
    }
    $raw = @file_get_contents($file['tmp_name']);
    if ($raw === false) return ['success' => false, 'message' => 'Read failed.'];
    // Suppress libgd warnings, validate result
    $img = @imagecreatefromstring($raw);
    if (!$img) return ['success' => false, 'message' => 'Corrupt or unsupported image.'];

    // Cap dimensions to mitigate decompression bombs
    $w = imagesx($img); $h = imagesy($img);
    if ($w > 4096 || $h > 4096) {
        $scale = min(4096 / $w, 4096 / $h);
        $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img); $img = $resized;
    }

    $userDir = UPLOADS_PATH . 'photos' . DIRECTORY_SEPARATOR . $userId;
    if (!is_dir($userDir)) { mkdir($userDir, 0750, true); }

    // Opaque, unguessable filename; safe fixed extension; force JPEG output for uniformity
    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $filepath = $userDir . DIRECTORY_SEPARATOR . $filename;
    $ok = imagejpeg($img, $filepath, 85);
    imagedestroy($img);
    if (!$ok) return ['success' => false, 'message' => 'Failed to save file'];
    @chmod($filepath, 0640);

    return [
        'success' => true,
        'path' => 'uploads/photos/' . $userId . '/' . $filename,
        'filename' => $filename,
        'access_token' => bin2hex(random_bytes(16)),
    ];
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
        // F-15 Use encoded id; for non-premium recipients front-end can hide the link.
        createNotification($visitedId, 'visit', 'Profile Viewed', 'Someone viewed your profile', 'profile.php?id=' . encodeProfileId($visitorId));
    }
}

/**
 * F-13 Enforce a daily cap on distinct profile views per viewer to deter scraping.
 * Returns true when viewer is allowed to view another *new* profile today.
 * Premium users get a higher cap. Already-seen profiles within 24h do not count.
 */
function canViewAnotherProfile($visitorId, $targetId) {
    if ($visitorId === $targetId) return true;
    try {
        $pdo = getDBConnection();
        // Already viewed today? -> always allowed (revisit)
        $stmt = $pdo->prepare(
            "SELECT 1 FROM profile_visits WHERE visitor_id = ? AND visited_id = ?
             AND visited_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1"
        );
        $stmt->execute([$visitorId, $targetId]);
        if ($stmt->fetch()) return true;

        $cap = isPremium($visitorId) ? 500 : 100;
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT visited_id) FROM profile_visits
             WHERE visitor_id = ? AND visited_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute([$visitorId]);
        return ((int)$stmt->fetchColumn()) < $cap;
    } catch (Throwable $e) { return true; }
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
 * Registration payment helpers
 * ----------------------------------------------------------------------------
 * Pricing is driven entirely by the `plans` table. We pick the plan whose
 * `name` contains 'Male' or 'Female' (mirrors the admin upgrade modal). If no
 * gender-specific plan is found we fall back to the cheapest active plan.
 */
function getRegistrationPlanForGender($gender) {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM plans WHERE is_active = 1 ORDER BY price ASC");
    $plans = $stmt->fetchAll();
    if (!$plans) return null;
    $genderLc = strtolower((string)$gender);
    foreach ($plans as $p) {
        $isFemalePlan = stripos($p['name'], 'Female') !== false;
        $isMalePlan   = !$isFemalePlan && stripos($p['name'], 'Male') !== false;
        if ($genderLc === 'female' && $isFemalePlan) return $p;
        if ($genderLc === 'male'   && $isMalePlan)   return $p;
    }
    return $plans[0];
}

/**
 * Server-side coupon validation. Returns ['ok'=>true,'coupon'=>row,'discount_amount'=>x,'final_amount'=>y]
 * or ['ok'=>false,'message'=>...]. NEVER trust a client-supplied discount.
 */
function validateCoupon($code, $userGender, $originalAmount) {
    $code = strtoupper(trim((string)$code));
    if ($code === '') return ['ok' => false, 'message' => 'Enter a coupon code.'];

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    if (!$coupon)              return ['ok' => false, 'message' => 'Invalid coupon code.'];
    if (!$coupon['is_active']) return ['ok' => false, 'message' => 'This coupon is no longer active.'];

    $today = date('Y-m-d');
    if (!empty($coupon['valid_from'])  && $today < $coupon['valid_from'])  return ['ok' => false, 'message' => 'Coupon not yet valid.'];
    if (!empty($coupon['valid_until']) && $today > $coupon['valid_until']) return ['ok' => false, 'message' => 'Coupon has expired.'];

    if (!empty($coupon['max_redemptions'])
        && (int)$coupon['redemptions_count'] >= (int)$coupon['max_redemptions']) {
        return ['ok' => false, 'message' => 'Coupon redemption limit reached.'];
    }

    if ($coupon['gender_restriction'] !== 'any'
        && strcasecmp($coupon['gender_restriction'], (string)$userGender) !== 0) {
        return ['ok' => false, 'message' => 'Coupon not applicable to your profile.'];
    }

    $percent = max(0, min(100, (int)$coupon['discount_percent']));
    $original = round((float)$originalAmount, 2);
    $discount = round($original * $percent / 100, 2);
    $final    = round(max(0, $original - $discount), 2);

    return [
        'ok'              => true,
        'coupon'          => $coupon,
        'discount_amount' => $discount,
        'final_amount'    => $final,
    ];
}

/**
 * Returns true when Razorpay is fully configured at runtime.
 */
function isRazorpayConfigured() {
    return defined('RAZORPAY_KEY_ID') && defined('RAZORPAY_KEY_SECRET')
        && RAZORPAY_KEY_ID !== '' && RAZORPAY_KEY_SECRET !== ''
        && RAZORPAY_KEY_ID !== 'your_razorpay_key_id'
        && RAZORPAY_KEY_SECRET !== 'your_razorpay_key_secret';
}

/**
 * Normalise a name part: trim, collapse internal whitespace, decode HTML entities.
 * Preserves Unicode letters, apostrophes and hyphens for international names
 * (e.g. "D'Souza", "Al-Rashid", "Mary-Jane").
 */
function normalizeNamePart($value) {
    $value = (string)$value;
    // Strip control characters but keep Unicode letters
    $value = preg_replace('/[\p{C}]+/u', '', $value) ?? '';
    // Collapse all whitespace runs to single spaces
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

/**
 * Validate a single name part.
 *  - $required=true means non-empty after normalization.
 *  - Allows Unicode letters, spaces, apostrophes and hyphens.
 *  - Length: 1-60 chars.
 *  - First character must be a letter.
 *
 * Returns null if valid, or an error message string if invalid.
 */
function validateNamePart($value, $fieldLabel, $required = true, $maxLen = 60) {
    $value = normalizeNamePart($value);
    if ($value === '') {
        return $required ? "$fieldLabel is required." : null;
    }
    if (mb_strlen($value) > $maxLen) {
        return "$fieldLabel must be $maxLen characters or fewer.";
    }
    // First char letter; remaining letters / spaces / hyphens / apostrophes
    if (!preg_match("/^\p{L}[\p{L}\s'\-]*$/u", $value)) {
        return "$fieldLabel may contain only letters, spaces, hyphens and apostrophes.";
    }
    return null;
}

/**
 * Build a display name from a user/profile array. Accepts either:
 *   - a row containing first_name / middle_name / last_name, or
 *   - a row containing only `name` (legacy fallback).
 * Always returns a single trimmed string with no double spaces.
 */
function displayName($u) {
    if (is_array($u)) {
        $first  = isset($u['first_name'])  ? trim((string)$u['first_name'])  : '';
        $middle = isset($u['middle_name']) ? trim((string)$u['middle_name']) : '';
        $last   = isset($u['last_name'])   ? trim((string)$u['last_name'])   : '';
        if ($first !== '' || $last !== '') {
            $parts = array_filter([$first, $middle, $last], 'strlen');
            return implode(' ', $parts);
        }
        if (isset($u['name'])) {
            return trim(preg_replace('/\s+/u', ' ', (string)$u['name']));
        }
    }
    return '';
}

/**
 * Convenience: short greeting name (first name only) with safe fallback to legacy `name`.
 */
function firstNameOf($u) {
    if (is_array($u)) {
        if (!empty($u['first_name'])) return trim($u['first_name']);
        if (!empty($u['name'])) {
            $parts = preg_split('/\s+/u', trim($u['name']));
            return $parts[0] ?? '';
        }
    }
    return '';
}

/**
 * F-02 Build a proxied photo URL that enforces ACL via photo.php.
 * Accepts a stored relative path (e.g. 'uploads/photos/42/abc.jpg').
 * Falls back to a placeholder when the photo row cannot be resolved.
 */
function photoUrl($photoPath, $gender = null) {
    static $cache = [];
    if (empty($photoPath)) {
        return getPlaceholderPic($gender);
    }
    if (isset($cache[$photoPath])) return $cache[$photoPath];
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, access_token FROM photos WHERE photo_path = ? LIMIT 1");
        $stmt->execute([$photoPath]);
        $row = $stmt->fetch();
        if ($row) {
            $param = !empty($row['access_token'])
                ? ('t=' . urlencode($row['access_token']))
                : ('id=' . (int)$row['id']);
            $url = SITE_URL . '/photo.php?' . $param;
            $cache[$photoPath] = $url;
            return $url;
        }
    } catch (Throwable $e) {}
    // No DB row (legacy users.profile_pic strings): still route through proxy by path lookup
    return SITE_URL . '/photo.php?path=' . urlencode($photoPath);
}

/**
 * Gender-based placeholder helper (split out of getProfilePic for reuse).
 */
function getPlaceholderPic($gender = null) {
    $placeholder = ($gender && in_array($gender, ['Male', 'Female'])) ? 'default-' . strtolower($gender) : 'default-male';
    if (file_exists(ROOT_PATH . 'assets/images/' . $placeholder . '.png') && filesize(ROOT_PATH . 'assets/images/' . $placeholder . '.png') > 0) {
        return SITE_URL . '/assets/images/' . $placeholder . '.png';
    }
    return SITE_URL . '/assets/images/' . $placeholder . '.svg';
}

/**
 * Get profile picture URL
 */
function getProfilePic($profilePic, $gender = null) {
    if ($profilePic && file_exists(ROOT_PATH . $profilePic)) {
        return photoUrl($profilePic, $gender);
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
        
        // F-17 Avoid logging credentials. Log only non-sensitive context.
        error_log("sendEmail: PHPMailer=" . ($usePHPMailer ? 'yes' : 'no') . ", to_hash=" . substr(hash('sha256', $to), 0, 12) . ", host=" . SMTP_HOST);
        
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
