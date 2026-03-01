-- WebAuthn / Passkey credentials (fingerprint, face ID, device unlock)
-- Run once: mysql -u root -p hrms_db < database/migrate_passkeys.sql

USE hrms_db;

CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    credential_id VARCHAR(255) NOT NULL,
    public_key TEXT NOT NULL,
    counter INT UNSIGNED NOT NULL DEFAULT 0,
    name VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_credential (credential_id(191)),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
