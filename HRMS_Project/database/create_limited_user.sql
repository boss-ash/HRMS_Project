-- Create a limited-privilege MySQL user for HRMS (security best practice).
-- Run this as root (or admin) in MySQL. Replace 'YourStrongPassword' with a strong password.
-- Then set DB_USER and DB_PASS in config/database.php to use this user.

-- Create user (MySQL 5.7+ / MariaDB 10.2+)
CREATE USER IF NOT EXISTS 'hrms_app'@'localhost' IDENTIFIED BY 'YourStrongPassword';

-- Grant only the privileges the app needs (no DROP, no CREATE, no GRANT)
GRANT SELECT, INSERT, UPDATE, DELETE ON hrms_db.* TO 'hrms_app'@'localhost';

FLUSH PRIVILEGES;

-- Revoke anything else (default is no other privileges). To verify:
-- SHOW GRANTS FOR 'hrms_app'@'localhost';
