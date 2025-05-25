
<?php
session_start();
require_once 'includes/db.php';

// Set JSON header for all responses
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate required fields
    if (!isset($_POST['from_user_id'], $_POST['to_user_id'], $_POST['rating'])) {
        throw new Exception('Missing required fields');
    }

    $from = (int)$_POST['from_user_id'];
    $to = (int)$_POST['to_user_id'];
    $rating = (int)$_POST['rating'];
    $review = trim($_POST['review'] ?? '');

    // Validate rating range
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5');
    }

    // Check if user already rated
    $pdo = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($pdo->connect_error) {
        throw new Exception("Connection failed: " . $pdo->connect_error);
    }

    $check_stmt = $pdo->prepare("SELECT id FROM ratings WHERE from_user_id = ? AND to_user_id = ?");
    $check_stmt->bind_param("ii", $from, $to);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('You have already rated this user');
    }

    // Insert new rating
    $stmt = $pdo->prepare("INSERT INTO ratings (from_user_id, to_user_id, rating, review) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $from, $to, $rating, $review);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save rating');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Rating submitted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>