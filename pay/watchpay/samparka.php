<?php
require_once __DIR__ . '/../../config_loader.php';

date_default_timezone_set(config('APP_TIMEZONE', 'Asia/Kolkata'));

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    error_log('Database Connection Failed: ' . $e->getMessage());
    die('Error: Cannot connect to database');
}

mysqli_set_charset($conn, 'utf8mb4');
?>
