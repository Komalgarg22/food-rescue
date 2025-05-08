<?php
header("Content-Type: application/json");
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/functions.php';

$response = ['success' => false, 'data' => []];

// Verify API token
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';

if (empty($token)) {
    $response['message'] = 'Authorization token required';
    echo json_encode($response);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE api_token = ? AND token_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $response['message'] = 'Invalid or expired token';
    echo json_encode($response);
    exit;
}

// Handle different request methods
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get food items
        $stmt = $pdo->prepare("SELECT f.*, u.name as user_name 
                              FROM food_items f
                              JOIN users u ON f.user_id = u.id
                              WHERE f.expiration_time > NOW()
                              ORDER BY f.created_at DESC");
        $stmt->execute();
        $foods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'data' => $foods,
            'count' => count($foods)
        ];
        break;
        
    case 'POST':
        // Create new food item (requires authentication)
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate and process data
        // Similar to add_food.php but return JSON response
        break;
        
    default:
        $response['message'] = 'Method not allowed';
}

echo json_encode($response);
?>