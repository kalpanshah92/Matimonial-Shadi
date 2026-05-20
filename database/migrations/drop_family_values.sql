-- =====================================================================
-- Migration: DROP family_values column from family_details table
-- Idempotent: safe to run multiple times.
-- =====================================================================

SET @db := DATABASE();

-- Check if column exists before attempting to drop
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'family_details' AND COLUMN_NAME = 'family_values'
);

SET @sql := IF(@col_exists = 1,
    'ALTER TABLE family_details DROP COLUMN family_values',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
