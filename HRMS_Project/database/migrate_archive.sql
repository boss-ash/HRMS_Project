-- Archive instead of delete: employees are soft-deleted (archived_at set), stored for 30 days, then auto-deleted.
-- Run once in phpMyAdmin or MySQL:
--   USE hrms_db;
--   Then run the ALTER below. (If you get "Duplicate column", the migration was already applied.)

ALTER TABLE employees
ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER updated_at,
ADD INDEX idx_archived_at (archived_at);
