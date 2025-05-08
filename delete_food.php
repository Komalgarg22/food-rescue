<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $food_id = intval($_POST['id']);

    // Verify owner
    $stmt = $conn->prepare("SELECT image, user_id FROM food_items WHERE id = ?");
    $stmt->bind_param("i", $food_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Item not found']);
        exit;
    }

    $row = $result->fetch_assoc();

    if ($row['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    // Delete image
    $image_path = 'uploads/food/' . $row['image'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }

    // Delete from DB
    $stmt = $conn->prepare("DELETE FROM food_items WHERE id = ?");
    $stmt->bind_param("i", $food_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
