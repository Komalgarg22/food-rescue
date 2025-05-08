<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Messages";
$current_chat = null;
$messages = [];

// Get list of conversations
$query = "SELECT DISTINCT 
                CASE 
                    WHEN sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END as other_user_id,
                u.name as other_user_name, u.profile_picture as other_user_image,
                MAX(m.created_at) as last_message_time
          FROM messages m
          JOIN users u ON CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END = u.id
          WHERE sender_id = ? OR receiver_id = ?
          GROUP BY other_user_id
          ORDER BY last_message_time DESC";

$stmt = $conn->prepare($query);

// Check for prepare failure
if ($stmt === false) {
    die('MySQL prepare failed: ' . $conn->error);  // Display the error message
}

$stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle opening a specific chat
if (isset($_GET['to'])) {
    $other_user_id = $_GET['to'];

    // Verify the other user exists
    $stmt = $conn->prepare("SELECT id, name, profile_picture FROM users WHERE id = ?");
    if ($stmt === false) {
        die('MySQL prepare failed: ' . $conn->error);  // Display the error message
    }
    $stmt->bind_param("i", $other_user_id);
    $stmt->execute();
    $other_user = $stmt->get_result()->fetch_assoc();

    if ($other_user) {
        $current_chat = $other_user;

        // Get messages between current user and the other user
        $stmt = $conn->prepare("SELECT m.*, 
                              u.name as sender_name, u.profile_picture as sender_image
                              FROM messages m
                              JOIN users u ON m.sender_id = u.id
                              WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
                              ORDER BY created_at ASC");

        if ($stmt === false) {
            die('MySQL prepare failed: ' . $conn->error);  // Display the error message
        }

        $stmt->bind_param("iiii", $_SESSION['user_id'], $other_user_id, $other_user_id, $_SESSION['user_id']);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Mark messages as read
        $stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE");
        if ($stmt === false) {
            die('MySQL prepare failed: ' . $conn->error);  // Display the error message
        }
        $stmt->bind_param("ii", $_SESSION['user_id'], $other_user_id);
        $stmt->execute();
    }
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $current_chat) {
    $message = sanitizeInput($_POST['message']);

    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt === false) {
            die('MySQL prepare failed: ' . $conn->error);  // Display the error message
        }

        $stmt->bind_param("iis", $_SESSION['user_id'], $current_chat['id'], $message);
        $stmt->execute();

        // Create notification for the receiver
        $notification = "New message from {$_SESSION['user_name']}";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, content, created_at) VALUES (?, ?, NOW())");
        if ($stmt === false) {
            die('MySQL prepare failed: ' . $conn->error);  // Display the error message
        }

        $stmt->bind_param("is", $current_chat['id'], $notification);
        $stmt->execute();

        // Redirect to prevent form resubmission
        header("Location: messages.php?to={$current_chat['id']}");
        exit();
    }
}

include 'includes/header.php';
?>

<div class="flex h-[calc(100vh-160px)] max-w-6xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
    <!-- Conversations List -->
    <div class="w-1/3 border-r overflow-y-auto">
        <div class="p-4 border-b">
            <h2 class="text-xl font-semibold">Conversations</h2>
        </div>

        <?php if (empty($conversations)): ?>
            <p class="p-4 text-gray-500">No conversations yet.</p>
        <?php else: ?>
            <div class="divide-y">
                <?php foreach ($conversations as $conv): ?>
                    <a href="messages.php?to=<?php echo $conv['other_user_id']; ?>" class="block p-4 hover:bg-gray-50 transition <?php echo $current_chat && $current_chat['id'] == $conv['other_user_id'] ? 'bg-gray-100' : ''; ?>">
                        <div class="flex items-center">
                            <img src="uploads/profile/<?php echo $conv['other_user_image'] ?? 'default.png'; ?>" alt="<?php echo $conv['other_user_name']; ?>" class="w-10 h-10 rounded-full mr-3">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate"><?php echo $conv['other_user_name']; ?></p>
                                <p class="text-sm text-gray-500 truncate">
                                    Last message: <?php echo date('M j, g:i A', strtotime($conv['last_message_time'])); ?>
                                </p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col">
        <?php if ($current_chat): ?>
            <!-- Chat Header -->
            <div class="p-4 border-b flex items-center">
                <img src="uploads/profile/<?php echo $current_chat['profile_picture'] ?? 'default.png'; ?>" alt="<?php echo $current_chat['name']; ?>" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h3 class="font-semibold"><?php echo $current_chat['name']; ?></h3>
                </div>
            </div>

            <!-- Messages -->
            <div class="flex-1 p-4 overflow-y-auto bg-gray-50" id="messagesContainer">
                <?php if (empty($messages)): ?>
                    <p class="text-center text-gray-500 mt-8">No messages yet. Start the conversation!</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($messages as $msg): ?>
                            <div class="<?php echo $msg['sender_id'] == $_SESSION['user_id'] ? 'flex justify-end' : 'flex justify-start'; ?>">
                                <div class="<?php echo $msg['sender_id'] == $_SESSION['user_id'] ? 'bg-green-100' : 'bg-white'; ?> rounded-lg p-3 max-w-xs md:max-w-md lg:max-w-lg shadow">
                                    <p class="text-gray-800"><?php echo $msg['message']; ?></p>
                                    <p class="text-xs text-gray-500 mt-1 text-right">
                                        <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                        <?php if ($msg['sender_id'] == $_SESSION['user_id']): ?>
                                            <?php if ($msg['is_read']): ?>
                                                <svg class="w-3 h-3 inline-block ml-1 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="w-3 h-3 inline-block ml-1 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                </svg>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <div class="p-4 border-t">
                <form action="messages.php?to=<?php echo $current_chat['id']; ?>" method="POST" class="flex gap-2">
                    <input type="text" name="message" placeholder="Type your message..." class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Send
                    </button>
                </form>
            </div>

            <script>
            // Scroll to bottom of messages
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('messagesContainer');
                container.scrollTop = container.scrollHeight;
            });
            </script>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No chat selected</h3>
                    <p class="mt-1 text-gray-500">Select a conversation from the list or start a new one.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
