-- Migration: Remove default 'Never Married' on marital_status and reset existing values to NULL

ALTER TABLE users
    MODIFY marital_status VARCHAR(30) DEFAULT NULL;

-- Reset all existing marital_status values to NULL so users explicitly choose
UPDATE users SET marital_status = NULL;
