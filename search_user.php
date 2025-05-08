<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

if (empty($search_term)) {
    echo json_encode(['error' => 'Search term is required']);
    exit;
}

$search_term = '%' . $conn->real_escape_string($search_term) . '%';

$stmt = $conn->prepare("SELECT id, name, email, phone, profile_picture 
                       FROM users 
                       WHERE (name LIKE ? OR email LIKE ? OR phone LIKE ?) 
                       AND id != ?
                       LIMIT 10");
$stmt->bind_param("sssi", $search_term, $search_term, $search_term, $user_id);
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'results' => $results]);