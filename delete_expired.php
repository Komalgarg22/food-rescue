<?php 
require_once 'includes/db.php'; // $conn = new mysqli(...)
require_once 'includes/functions.php';

// This script should be run daily via a CRON job to clean up expired food items

// Log the start of the process
$log = [];
$log[] = "[" . date('Y-m-d H:i:s') . "] Starting expired food cleanup process";

// Find all food items that have expired
$current_time = date('Y-m-d H:i:s');
$stmt = $conn->prepare("SELECT id, title, user_id FROM food_items WHERE expiration_time < ? AND expiration_time > DATE_SUB(?, INTERVAL 7 DAY)");
$stmt->bind_param("ss", $current_time, $current_time);
$stmt->execute();
$result = $stmt->get_result();

$expired_foods = [];
while ($row = $result->fetch_assoc()) {
    $expired_foods[] = $row;
}

$log[] = "Found " . count($expired_foods) . " recently expired food items";

// Delete each expired food item and notify the owner
foreach ($expired_foods as $food) {
    try {
        // Delete the food item
        $stmt = $conn->prepare("DELETE FROM food_items WHERE id = ?");
        $stmt->bind_param("i", $food['id']);
        $stmt->execute();

        // Create notification for the owner
        $message = "Your food listing '{$food['title']}' has expired and been removed.";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, content, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $food['user_id'], $message);
        $stmt->execute();

        $log[] = "Deleted food item #{$food['id']} - '{$food['title']}' (User: {$food['user_id']})";
    } catch (Exception $e) {
        $log[] = "Error deleting food item #{$food['id']}: " . $e->getMessage();
    }
}

// Also clean up very old expired items (older than 7 days) without notification
$stmt = $conn->prepare("DELETE FROM food_items WHERE expiration_time < DATE_SUB(?, INTERVAL 7 DAY)");
$stmt->bind_param("s", $current_time);
$stmt->execute();
$deleted_count = $stmt->affected_rows;

$log[] = "Deleted $deleted_count very old expired food items";

// Clean up old notifications (older than 30 days)
$stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(?, INTERVAL 30 DAY)");
$stmt->bind_param("s", $current_time);
$stmt->execute();
$deleted_notifications = $stmt->affected_rows;

$log[] = "Deleted $deleted_notifications old notifications";

// Write to log file
$log_path = __DIR__ . '/logs/cleanup_' . date('Y-m-d') . '.log';
file_put_contents($log_path, implode("\n", $log) . "\n", FILE_APPEND);

// Output for debugging
echo implode("<br>", $log);
?>
