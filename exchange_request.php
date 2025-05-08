<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_GET['food_id'])) {
    header('Location: index.php');
    exit();
}

$requested_food_id = $_GET['food_id'];
$page_title = "Request Food Exchange";
$errors = [];

// Get the food item being requested
$stmt = $conn->prepare("SELECT f.*, u.name as user_name 
                          FROM food_items f 
                          JOIN users u ON f.user_id = u.id 
                          WHERE f.id = ? AND f.expiration_time > NOW()");
$stmt->bind_param("i", $requested_food_id);
$stmt->execute();
$result = $stmt->get_result();
$requested_food = $result->fetch_assoc();

if (!$requested_food) {
    $_SESSION['error'] = 'The requested food item is no longer available for exchange.';
    header('Location: index.php');
    exit();
}

if ($requested_food['user_id'] == $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot exchange with yourself.';
    header('Location: view_food.php?id=' . $requested_food_id);
    exit();
}

// Get current user's available food items for exchange
$stmt = $conn->prepare("SELECT * FROM food_items 
                          WHERE user_id = ? AND expiration_time > NOW() AND id != ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $requested_food_id);
$stmt->execute();
$my_foods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle exchange request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $my_food_id = sanitizeInput($_POST['my_food_id']);
    $message = sanitizeInput($_POST['message']);
    
    // Validate inputs
    if (empty($my_food_id)) {
        $errors[] = 'Please select one of your food items to offer in exchange';
    }
    
    // Verify the selected food belongs to the current user
    $valid_food = false;
    foreach ($my_foods as $food) {
        if ($food['id'] == $my_food_id) {
            $valid_food = true;
            break;
        }
    }
    
    if (!$valid_food) {
        $errors[] = 'Invalid food item selected';
    }
    
    if (empty($errors)) {
        // Create exchange request
        $stmt = $conn->prepare("INSERT INTO exchanges 
                                  (from_user_id, to_user_id, from_food_id, to_food_id, status, created_at) 
                                  VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("iiii", $_SESSION['user_id'], $requested_food['user_id'], $my_food_id, $requested_food_id);
        if ($stmt->execute()) {
            // Create notification
            $notification = "New ♻️ 🍲 exchange request for your food: " . $requested_food['title'];
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, content, created_at) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $requested_food['user_id'], $notification);
            $stmt->execute();

            $_SESSION['success'] = 'Exchange request sent successfully!';
            header('Location: exchange_list.php');
            exit();
        } else {
            $errors[] = 'Failed to send exchange request. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold mb-6">Request Food Exchange</h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Requested Food -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3">Food You Want</h3>
            <div class="flex items-center mb-3">
                <img src="uploads/food/<?php echo $requested_food['image']; ?>" alt="<?php echo $requested_food['title']; ?>" class="w-16 h-16 rounded-md object-cover mr-3">
                <div>
                    <p class="font-medium"><?php echo $requested_food['title']; ?></p>
                    <p class="text-sm text-gray-500"><?php echo $requested_food['category']; ?></p>
                    <p class="text-sm text-gray-500">From: <?php echo $requested_food['user_name']; ?></p>
                </div>
            </div>
            <p class="text-sm text-gray-600"><?php echo $requested_food['description']; ?></p>
        </div>

        <!-- Your Food Selection -->
        <div class="border rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-3">Your Food to Offer</h3>

            <?php if (empty($my_foods)): ?>
                <p class="text-gray-500 mb-4">You don't have any active food listings to exchange.</p>
                <a href="add_food.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                    Add New Food Item
                </a>
            <?php else: ?>
                <form action="exchange_request.php?food_id=<?php echo $requested_food_id; ?>" method="POST">
                    <div class="mb-4">
                        <label for="my_food_id" class="block text-gray-700 mb-2">Select your food item*</label>
                        <select id="my_food_id" name="my_food_id" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            <option value="">-- Select --</option>
                            <?php foreach ($my_foods as $food): ?>
                                <option value="<?php echo $food['id']; ?>">
                                    <?php echo $food['title']; ?> (<?php echo $food['category']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="message" class="block text-gray-700 mb-2">Message (Optional)</label>
                        <textarea id="message" name="message" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600" placeholder="Add a message to the seller..."></textarea>
                    </div>

                    <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition">
                        Send Exchange Request
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Preview of Selected Food (dynamic with JavaScript) -->
    <div id="selectedFoodPreview" class="hidden border rounded-lg p-4 mb-4">
        <h3 class="text-lg font-semibold mb-3">Your Offered Food Preview</h3>
        <div id="previewContent"></div>
    </div>
</div>

<script>
// Show preview of selected food item
document.getElementById('my_food_id')?.addEventListener('change', function () {
    const foodId = this.value;
    const previewDiv = document.getElementById('selectedFoodPreview');
    const previewContent = document.getElementById('previewContent');

    if (!foodId) {
        previewDiv.classList.add('hidden');
        return;
    }

    const foods = <?php echo json_encode($my_foods); ?>;
    const selectedFood = foods.find(food => food.id == foodId);

    if (selectedFood) {
        previewContent.innerHTML = `
            <div class="flex items-center mb-3">
                <img src="uploads/food/${selectedFood.image}" alt="${selectedFood.title}" class="w-16 h-16 rounded-md object-cover mr-3">
                <div>
                    <p class="font-medium">${selectedFood.title}</p>
                    <p class="text-sm text-gray-500">${selectedFood.category}</p>
                    <p class="text-sm text-gray-500">Quantity: ${selectedFood.quantity}</p>
                </div>
            </div>
            <p class="text-sm text-gray-600">${selectedFood.description}</p>
        `;
        previewDiv.classList.remove('hidden');
    } else {
        previewDiv.classList.add('hidden');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
