<div class="flex h-[calc(100vh-160px)] max-w-6xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
    <!-- Conversations List -->
    <div class="w-1/3 border-r overflow-y-auto">
        <div class="p-4 border-b">
            <h2 class="text-xl font-semibold">Conversations</h2>
        </div>
        
        <div id="conversationsList" class="divide-y">
            <?php foreach ($conversations as $conv): ?>
                <div class="conversation-item block p-4 hover:bg-gray-50 transition cursor-pointer" 
                     data-chat-id="<?= $conv['id'] ?>" 
                     data-user-id="<?= $conv['other_user_id'] ?>">
                    <div class="flex items-center">
                        <img src="uploads/profile/<?= $conv['other_user_image'] ?? 'default.png' ?>" 
                             class="w-10 h-10 rounded-full mr-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between">
                                <p class="font-medium truncate"><?= $conv['other_user_name'] ?></p>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="bg-green-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                        <?= $conv['unread_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-500 truncate">
                                <?= $conv['last_message'] ?>
                            </p>
                            <p class="text-xs text-gray-400">
                                <?= date('M j, g:i A', strtotime($conv['last_message_time'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col">
        <?php if ($current_chat): ?>
            <!-- Chat Header -->
            <div class="p-4 border-b flex items-center">
                <img src="uploads/profile/<?= $current_chat['profile_picture'] ?? 'default.png' ?>" 
                     class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h3 class="font-semibold"><?= $current_chat['name'] ?></h3>
                    <p id="typingStatus" class="text-xs text-gray-500 hidden">typing...</p>
                </div>
            </div>

            <!-- Messages Container -->
            <div id="chatContainer" class="flex-1 p-4 overflow-y-auto bg-gray-50">
                <div id="messagesList" class="space-y-4">
                    <!-- Messages will be loaded here -->
                </div>
            </div>

            <!-- Message Input -->
            <div class="p-4 border-t">
                <form id="messageForm" class="flex gap-2">
                    <input type="hidden" id="receiverId" value="<?= $current_chat['id'] ?>">
                    <input type="text" id="messageInput" placeholder="Type your message..." 
                           class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-600" required>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                        Send
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No chat selected</h3>
                    <p class="mt-1 text-gray-500">Select a conversation to start chatting</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="js/chat.js"></script>