<?php
session_start();

require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$page_title = "Admin Dashboard";

// Get stats for dashboard
$users_count = 0;
$active_users = 0;
$recent_users = 0;

// Prepare statements to get counts
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $users_count = $row['count'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 30 DAY)");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $active_users = $row['count'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $recent_users = $row['count'];
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Admin Dashboard</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Users Card -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Users</h3>
            <p class="text-3xl font-bold text-green-600"><?php echo $users_count; ?></p>
            <p class="text-sm text-gray-500 mt-2">All registered users</p>
        </div>
        
        <!-- Active Users Card -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Active Users</h3>
            <p class="text-3xl font-bold text-blue-600"><?php echo $active_users; ?></p>
            <p class="text-sm text-gray-500 mt-2">Logged in last 30 days</p>
        </div>
        
        <!-- New Users Card -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">New Users</h3>
            <p class="text-3xl font-bold text-purple-600"><?php echo $recent_users; ?></p>
            <p class="text-sm text-gray-500 mt-2">Registered last 7 days</p>
        </div>
    </div>
    
    <!-- Recent Users Table -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-xl font-semibold mb-4">Recent Users</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">ID</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Name</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Email</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Role</th>
                        <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Last Login</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT id, name, email, role, last_login FROM users ORDER BY last_login DESC LIMIT 5");
                    if ($stmt) {
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td class='py-2 px-4 border-b border-gray-200'>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td class='py-2 px-4 border-b border-gray-200'>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td class='py-2 px-4 border-b border-gray-200'>" . htmlspecialchars($row['email']) . "</td>";
                                echo "<td class='py-2 px-4 border-b border-gray-200'>" . htmlspecialchars($row['role']) . "</td>";
                                echo "<td class='py-2 px-4 border-b border-gray-200'>" . ($row['last_login'] ? date('M j, Y g:i a', strtotime($row['last_login'])) : 'Never') . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='py-4 text-center text-gray-500'>No users found</td></tr>";
                        }
                        $stmt->close();
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-semibold mb-4">Quick Actions</h2>
        <div class="flex flex-wrap gap-4">
            <a href="users.php" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">Manage Users</a>
            <a href="settings.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">System Settings</a>
            <a href="../logout.php" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">Logout</a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>