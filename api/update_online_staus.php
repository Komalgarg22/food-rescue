<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Update last online time
$stmt = $conn->prepare("UPDATE users SET last_online = NOW(), is_online = 1 WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Mark users as offline if they haven't been active for 5 minutes
$offlineTime = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$stmt = $conn->prepare("UPDATE users SET is_online = 0 WHERE last_online < ?");
$stmt->bind_param("s", $offlineTime);
$stmt->execute();

echo json_encode(['success' => true]);