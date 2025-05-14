<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['seller_id']) || !isset($_POST['reason'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$seller_id = (int)$_POST['seller_id'];
$reason = trim($_POST['reason']);
$details = isset($_POST['details']) ? trim($_POST['details']) : '';
$user_id = $_SESSION['user_id'];

// Validate reason
$valid_reasons = ['Fraud', 'Inappropriate', 'Spam', 'Quality', 'Other'];
if (!in_array($reason, $valid_reasons)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reason']);
    exit();
}

// Insert report
$stmt = $pdo->prepare("INSERT INTO seller_reports (reporter_id, seller_id, reason, details, status, created_at) 
                      VALUES (?, ?, ?, ?, 'Pending', NOW())");
$stmt->bind_param('iiss', $user_id, $seller_id, $reason, $details);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Report submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit report']);
}
?>