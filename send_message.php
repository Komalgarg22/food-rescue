<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$receiver_id = (int)$_POST['receiver_id'];
$message = sanitizeInput($_POST['message']);

if (!$receiver_id || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $_SESSION['user_id'], $receiver_id, $message);

if ($stmt->execute()) {
    $message_id = $stmt->insert_id;
    
    // Create notification
    $notification = "New message from {$_SESSION['user_name']}";
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, content) VALUES (?, ?)");
    $stmt->bind_param("is", $receiver_id, $notification);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message_id' => $message_id
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}