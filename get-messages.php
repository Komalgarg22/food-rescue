<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;

if (!$receiver_id) {
    echo json_encode(['error' => 'Receiver ID is required']);
    exit;
}

// Get new messages
$query = "SELECT m.*, u.name as sender_name, u.profile_picture as sender_image
          FROM messages m
          JOIN users u ON m.sender_id = u.id
          WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
          AND (m.deleted_at IS NULL OR m.sender_id = ? OR m.receiver_id = ?)
          AND m.id > ?
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiiiii", $user_id, $receiver_id, $receiver_id, $user_id, $user_id, $user_id, $last_message_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mark received messages as read
if (!empty($messages)) {
    $unread_ids = array_filter(array_map(function($msg) use ($user_id) {
        return $msg['receiver_id'] == $user_id && !$msg['is_read'] ? $msg['id'] : null;
    }, $messages));
    
    if (!empty($unread_ids)) {
        $placeholders = implode(',', array_fill(0, count($unread_ids), '?'));
        $types = str_repeat('i', count($unread_ids));
        
        $stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE id IN ($placeholders)");
        $stmt->bind_param($types, ...$unread_ids);
        $stmt->execute();
        $stmt->close();
    }
}

echo json_encode([
    'success' => true,
    'messages' => $messages
]);