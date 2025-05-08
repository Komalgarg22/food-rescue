<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Search Results";
include 'includes/header.php';

$q = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$near_me = isset($_GET['near_me']);
$expiring_soon = isset($_GET['expiring_soon']);
$available_now = isset($_GET['available_now']);

$user_lat = $_SESSION['user_lat'] ?? null;
$user_lng = $_SESSION['user_lng'] ?? null;

$query = "SELECT f.*, u.name as user_name, u.profile_picture as user_image 
          FROM food_items f 
          JOIN users u ON f.user_id = u.id 
          WHERE f.expiration_time > NOW()";

$params = [];

// Search keyword
if (!empty($q)) {
    $q = $conn->real_escape_string($q);
    $query .= " AND (f.title LIKE '%$q%' OR f.description LIKE '%$q%' OR f.price LIKE '%$q%')";
}

// Category filter
if (!empty($category)) {
    $category = $conn->real_escape_string($category);
    $query .= " AND f.category = '$category'";
}

// Available now
if ($available_now) {
    $query .= " AND f.pickup_start <= NOW() AND f.pickup_end >= NOW()";
}

// Expiring soon (within 6 hours)
if ($expiring_soon) {
    $query .= " AND f.expiration_time <= DATE_ADD(NOW(), INTERVAL 6 HOUR)";
}

$query .= " ORDER BY f.created_at DESC";

$result = $conn->query($query);

$food_items = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($near_me && $user_lat && $user_lng) {
            $distance = calculateDistance($user_lat, $user_lng, $row['latitude'], $row['longitude']);
            if ($distance <= 5) { // Only within 5 km
                $row['distance'] = $distance;
                $food_items[] = $row;
            }
        } else {
            $food_items[] = $row;
        }
    }
}
?>

<!-- ✅ MAIN CONTENT AREA -->
<main class="min-h-screen pt-16 pb-24 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4">
        <h1 class="text-2xl font-bold mb-6">Search Results</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (count($food_items) === 0): ?>
                <p class="text-gray-500 col-span-full">No food items found matching your criteria.</p>
            <?php else: ?>
                <?php foreach ($food_items as $item): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <img src="uploads/food/<?php echo $item['image']; ?>" alt="<?php echo $item['title']; ?>" class="w-full h-48 object-cover">
                        <div class="p-4">
                            <h3 class="text-xl font-semibold mb-2"><?php echo $item['title']; ?></h3>
                            <p class="text-gray-600 mb-2"><?php echo $item['description']; ?></p>
                            <p class="text-green-600 font-bold mb-2">$<?php echo $item['price']; ?></p>
                            <p class="text-gray-500 text-sm mb-4">Quantity: <?php echo $item['quantity']; ?></p>

                            <?php if (isset($item['distance'])): ?>
                                <p class="text-sm text-gray-500"><?php echo round($item['distance'], 2); ?> km away</p>
                            <?php elseif ($user_lat && $user_lng): ?>
                                <p class="text-sm text-gray-500">
                                    <?php echo round(calculateDistance($user_lat, $user_lng, $item['latitude'], $item['longitude']), 2); ?> km away
                                </p>
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
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
