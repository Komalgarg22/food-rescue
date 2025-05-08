<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Available Food Items";
include 'includes/header.php';

$user_lat = $_SESSION['user_lat'] ?? null;
$user_lng = $_SESSION['user_lng'] ?? null;

// Fetch latest food items
$query = "SELECT f.*, u.name as user_name, u.profile_picture as user_image 
          FROM food_items f 
          JOIN users u ON f.user_id = u.id 
          WHERE f.expiration_time > NOW() 
          ORDER BY f.created_at DESC";

$result = $conn->query($query);
$food_items = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $food_items[] = $row;
    }
}

// Initialize empty variables to prevent undefined errors
$keywords = '';
$category = '';
$near_me = false;
$expiring_soon = false;
$available_now = false;
?>

<!-- Search Form -->
<form action="search.php" method="GET" class="mb-6 bg-white p-4 rounded-lg shadow">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <label class="block text-gray-700 mb-1">Search</label>
            <input type="text" name="q" placeholder="Enter food name, description or price"
                class="w-full px-3 py-2 border rounded-lg" value="">
        </div>
        <!-- <div>
            <label class="block text-gray-700 mb-1">Category</label>
            <select name="category" class="w-full px-3 py-2 border rounded-lg">
                <option value="">All Categories</option>
                <?php
                $categories = [
                    "Home Cooked",
                    "Leftover Food",
                    "Fruits & Vegetables",
                    "Meals",
                    "Snacks",
                    "Beverages",
                    "Bakery & Desserts",
                    "Non-Veg",
                    "South Indian",
                    "North Indian",
                    "Chinese",
                    "Fast Food",
                    "Salads"
                ];
                foreach ($categories as $cat) {
                    echo "<option value=\"$cat\">$cat</option>";
                }
                ?>
            </select>
        </div> -->
    </div>

    <!-- <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <label class="flex items-center">
            <input type="checkbox" name="near_me" class="mr-2"> Near me only
        </label>
        <label class="flex items-center">
            <input type="checkbox" name="expiring_soon" class="mr-2"> Expiring soon
        </label>
        <label class="flex items-center">
            <input type="checkbox" name="available_now" class="mr-2"> Available now
        </label>
    </div> -->

    <div class="mt-4">
        <button type="submit"
            class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 w-full md:w-auto">
            Search
        </button>
    </div>
</form>

<!-- Food Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($food_items as $item): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <img src="uploads/food/<?php echo $item['image']; ?>" alt="<?php echo $item['title']; ?>" class="w-full h-48 object-cover">
            <div class="p-4">
                <h3 class="text-xl font-semibold mb-2"><?php echo $item['title']; ?></h3>
                <p class="text-gray-600 mb-2"><?php echo $item['description']; ?></p>
                <p class="text-green-600 font-bold mb-2">$<?php echo $item['price']; ?></p>
                <p class="text-gray-500 text-sm mb-4">Quantity: <?php echo $item['quantity']; ?></p>

                <?php if ($user_lat && $user_lng): ?>
                    <?php $distance = calculateDistance($user_lat, $user_lng, $item['latitude'], $item['longitude']); ?>
                    <p class="text-sm text-gray-500"><?php echo round($distance, 2); ?> km away</p>
                <?php endif; ?>

                <div class="flex items-center mt-4">
                    <img src="uploads/profile/<?php echo $item['user_image']; ?>" alt="<?php echo $item['user_name']; ?>" class="w-8 h-8 rounded-full mr-2">
                    <span class="text-sm"><?php echo $item['user_name']; ?></span>
                </div>

                <a href="view_food.php?id=<?php echo $item['id']; ?>" class="block mt-4 bg-green-600 text-white text-center py-2 rounded hover:bg-green-700 transition">
                    View Details
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>