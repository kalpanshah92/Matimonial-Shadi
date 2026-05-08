-- Migration: Add country column to users table
-- Run this on existing databases to add country support

ALTER TABLE users
ADD COLUMN country VARCHAR(100) DEFAULT 'India' AFTER marital_status;

-- Optional: backfill existing users with default country
UPDATE users SET country = 'India' WHERE country IS NULL OR country = '';
