<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$page_title = "My Orders";

// Fetch orders placed by the user (as buyer)
$placed_orders = [];
$stmt_placed = $conn->prepare("
    SELECT o.id AS order_id, o.status, o.created_at, 
           f.title AS product_name, f.price,
           u.name AS seller_name, u.id AS seller_id
    FROM orders o
    JOIN food_items f ON o.food_id = f.id
    JOIN users u ON f.user_id = u.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
if (!$stmt_placed) {
    die("Prepare failed: " . $conn->error);
}

$stmt_placed->bind_param("i", $user_id);
$stmt_placed->execute();
$result_placed = $stmt_placed->get_result();
while ($row = $result_placed->fetch_assoc()) {
    $placed_orders[] = $row;
}
$stmt_placed->close();

// Fetch orders received by the user (as seller)
$received_orders = [];
$stmt_received = $conn->prepare("
    SELECT o.id AS order_id, o.status, o.created_at, 
           f.title AS product_name, f.price,
           u.name AS buyer_name, u.email AS buyer_email, u.id AS buyer_id
    FROM orders o
    JOIN food_items f ON o.food_id = f.id
    JOIN users u ON o.buyer_id = u.id
    WHERE f.user_id = ? AND o.buyer_id != ?
    ORDER BY o.created_at DESC
");
if (!$stmt_received) {
    die("Prepare failed: " . $conn->error);
}

$stmt_received->bind_param("ii", $user_id, $user_id);
$stmt_received->execute();
$result_received = $stmt_received->get_result();
while ($row = $result_received->fetch_assoc()) {
    $received_orders[] = $row;
}
$stmt_received->close();

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">My Orders</h1>

    <!-- Orders Placed Section -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4 border-b pb-2">Orders I've Placed</h2>
        
        <?php if (empty($placed_orders)): ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="mt-4 text-gray-600">You haven't placed any orders yet.</p>
                <a href="products.php" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seller</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($placed_orders as $order): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['seller_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">$<?php echo number_format($order['price'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo getStatusColorClass($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="track_order.php?id=<?php echo $order['order_id']; ?>" class="text-green-600 hover:text-green-900 mr-2">View</a>
                                    <a href="messages.php?to=<?php echo $order['seller_id']; ?>&order=<?php echo $order['order_id']; ?>" class="text-blue-600 hover:text-blue-900">Message</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Orders Received Section -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4 border-b pb-2">Orders Received</h2>
        
        <?php if (empty($received_orders)): ?>
            <div class="text-center py-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <p class="mt-4 text-gray-600">You haven't received any orders yet.</p>
                <a href="add_product.php" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">Add Products</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Buyer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($received_orders as $order): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['product_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($order['buyer_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['buyer_email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">$<?php echo number_format($order['price'], 2); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo getStatusColorClass($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="track_order.php?id=<?php echo $order['order_id']; ?>" class="text-green-600 hover:text-green-900 mr-2">View</a>
                                    <a href="messages.php?to=<?php echo $order['buyer_id']; ?>&order=<?php echo $order['order_id']; ?>" class="text-blue-600 hover:text-blue-900">Message</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
// Helper function to get Tailwind color classes based on status
function getStatusColorClass($status) {
    switch ($status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'accepted': return 'bg-blue-100 text-blue-800';
        case 'preparing': return 'bg-purple-100 text-purple-800';
        case 'ready': return 'bg-green-100 text-green-800';
        case 'delivered': return 'bg-green-500 text-white';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

include 'includes/footer.php'; 
?>