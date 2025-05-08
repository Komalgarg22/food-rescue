<?php
session_start();
require_once 'db.php'; // Assuming your database connection is set up in this file

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Prepare the query using MySQLi OOP
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id); // Binding the parameter for the user_id
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit();
}
?>
