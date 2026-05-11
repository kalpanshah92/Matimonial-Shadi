-- Add resend_count column to otp_verifications table
ALTER TABLE otp_verifications ADD COLUMN resend_count INT DEFAULT 0 AFTER attempts;
