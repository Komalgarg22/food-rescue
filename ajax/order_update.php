<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

// Parse JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['order_id'], $data['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

$order_id = (int)$data['order_id'];
$new_status = $data['status'];
$user_id = $_SESSION['user_id'];

// Validate allowed status changes
$allowed_statuses = ['pending', 'accepted', 'cancelled', 'delivered'];
if (!in_array($new_status, $allowed_statuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status']);
    exit();
}

// Check order ownership
$stmt = $conn->prepare("SELECT buyer_id, seller_id, status FROM orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit();
}

// Determine user role in this order
if ($order['buyer_id'] === $user_id) {
    $user_role = 'buyer';
} elseif ($order['seller_id'] === $user_id) {
    $user_role = 'seller';
} else {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit();
}

// Logic to allow only certain transitions per role
$valid_transition = false;

if ($user_role === 'seller') {
    // Seller can accept pending, cancel pending, mark accepted as delivered
    if ($order['status'] === 'pending' && ($new_status === 'accepted' || $new_status === 'cancelled')) {
        $valid_transition = true;
    }
    if ($order['status'] === 'accepted' && $new_status === 'delivered') {
        $valid_transition = true;
    }
} elseif ($user_role === 'buyer') {
    // Buyer can cancel pending orders
    if ($order['status'] === 'pending' && $new_status === 'cancelled') {
        $valid_transition = true;
    }
}

if (!$valid_transition) {
    echo json_encode(['success' => false, 'error' => 'Invalid status change']);
    exit();
}

// Update order status
$stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param("si", $new_status, $order_id);
if ($stmt->execute()) {
    $stmt->close();

    // Fetch updated order data with user_role for frontend
    $stmt = $conn->prepare("SELECT o.*, 
        CASE 
            WHEN o.buyer_id = ? THEN 'buyer'
            WHEN o.seller_id = ? THEN 'seller'
            ELSE 'guest'
        END AS user_role
        FROM orders o WHERE o.id = ?");
    $stmt->bind_param("iii", $user_id, $user_id, $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $updated_order = $res->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'order' => $updated_order]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update order']);
}
?>
