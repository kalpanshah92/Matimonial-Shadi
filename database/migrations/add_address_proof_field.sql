-- Add Address Proof document fields to users table
ALTER TABLE users
ADD COLUMN address_proof_document VARCHAR(255) DEFAULT NULL,
ADD COLUMN address_proof_uploaded_at DATETIME DEFAULT NULL;
