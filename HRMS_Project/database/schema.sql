-- HRMS Database Schema (with Security Patches)
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS hrms_db;
USE hrms_db;

-- Login rate-limiting: track failed attempts by IP (brute-force protection)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
);

-- Employees table
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_code VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    department VARCHAR(50),
    position VARCHAR(50),
    hire_date DATE,
    salary DECIMAL(12, 2) DEFAULT NULL,
    status ENUM('active', 'inactive', 'on_leave') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users: admin = full access, staff = view own profile only. employee_id links staff to their record.
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff', 'hr') DEFAULT 'staff',
    employee_id INT NULL,
    totp_secret VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
);

-- Activity logs (login, logout, employee actions)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    details VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_action (action),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- For existing DBs: add new columns (ignore errors if already present)
-- INSERT login_attempts if missing is already above. Add employee_id to users:
-- ALTER TABLE users ADD COLUMN employee_id INT NULL;
-- ALTER TABLE users ADD CONSTRAINT fk_user_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL;

-- Sample employees (insert first so we can link staff to employee_id 1)
INSERT IGNORE INTO employees (employee_code, first_name, last_name, email, phone, department, position, hire_date, status) VALUES
('EMP001', 'John', 'Doe', 'john.doe@company.com', '555-0101', 'Engineering', 'Software Developer', '2023-01-15', 'active'),
('EMP002', 'Jane', 'Smith', 'jane.smith@company.com', '555-0102', 'Human Resources', 'HR Manager', '2022-06-01', 'active'),
('EMP003', 'Bob', 'Wilson', 'bob.wilson@company.com', '555-0103', 'Sales', 'Sales Representative', '2023-03-20', 'active');

-- Default admin: BCrypt hash for 'password'
INSERT INTO users (username, password, full_name, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- Default staff: same password, linked to employee EMP001 (John Doe)
INSERT INTO users (username, password, full_name, role, employee_id) VALUES
('staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Staff User', 'staff', 1)
ON DUPLICATE KEY UPDATE username = username;
