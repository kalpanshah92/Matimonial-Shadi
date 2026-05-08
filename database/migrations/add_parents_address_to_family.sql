-- Migration: Add parents_address and parents_address_type columns to family_details table

ALTER TABLE family_details
ADD COLUMN parents_address TEXT AFTER family_location,
ADD COLUMN parents_address_type ENUM('Own', 'Rent') DEFAULT NULL AFTER parents_address;
