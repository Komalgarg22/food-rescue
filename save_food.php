<?php
// Enable error reporting but don't display to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Start output buffering
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Include necessary files
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Initialize response array
$response = ['success' => false, 'message' => ''];

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST requests allowed', 405);
    }

    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in', 401);
    }

    // Validate food_id
    if (!isset($_POST['food_id']) || !is_numeric($_POST['food_id'])) {
        throw new Exception('Invalid food ID', 400);
    }

    $user_id = $_SESSION['user_id'];
    $food_id = (int)$_POST['food_id'];

    // Initialize database connection
    $db = $conn;
    if ($db->connect_error) {
        throw new Exception('Database connection failed', 500);
    }

    // Verify food item exists and is not expired
    $stmt = $db->prepare("SELECT id FROM food_items WHERE id = ? AND expiration_time > NOW()");
    if (!$stmt) {
        throw new Exception('Failed to prepare food check statement', 500);
    }
    
    $stmt->bind_param('i', $food_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute food check', 500);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Food item not found or expired', 404);
    }
    $stmt->close();

    // Check if already saved
    $stmt = $db->prepare("SELECT id FROM saved_items WHERE user_id = ? AND food_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare saved items check', 500);
    }
    
    $stmt->bind_param('ii', $user_id, $food_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute saved items check', 500);
    }
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Item already saved', 409);
    }
    $stmt->close();

    // Save the item
    $stmt = $db->prepare("INSERT INTO saved_items (user_id, food_id, saved_at) VALUES (?, ?, NOW())");
    if (!$stmt) {
        throw new Exception('Failed to prepare save statement', 500);
    }
    
    $stmt->bind_param('ii', $user_id, $food_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to save item', 500);
    }

    // Success response
    $response = [
        'success' => true,
        'message' => 'Item saved successfully'
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode() ?: 500
    ];
} finally {
    // Close connections if they exist
    if (isset($stmt)) { $stmt->close(); }
    if (isset($db)) { $db->close(); }
    
    // Clean output buffer and send JSON response
    ob_end_clean();
    echo json_encode($response);
    exit();
}
?>