<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($conn) || !$conn instanceof mysqli || !$conn->ping()) {
    die("Database connection failed. Please try again later.");
}

$user_id = $_SESSION['user_id'];
$mysqli = $conn;

$filters = [
    'status' => isset($_GET['status']) ? sanitizeInput($_GET['status']) : '',
    'date_from' => isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '',
    'search' => isset($_GET['search']) ? sanitizeInput($_GET['search']) : ''
];

$allowed_statuses = ['pending', 'accepted', 'delivered', 'cancelled'];
if ($filters['status'] && !in_array($filters['status'], $allowed_statuses)) {
    $filters['status'] = '';
}

$required_tables = ['orders', 'food_items', 'users', 'messages'];
foreach ($required_tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if (!$result || $result->num_rows == 0) {
        die("System error: Required table '$table' not found");
    }
}

// New subquery using LEFT JOIN for unread messages
$query = "
SELECT 
    o.id AS order_id,
    o.status,
    o.created_at AS order_date,
    f.id AS food_id,
    f.title AS food_title,
    f.image AS food_image,
    f.price,
    u.id AS seller_id,
    u.name AS seller_name,
    COALESCE(m.unread_messages, 0) AS unread_messages
FROM orders o
INNER JOIN food_items f ON o.food_id = f.id
INNER JOIN users u ON o.seller_id = u.id
LEFT JOIN (
    SELECT order_id, COUNT(*) AS unread_messages
    FROM messages
    WHERE is_read = 0 AND sender_id != ?
    GROUP BY order_id
) m ON o.id = m.order_id
WHERE o.buyer_id = ?
";

$params = [$user_id, $user_id];
$types = 'ii';

if (!empty($filters['status'])) {
    $query .= " AND o.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if (!empty($filters['date_from']) && validateDate($filters['date_from'], 'Y-m-d')) {
    $query .= " AND o.created_at >= ?";
    $params[] = $filters['date_from'];
    $types .= 's';
}

if (!empty($filters['date_to']) && validateDate($filters['date_to'], 'Y-m-d')) {
    $query .= " AND o.created_at <= ?";
    $params[] = $filters['date_to'] . ' 23:59:59';
    $types .= 's';
}

if (!empty($filters['search'])) {
    $query .= " AND (f.title LIKE ? OR u.name LIKE ?)";
    $params[] = '%' . $filters['search'] . '%';
    $params[] = '%' . $filters['search'] . '%';
    $types .= 'ss';
}

$query .= " ORDER BY o.created_at DESC";

error_log("[My Orders] Final Query: " . $query);
error_log("[My Orders] Params: " . print_r($params, true));

if (!$stmt = $mysqli->prepare($query)) {
    error_log("Prepare failed: " . $mysqli->error);
    die("System error: Could not prepare query. Please try again later.");
}

if (!$stmt->bind_param($types, ...$params)) {
    error_log("Bind failed: " . $stmt->error);
    die("System error: Parameter binding failed.");
}

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("System error: Query execution failed.");
}

if (!$result = $stmt->get_result()) {
    error_log("Get result failed: " . $stmt->error);
    die("System error: Could not retrieve results.");
}

$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_spent = 0;
foreach ($orders as $order) {
    $total_spent += (float)$order['price'];
}

$page_title = "My Orders";
include 'includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">My Orders</h1>
        <div class="text-lg font-medium text-gray-600">
            Total Spent: <span class="text-green-600">$<?= htmlspecialchars(number_format($total_spent, 2)); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <form method="GET" action="my_orders.php" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label for="status" class="block mb-1 text-sm font-medium">Status</label>
                <select name="status" id="status" class="w-full border-gray-300 rounded-md">
                    <option value="">All</option>
                    <?php foreach ($allowed_statuses as $status): ?>
                        <option value="<?= htmlspecialchars($status); ?>" <?= $filters['status'] === $status ? 'selected' : ''; ?>>
                            <?= htmlspecialchars(ucfirst($status)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="date_from" class="block mb-1 text-sm font-medium">From</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filters['date_from']); ?>" class="w-full border-gray-300 rounded-md">
            </div>
            <div>
                <label for="date_to" class="block mb-1 text-sm font-medium">To</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filters['date_to']); ?>" class="w-full border-gray-300 rounded-md">
            </div>
            <div>
                <label for="search" class="block mb-1 text-sm font-medium">Search</label>
                <div class="flex">
                    <input type="text" name="search" id="search" value="<?= htmlspecialchars($filters['search']); ?>" placeholder="Food or seller..." class="w-full border-gray-300 rounded-l-md">
                    <button type="submit" class="bg-green-600 text-white px-4 rounded-r-md hover:bg-green-700">Search</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($orders)): ?>
            <div class="p-6 text-center text-gray-600">
                <h2 class="text-xl font-semibold">No orders found.</h2>
                <p class="mt-2">You haven't placed any orders yet.</p>
                <a href="index.php" class="mt-4 inline-block bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">Browse Food</a>
            </div>
        <?php else: ?>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500">Order ID</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500">Food</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500">Seller</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500">Price</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500">Date</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-gray-500">Status</th>
                        <th class="px-6 py-3 text-right text-sm font-semibold text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    <?php foreach ($orders as $order): ?>
                        <tr class="<?= $order['unread_messages'] > 0 ? 'bg-blue-50' : ''; ?>">
                            <td class="px-6 py-4 text-sm text-gray-700 font-medium">#<?= htmlspecialchars($order['order_id']); ?></td>
                            <td class="px-6 py-4 flex items-center space-x-3">
                                <img src="uploads/food/<?= htmlspecialchars($order['food_image']); ?>" alt="<?= htmlspecialchars($order['food_title']); ?>" class="w-12 h-12 rounded object-cover">
                                <span class="text-sm"><?= htmlspecialchars($order['food_title']); ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($order['seller_name']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700">$<?= number_format($order['price'], 2); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars(date("Y-m-d", strtotime($order['order_date']))); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= ucfirst(htmlspecialchars($order['status'])); ?></td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="order_details.php?id=<?= urlencode($order['order_id']); ?>" class="text-green-600 hover:text-green-800">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
