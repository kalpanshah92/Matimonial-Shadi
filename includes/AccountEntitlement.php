<?php
/**
 * Account Entitlement System
 * 
 * Centralized permission and account status management.
 * Handles: account status, expiry, subscription validity, feature access.
 * 
 * Usage:
 *   $entitlement = AccountEntitlement::forUser($userId);
 *   if ($entitlement->canAccess('search')) { ... }
 *   if ($entitlement->isExpired()) { ... }
 */

require_once __DIR__ . '/functions.php';

class AccountEntitlement
{
    private int $userId;
    private ?array $userData = null;
    private ?DateTime $expiryDate = null;
    private ?DateTime $now = null;
    
    // Account status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SUSPENDED = 'suspended';
    
    // Feature constants
    public const FEATURE_SEARCH = 'search';
    public const FEATURE_MATCHES = 'matches';
    public const FEATURE_CHAT = 'chat';
    public const FEATURE_DISCOVERY = 'discovery';
    public const FEATURE_PROFILE_VIEW = 'profile_view';
    public const FEATURE_EDIT_PROFILE = 'edit_profile';
    public const FEATURE_DASHBOARD = 'dashboard';
    public const FEATURE_SETTINGS = 'settings';
    
    // Grace period in days (configurable)
    public const GRACE_PERIOD_DAYS = 7;
    
    /**
     * Factory method to get entitlement for a user
     */
    public static function forUser(int $userId): self
    {
        return new self($userId);
    }
    
    /**
     * Constructor
     */
    private function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->now = new DateTime();
        $this->loadUserData();
    }
    
    /**
     * Load user data from database
     */
    private function loadUserData(): void
    {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, account_status, expiry_date, created_at FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $this->userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($this->userData && $this->userData['expiry_date']) {
            $this->expiryDate = new DateTime($this->userData['expiry_date']);
        }
    }
    
    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        if (!$this->userData) {
            return false;
        }
        
        // Must not be suspended
        if ($this->userData['account_status'] === self::STATUS_SUSPENDED) {
            return false;
        }
        
        // Must not be expired (or in grace period we still consider "active" with warning)
        if ($this->isExpired() && !$this->isInGracePeriod()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if account is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expiryDate) {
            return false;
        }
        
        return $this->now > $this->expiryDate;
    }
    
    /**
     * Check if account is in grace period (expired but within grace days)
     */
    public function isInGracePeriod(): bool
    {
        if (!$this->isExpired() || !$this->expiryDate) {
            return false;
        }
        
        $graceEnd = clone $this->expiryDate;
        $graceEnd->modify('+' . self::GRACE_PERIOD_DAYS . ' days');
        
        return $this->now <= $graceEnd;
    }
    
    /**
     * Check if account is suspended
     */
    public function isSuspended(): bool
    {
        return $this->userData && $this->userData['account_status'] === self::STATUS_SUSPENDED;
    }
    
    /**
     * Get days until expiry (negative if expired)
     */
    public function daysUntilExpiry(): ?int
    {
        if (!$this->expiryDate) {
            return null;
        }
        
        $diff = $this->now->diff($this->expiryDate);
        $days = (int) $diff->format('%r%a');
        
        return $days;
    }
    
    /**
     * Get expiry date string
     */
    public function getExpiryDate(): ?string
    {
        return $this->expiryDate ? $this->expiryDate->format('Y-m-d') : null;
    }
    
    /**
     * Get formatted expiry date for display
     */
    public function getFormattedExpiryDate(): ?string
    {
        return $this->expiryDate ? $this->expiryDate->format('d M Y') : null;
    }
    
    /**
     * Get grace period end date
     */
    public function getGracePeriodEndDate(): ?string
    {
        if (!$this->expiryDate) {
            return null;
        }
        
        $graceEnd = clone $this->expiryDate;
        $graceEnd->modify('+' . self::GRACE_PERIOD_DAYS . ' days');
        
        return $graceEnd->format('d M Y');
    }
    
    /**
     * Main access control method - check if user can access a feature
     */
    public function canAccess(string $feature): bool
    {
        // Always allow these features regardless of expiry
        $alwaysAllowed = [
            self::FEATURE_DASHBOARD,
            self::FEATURE_SETTINGS,
            self::FEATURE_EDIT_PROFILE,
            self::FEATURE_PROFILE_VIEW,
        ];
        
        if (in_array($feature, $alwaysAllowed, true)) {
            // But still check for suspended
            return !$this->isSuspended();
        }
        
        // For other features, require active (non-expired) account
        return $this->isActive() && !$this->isExpired();
    }
    
    /**
     * Check specific feature access with detailed reasons
     */
    public function getAccessDetails(string $feature): array
    {
        $result = [
            'allowed' => false,
            'reason' => null,
            'expired' => $this->isExpired(),
            'suspended' => $this->isSuspended(),
            'in_grace_period' => $this->isInGracePeriod(),
            'days_remaining' => $this->daysUntilExpiry(),
        ];
        
        if ($this->isSuspended()) {
            $result['reason'] = 'Your account has been suspended. Please contact support.';
            return $result;
        }
        
        // Always allowed features
        $alwaysAllowed = [
            self::FEATURE_DASHBOARD,
            self::FEATURE_SETTINGS,
            self::FEATURE_EDIT_PROFILE,
            self::FEATURE_PROFILE_VIEW,
        ];
        
        if (in_array($feature, $alwaysAllowed, true)) {
            $result['allowed'] = true;
            return $result;
        }
        
        // Expired check
        if ($this->isExpired()) {
            if ($this->isInGracePeriod()) {
                $result['reason'] = 'Your account has expired. Please renew your subscription to continue accessing this feature. Grace period ends on ' . $this->getGracePeriodEndDate() . '.';
            } else {
                $result['reason'] = 'Your account has expired. Please renew your subscription to access this feature.';
            }
            return $result;
        }
        
        $result['allowed'] = true;
        return $result;
    }
    
    /**
     * Update account status (used by cron job or admin)
     */
    public function updateStatus(string $newStatus): bool
    {
        $validStatuses = [self::STATUS_ACTIVE, self::STATUS_EXPIRED, self::STATUS_SUSPENDED];
        
        if (!in_array($newStatus, $validStatuses, true)) {
            return false;
        }
        
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE id = ?");
        return $stmt->execute([$newStatus, $this->userId]);
    }
    
    /**
     * Extend expiry date (used by admin or renewal flow)
     */
    public function extendExpiry(int $days, ?int $adminId = null): bool
    {
        $pdo = getDBConnection();
        
        // Calculate new expiry date
        if ($this->isExpired()) {
            // If expired, start from today
            $newExpiry = new DateTime();
        } else {
            // If active, extend from current expiry
            $newExpiry = clone $this->expiryDate;
        }
        $newExpiry->modify("+{$days} days");
        
        $oldExpiry = $this->getExpiryDate();
        $newExpiryStr = $newExpiry->format('Y-m-d');
        
        // Update user
        $stmt = $pdo->prepare("UPDATE users SET expiry_date = ?, account_status = ? WHERE id = ?");
        $success = $stmt->execute([$newExpiryStr, self::STATUS_ACTIVE, $this->userId]);
        
        // Log to audit log if admin performed
        if ($success && $adminId) {
            $this->logAdminAction($adminId, 'extend_expiry', $oldExpiry, $newExpiryStr, [
                'days_added' => $days,
                'previous_status' => $this->userData['account_status'] ?? 'unknown'
            ]);
        }
        
        // Refresh local data
        $this->expiryDate = $newExpiry;
        $this->userData['expiry_date'] = $newExpiryStr;
        $this->userData['account_status'] = self::STATUS_ACTIVE;
        
        return $success;
    }
    
    /**
     * Set specific expiry date (used by admin)
     */
    public function setExpiryDate(string $date, ?int $adminId = null): bool
    {
        $pdo = getDBConnection();
        
        $oldExpiry = $this->getExpiryDate();
        
        // Determine status based on new date
        $newExpiry = new DateTime($date);
        $now = new DateTime();
        $newStatus = ($newExpiry < $now) ? self::STATUS_EXPIRED : self::STATUS_ACTIVE;
        
        $stmt = $pdo->prepare("UPDATE users SET expiry_date = ?, account_status = ? WHERE id = ?");
        $success = $stmt->execute([$date, $newStatus, $this->userId]);
        
        if ($success && $adminId) {
            $this->logAdminAction($adminId, 'set_expiry', $oldExpiry, $date, [
                'new_status' => $newStatus
            ]);
        }
        
        // Refresh local data
        $this->expiryDate = $newExpiry;
        $this->userData['expiry_date'] = $date;
        $this->userData['account_status'] = $newStatus;
        
        return $success;
    }
    
    /**
     * Log admin action to audit log
     */
    private function logAdminAction(int $adminId, string $action, ?string $oldValue, string $newValue, array $details = []): void
    {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO admin_audit_log (admin_id, user_id, action, old_value, new_value, details) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $adminId,
            $this->userId,
            $action,
            $oldValue,
            $newValue,
            json_encode($details)
        ]);
    }
    
    /**
     * Get audit log for this user
     */
    public function getAuditLog(int $limit = 50): array
    {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "SELECT aal.*, a.name as admin_name 
             FROM admin_audit_log aal 
             LEFT JOIN admin_users a ON aal.admin_id = a.id 
             WHERE aal.user_id = ? 
             ORDER BY aal.created_at DESC 
             LIMIT ?"
        );
        $stmt->execute([$this->userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Helper function to get entitlement for current logged-in user
 */
function currentUserEntitlement(): ?AccountEntitlement
{
    if (!isLoggedIn() || empty($_SESSION['user_id'])) {
        return null;
    }
    return AccountEntitlement::forUser((int)$_SESSION['user_id']);
}

/**
 * Enforce access control - redirect or show error if not allowed
 * 
 * @param string $feature Feature to check
 * @param string $redirectUrl Where to redirect if denied
 * @param bool $returnOnly If true, return boolean instead of redirecting
 */
function requireAccountAccess(string $feature, string $redirectUrl = '/dashboard.php', bool $returnOnly = false): bool
{
    $entitlement = currentUserEntitlement();
    
    if (!$entitlement) {
        if (!$returnOnly) {
            setFlash('error', 'Please log in to access this feature.');
            redirect(SITE_URL . '/login.php');
        }
        return false;
    }
    
    $access = $entitlement->getAccessDetails($feature);
    
    if (!$access['allowed']) {
        if (!$returnOnly) {
            $_SESSION['account_expired_message'] = $access['reason'] ?? 'Access denied.';
            redirect(SITE_URL . $redirectUrl);
        }
        return false;
    }
    
    // If expiring soon (within 30 days), set warning
    if ($access['days_remaining'] !== null && $access['days_remaining'] <= 30 && $access['days_remaining'] > 0) {
        $_SESSION['expiry_warning'] = "Your account will expire in {$access['days_remaining']} days. Please renew to avoid interruption.";
    }
    
    return true;
}
