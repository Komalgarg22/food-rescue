<?php
header("Content-Type: application/json");
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/functions.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $email = sanitizeInput($data['email'] ?? '');
    $password = $data['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Generate API token (simple version - consider using JWT in production)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $pdo->prepare("UPDATE users SET api_token = ?, token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user['id']]);
        
        $response = [
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    } else {
        $response['message'] = 'Invalid email or password';
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>