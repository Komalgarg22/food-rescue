<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$message_id = (int)$_POST['message_id'];

$stmt = $conn->prepare("UPDATE messages SET is_read = TRUE 
                      WHERE id = ? AND receiver_id = ?");
$stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
$stmt->execute();

echo json_encode(['success' => true]);