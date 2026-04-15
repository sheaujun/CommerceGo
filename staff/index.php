<?php
session_start();

if (!isset($_SESSION['userID']) || ($_SESSION['role'] ?? '') !== 'staff') {
    header('Location: ../login.php');
    exit;
}

header('Location: dashboard.php');
exit;
