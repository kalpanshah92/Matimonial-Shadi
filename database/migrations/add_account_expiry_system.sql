-- =====================================================================
-- Migration: Add Account Expiry System
-- - Adds account_status and expiry_date to users table
-- - Creates admin_audit_log table for tracking admin changes
-- - Sets default values for existing users (active + 2 years from now)
-- =====================================================================

SET @db := DATABASE();

-- =====================================================================
-- 1. Add account_status column to users table
-- =====================================================================
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'account_status'
);

SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN account_status VARCHAR(20) NOT NULL DEFAULT "active"',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 2. Add expiry_date column to users table
-- =====================================================================
SET @col_exists2 := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'expiry_date'
);

SET @sql2 := IF(@col_exists2 = 0,
    'ALTER TABLE users ADD COLUMN expiry_date DATE NULL',
    'SELECT 1');
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- 3. Create admin_audit_log table
-- =====================================================================
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT "e.g., extend_expiry, update_status",
    old_value TEXT NULL,
    new_value TEXT NULL,
    details JSON NULL COMMENT "Additional context for the change",
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_admin_id (admin_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 4. Backfill existing users: set expiry_date to 2 years from now if NULL
-- =====================================================================
UPDATE users 
SET expiry_date = DATE_ADD(CURDATE(), INTERVAL 2 YEAR)
WHERE expiry_date IS NULL;

-- Set all existing users to active status (unless already set)
UPDATE users 
SET account_status = 'active'
WHERE account_status IS NULL OR account_status = '';

-- =====================================================================
-- 5. Update schema.sql reference (comment for documentation)
-- =====================================================================
-- Users table now includes:
--   account_status VARCHAR(20) DEFAULT 'active' -- active, expired, suspended
--   expiry_date DATE -- Account expiration date (reg_date + 2 years)
