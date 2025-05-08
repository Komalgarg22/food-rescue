<?php
require_once 'includes/auth.php';
require_once 'includes/db.php'; // Should contain MySQLi connection as $conn
require_once 'includes/functions.php';

// Enhanced security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    header('Location: login.php');
    exit();
}

// Validate user ID
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: admin_panel.php?tab=users');
    exit();
}

$user_id = (int)$_GET['id'];

try {
    // Get user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: admin_panel.php?tab=users');
        exit();
    }

    $page_title = "User: " . htmlspecialchars($user['name']);

    // Get user's food listings
    $stmt = $conn->prepare("SELECT * FROM food_items WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $food_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get user's orders
    $stmt = $conn->prepare("SELECT o.*, f.title as food_title, 
                          CASE WHEN o.buyer_id = ? THEN 'Buyer' ELSE 'Seller' END as user_role
                          FROM orders o
                          JOIN food_items f ON o.food_id = f.id
                          WHERE o.buyer_id = ? OR o.seller_id = ?
                          ORDER BY o.created_at DESC LIMIT 5");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get user's exchange requests
    $stmt = $conn->prepare("SELECT e.*, 
                          f1.title as from_food_title, f2.title as to_food_title,
                          CASE WHEN e.from_user_id = ? THEN 'Initiator' ELSE 'Recipient' END as user_role
                          FROM exchanges e
                          JOIN food_items f1 ON e.from_food_id = f1.id
                          JOIN food_items f2 ON e.to_food_id = f2.id
                          WHERE e.from_user_id = ? OR e.to_user_id = ?
                          ORDER BY e.created_at DESC LIMIT 5");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $exchanges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get reports about this user
    $stmt = $conn->prepare("SELECT r.*, u.name as reporter_name 
                          FROM reports r
                          JOIN users u ON r.reporter_id = u.id
                          WHERE r.reported_user_id = ?
                          ORDER BY r.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (mysqli_sql_exception $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = 'Database error occurred.';
    header('Location: admin_panel.php?tab=users');
    exit();
}

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">User Details</h2>
        <a href="admin_panel.php?tab=users" class="text-blue-600 hover:underline">Back to Users</a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
        <div class="p-6">
            <div class="flex flex-col md:flex-row gap-6">
                <div class="flex-shrink-0">
                    <img src="uploads/profile/<?php echo htmlspecialchars($user['profile_picture'] ?? 'default.png'); ?>" 
                         alt="<?php echo htmlspecialchars($user['name']); ?>" 
                         class="w-32 h-32 rounded-full object-cover border-4 border-gray-200">
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-xl font-bold"><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="text-gray-600"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-sm font-bold <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                            <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                        </span>
                    </div>
                    
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Address</p>
                            <p class="text-gray-800"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Member Since</p>
                            <p class="text-gray-800"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Last Login</p>
                            <p class="text-gray-800"><?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <p class="text-gray-800"><?php echo empty($user['banned_at']) ? 'Active' : 'Banned'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex gap-3">
                <a href="edit_user.php?id=<?php echo $user_id; ?>" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    Edit Profile
                </a>
                <?php if ($user['role'] !== 'admin'): ?>
                    <form action="admin_actions.php" method="post" class="inline">
                        <input type="hidden" name="action" value="make_admin">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            Make Admin
                        </button>
                    </form>
                <?php endif; ?>
                <form action="admin_actions.php" method="post" class="inline" onsubmit="return confirm('Are you sure you want to perform this action?');">
                    <input type="hidden" name="action" value="<?php echo empty($user['banned_at']) ? 'ban_user' : 'unban_user'; ?>">
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                        <?php echo empty($user['banned_at']) ? 'Ban User' : 'Unban User'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- User Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-gray-500 text-sm font-medium mb-1">Food Listings</h3>
            <p class="text-3xl font-bold">
                <?php 
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM food_items WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo htmlspecialchars($result->fetch_assoc()['count']);
                $stmt->close();
                ?>
            </p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-gray-500 text-sm font-medium mb-1">Completed Orders</h3>
            <p class="text-3xl font-bold">
                <?php 
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE (buyer_id = ? OR seller_id = ?) AND status = 'delivered'");
                $stmt->bind_param("ii", $user_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo htmlspecialchars($result->fetch_assoc()['count']);
                $stmt->close();
                ?>
            </p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-gray-500 text-sm font-medium mb-1">Exchange Requests</h3>
            <p class="text-3xl font-bold">
                <?php 
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM exchanges WHERE from_user_id = ? OR to_user_id = ?");
                $stmt->bind_param("ii", $user_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo htmlspecialchars($result->fetch_assoc()['count']);
                $stmt->close();
                ?>
            </p>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Food Listings -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold">Recent Food Listings</h3>
            </div>
            <div class="divide-y">
                <?php if (empty($food_items)): ?>
                    <p class="p-4 text-gray-500">No food listings found.</p>
                <?php else: ?>
                    <?php foreach ($food_items as $item): ?>
                        <div class="p-4">
                            <div class="flex items-center">
                                <img src="uploads/food/<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     class="w-12 h-12 rounded-md object-cover mr-3">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($item['title']); ?></p>
                                    <p class="text-sm text-gray-500">$<?php echo htmlspecialchars($item['price']); ?> • <?php echo htmlspecialchars($item['category']); ?></p>
                                </div>
                            </div>
                            <div class="mt-2 flex justify-between items-center">
                                <span class="text-xs <?php echo strtotime($item['expiration_time']) < time() ? 'text-red-500' : 'text-green-500'; ?>">
                                    <?php echo strtotime($item['expiration_time']) < time() ? 'Expired' : 'Active'; ?>
                                </span>
                                <a href="view_food.php?id=<?php echo (int)$item['id']; ?>" class="text-blue-600 hover:underline text-xs">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="p-4 border-t">
                    <a href="admin_panel.php?tab=food&user=<?php echo $user_id; ?>" class="text-blue-600 hover:underline">View all listings</a>
                </div>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold">Recent Orders</h3>
            </div>
            <div class="divide-y">
                <?php if (empty($orders)): ?>
                    <p class="p-4 text-gray-500">No orders found.</p>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="p-4">
                            <div class="flex justify-between">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($order['food_title']); ?></p>
                                    <p class="text-sm text-gray-500">As <?php echo htmlspecialchars($order['user_role']); ?></p>
                                </div>
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
                                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-2"><?php echo date('M j, g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="p-4 border-t">
                    <a href="admin_panel.php?tab=orders&user=<?php echo $user_id; ?>" class="text-blue-600 hover:underline">View all orders</a>
                </div>
            </div>
        </div>
        
        <!-- Recent Reports -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold">Reports About This User</h3>
            </div>
            <div class="divide-y">
                <?php if (empty($reports)): ?>
                    <p class="p-4 text-gray-500">No reports found.</p>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <div class="p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium">Reported by <?php echo htmlspecialchars($report['reporter_name']); ?></p>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($report['reason']); ?></p>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo date('M j, g:i A', strtotime($report['created_at'])); ?></span>
                            </div>
                            <div class="mt-2">
                                <form action="admin_actions.php" method="post" class="inline">
                                    <input type="hidden" name="action" value="resolve_report">
                                    <input type="hidden" name="report_id" value="<?php echo (int)$report['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <button type="submit" class="text-green-600 hover:underline text-xs">Mark Resolved</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div class="p-4 border-t">
                    <a href="admin_panel.php?tab=reports&user=<?php echo $user_id; ?>" class="text-blue-600 hover:underline">View all reports</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>