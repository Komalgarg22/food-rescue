<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;

if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Message ID is required']);
    exit;
}

// Verify the message belongs to the user
$stmt = $conn->prepare("UPDATE messages SET is_read = TRUE 
                      WHERE id = ? AND receiver_id = ? AND is_read = FALSE");
$stmt->bind_param("ii", $message_id, $user_id);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode([
    'success' => $affected > 0
]);