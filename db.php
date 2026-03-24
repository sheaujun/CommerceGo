<?php
// Database connection using MySQLi

$db_host = 'localhost';
$db_user = 'root';       // XAMPP default user
$db_pass = '';           // XAMPP default has no password; change if needed
$db_name = 'commercego'; // Existing database name

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

// Ensure UTF-8 encoding
$conn->set_charset('utf8mb4');
