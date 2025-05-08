<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$page_title = "Saved Food Items";
include 'includes/header.php';

// Create database connection
$db = $conn;

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get saved items with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("SELECT f.*, u.name as user_name 
                     FROM saved_items s
                     JOIN food_items f ON s.food_id = f.id
                     JOIN users u ON f.user_id = u.id
                     WHERE s.user_id = ?
                     ORDER BY s.saved_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $_SESSION['user_id'], $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$saved_items = [];
while ($row = $result->fetch_assoc()) {
    $saved_items[] = $row;
}

// Get total count for pagination
$stmt = $db->prepare("SELECT COUNT(*) as total FROM saved_items WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$total_items = $result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Your Saved Food Items</h1>
    
    <?php if (empty($saved_items)): ?>
        <div class="bg-white rounded-lg shadow-md p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <h3 class="mt-2 text-lg font-medium text-gray-900">No saved items yet</h3>
            <p class="mt-1 text-gray-500">Save food items to see them here.</p>
            <div class="mt-6">
                <a href="browse.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                    Browse Food Items
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($saved_items as $item): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-300">
                    <a href="view_food.php?id=<?php echo $item['id']; ?>">
                        <div class="relative h-48">
                            <img src="uploads/food/<?php echo $item['image']; ?>" alt="<?php echo $item['title']; ?>" class="w-full h-full object-cover">
                            <?php if (strtotime($item['expiration_time']) < time()): ?>
                                <div class="absolute top-2 right-2 bg-red-600 text-white px-2 py-1 rounded-full text-xs font-bold">
                                    Expired
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-bold text-lg"><?php echo $item['title']; ?></h3>
                                    <p class="text-gray-500 text-sm"><?php echo $item['category']; ?></p>
                                </div>
                                <span class="font-bold text-green-600">$<?php echo $item['price']; ?></span>
                            </div>
                            <div class="mt-2 flex justify-between items-center">
                                <span class="text-sm text-gray-500">
                                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <?php echo $item['user_name']; ?>
                                </span>
                                <button onclick="removeSavedItem(event, <?php echo $item['id']; ?>)" class="text-red-500 hover:text-red-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="px-3 py-1 border rounded-lg <?php echo $i === $page ? 'bg-green-600 text-white' : 'hover:bg-gray-100'; ?> transition">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">
                            Next
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function removeSavedItem(event, foodId) {
    event.preventDefault();
    event.stopPropagation();
    
    if (confirm('Remove this item from your saved list?')) {
        fetch('remove_saved_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'food_id=' + foodId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the item from the DOM
                event.target.closest('.bg-white').remove();
                // Show success message
                alert('Item removed from your saved list');
                // Reload if no items left
                if (document.querySelectorAll('.bg-white').length === 0) {
                    location.reload();
                }
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
} f
</script>

<?php 
$db->close();
include 'includes/footer.php'; 
?>