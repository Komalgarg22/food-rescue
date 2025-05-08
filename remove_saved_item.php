<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/db.php';
require_once 'includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

if (!isset($_POST['food_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No food item specified']);
    exit();
}

$user_id = $_SESSION['user_id'];
$food_id = intval($_POST['food_id']);

// Create database connection
$db = $conn

// Check connection
if ($db->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$stmt = $db->prepare("DELETE FROM saved_items WHERE user_id = ? AND food_id = ?");
$stmt->bind_param('ii', $user_id, $food_id);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Item removed']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
}

$stmt->close();
$db->close();
?>