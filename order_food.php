<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$mysqli = $conn; // Use mysqli OOP

// Validate ID from URL
$food_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$food_id) {
    header('Location: index.php');
    exit();
}

// Get food item with seller info
$stmt = $mysqli->prepare("SELECT f.*, u.id AS seller_id, u.name AS seller_name, u.profile_picture, u.created_at 
                          FROM food_items f 
                          JOIN users u ON f.user_id = u.id 
                          WHERE f.id = ?");
$stmt->bind_param('i', $food_id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();

if (!$food || strtotime($food['expiration_time']) < time()) {
    $_SESSION['error'] = 'This food item is no longer available.';
    header("Location: index.php");
    exit();
}

if ($food['user_id'] == $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot order your own food item.';
    header('Location: view_food.php?id=' . $food_id);
    exit();
}

$page_title = "Order " . htmlspecialchars($food['title']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pickup_time = sanitizeInput($_POST['pickup_time']);
    $notes = sanitizeInput($_POST['notes']);

    if (empty($pickup_time)) {
        $errors[] = 'Pickup time is required';
    } elseif (strtotime($pickup_time) < time()) {
        $errors[] = 'Pickup time must be in the future';
    }

    if (empty($errors)) {
        // Create order
        $stmt = $mysqli->prepare("INSERT INTO orders (food_id, buyer_id, seller_id, status, created_at) 
                                  VALUES (?, ?, ?, 'pending', NOW())");
        $stmt->bind_param('iii', $food_id, $_SESSION['user_id'], $food['seller_id']);

        if ($stmt->execute()) {
            $order_id = $mysqli->insert_id;
            $message = "New 🛒 🍔 Order for your food item: " . $food['title'];

            $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, content, created_at) 
                                      VALUES (?, ?, NOW())");
            $stmt->bind_param('is', $food['seller_id'], $message);
            $stmt->execute();

            $_SESSION['success'] = 'Order placed successfully! The seller will contact you soon.';
            header('Location: track_order.php?id=' . $order_id);
            exit();
        } else {
            $errors[] = 'Failed to place order. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6">Order: <?php echo htmlspecialchars($food['title']); ?></h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Food Summary -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3">Food Details</h3>
            <div class="flex items-center mb-3">
                <img src="uploads/food/<?php echo htmlspecialchars($food['image']); ?>" alt="<?php echo htmlspecialchars($food['title']); ?>" class="w-16 h-16 rounded-md object-cover mr-3">
                <div>
                    <p class="font-medium"><?php echo htmlspecialchars($food['title']); ?></p>
                    <p class="text-green-600 font-bold">$<?php echo htmlspecialchars($food['price']); ?></p>
                    <p class="text-sm text-gray-500">Quantity: <?php echo htmlspecialchars($food['quantity']); ?></p>
                </div>
            </div>
            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($food['description']); ?></p>
        </div>

        <!-- Seller Info -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3">Seller Information</h3>
            <div class="flex items-center mb-3">
                <img src="uploads/profile/<?php echo htmlspecialchars($food['profile_picture'] ?? 'default.png'); ?>" alt="<?php echo htmlspecialchars($food['seller_name']); ?>" class="w-12 h-12 rounded-full mr-3">
                <div>
                    <p class="font-medium"><?php echo htmlspecialchars($food['seller_name']); ?></p>
                    <p class="text-sm text-gray-500">Member since <?php echo date('M Y', strtotime($food['created_at'])); ?></p>
                </div>
            </div>
            <?php if ($food['latitude'] && $food['longitude']): ?>
                <p class="text-sm text-gray-600 mb-2">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <?php 
                        if (isset($_SESSION['user_lat'])) {
                            $distance = calculateDistance($_SESSION['user_lat'], $_SESSION['user_lng'], $food['latitude'], $food['longitude']);
                            echo round($distance, 2) . ' km away';
                        } else {
                            echo 'Location available after order';
                        }
                    ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Form -->
    <form action="order_food.php?id=<?php echo $food_id; ?>" method="POST">
        <div class="mb-4">
            <label for="pickup_time" class="block text-gray-700 mb-2">Preferred Pickup Time*</label>
            <input type="datetime-local" id="pickup_time" name="pickup_time" required 
                   min="<?php echo date('Y-m-d\TH:i'); ?>" 
                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
        </div>

        <div class="mb-6">
            <label for="notes" class="block text-gray-700 mb-2">Additional Notes</label>
            <textarea id="notes" name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600" placeholder="Any special instructions for the seller..."></textarea>
        </div>

        <div class="bg-gray-100 p-4 rounded-lg mb-6">
            <h3 class="font-semibold mb-2">Order Summary</h3>
            <div class="flex justify-between mb-1">
                <span class="text-gray-600">Item Price</span>
                <span>$<?php echo htmlspecialchars($food['price']); ?></span>
            </div>
            <div class="flex justify-between mb-1">
                <span class="text-gray-600">Service Fee</span>
                <span>$0.00</span>
            </div>
            <div class="flex justify-between font-bold text-lg mt-2">
                <span>Total</span>
                <span>$<?php echo htmlspecialchars($food['price']); ?></span>
            </div>
        </div>

        <button type="submit" class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition font-bold">
            Confirm Order
        </button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
