<?php
/**
 * Ensure employees.archived_at exists (for archive feature). Run once per request if needed.
 * Requires $conn (MySQLi) to be available.
 */
if (!isset($conn)) return;
$r = @mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'archived_at'");
if ($r && mysqli_num_rows($r) > 0) return;
@mysqli_query($conn, "ALTER TABLE employees ADD COLUMN archived_at DATETIME NULL DEFAULT NULL");
@mysqli_query($conn, "ALTER TABLE employees ADD INDEX idx_archived_at (archived_at)");
