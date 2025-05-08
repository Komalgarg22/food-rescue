<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/chat_functions.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$conversations = getConversations($user_id, $conn);

echo json_encode([
    'success' => true,
    'conversations' => $conversations
]);