<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($message) || !$receiver_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $user_id, $receiver_id, $message);
$stmt->execute();
$message_id = $stmt->insert_id;
$stmt->close();

echo json_encode([
    'success' => true,
    'message_id' => $message_id
]);