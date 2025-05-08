<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if admin already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    die("Admin user already exists. Initialization aborted.");
}

// Generate random admin data
// $admin_email = "admin_" . bin2hex(random_bytes(4)) . "@example.com";
// $admin_password = bin2hex(random_bytes(8)); // Temporary password
$admin_email = "admin5@gmail.com";
$admin_password = "111111";
$admin_name = "Admin User";
$hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

// Insert admin user
$stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
$stmt->bind_param("sss", $admin_name, $admin_email, $hashed_password);

if ($stmt->execute()) {
    echo "Admin user created successfully!<br>";
    echo "Email: " . htmlspecialchars($admin_email) . "<br>";
    echo "Password: " . htmlspecialchars($admin_password) . "<br>";
    echo "Please change this password immediately after login.";
} else {
    echo "Error creating admin user: " . $conn->error;
}

$stmt->close();
$conn->close();
?>