<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/chat_functions.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if (empty($message) || !$receiver_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$result = sendMessage($user_id, $receiver_id, $message, $conn);

// Update user's last online time
$stmt = $conn->prepare("UPDATE users SET last_online = NOW() WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo json_encode($result);