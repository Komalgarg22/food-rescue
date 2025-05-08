<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/chat_functions.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

$result = getUnreadMessages($user_id, $last_message_id, $conn);

// Update user's last online time
$stmt = $conn->prepare("UPDATE users SET last_online = NOW(), is_online = 1 WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo json_encode([
    'success' => true,
    'messages' => $result['messages'],
    'last_message_id' => $result['last_message_id']
]);