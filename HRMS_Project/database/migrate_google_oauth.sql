-- Add email and Google ID to users for "Sign in with Google"
-- Run once: mysql -u root -p hrms_db < database/migrate_google_oauth.sql

ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL;
ALTER TABLE users ADD COLUMN google_id VARCHAR(50) NULL UNIQUE;
