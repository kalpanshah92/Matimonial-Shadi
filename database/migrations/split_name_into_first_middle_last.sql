-- =====================================================================
-- Migration: Split users.name into first_name / middle_name / last_name
-- Idempotent: safe to run multiple times.
-- Strategy: ADDITIVE
--   - Adds first_name (NOT NULL), middle_name (NULL), last_name (NOT NULL)
--   - Backfills from existing `name` using whitespace splitting
--   - Keeps `name` column maintained automatically by triggers so all
--     existing reads of `users.name` continue to work unchanged
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Add the three new columns (idempotent via INFORMATION_SCHEMA check)
-- ---------------------------------------------------------------------
SET @db := DATABASE();

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'first_name'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN first_name VARCHAR(60) NULL AFTER profile_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'middle_name'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN middle_name VARCHAR(60) NULL AFTER first_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_name'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN last_name VARCHAR(60) NULL AFTER middle_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2. Backfill existing rows from `name`
--    Edge cases handled:
--      - extra spaces collapsed                  ("  Foo   Bar  " -> "Foo Bar")
--      - single-word names                       ("Madonna"     -> first=Madonna, last=Madonna)
--      - 2-word names                            ("John Smith"  -> first=John, last=Smith)
--      - 3+ word names                           ("Mary Jane O'Connor Smith" -> first=Mary, middle="Jane O'Connor", last=Smith)
--      - Unicode / international characters preserved (column is utf8mb4)
--    NOTE: only rows where first_name IS NULL are touched, so re-running is a no-op.
-- ---------------------------------------------------------------------

-- 2a. Normalise whitespace in existing `name` values once (collapse runs of spaces, trim)
UPDATE users
   SET name = TRIM(REGEXP_REPLACE(name, '[[:space:]]+', ' '))
 WHERE name IS NOT NULL
   AND first_name IS NULL;

-- 2b. Single-word names: copy to both first_name and last_name (matches the new
--     NOT NULL constraint on last_name; user can edit afterwards).
UPDATE users
   SET first_name = name,
       last_name  = name,
       middle_name = NULL
 WHERE first_name IS NULL
   AND name IS NOT NULL
   AND name <> ''
   AND LOCATE(' ', name) = 0;

-- 2c. Two or more words: first word -> first_name, last word -> last_name
UPDATE users
   SET first_name = SUBSTRING_INDEX(name, ' ', 1),
       last_name  = SUBSTRING_INDEX(name, ' ', -1)
 WHERE first_name IS NULL
   AND name IS NOT NULL
   AND LOCATE(' ', name) > 0;

-- 2d. Three or more words: middle = everything between first and last word
UPDATE users
   SET middle_name = TRIM(SUBSTRING(
            name,
            CHAR_LENGTH(SUBSTRING_INDEX(name, ' ', 1)) + 2,
            CHAR_LENGTH(name)
              - CHAR_LENGTH(SUBSTRING_INDEX(name, ' ', 1))
              - CHAR_LENGTH(SUBSTRING_INDEX(name, ' ', -1))
              - 2
       ))
 WHERE middle_name IS NULL
   AND name IS NOT NULL
   AND (CHAR_LENGTH(name) - CHAR_LENGTH(REPLACE(name, ' ', ''))) >= 2;

-- 2e. Safety net: any row still missing first_name / last_name gets a placeholder
--     so the NOT NULL constraint below succeeds. Should be 0 rows in practice.
UPDATE users
   SET first_name = COALESCE(NULLIF(first_name, ''), 'User'),
       last_name  = COALESCE(NULLIF(last_name,  ''), profile_id)
 WHERE first_name IS NULL OR last_name IS NULL OR first_name = '' OR last_name = '';

-- ---------------------------------------------------------------------
-- 3. Enforce NOT NULL on first_name / last_name now that data is populated
-- ---------------------------------------------------------------------
ALTER TABLE users
    MODIFY first_name VARCHAR(60) NOT NULL,
    MODIFY last_name  VARCHAR(60) NOT NULL;

-- ---------------------------------------------------------------------
-- 4. Triggers that keep `name` in sync with the three new columns.
--    This means every existing read of users.name (in PHP, emails, exports,
--    search, dashboards, etc.) keeps working without code changes.
-- ---------------------------------------------------------------------
DROP TRIGGER IF EXISTS users_sync_name_bi;
DROP TRIGGER IF EXISTS users_sync_name_bu;

DELIMITER $$

CREATE TRIGGER users_sync_name_bi
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    SET NEW.first_name  = TRIM(REGEXP_REPLACE(COALESCE(NEW.first_name,  ''), '[[:space:]]+', ' '));
    SET NEW.middle_name = NULLIF(TRIM(REGEXP_REPLACE(COALESCE(NEW.middle_name, ''), '[[:space:]]+', ' ')), '');
    SET NEW.last_name   = TRIM(REGEXP_REPLACE(COALESCE(NEW.last_name,   ''), '[[:space:]]+', ' '));
    SET NEW.name = TRIM(CONCAT_WS(' ', NEW.first_name, NEW.middle_name, NEW.last_name));
END$$

CREATE TRIGGER users_sync_name_bu
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.first_name  = TRIM(REGEXP_REPLACE(COALESCE(NEW.first_name,  ''), '[[:space:]]+', ' '));
    SET NEW.middle_name = NULLIF(TRIM(REGEXP_REPLACE(COALESCE(NEW.middle_name, ''), '[[:space:]]+', ' ')), '');
    SET NEW.last_name   = TRIM(REGEXP_REPLACE(COALESCE(NEW.last_name,   ''), '[[:space:]]+', ' '));
    SET NEW.name = TRIM(CONCAT_WS(' ', NEW.first_name, NEW.middle_name, NEW.last_name));
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 5. One-time resync of existing rows so `name` reflects the split values
--    (this is a no-op for rows whose name already matches concatenation).
-- ---------------------------------------------------------------------
UPDATE users
   SET name = TRIM(CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name));

-- ---------------------------------------------------------------------
-- 6. Helpful indexes for search and matchmaking
-- ---------------------------------------------------------------------
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_first_name'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_users_first_name ON users(first_name)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_last_name'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_users_last_name ON users(last_name)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
