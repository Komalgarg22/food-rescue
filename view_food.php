<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$food_id = $_GET['id'];
$pdo = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($pdo->connect_error) {
    die("Connection failed: " . $pdo->connect_error);
}

$stmt = $pdo->prepare("SELECT f.*, u.name as user_name, u.profile_picture as user_image, u.phone as user_phone 
                      FROM food_items f 
                      JOIN users u ON f.user_id = u.id 
                      WHERE f.id = ?");
$stmt->bind_param('i', $food_id);
$stmt->execute();
$result = $stmt->get_result();
$food = $result->fetch_assoc();

if (!$food) {
    header('Location: index.php');
    exit();
}

$page_title = $food['title'];
include 'includes/header.php';

// Check if current user is the owner of the food item
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $food['user_id'];

// Get similar food items (same category)
$stmt = $pdo->prepare("SELECT f.*, u.name as user_name 
                      FROM food_items f 
                      JOIN users u ON f.user_id = u.id 
                      WHERE f.category = ? AND f.id != ? AND f.expiration_time > NOW() 
                      ORDER BY f.created_at DESC LIMIT 3");
$stmt->bind_param('si', $food['category'], $food_id);
$stmt->execute();
$result = $stmt->get_result();
$similar_foods = $result->fetch_all(MYSQLI_ASSOC);

// Get user location if available
$user_lat = $_SESSION['user_lat'] ?? null;
$user_lng = $_SESSION['user_lng'] ?? null;
$distance = null;

if ($user_lat && $user_lng && $food['latitude'] && $food['longitude']) {
    $distance = calculateDistance($user_lat, $user_lng, $food['latitude'], $food['longitude']);
}
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Food Image -->
        <div class="relative h-64 md:h-96">
            <img src="uploads/food/<?php echo $food['image']; ?>" alt="<?php echo $food['title']; ?>" class="w-full h-full object-cover">
            <?php if (strtotime($food['expiration_time']) < time()): ?>
                <div class="absolute top-4 right-4 bg-red-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                    Expired
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Food Details -->
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-bold"><?php echo $food['title']; ?></h1>
                    <p class="text-gray-500"><?php echo $food['category']; ?></p>
                </div>
                <div class="text-right">
                    <p class="text-3xl font-bold text-green-600">$<?php echo $food['price']; ?></p>
                    <p class="text-sm text-gray-500">Quantity: <?php echo $food['quantity']; ?></p>
                </div>
            </div>
            
            <div class="mb-6">
                <p class="text-gray-700"><?php echo $food['description']; ?></p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Seller Info -->
                <div class="border rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-3">Seller Information</h3>
                    <div class="flex items-center mb-3">
                        <img src="uploads/profile/<?php echo $food['user_image']; ?>" alt="<?php echo $food['user_name']; ?>" class="w-12 h-12 rounded-full mr-3">
                        <div>
                            <p class="font-medium"><?php echo $food['user_name']; ?></p>
                            <p class="text-sm text-gray-500">Member since <?php echo date('M Y', strtotime($food['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($distance !== null): ?>
                        <p class="text-sm text-gray-600 mb-2">
                            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            About <?php echo round($distance, 2); ?> km away
                        </p>
                    <?php endif; ?>
                    
                    <p class="text-sm text-gray-600 mb-2">
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <?php echo $food['user_phone'] ?? 'Phone not available'; ?>
                    </p>
                    
                    <?php if (!$is_owner && isset($_SESSION['user_id'])): ?>
                        <a href="messages.php?to=<?php echo $food['user_id']; ?>" class="inline-block mt-2 text-green-600 hover:underline">
                            Contact Seller
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Food Details -->
                <div class="border rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-3">Food Details</h3>
                    <div class="space-y-2">
                        <p class="text-sm">
                            <span class="font-medium text-gray-600">Posted:</span> 
                            <?php echo date('M j, Y g:i A', strtotime($food['created_at'])); ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium text-gray-600">Expires:</span> 
                            <?php echo date('M j, Y g:i A', strtotime($food['expiration_time'])); ?>
                            <?php if (strtotime($food['expiration_time']) < time()): ?>
                                <span class="text-red-500 ml-2">(Expired)</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-sm">
                            <span class="font-medium text-gray-600">Condition:</span> 
                            <?php 
                            $expired = strtotime($food['expiration_time']) < time();
                            echo $expired ? 'Expired' : 'Fresh';
                            ?>
                        </p>
                        <?php if ($food['latitude'] && $food['longitude']): ?>
                            <p class="text-sm">
                                <span class="font-medium text-gray-600">Location:</span> 
                                <a href="https://www.google.com/maps?q=<?php echo $food['latitude']; ?>,<?php echo $food['longitude']; ?>" target="_blank" class="text-green-600 hover:underline">
                                    View on Map
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-3 mb-8">
                <?php if ($is_owner): ?>
                    <a href="edit_food.php?id=<?php echo $food_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        Edit Listing
                    </a>
   <button onclick="deleteFoodItem(<?php echo $food_id; ?>)" 
            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition flex items-center justify-center">
        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
        </svg>
        Delete Listing
    </button>
                <?php elseif (isset($_SESSION['user_id']) && strtotime($food['expiration_time']) > time()): ?>
                    <a href="order_food.php?id=<?php echo $food_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Order Now
                    </a>
                    <a href="exchange_request.php?food_id=<?php echo $food_id; ?>" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition">
                        Request Exchange
                    </a>
                    <button onclick="saveForLater(<?php echo $food_id; ?>)" 
        class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center justify-center">
    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
    </svg>
    Save for Later
</button>
<!-- Ratings and Reviews -->
<div class="mt-8 border-t pt-6">
    <h3 class="text-xl font-semibold mb-4">Seller Ratings & Reviews</h3>
    
    <?php
    // Get average rating
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                          FROM ratings 
                          WHERE to_user_id = ?");
    $stmt->execute([$food['user_id']]);
    $rating_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all reviews
    $stmt = $pdo->prepare("SELECT r.*, u.name as user_name, u.profile_picture as user_image 
                          FROM ratings r 
                          JOIN users u ON r.from_user_id = u.id 
                          WHERE r.to_user_id = ? 
                          ORDER BY r.created_at DESC");
    $stmt->execute([$food['user_id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <div class="flex items-center mb-6">
        <div class="text-4xl font-bold mr-4">
            <?php echo number_format($rating_stats['avg_rating'] ?? 0, 1); ?>
            <span class="text-2xl text-gray-500">/5</span>
        </div>
        <div>
            <div class="flex items-center mb-1">
                <?php
                $avg_rating = $rating_stats['avg_rating'] ?? 0;
                $full_stars = floor($avg_rating);
                $half_star = ($avg_rating - $full_stars) >= 0.5;
                ?>
                
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($i <= $full_stars): ?>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                    <?php elseif ($half_star && $i == $full_stars + 1): ?>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <defs>
                                <linearGradient id="half-star" x1="0" x2="100%" y1="0" y2="0">
                                    <stop offset="50%" stop-color="currentColor"></stop>
                                    <stop offset="50%" stop-color="#D1D5DB"></stop>
                                </linearGradient>
                            </defs>
                            <path fill="url(#half-star)" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <p class="text-sm text-gray-600">
                <?php echo $rating_stats['total_reviews'] ?? 0; ?> reviews
            </p>
        </div>
    </div>
    
    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $food['user_id']): ?>
        <a href="ratings.php?user_id=<?php echo $food['user_id']; ?>&food_id=<?php echo $food_id; ?>" 
           class="inline-block mb-6 bg-blue-100 text-blue-600 px-4 py-2 rounded-lg hover:bg-blue-200 transition">
            <?php 
            // Check if user already rated this seller
            $stmt = $pdo->prepare("SELECT id FROM ratings WHERE from_user_id = ? AND to_user_id = ?");
            $stmt->execute([$_SESSION['user_id'], $food['user_id']]);
            echo $stmt->rowCount() > 0 ? 'Update Your Review' : 'Write a Review';
            ?>
        </a>
    <?php endif; ?>
    
    <?php if (!empty($reviews)): ?>
        <div class="space-y-4">
            <?php foreach ($reviews as $review): ?>
                <div class="border rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <img src="uploads/profile/<?php echo $review['user_image']; ?>" alt="<?php echo $review['user_name']; ?>" class="w-10 h-10 rounded-full mr-3">
                        <div>
                            <p class="font-medium"><?php echo $review['user_name']; ?></p>
                            <div class="flex items-center">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="w-4 h-4 <?php echo $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?>" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                <?php endfor; ?>
                                <span class="text-xs text-gray-500 ml-2">
                                    <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($review['review'])): ?>
                        <p class="text-gray-700"><?php echo htmlspecialchars($review['review']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-gray-500">No reviews yet. Be the first to review!</p>
    <?php endif; ?>
</div>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php?redirect=view_food.php?id=<?php echo $food_id; ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Login to Order
                    </a>
                <?php else: ?>
                    <button disabled class="bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed">
                        Expired Item
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Similar Items -->
            <?php if (!empty($similar_foods)): ?>
                <div class="mt-8">
                    <h3 class="text-xl font-semibold mb-4">Similar Items</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($similar_foods as $similar): ?>
                            <div class="border rounded-lg overflow-hidden hover:shadow-md transition">
                                <a href="view_food.php?id=<?php echo $similar['id']; ?>">
                                    <img src="uploads/food/<?php echo $similar['image']; ?>" alt="<?php echo $similar['title']; ?>" class="w-full h-32 object-cover">
                                    <div class="p-3">
                                        <h4 class="font-medium"><?php echo $similar['title']; ?></h4>
                                        <p class="text-green-600 font-bold">$<?php echo $similar['price']; ?></p>
                                        <p class="text-sm text-gray-500"><?php echo $similar['category']; ?></p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
async function deleteFoodItem(foodId) {
    if (!confirm('Are you sure you want to delete this food item? This action cannot be undone.')) {
        return;
    }

    const button = event.currentTarget;
    const originalHTML = button.innerHTML;
    
    // Show loading state
    button.innerHTML = `
        <span class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Deleting...
        </span>
    `;
    button.disabled = true;

    try {
        const response = await fetch('delete_food.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${foodId}`
        });

        // First check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Server returned invalid response: ${text.substring(0, 100)}`);
        }

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete item');
        }

        // Success - show message and redirect
        showToast('Food item deleted successfully!', 'success');
        
        // Redirect after 1.5 seconds
        setTimeout(() => {
            window.location.href = 'dashboard.php';
        }, 1500);

    } catch (error) {
        console.error('Delete error:', error);
        showToast(error.message, 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    }
}
async function saveForLater(foodId, buttonElement) {
    const button = buttonElement || event.currentTarget;
    const originalHTML = button.innerHTML;
    
    // Show loading state
    button.innerHTML = `
        <span class="flex items-center">
            <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
        </span>
    `;
    button.disabled = true;

    try {
        const response = await fetch('save_food.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `food_id=${foodId}`
        });

        // First check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error(`Server returned invalid response: ${text.substring(0, 100)}`);
        }

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to save item');
        }

        // Success - update button
        button.innerHTML = `
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                </svg>
                Saved
            </span>
        `;
        button.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        button.classList.add('bg-green-600', 'hover:bg-green-700');
        button.disabled = true;
        
        showToast('Item saved to your favorites!', 'success');
    } catch (error) {
        console.error('Save error:', error);
        showToast(error.message, 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    }
}
// Add this toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-md shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    }`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<?php include 'includes/footer.php'; ?>
