<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch order
$stmt = $conn->prepare("SELECT o.*, f.title AS food_title, f.image AS food_image, 
    u1.name AS buyer_name, u2.name AS seller_name,
    u1.latitude AS buyer_lat, u1.longitude AS buyer_lng,
    u2.latitude AS seller_lat, u2.longitude AS seller_lng
    FROM orders o
    JOIN food_items f ON o.food_id = f.id
    JOIN users u1 ON o.buyer_id = u1.id
    JOIN users u2 ON o.seller_id = u2.id
    WHERE o.id = ? AND (o.buyer_id = ? OR o.seller_id = ?)");
$stmt->bind_param("iii", $order_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    $_SESSION['error'] = 'Order not found or you do not have permission to view it.';
    header('Location: dashboard.php');
    exit();
}

$page_title = "Order #" . $order_id;
$is_seller = $order['seller_id'] == $user_id;

include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Header -->
        <div class="bg-green-600 p-6 text-white">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold">Order #<?php echo $order_id; ?></h1>
                    <p class="text-green-100"><?php echo htmlspecialchars($order['food_title']); ?></p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 rounded-full text-sm font-bold 
                        <?php 
                        switch($order['status']) {
                            case 'pending': echo 'bg-yellow-500'; break;
                            case 'accepted': echo 'bg-blue-500'; break;
                            case 'delivered': echo 'bg-green-500'; break;
                            case 'cancelled': echo 'bg-red-500'; break;
                            default: echo 'bg-gray-500';
                        }
                        ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                    <p class="text-sm text-green-100 mt-1"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Left: Order Details -->
                <div class="md:col-span-2">
                    <h2 class="text-xl font-semibold mb-4">Order Details</h2>
                    <div class="flex items-start mb-6">
                        <img src="uploads/food/<?php echo htmlspecialchars($order['food_image']); ?>" class="w-16 h-16 rounded-md object-cover mr-4">
                        <div>
                            <h3 class="font-bold"><?php echo htmlspecialchars($order['food_title']); ?></h3>
                            <p class="text-green-600 font-bold">
                                $<?php
                                $stmt = $conn->prepare("SELECT price FROM food_items WHERE id = ?");
                                $stmt->bind_param("i", $order['food_id']);
                                $stmt->execute();
                                $price_result = $stmt->get_result();
                                $price_data = $price_result->fetch_assoc();
                                echo htmlspecialchars($price_data['price']);
                                $stmt->close();
                                ?>
                            </p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div><p class="text-sm text-gray-500">Buyer</p><p class="font-medium"><?php echo htmlspecialchars($order['buyer_name']); ?></p></div>
                        <div><p class="text-sm text-gray-500">Seller</p><p class="font-medium"><?php echo htmlspecialchars($order['seller_name']); ?></p></div>
                        <div><p class="text-sm text-gray-500">Order Date</p><p class="font-medium"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p></div>
                        <div><p class="text-sm text-gray-500">Status</p><p class="font-medium"><?php echo ucfirst($order['status']); ?></p></div>
                    </div>
                </div>

                <!-- Right: Actions -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">Actions</h2>

                    <?php if ($is_seller): ?>
                        <?php if ($order['status'] == 'pending'): ?>
                            <form action="update_order_status.php" method="POST" class="mb-3">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <input type="hidden" name="status" value="accepted">
                                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">Accept Order</button>
                            </form>
                            <form action="update_order_status.php" method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <input type="hidden" name="status" value="cancelled">
                                <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">Cancel Order</button>
                            </form>
                        <?php elseif ($order['status'] == 'accepted'): ?>
                            <form action="update_order_status.php" method="POST">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <input type="hidden" name="status" value="delivered">
                                <button type="submit" class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700">Mark as Delivered</button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($order['status'] == 'pending'): ?>
                        <form action="update_order_status.php" method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-lg hover:bg-red-700">Cancel Order</button>
                        </form>
                    <?php endif; ?>

                    <a href="messages.php?order=<?php echo $order_id; ?>" class="block mt-3 bg-blue-600 text-white text-center py-2 rounded-lg hover:bg-blue-700">Message <?php echo $is_seller ? 'Buyer' : 'Seller'; ?></a>
                </div>
            </div>

            <!-- Map -->
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Pickup Location</h2>
                <div id="map" class="h-64 w-full rounded-lg border-2 border-gray-200"></div>
            </div>

            <!-- Timeline -->
            <div>
                <h2 class="text-xl font-semibold mb-4">Order Timeline</h2>
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="bg-green-600 rounded-full p-2 mr-4">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div>
                            <p class="font-medium">Order Placed</p>
                            <p class="text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>

                    <?php
                    if ($order['status'] != 'pending') {
                        $check_status = $order['status'] == 'cancelled' ? 'cancelled' : 'accepted';
                        $stmt = $conn->prepare("SELECT created_at FROM order_status_history WHERE order_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1");
                        $stmt->bind_param("is", $order_id, $check_status);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $status_date = $result->fetch_assoc();
                        $stmt->close();
                    ?>
                        <div class="flex items-start">
                            <div class="bg-green-600 rounded-full p-2 mr-4">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="font-medium">Order <?php echo ucfirst($check_status); ?></p>
                                <p class="text-sm text-gray-500"><?php echo $status_date ? date('M j, Y g:i A', strtotime($status_date['created_at'])) : 'N/A'; ?></p>
                            </div>
                        </div>
                    <?php } ?>

                    <?php
                    if ($order['status'] == 'delivered') {
                        $stmt = $conn->prepare("SELECT created_at FROM order_status_history WHERE order_id = ? AND status = 'delivered' ORDER BY created_at DESC LIMIT 1");
                        $stmt->bind_param("i", $order_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $delivered = $result->fetch_assoc();
                        $stmt->close();
                    ?>
                        <div class="flex items-start">
                            <div class="bg-green-600 rounded-full p-2 mr-4">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div>
                                <p class="font-medium">Order Delivered</p>
                                <p class="text-sm text-gray-500"><?php echo $delivered ? date('M j, Y g:i A', strtotime($delivered['created_at'])) : 'N/A'; ?></p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet.js Map Integration -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sellerLat = <?php echo $order['seller_lat']; ?>;
    const sellerLng = <?php echo $order['seller_lng']; ?>;
    const map = L.map('map').setView([sellerLat, sellerLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([sellerLat, sellerLng]).addTo(map).bindPopup('Seller Location').openPopup();

    <?php if ($order['buyer_lat'] && $order['buyer_lng']): ?>
    const buyerLat = <?php echo $order['buyer_lat']; ?>;
    const buyerLng = <?php echo $order['buyer_lng']; ?>;
    L.marker([buyerLat, buyerLng]).addTo(map).bindPopup('Your Location');
    map.fitBounds([
        [sellerLat, sellerLng],
        [buyerLat, buyerLng]
    ]);
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
