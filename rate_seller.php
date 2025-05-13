<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['seller_id']) || !isset($_POST['rating'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$seller_id = (int)$_POST['seller_id'];
$rating = (int)$_POST['rating'];
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$user_id = $_SESSION['user_id'];

// Validate rating
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Invalid rating value']);
    exit();
}

// Check if user has already rated this seller
$stmt = $pdo->prepare("SELECT id FROM seller_ratings WHERE user_id = ? AND seller_id = ?");
$stmt->bind_param('ii', $user_id, $seller_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing rating
    $stmt = $pdo->prepare("UPDATE seller_ratings SET rating = ?, comment = ?, updated_at = NOW() WHERE user_id = ? AND seller_id = ?");
    $stmt->bind_param('isii', $rating, $comment, $user_id, $seller_id);
} else {
    // Create new rating
    $stmt = $pdo->prepare("INSERT INTO seller_ratings (user_id, seller_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('iiis', $user_id, $seller_id, $rating, $comment);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit rating']);
}
?>