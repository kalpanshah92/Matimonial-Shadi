-- ============================================================================
-- Registration payment + coupon system
-- Run order: AFTER schema.sql
-- ============================================================================

-- Coupons defined by super admins
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    discount_percent TINYINT UNSIGNED NOT NULL,
    -- Optional caps
    max_redemptions INT UNSIGNED NULL COMMENT 'NULL = unlimited',
    redemptions_count INT UNSIGNED NOT NULL DEFAULT 0,
    -- Optional validity window
    valid_from DATE NULL,
    valid_until DATE NULL,
    -- Optional gender restriction so a coupon can be M/F only or both
    gender_restriction ENUM('any','Male','Female') NOT NULL DEFAULT 'any',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by_admin INT NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_coupon_code (code),
    INDEX idx_coupons_active (is_active, valid_from, valid_until),
    CHECK (discount_percent BETWEEN 1 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per registration payment (Razorpay OR 100%-coupon bypass OR fully-free plan)
CREATE TABLE IF NOT EXISTS registration_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    original_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    final_amount DECIMAL(10,2) NOT NULL,
    coupon_id INT NULL,
    coupon_code VARCHAR(40) NULL COMMENT 'Snapshot of the code at time of redemption',
    payment_method ENUM('razorpay','coupon_bypass','manual') NOT NULL DEFAULT 'razorpay',
    razorpay_order_id VARCHAR(80) NULL,
    razorpay_payment_id VARCHAR(80) NULL,
    razorpay_signature VARCHAR(255) NULL,
    status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    UNIQUE KEY uniq_razorpay_payment (razorpay_payment_id),
    INDEX idx_regpay_user (user_id),
    INDEX idx_regpay_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES plans(id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Flag on users so login can verify the registration fee was settled before admin review.
-- 'not_started' = newly verified email, hasn't paid
-- 'completed'   = paid via Razorpay
-- 'bypassed'    = 100% coupon (no Razorpay charge)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS registration_payment_status
        ENUM('not_started','completed','bypassed') NOT NULL DEFAULT 'not_started'
        AFTER status;

-- Older MySQL versions don't support ADD COLUMN IF NOT EXISTS. Use the fallback below if needed:
--   SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
--                WHERE TABLE_SCHEMA = DATABASE()
--                  AND TABLE_NAME   = 'users'
--                  AND COLUMN_NAME  = 'registration_payment_status');
--   SET @ddl := IF(@col = 0,
--                  'ALTER TABLE users ADD COLUMN registration_payment_status
--                   ENUM(''not_started'',''completed'',''bypassed'') NOT NULL DEFAULT ''not_started'' AFTER status',
--                  'SELECT 1');
--   PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;

-- Mark every existing user as completed so the registration-payment gate doesn't lock them out.
UPDATE users SET registration_payment_status = 'completed' WHERE registration_payment_status = 'not_started';
