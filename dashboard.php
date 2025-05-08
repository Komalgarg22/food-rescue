<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Dashboard";
include 'includes/header.php';

// Get user's food listings
$stmt = $conn->prepare("SELECT * FROM food_items WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$food_items = $result->fetch_all(MYSQLI_ASSOC);

// Get user's orders (both as seller and buyer)
$stmt = $conn->prepare("SELECT o.*, f.title as food_title, f.image as food_image, 
                        u1.name as buyer_name, u2.name as seller_name
                        FROM orders o
                        JOIN food_items f ON o.food_id = f.id
                        JOIN users u1 ON o.buyer_id = u1.id
                        JOIN users u2 ON o.seller_id = u2.id
                        WHERE o.seller_id = ? OR o.buyer_id = ?
                        ORDER BY o.created_at DESC LIMIT 5");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);

// Get unread notifications count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$unread_notifications = $result->fetch_assoc()['count'];
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Quick Stats -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h3 class="text-xl font-semibold mb-4">Your Stats</h3>
        <div class="space-y-4">
            <div>
                <p class="text-gray-500">Active Listings</p>
                <p class="text-2xl font-bold"><?php echo count($food_items); ?></p>
            </div>
            <div>
                <p class="text-gray-500">Pending Orders</p>
                <p class="text-2xl font-bold">
                    <?php 
                    $count = 0;
                    foreach ($orders as $order) {
                        if ($order['status'] == 'pending' && $order['seller_id'] == $_SESSION['user_id']) {
                            $count++;
                        }
                    }
                    echo $count;
                    ?>
                </p>
            </div>
            <div>
                <p class="text-gray-500">Unread Notifications</p>
                <p class="text-2xl font-bold"><?php echo $unread_notifications; ?></p>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white p-6 rounded-lg shadow-md md:col-span-2">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Recent Orders</h3>
            <a href="track_order.php" class="text-green-600 hover:underline">View All</a>
        </div>
        
        <?php if (empty($orders)): ?>
            <p class="text-gray-500">No orders yet.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order): ?>
                    <div class="border-b pb-4">
                        <div class="flex justify-between">
                            <div class="flex items-center">
                                <img src="uploads/food/<?php echo $order['food_image']; ?>" alt="<?php echo $order['food_title']; ?>" class="w-12 h-12 rounded-md object-cover mr-3">
                                <div>
                                    <p class="font-semibold"><?php echo $order['food_title']; ?></p>
                                    <p class="text-sm text-gray-500">
                                        <?php if ($order['seller_id'] == $_SESSION['user_id']): ?>
                                            Buyer: <?php echo $order['buyer_name']; ?>
                                        <?php else: ?>
                                            Seller: <?php echo $order['seller_name']; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 rounded-full text-xs 
                                    <?php 
                                    switch($order['status']) {
                                        case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'accepted': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                        case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                    ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                                <p class="text-sm text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Your Listings -->
    <div class="bg-white p-6 rounded-lg shadow-md md:col-span-3">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold">Your Food Listings</h3>
            <a href="add_food.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">Add New</a>
        </div>
        
        <?php if (empty($food_items)): ?>
            <p class="text-gray-500">You haven't listed any food items yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($food_items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img src="uploads/food/<?php echo $item['image']; ?>" alt="<?php echo $item['title']; ?>" class="h-10 w-10 rounded-md object-cover">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo $item['title']; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $item['category']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    $<?php echo $item['price']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($item['expiration_time'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $expired = strtotime($item['expiration_time']) < time();
                                    echo $expired ? 
                                        '<span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-800">Expired</span>' : 
                                        '<span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">Active</span>';
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="edit_food.php?id=<?php echo $item['id']; ?>" class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                                    <!-- Example inside loop -->
<button class="delete-btn bg-red-500 text-white px-3 py-1 rounded" data-id="<?php echo $item['id']; ?>">Delete</button>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function () {
    $('.delete-btn').click(function () {
      const btn = $(this);
      const id = btn.data('id');

      if (!confirm("Are you sure you want to delete this food item?")) return;

      $.ajax({
        url: './delete_food.php',
        type: 'POST',
        data: { id: id },
        success: function (response) {
          if (response.status === 'success') {
            btn.closest('tr').fadeOut(); // Hide row (assuming it's in a <tr>)
          } else {
            alert(response.message || 'Something went wrong');
          }
        },
        error: function () {
          alert('Failed to connect to server.');
        }
      });
    });
  });
</script>


<?php include 'includes/footer.php'; ?>