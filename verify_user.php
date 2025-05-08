<?php
require_once 'includes/auth.php';
require_once 'includes/db.php'; // `$conn` is your MySQLi connection
require_once 'includes/functions.php';

// Only admins can access this page
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$page_title = "Verify Users";
$success = '';
$error = '';

// Handle verification action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];

    if ($action === 'verify' || $action === 'unverify') {
        $verified = $action === 'verify' ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET verified = ? WHERE id = ?");
        $stmt->bind_param("ii", $verified, $user_id);
        if ($stmt->execute()) {
            $success = "User verification status updated successfully!";
        } else {
            $error = "Failed to update user verification status.";
        }
        $stmt->close();
    }
}

// Get all unverified users
$unverified_users = [];
$stmt = $conn->prepare("SELECT * FROM users WHERE verified = 0 ORDER BY created_at DESC");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $unverified_users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get recently verified users
$verified_users = [];
$stmt = $conn->prepare("SELECT * FROM users WHERE verified = 1 ORDER BY verified_at DESC LIMIT 10");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $verified_users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">User Verification</h1>
    
    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Unverified Users -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
        <div class="bg-yellow-500 text-white p-4">
            <h2 class="text-xl font-semibold">Pending Verification</h2>
        </div>
        <div class="p-4">
            <?php if (empty($unverified_users)): ?>
                <p class="text-gray-500">No users pending verification.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($unverified_users as $user): ?>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="verify">
                                            <button type="submit" class="text-green-600 hover:text-green-900 mr-3">Verify</button>
                                        </form>
                                        <a href="admin_user.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Verified Users -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-green-500 text-white p-4">
            <h2 class="text-xl font-semibold">Recently Verified</h2>
        </div>
        <div class="p-4">
            <?php if (empty($verified_users)): ?>
                <p class="text-gray-500">No verified users yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verified On</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($verified_users as $user): ?>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $user['verified_at'] ? date('M j, Y', strtotime($user['verified_at'])) : 'N/A'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="unverify">
                                            <button type="submit" class="text-yellow-600 hover:text-yellow-900 mr-3">Unverify</button>
                                        </form>
                                        <a href="admin_user.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
