-- Add horoscope fields to profile_details table
ALTER TABLE profile_details
ADD COLUMN birth_time TIME DEFAULT NULL,
ADD COLUMN birth_period ENUM('AM', 'PM') DEFAULT NULL,
ADD COLUMN place_of_birth VARCHAR(255) DEFAULT NULL;
