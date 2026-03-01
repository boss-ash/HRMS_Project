-- Run this if you already have hrms_db and want to add security tables/columns only.
USE hrms_db;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
);

-- Add employee_id to users if missing (MySQL 5.7: ignore error if column exists)
ALTER TABLE users ADD COLUMN employee_id INT NULL;
-- If you get "Duplicate column", the column already exists.

-- Ensure role enum includes 'staff' and 'hr' (adjust if your enum differs)
-- ALTER TABLE users MODIFY role ENUM('admin', 'staff', 'hr') DEFAULT 'staff';
