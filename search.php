<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Search Results";
include 'includes/header.php';

// Get search parameters
$keywords = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$near_me = isset($_GET['near_me']);
$expiring_soon = isset($_GET['expiring_soon']);
$available_now = isset($_GET['available_now']);

// Build query
$query = "SELECT f.*, u.name as user_name, u.profile_picture as user_image 
          FROM food_items f 
          JOIN users u ON f.user_id = u.id 
          WHERE f.expiration_time > NOW()";

$params = [];

if (!empty($keywords)) {
    $query .= " AND (f.title LIKE ? OR f.description LIKE ?)";
    $params[] = "%$keywords%";
    $params[] = "%$keywords%";
}

if (!empty($category)) {
    $query .= " AND f.category = ?";
    $params[] = $category;
}

if (!empty($max_price) && is_numeric($max_price)) {
    $query .= " AND f.price <= ?";
    $params[] = $max_price;
}

if ($expiring_soon) {
    $query .= " AND f.expiration_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)";
}

if ($available_now) {
    $query .= " AND f.available_from <= NOW()";
}

// Add location filtering if needed
if ($near_me && isset($_SESSION['user_lat']) && isset($_SESSION['user_lng'])) {
    $query .= " AND (f.latitude IS NOT NULL AND f.longitude IS NOT NULL)";
    // Note: Actual distance calculation would be done in PHP after fetching
}

$query .= " ORDER BY f.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Bind parameters dynamically (if any)
if (!empty($params)) {
    $types = str_repeat('s', count($params)); // Assumes all parameters are strings
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$food_items = $result->fetch_all(MYSQLI_ASSOC);

// Filter by distance if needed
if ($near_me && isset($_SESSION['user_lat']) && isset($_SESSION['user_lng'])) {
    $food_items = array_filter($food_items, function($item) {
        if (!$item['latitude'] || !$item['longitude']) return false;
        
        $distance = calculateDistance(
            $_SESSION['user_lat'], 
            $_SESSION['user_lng'],
            $item['latitude'],
            $item['longitude']
        );
        
        $item['distance'] = $distance;
        return $distance <= 10; // 10 km radius
    });
    
    // Sort by distance
    usort($food_items, function($a, $b) {
        return $a['distance'] <=> $b['distance'];
    });
}
?>

<!-- Display results -->
<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Search Results</h1>

    <?php if (empty($food_items)): ?>
        <p class="text-gray-500">No results found for your search.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($food_items as $item): ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <img src="uploads/food/<?php echo $item['image']; ?>" alt="<?php echo $item['title']; ?>" class="w-full h-48 object-cover">
                    <div class="p-4">
                        <h2 class="text-xl font-semibold"><?php echo $item['title']; ?></h2>
                        <p class="text-gray-700 mt-2"><?php echo substr($item['description'], 0, 100); ?>...</p>
                        <div class="mt-3">
                            <span class="text-gray-600">Price: ₹<?php echo $item['price']; ?></span><br>
                            <span class="text-gray-600">Category: <?php echo $item['category']; ?></span>
                        </div>
                        <div class="mt-4 flex justify-between items-center">
                            <a href="view_food.php?id=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-800">View Details</a>
                            <?php if ($near_me): ?>
                                <span class="text-sm text-gray-500"><?php echo number_format($item['distance'], 2); ?> km away</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
