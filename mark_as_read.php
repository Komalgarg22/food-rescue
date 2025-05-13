<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['message_ids'])) {
    echo json_encode(['success' => false, 'error' => 'No message IDs provided']);
    exit;
}

$message_ids = explode(',', $_POST['message_ids']);
$placeholders = implode(',', array_fill(0, count($message_ids), '?'));
$types = str_repeat('i', count($message_ids));

$stmt = $conn->prepare("UPDATE messages SET is_read = TRUE 
                       WHERE id IN ($placeholders) AND receiver_id = ?");
$params = array_merge($message_ids, [$_SESSION['user_id']]);
$stmt->bind_param($types . 'i', ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$stmt->close();
?>