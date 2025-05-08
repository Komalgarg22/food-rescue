<?php
require_once 'includes/auth.php';
require_once 'includes/db.php'; // Contains $conn = new mysqli(...)
require_once 'includes/functions.php';

// Handle marking notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $_SESSION['success'] = 'All notifications marked as read.';
    } elseif (isset($_POST['notification_id'])) {
        $notification_id = $_POST['notification_id'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
        $stmt->execute();
    }

    header('Location: notifications.php');
    exit();
}

// Fetch all notifications
$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
$unread_count = 0;

while ($row = $result->fetch_assoc()) {
    if (!$row['is_read']) {
        $unread_count++;
    }
    $notifications[] = $row;
}

$page_title = "Notifications";
include 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class=" flex flex-col">

<main class="container mx-auto p-4 flex-grow">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-green-600 text-white p-4">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">Notifications</h2>
                <div class="flex items-center">
                    <span class="bg-white text-green-600 px-2 py-1 rounded-full text-xs font-bold mr-3">
                        <?php echo $unread_count; ?> unread
                    </span>
                    <form method="POST" class="m-0">
                        <button type="submit" name="mark_all_read" class="text-white hover:text-gray-200 text-sm font-medium">
                            Mark all as read
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="divide-y">
            <?php if (empty($notifications)): ?>
                <div class="p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No notifications</h3>
                    <p class="mt-1 text-gray-500">You don't have any notifications yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="<?php echo $notification['is_read'] ? 'bg-white' : 'bg-blue-50'; ?>">
                        <div class="p-4 hover:bg-gray-50 transition">
                            <div class="flex justify-between items-start">
                                <div class="flex-1 min-w-0">
                                    <p class="<?php echo $notification['is_read'] ? 'text-gray-600' : 'font-semibold text-gray-900'; ?>">
                                        <?php echo $notification['content']; ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </p>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <form method="POST" class="ml-2">
                                        <input type="hidden" name="notification_id"
                                               value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="text-gray-400 hover:text-gray-600">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (count($notifications) > 10): ?>
            <div class="p-4 border-t text-center">
                <nav class="inline-flex rounded-md shadow">
                    <a href="#"
                       class="px-3 py-2 rounded-l-md border bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        Previous
                    </a>
                    <a href="#"
                       class="px-3 py-2 border-t border-b border-r bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        1
                    </a>
                    <a href="#"
                       class="px-3 py-2 border-t border-b border-r bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        2
                    </a>
                    <a href="#"
                       class="px-3 py-2 border-t border-b border-r bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        3
                    </a>
                    <a href="#"
                       class="px-3 py-2 rounded-r-md border bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        Next
                    </a>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</main>



</body>
</html>
<include 'includes/footer.php';?>
