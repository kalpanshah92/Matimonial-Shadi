-- Migration: Add address and address_type columns to users table
-- Run this on existing databases

ALTER TABLE users
ADD COLUMN address TEXT AFTER city,
ADD COLUMN address_type ENUM('Own', 'Rent') DEFAULT NULL AFTER address;
