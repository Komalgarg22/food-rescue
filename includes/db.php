<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rescue');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");
?>
