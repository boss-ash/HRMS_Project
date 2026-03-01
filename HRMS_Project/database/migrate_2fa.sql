-- Add Google Authenticator (2FA) secret to users. Run once.
USE hrms_db;
ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255) NULL DEFAULT NULL;
