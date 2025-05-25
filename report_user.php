
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
    if (!isset($_POST['reporter_id'], $_POST['reported_user_id'], $_POST['reason'])) {
        throw new Exception('Missing required fields');
    }

    $reporter = (int)$_POST['reporter_id'];
    $reported = (int)$_POST['reported_user_id'];
    $reason = trim($_POST['reason']);
    $details = trim($_POST['details'] ?? '');

    // Check for duplicate reports
    $pdo = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($pdo->connect_error) {
        throw new Exception("Connection failed: " . $pdo->connect_error);
    }

    $check_stmt = $pdo->prepare("SELECT id FROM reports WHERE reporter_id = ? AND reported_user_id = ?");
    $check_stmt->bind_param("ii", $reporter, $reported);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        throw new Exception('You have already reported this user');
    }

    // Insert new report
    $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, reported_user_id, reason, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $reporter, $reported, $reason, $details);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save report');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Report submitted successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>