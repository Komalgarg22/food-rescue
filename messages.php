<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$page_title = "Messages";
$current_chat = null;
$messages = [];
$user_id = $_SESSION['user_id'];

// Handle search
$search_results = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = '%' . $conn->real_escape_string($_GET['search']) . '%';
    $stmt = $conn->prepare("SELECT id, name, email, phone, profile_picture FROM users 
                          WHERE (name LIKE ? OR email LIKE ? OR phone LIKE ?) 
                          AND id != ?");
    $stmt->bind_param("sssi", $search_term, $search_term, $search_term, $user_id);
    $stmt->execute();
    $search_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get conversations list
$query = "SELECT DISTINCT 
            CASE 
                WHEN sender_id = ? THEN receiver_id 
                ELSE sender_id 
            END as other_user_id,
            u.name as other_user_name, 
            u.profile_picture as other_user_image,
            MAX(m.created_at) as last_message_time,
            SUM(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 ELSE 0 END) as unread_count
          FROM messages m
          JOIN users u ON CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END = u.id
          WHERE (sender_id = ? OR receiver_id = ?)
          AND (m.deleted_at IS NULL OR m.sender_id = ? OR m.receiver_id = ?)
          GROUP BY other_user_id, other_user_name, other_user_image
          ORDER BY last_message_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle chat selection
if (isset($_GET['to'])) {
    $other_user_id = (int)$_GET['to'];

    $stmt = $conn->prepare("SELECT id, name, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $other_user_id);
    $stmt->execute();
    $current_chat = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($current_chat) {
        // Get messages with explicit is_read status
        $stmt = $conn->prepare("SELECT m.*, u.name as sender_name, u.profile_picture as sender_image,
                              CASE WHEN m.is_read THEN 1 ELSE 0 END as is_read
                              FROM messages m
                              JOIN users u ON m.sender_id = u.id
                              WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
                              AND (m.deleted_at IS NULL OR m.sender_id = ? OR m.receiver_id = ?)
                              ORDER BY created_at ASC");
        $stmt->bind_param("iiiiii", $user_id, $other_user_id, $other_user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Mark messages as read
        $unread_ids = array_filter(array_map(function($msg) use ($user_id) {
            return ($msg['receiver_id'] == $user_id && !$msg['is_read']) ? $msg['id'] : null;
        }, $messages));

        if (!empty($unread_ids)) {
            $ids = implode(',', $unread_ids);
            $conn->query("UPDATE messages SET is_read = TRUE WHERE id IN ($ids)");
            
            // Update the messages array to reflect the read status
            foreach ($messages as &$msg) {
                if (in_array($msg['id'], $unread_ids)) {
                    $msg['is_read'] = 1;
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="flex h-[calc(100vh-160px)] max-w-6xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
    <!-- Conversations List -->
    <div class="w-1/3 border-r overflow-y-auto">
        <div class="p-4 border-b">
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold">Conversations</h2>
                <button id="newChatBtn" class="text-green-600 p-4 hover:text-green-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </button>
            </div>

            <!-- Search Form -->
            <div id="searchContainer" class="mt-2 hidden">
                <form method="GET" action="messages.php" class="flex">
                    <input type="text" name="search" placeholder="Search by name, email or phone"
                        class="flex-1 px-3 py-2 border rounded-l-lg focus:outline-none focus:ring-2 focus:ring-green-600">
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-r-lg hover:bg-green-700 transition">
                        Search
                    </button>
                </form>

                <!-- Search Results -->
                <div id="searchResults" class="mt-2">
                    <?php if (!empty($search_results)): ?>
                        <div class="divide-y">
                            <?php foreach ($search_results as $user): ?>
                                <a href="messages.php?to=<?= $user['id'] ?>" class="block p-3 hover:bg-gray-50 transition">
                                    <div class="flex items-center">
                                        <img src="uploads/profile/<?= $user['profile_picture'] ?? 'default.png' ?>"
                                            class="w-8 h-8 rounded-full mr-2">
                                        <div>
                                            <p class="font-medium"><?= htmlspecialchars($user['name']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif (isset($_GET['search'])): ?>
                        <p class="text-center text-gray-500 py-4">No users found</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Conversations List -->
        <?php if (empty($conversations) && !isset($_GET['search'])): ?>
            <p class="p-4 text-gray-500">No conversations yet.</p>
        <?php else: ?>
            <div class="divide-y" id="conversationsList">
                <?php foreach ($conversations as $conv): ?>
                    <a href="messages.php?to=<?= $conv['other_user_id'] ?>"
                        class="block p-4 hover:bg-gray-50 transition <?= isset($current_chat['id']) && $current_chat['id'] == $conv['other_user_id'] ? 'bg-gray-100' : '' ?>">
                        <div class="flex items-center">
                            <img src="uploads/profile/<?= $conv['other_user_image'] ?? 'default.png' ?>"
                                class="w-10 h-10 rounded-full mr-3">
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between">
                                    <p class="font-medium truncate"><?= htmlspecialchars($conv['other_user_name']) ?></p>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="bg-green-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                            <?= $conv['unread_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-gray-500 truncate">
                                    <?= date('M j, g:i A', strtotime($conv['last_message_time'])) ?>
                                </p>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col min-h-0">
        <?php if ($current_chat): ?>
            <!-- Chat Header -->
            <div class="p-4 border-b flex items-center shrink-0">
                <img src="uploads/profile/<?= $current_chat['profile_picture'] ?? 'default.png' ?>"
                    class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h3 class="font-semibold"><?= htmlspecialchars($current_chat['name']) ?></h3>
                    <p id="typingStatus" class="text-xs text-gray-500 hidden">typing...</p>
                </div>
            </div>

            <!-- Messages -->
            <div id="messagesContainer" class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                <?php if (empty($messages)): ?>
                    <p class="text-center text-gray-500 mt-8">No messages yet. Start the conversation!</p>
                <?php else: ?>
                    <div id="messagesList" class="space-y-4 min-h-0">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-item flex <?= $msg['sender_id'] == $user_id ? 'justify-end' : 'justify-start' ?>"
                                data-id="<?= $msg['id'] ?>">
                                <div class="<?= $msg['sender_id'] == $user_id ? 'bg-green-100' : 'bg-white' ?> rounded-lg p-3 max-w-xs md:max-w-md lg:max-w-lg shadow">
                                    <p class="text-gray-800"><?= htmlspecialchars($msg['message']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1 text-right">
                                        <?= date('g:i A', strtotime($msg['created_at'])) ?>
                                        <?php if ($msg['sender_id'] == $user_id): ?>
                                            <span class="read-indicator">
                                                <?php if ($msg['is_read']): ?>
                                                    <svg class="w-3 h-3 inline-block ml-1 text-blue-500" viewBox="0 0 20 20">
                                                        <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                                    </svg>
                                                <?php else: ?>
                                                    <svg class="w-3 h-3 inline-block ml-1 text-gray-400" viewBox="0 0 20 20">
                                                        <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                                    </svg>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($msg['sender_id'] == $user_id): ?>
                                        <div class="mt-1 flex justify-end">
                                            <button class="delete-message text-xs text-red-500 hover:text-red-700"
                                                data-id="<?= $msg['id'] ?>">Delete</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Message Input -->
            <div class="p-4 border-t shrink-0">
                <form id="messageForm" class="flex gap-2">
                    <input type="hidden" name="receiver_id" value="<?= $current_chat['id'] ?>">
                    <input type="text" name="message" id="messageInput"
                        placeholder="Type your message..."
                        class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600" required>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Send
                    </button>
                </form>
            </div>

            <!-- JavaScript -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    let currentChatId = <?= $current_chat['id'] ?? 0 ?>;
                    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
                    let isPolling = false;
                    let pollingInterval = 2000;
                    let typingTimeout;
                    let searchTimeout;
                    let isWindowFocused = true;

                    // Track window focus
                    window.addEventListener('focus', () => {
                        isWindowFocused = true;
                        markUnreadMessagesAsRead();
                    });
                    window.addEventListener('blur', () => isWindowFocused = false);

                    // Audio elements
                    const sendSound = new Audio('sounds/send.mp3');
                    const receiveSound = new Audio('sounds/receive.mp3');

                    // Set sound duration to 1 second
                    sendSound.addEventListener('loadedmetadata', function() {
                        sendSound.currentTime = Math.min(1, sendSound.duration);
                    });
                    receiveSound.addEventListener('loadedmetadata', function() {
                        receiveSound.currentTime = Math.min(1, receiveSound.duration);
                    });

                    // Initialize
                    scrollToBottom('auto');
                    startPolling();

                    // Helper functions
                    function isNearBottom(threshold = 100) {
                        const container = document.getElementById('messagesContainer');
                        if (!container) return true;
                        return container.scrollTop + container.clientHeight + threshold >= container.scrollHeight;
                    }

                    function scrollToBottom(behavior = 'smooth') {
                        const container = document.getElementById('messagesContainer');
                        if (container && isNearBottom()) {
                            container.scrollTo({
                                top: container.scrollHeight,
                                behavior: behavior
                            });
                        }
                    }

                    function escapeHtml(unsafe) {
                        return unsafe
                            .replace(/&/g, "&amp;")
                            .replace(/</g, "&lt;")
                            .replace(/>/g, "&gt;")
                            .replace(/"/g, "&quot;")
                            .replace(/'/g, "&#039;");
                    }

                    function formatMessageTime(timestamp) {
                        const now = new Date();
                        const msgDate = new Date(timestamp);
                        const diffMinutes = Math.floor((now - msgDate) / (1000 * 60));

                        if (diffMinutes < 1) return 'Just now';
                        if (diffMinutes < 60) return `${diffMinutes} min ago`;
                        if (msgDate.toDateString() === now.toDateString()) {
                            return msgDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                        }
                        return msgDate.toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                    }

                    // Message functions
                    function startPolling() {
                        fetchNewMessages();
                        setInterval(fetchNewMessages, pollingInterval);
                    }

                    function fetchNewMessages() {
                        if (!currentChatId || isPolling) return;

                        isPolling = true;

                        fetch(`get-messages.php?receiver_id=${currentChatId}&last_message_id=${lastMessageId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.messages.length > 0) {
                                    receiveSound.currentTime = 0;
                                    receiveSound.play().catch(e => console.log("Sound play failed:", e));

                                    appendMessages(data.messages);
                                    lastMessageId = data.messages[data.messages.length - 1].id;
                                    scrollToBottom();

                                    if (isWindowFocused) {
                                        markUnreadMessagesAsRead(data.messages);
                                    }
                                }
                            })
                            .catch(error => console.error('Error fetching messages:', error))
                            .finally(() => {
                                isPolling = false;
                            });
                    }

                    function markUnreadMessagesAsRead(messages = null) {
                        let unreadMessages = [];
                        
                        if (messages) {
                            unreadMessages = messages.filter(msg => 
                                msg.receiver_id == <?= $user_id ?> && !msg.is_read
                            );
                        } else {
                            // Find all unread messages in the DOM
                            const messageElements = document.querySelectorAll('.message-item:not(.justify-end)');
                            messageElements.forEach(el => {
                                const readIndicator = el.querySelector('.read-indicator svg.text-gray-400');
                                if (readIndicator) {
                                    unreadMessages.push({
                                        id: el.dataset.id,
                                        receiver_id: <?= $user_id ?>,
                                        is_read: false
                                    });
                                }
                            });
                        }

                        if (unreadMessages.length === 0) return;

                        const messageIds = unreadMessages.map(msg => msg.id).join(',');

                        fetch('mark_as_read.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `message_ids=${messageIds}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update the UI
                                unreadMessages.forEach(msg => {
                                    const messageElement = document.querySelector(`[data-id="${msg.id}"]`);
                                    if (messageElement) {
                                        const readIndicator = messageElement.querySelector('.read-indicator');
                                        if (readIndicator) {
                                            readIndicator.innerHTML = `
                                                <svg class="w-3 h-3 inline-block ml-1 text-blue-500" viewBox="0 0 20 20">
                                                    <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                                </svg>
                                            `;
                                        }
                                    }
                                });
                            }
                        })
                        .catch(error => console.error('Error marking messages as read:', error));
                    }

                    function appendMessages(messages) {
                        const messagesList = document.getElementById('messagesList');
                        const wasNearBottom = isNearBottom();
                        const prevScrollHeight = messagesList?.scrollHeight || 0;
                        const prevScrollTop = messagesList?.scrollTop || 0;

                        messages.forEach(msg => {
                            const isSender = msg.sender_id == <?= $user_id ?>;
                            const messageTime = formatMessageTime(msg.created_at);
                            
                            const messageHtml = `
                                <div class="message-item flex ${isSender ? 'justify-end' : 'justify-start'} opacity-0 animate-fade-in" 
                                     data-id="${msg.id}">
                                    <div class="${isSender ? 'bg-green-100' : 'bg-white'} rounded-lg p-3 max-w-xs md:max-w-md lg:max-w-lg shadow">
                                        <p class="text-gray-800">${escapeHtml(msg.message)}</p>
                                        <p class="text-xs text-gray-500 mt-1 text-right">
                                            ${messageTime}
                                            ${isSender ? `
                                                <span class="read-indicator">
                                                    ${msg.is_read ? `
                                                        <svg class="w-3 h-3 inline-block ml-1 text-blue-500" viewBox="0 0 20 20">
                                                            <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                                        </svg>
                                                    ` : `
                                                        <svg class="w-3 h-3 inline-block ml-1 text-gray-400" viewBox="0 0 20 20">
                                                            <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                                        </svg>
                                                    `}
                                                </span>
                                            ` : ''}
                                        </p>
                                        ${isSender ? `
                                            <div class="mt-1 flex justify-end">
                                                <button class="delete-message text-xs text-red-500 hover:text-red-700" 
                                                        data-id="${msg.id}">Delete</button>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `;
                            
                            if (messagesList) {
                                messagesList.insertAdjacentHTML('beforeend', messageHtml);
                                const element = messagesList.lastElementChild;
                                setTimeout(() => {
                                    element.classList.remove('opacity-0');
                                    if (wasNearBottom) scrollToBottom();
                                }, 10);
                            }
                        });

                        if (!wasNearBottom && messages.length > 0 && messagesList) {
                            requestAnimationFrame(() => {
                                messagesList.scrollTop = prevScrollTop + (messagesList.scrollHeight - prevScrollHeight);
                            });
                        }
                    }

                    function sendMessage() {
                        const formData = new FormData(document.getElementById('messageForm'));
                        const message = formData.get('message').trim();
                        const receiverId = formData.get('receiver_id');

                        if (!message || !receiverId) return;

                        // Play send sound
                        sendSound.currentTime = 0;
                        sendSound.play().catch(e => console.log("Sound play failed:", e));

                        // Show sending state
                        const tempId = 'temp-' + Date.now();
                        const messagesList = document.getElementById('messagesList');

                        const tempMsg = `
                            <div class="flex justify-end message-item opacity-0 animate-fade-in" data-id="${tempId}">
                                <div class="bg-green-100 rounded-lg p-3 max-w-xs md:max-w-md lg:max-w-lg shadow animate-pulse">
                                    <p class="text-gray-800">${escapeHtml(message)}</p>
                                    <p class="text-xs text-gray-500 mt-1 text-right">Sending...</p>
                                </div>
                            </div>
                        `;

                        if (messagesList) {
                            messagesList.insertAdjacentHTML('beforeend', tempMsg);
                            const element = messagesList.lastElementChild;
                            void element.offsetWidth;
                            element.classList.remove('opacity-0');
                            scrollToBottom();
                        }

                        // Send via AJAX
                        fetch('send_message.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    // Replace temp message with real one
                                    const tempElement = document.querySelector(`[data-id="${tempId}"]`);
                                    if (tempElement) {
                                        tempElement.outerHTML = `
                                            <div class="flex justify-end message-item opacity-0 animate-fade-in" data-id="${data.message_id}">
                                                <div class="bg-green-100 rounded-lg p-3 max-w-xs md:max-w-md lg:max-w-lg shadow">
                                                    <p class="text-gray-800">${escapeHtml(message)}</p>
                                                    <p class="text-xs text-gray-500 mt-1 text-right">
                                                        Just now
                                                        <span class="read-indicator">
                                                            <svg class="w-3 h-3 inline-block ml-1 text-gray-400" viewBox="0 0 20 20">
                                                                <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                                            </svg>
                                                        </span>
                                                    </p>
                                                    <div class="mt-1 flex justify-end">
                                                        <button class="delete-message text-xs text-red-500 hover:text-red-700" 
                                                                data-id="${data.message_id}">Delete</button>
                                                    </div>
                                                </div>
                                            </div>
                                        `;

                                        // Trigger animation for the new element
                                        const newElement = document.querySelector(`[data-id="${data.message_id}"]`);
                                        if (newElement) {
                                            setTimeout(() => {
                                                newElement.classList.remove('opacity-0');
                                            }, 10);
                                        }
                                    }

                                    // Update last message ID
                                    lastMessageId = data.message_id;

                                    document.getElementById('messageInput').value = '';
                                }
                            });
                    }

                    function deleteMessage(messageId) {
                        if (!confirm('Are you sure you want to delete this message?')) return;

                        fetch('delete_message.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `message_id=${messageId}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    const msgElement = document.querySelector(`[data-id="${messageId}"]`);
                                    if (msgElement) {
                                        msgElement.classList.add('animate-fade-out');
                                        setTimeout(() => {
                                            msgElement.remove();
                                        }, 300);
                                    }
                                }
                            });
                    }

                    // Event listeners
                    document.getElementById('newChatBtn').addEventListener('click', function() {
                        const searchContainer = document.getElementById('searchContainer');
                        searchContainer.classList.toggle('hidden');
                        if (searchContainer.classList.contains('hidden')) {
                            document.getElementById('searchResults').innerHTML = '';
                        }
                    });

                    const searchInput = document.querySelector('input[name="search"]');
                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            clearTimeout(searchTimeout);
                            searchTimeout = setTimeout(() => {
                                const searchTerm = this.value.trim();
                                if (searchTerm.length >= 2) {
                                    searchUsers(searchTerm);
                                } else {
                                    document.getElementById('searchResults').innerHTML = '';
                                }
                            }, 300);
                        });
                    }

                    document.getElementById('messageForm').addEventListener('submit', function(e) {
                        e.preventDefault();
                        sendMessage();
                    });

                    document.addEventListener('click', function(e) {
                        if (e.target.classList.contains('delete-message')) {
                            deleteMessage(e.target.dataset.id);
                        }
                    });

                    function searchUsers(searchTerm) {
                        fetch(`search_user.php?search=${encodeURIComponent(searchTerm)}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    displaySearchResults(data.results);
                                }
                            })
                            .catch(error => console.error('Search error:', error));
                    }

                    function displaySearchResults(results) {
                        const searchResults = document.getElementById('searchResults');
                        if (results.length === 0) {
                            searchResults.innerHTML = '<p class="text-center text-gray-500 py-4">No users found</p>';
                            return;
                        }
                        searchResults.innerHTML = results.map(user => `
                            <a href="messages.php?to=${user.id}" class="block p-3 hover:bg-gray-50 transition">
                                <div class="flex items-center">
                                    <img src="uploads/profile/${user.profile_picture || 'default.png'}" 
                                         class="w-8 h-8 rounded-full mr-2">
                                    <div>
                                        <p class="font-medium">${escapeHtml(user.name)}</p>
                                        <p class="text-xs text-gray-500">${escapeHtml(user.email)}</p>
                                    </div>
                                </div>
                            </a>
                        `).join('');
                    }
                });
            </script>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No chat selected</h3>
                    <p class="mt-1 text-gray-500">Select a conversation or start a new one</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>