<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set content type to JSON for all responses
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
        throw new Exception('Missing required parameters');
    }

    $order_id = (int)$_POST['order_id'];
    $status = strtolower(trim($_POST['status']));
    $user_id = $_SESSION['user_id'];

    // Verify user has permission to update this order
    $stmt = $conn->prepare("SELECT buyer_id, seller_id FROM orders WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order || ($order['buyer_id'] != $user_id && $order['seller_id'] != $user_id)) {
        throw new Exception('You do not have permission to update this order');
    }

    // Update order status
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $stmt->bind_param("si", $status, $order_id);
    $stmt->execute();
    $stmt->close();

    // Record status change in history
    $stmt = $conn->prepare("INSERT INTO order_status_history (order_id, status, user_id) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    $stmt->bind_param("isi", $order_id, $status, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
    exit();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}