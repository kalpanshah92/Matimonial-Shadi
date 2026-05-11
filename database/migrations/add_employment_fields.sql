-- Add Employment Status, Job Description, and Business Description fields to profile_details table
ALTER TABLE profile_details
ADD COLUMN employment_status VARCHAR(20) DEFAULT NULL,
ADD COLUMN job_description VARCHAR(50) DEFAULT NULL,
ADD COLUMN business_description VARCHAR(50) DEFAULT NULL;
