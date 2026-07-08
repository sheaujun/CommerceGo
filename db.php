<?php
// Database connection using MySQLi

require_once __DIR__ . '/includes/app-config.php';

$config = commercego_config();

$db_host = $config['db_host'];
$db_user = $config['db_user'];
$db_pass = $config['db_pass'];
$db_name = $config['db_name'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Ensure UTF-8 encoding
$conn->set_charset('utf8mb4');
