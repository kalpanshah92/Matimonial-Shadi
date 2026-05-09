-- Migration: Remove default 'Never Married' on marital_status
-- Set existing users where marital_status was the auto-default to NULL is risky.
-- This only changes the column default for new users.

ALTER TABLE users
    ALTER COLUMN marital_status DROP DEFAULT;

ALTER TABLE users
    MODIFY marital_status VARCHAR(30) DEFAULT NULL;
