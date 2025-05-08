<?php
require_once './includes/db.php';
require_once './includes/functions.php';

// Assume $foodItems array is defined above this code block
$foodItems = [
    ["Biryani", "Fragrant rice dish cooked with aromatic spices and marinated meat or vegetables, layered and slow-cooked to perfection."],
    ["Butter Chicken", "Tender chicken pieces cooked in a creamy tomato-based sauce, enriched with butter and a blend of spices."],
];

// Step 1: Insert unique food items
$insertedFoods = [];

foreach ($foodItems as $item) {
    $title = $item[0];
    $description = $item[1];

    // Skip duplicate food names (check if the title already exists in the database)
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM food_items WHERE title = ?");
    $checkStmt->bind_param("s", $title);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    // If the food item already exists, skip it
    if ($count > 0) {
        continue;
    }

    // Insert into food_items table
    $stmt = $conn->prepare("INSERT INTO food_items (title, description) VALUES (?, ?)");
    
    // Error handling for prepare
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param("ss", $title, $description);
    
    if (!$stmt->execute()) {
        die('Execute failed: ' . $stmt->error);
    }
    
    $stmt->close();

    $insertedFoods[] = $title;
}

// Step 2: Fetch all food item IDs
$foodItemIds = [];
$result = $conn->query("SELECT id FROM food_items");
if (!$result) {
    die('Query failed: ' . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $foodItemIds[] = $row['id'];
}

// Step 3: Randomly assign food items to each user
$userIds = range(19, 32); // Assuming user IDs are between 19 and 32
$categories = ["Snacks", "Vegetarian", "Non-Vegetarian", "Dessert", "Street Food"];

foreach ($userIds as $userId) {
    $numItems = rand(2, 5); // Random number of items for each user (between 2 and 5)
    $assigned = [];

    while (count($assigned) < $numItems) {
        $foodId = $foodItemIds[array_rand($foodItemIds)];

        // Avoid assigning same item twice to same user
        if (in_array($foodId, $assigned)) continue;

        $assigned[] = $foodId;

        // Generate random category, image, created_at, and expiry_time
        $category = $categories[array_rand($categories)];
        $image = "0"; // You can add actual image URLs here
        $createdAt = date("Y-m-d H:i:s", strtotime("+".rand(0, 12)." hours"));
        $expiry = date("Y-m-d H:i:s", strtotime($createdAt . " +".rand(1, 5)." hours"));

        // Insert the food item assignment into the food table
        $stmt = $conn->prepare("INSERT INTO food_items (user_id, title, image, category, created_at, expiration_time) VALUES (?, ?, ?, ?, ?, ?)");
        
        // Error handling for prepare
        if (!$stmt) {
            die('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param("iissss", $userId, $foodId, $image, $category, $createdAt, $expiry);
        
        if (!$stmt->execute()) {
            die('Execute failed: ' . $stmt->error);
        }
        
        $stmt->close();
    }
}

echo "✅ Food items inserted and randomly assigned to users.";
?>
