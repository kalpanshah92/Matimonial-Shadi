-- Security hardening migration
-- F-07: Rate limiting infrastructure
CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rl_key VARCHAR(191) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rl_key_created (rl_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- F-07 / F-16: Login lockout tracking
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(191) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    scope ENUM('user','admin') NOT NULL DEFAULT 'user',
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier, scope, created_at),
    INDEX idx_ip (ip_address, scope, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- F-09: Secure remember-me tokens
CREATE TABLE IF NOT EXISTS remember_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    validator_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- F-02: Opaque photo tokens for secure proxied access
ALTER TABLE photos
    ADD COLUMN IF NOT EXISTS access_token CHAR(32) NULL UNIQUE AFTER photo_path;
UPDATE photos SET access_token = SUBSTR(REPLACE(REPLACE(REPLACE(TO_BASE64(RANDOM_BYTES(24)),'+','A'),'/','B'),'=',''),1,32)
    WHERE access_token IS NULL OR access_token = '';

-- F-13: Daily view tracking (uses existing profile_visits.visited_at)
-- No new table; rely on profile_visits + new query.
