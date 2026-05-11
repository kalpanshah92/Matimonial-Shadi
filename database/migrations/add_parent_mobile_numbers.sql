-- Add Father's and Mother's Mobile Number fields to family_details table
ALTER TABLE family_details
ADD COLUMN father_mobile VARCHAR(20) DEFAULT NULL,
ADD COLUMN mother_mobile VARCHAR(20) DEFAULT NULL;
