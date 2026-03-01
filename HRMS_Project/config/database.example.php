<?php
/**
 * Database configuration — copy to database.php and set your values.
 * For production: use the limited user from database/create_limited_user.sql
 * (hrms_app with only SELECT, INSERT, UPDATE, DELETE on hrms_db).
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Production: use 'hrms_app' (see create_limited_user.sql)
define('DB_PASS', '');               // Production: use strong password
define('DB_NAME', 'hrms_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');
