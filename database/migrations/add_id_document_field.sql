-- Add Photo ID / Documentation field to users table
ALTER TABLE users
ADD COLUMN id_document VARCHAR(500) DEFAULT NULL,
ADD COLUMN id_document_uploaded_at DATETIME DEFAULT NULL;
