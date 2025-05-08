<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: admin_panel.php');
    exit();
}

$page_title = "Admin Panel";
$active_tab = $_GET['tab'] ?? 'dashboard';

// Create database connection
$db = $conn;

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Get stats for dashboard
$stats = [];

// Total users
$result = $db->query("SELECT COUNT(*) as total_users FROM users");
$stats['total_users'] = $result->fetch_assoc()['total_users'];

// Total food items
$result = $db->query("SELECT COUNT(*) as total_food_items FROM food_items");
$stats['total_food_items'] = $result->fetch_assoc()['total_food_items'];

// Active food items
$result = $db->query("SELECT COUNT(*) as active_food_items FROM food_items WHERE expiration_time > NOW()");
$stats['active_food_items'] = $result->fetch_assoc()['active_food_items'];

// Total orders
$result = $db->query("SELECT COUNT(*) as total_orders FROM orders");
$stats['total_orders'] = $result->fetch_assoc()['total_orders'];

// Total exchanges
$result = $db->query("SELECT COUNT(*) as total_exchanges FROM exchanges");
$stats['total_exchanges'] = $result->fetch_assoc()['total_exchanges'];

// Get recent reports
$result = $db->query("SELECT r.*, u1.name as reporter_name, u2.name as reported_user_name 
                     FROM reports r
                     JOIN users u1 ON r.reporter_id = u1.id
                     JOIN users u2 ON r.reported_user_id = u2.id
                     ORDER BY r.created_at DESC LIMIT 5");
$recent_reports = [];
while ($row = $result->fetch_assoc()) {
    $recent_reports[] = $row;
}

// Get recent users
$result = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = [];
while ($row = $result->fetch_assoc()) {
    $recent_users[] = $row;
}

include 'includes/header.php';
?>

<div class="max-w-7xl">
    <div class="flex flex-col md:flex-row">
        <!-- Sidebar -->
        <div class="w-full md:w-64 bg-gray-800 text-white p-4">
            <h2 class="text-xl font-bold mb-6">Admin Panel</h2>
            <nav class="space-y-2">
                <a href="?tab=dashboard" class="block px-3 py-2 rounded-lg <?php echo $active_tab === 'dashboard' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition">
                    Dashboard
                </a>
                <a href="?tab=users" class="block px-3 py-2 rounded-lg <?php echo $active_tab === 'users' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition">
                    User Management
                </a>
                <a href="?tab=food" class="block px-3 py-2 rounded-lg <?php echo $active_tab === 'food' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition">
                    Food Listings
                </a>
                <a href="?tab=orders" class="block px-3 py-2 rounded-lg <?php echo $active_tab === 'orders' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition">
                    Orders
                </a>
                <a href="?tab=exchanges" class="block px-3 py-2 rounded-lg <?php echo $active_tab === 'exchanges' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition">
                    Exchanges
                </a>
                <a href="?tab=reports" class="block px-3 py-2 rounded-lg <?php echo $active_tab === 'reports' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition">
                    Reports
                </a>
                <a href="?tab=settings" class="block px-3 py-2 rounded-lg <?php echo $active_tab === 'settings' ? 'bg-gray-700' : 'hover:bg-gray-700'; ?> transition">
                    System Settings
                </a>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-6">
            <?php if ($active_tab === 'dashboard'): ?>
                <h2 class="text-2xl font-bold mb-6">Dashboard Overview</h2>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Total Users</h3>
                        <p class="text-3xl font-bold"><?php echo $stats['total_users']; ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Active Food Listings</h3>
                        <p class="text-3xl font-bold"><?php echo $stats['active_food_items']; ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-gray-500 text-sm font-medium mb-1">Completed Orders</h3>
                        <p class="text-3xl font-bold"><?php echo $stats['total_orders']; ?></p>
                    </div>
                </div>
                
                <!-- Recent Reports -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-semibold">Recent Reports</h3>
                    </div>
                    <div class="divide-y">
                        <?php if (empty($recent_reports)): ?>
                            <p class="p-4 text-gray-500">No recent reports.</p>
                        <?php else: ?>
                            <?php foreach ($recent_reports as $report): ?>
                                <div class="p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium"><?php echo $report['reporter_name']; ?> reported <?php echo $report['reported_user_name']; ?></p>
                                            <p class="text-sm text-gray-600"><?php echo $report['reason']; ?></p>
                                        </div>
                                        <span class="text-xs text-gray-500"><?php echo date('M j, g:i A', strtotime($report['created_at'])); ?></span>
                                    </div>
                                    <div class="mt-2 flex gap-2">
                                        <a href="admin_user.php?id=<?php echo $report['reported_user_id']; ?>" class="text-blue-600 hover:underline text-sm">View User</a>
                                        <a href="#" class="text-green-600 hover:underline text-sm">Mark Resolved</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Users -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-semibold">Recent Users</h3>
                    </div>
                    <div class="divide-y">
                        <?php if (empty($recent_users)): ?>
                            <p class="p-4 text-gray-500">No recent users.</p>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($recent_users as $user): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <img src="uploads/profile/<?php echo $user['profile_picture'] ?? 'default.png'; ?>" alt="<?php echo $user['name']; ?>" class="h-10 w-10 rounded-full">
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo $user['name']; ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo ucfirst($user['role']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $user['email']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="admin_user.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                                <a href="#" class="text-green-600 hover:text-green-900">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
            <?php elseif ($active_tab === 'users'): ?>
                <h2 class="text-2xl font-bold mb-6">User Management</h2>
                <?php
                // Get all users with pagination
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                $stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                
                // Get total count for pagination
                $result = $db->query("SELECT COUNT(*) as total FROM users");
                $total_users = $result->fetch_assoc()['total'];
                $total_pages = ceil($total_users / $limit);
                ?>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="text-lg font-semibold">All Users</h3>
                        <div class="relative">
                            <input type="text" placeholder="Search users..." class="pl-8 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img src="uploads/profile/<?php echo $user['profile_picture'] ?? 'default.png'; ?>" alt="<?php echo $user['name']; ?>" class="h-10 w-10 rounded-full">
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo $user['name']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $user['email']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs rounded-full <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="admin_user.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                            <a href="#" class="text-green-600 hover:text-green-900 mr-3">Edit</a>
                                            <a href="#" class="text-red-600 hover:text-red-900">Ban</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_users); ?> of <?php echo $total_users; ?> users
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?tab=users&page=<?php echo $page - 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?tab=users&page=<?php echo $i; ?>" class="px-3 py-1 border rounded-lg <?php echo $i === $page ? 'bg-green-600 text-white' : 'hover:bg-gray-100'; ?> transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?tab=users&page=<?php echo $page + 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($active_tab === 'food'): ?>
                <h2 class="text-2xl font-bold mb-6">Food Listings Management</h2>
                <?php
                // Get all food items with pagination
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                $stmt = $db->prepare("SELECT f.*, u.name as user_name 
                                    FROM food_items f
                                    JOIN users u ON f.user_id = u.id
                                    ORDER BY f.created_at DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                $food_items = [];
                while ($row = $result->fetch_assoc()) {
                    $food_items[] = $row;
                }
                
                // Get total count for pagination
                $result = $db->query("SELECT COUNT(*) as total FROM food_items");
                $total_food = $result->fetch_assoc()['total'];
                $total_pages = ceil($total_food / $limit);
                ?>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="text-lg font-semibold">All Food Listings</h3>
                        <div class="flex space-x-3">
                            <select class="border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-600">
                                <option>Filter by status</option>
                                <option>Active</option>
                                <option>Expired</option>
                            </select>
                            <div class="relative">
                                <input type="text" placeholder="Search food..." class="pl-8 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                                <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Food Item</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
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
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo $item['user_name']; ?></div>
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
                                            <a href="view_food.php?id=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">View</a>
                                            <a href="#" class="text-red-600 hover:text-red-900">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_food); ?> of <?php echo $total_food; ?> listings
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?tab=food&page=<?php echo $page - 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?tab=food&page=<?php echo $i; ?>" class="px-3 py-1 border rounded-lg <?php echo $i === $page ? 'bg-green-600 text-white' : 'hover:bg-gray-100'; ?> transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?tab=food&page=<?php echo $page + 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($active_tab === 'reports'): ?>
                <h2 class="text-2xl font-bold mb-6">User Reports</h2>
                <?php
                // Get all reports with pagination
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                $stmt = $db->prepare("SELECT r.*, u1.name as reporter_name, u2.name as reported_user_name 
                                    FROM reports r
                                    JOIN users u1 ON r.reporter_id = u1.id
                                    JOIN users u2 ON r.reported_user_id = u2.id
                                    ORDER BY r.created_at DESC LIMIT ? OFFSET ?");
                $stmt->bind_param("ii", $limit, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                $reports = [];
                while ($row = $result->fetch_assoc()) {
                    $reports[] = $row;
                }
                
                // Get total count for pagination
                $result = $db->query("SELECT COUNT(*) as total FROM reports");
                $total_reports = $result->fetch_assoc()['total'];
                $total_pages = ceil($total_reports / $limit);
                ?>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-semibold">All Reports</h3>
                    </div>
                    <div class="divide-y">
                        <?php if (empty($reports)): ?>
                            <p class="p-4 text-gray-500">No reports found.</p>
                        <?php else: ?>
                            <?php foreach ($reports as $report): ?>
                                <div class="p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-medium">
                                                <a href="admin_user.php?id=<?php echo $report['reporter_id']; ?>" class="text-blue-600 hover:underline"><?php echo $report['reporter_name']; ?></a>
                                                reported 
                                                <a href="admin_user.php?id=<?php echo $report['reported_user_id']; ?>" class="text-blue-600 hover:underline"><?php echo $report['reported_user_name']; ?></a>
                                            </p>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo $report['reason']; ?></p>
                                        </div>
                                        <span class="text-xs text-gray-500"><?php echo date('M j, g:i A', strtotime($report['created_at'])); ?></span>
                                    </div>
                                    <div class="mt-3 flex gap-3">
                                        <a href="admin_user.php?id=<?php echo $report['reported_user_id']; ?>" class="text-blue-600 hover:underline text-sm">View Reported User</a>
                                        <a href="#" class="text-green-600 hover:underline text-sm">Mark Resolved</a>
                                        <a href="#" class="text-red-600 hover:underline text-sm">Ban User</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="p-4 border-t flex justify-between items-center">
                        <div class="text-sm text-gray-500">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_reports); ?> of <?php echo $total_reports; ?> reports
                        </div>
                        <div class="flex space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?tab=reports&page=<?php echo $page - 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?tab=reports&page=<?php echo $i; ?>" class="px-3 py-1 border rounded-lg <?php echo $i === $page ? 'bg-green-600 text-white' : 'hover:bg-gray-100'; ?> transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?tab=reports&page=<?php echo $page + 1; ?>" class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($active_tab === 'settings'): ?>
                <h2 class="text-2xl font-bold mb-6">System Settings</h2>
                
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 border-b">
                        <h3 class="text-lg font-semibold">Application Settings</h3>
                    </div>
                    <div class="p-6">
                        <form>
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2">Site Name</label>
                                <input type="text" value="Food Rescue" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2">Site Description</label>
                                <textarea rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">A platform to rescue surplus food and connect with your community</textarea>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2">Maintenance Mode</label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" class="form-checkbox h-5 w-5 text-green-600">
                                    <span class="ml-2">Enable maintenance mode</span>
                                </label>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2">New User Registration</label>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" checked class="form-checkbox h-5 w-5 text-green-600">
                                    <span class="ml-2">Allow new user registration</span>
                                </label>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2">Food Listing Duration</label>
                                <select class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                                    <option>24 hours</option>
                                    <option selected>48 hours</option>
                                    <option>72 hours</option>
                                    <option>1 week</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                Save Settings
                            </button>
                        </form>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
include 'includes/footer.php';
$db->close();
?>