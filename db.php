<?php
require_once __DIR__ . '/includes/app-config.php';

$commercegoConfig = commercego_config();

$conn = new mysqli(
    $commercegoConfig['db_host'],
    $commercegoConfig['db_user'],
    $commercegoConfig['db_pass'],
    $commercegoConfig['db_name']
);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Ensure UTF-8 encoding
$conn->set_charset('utf8mb4');
