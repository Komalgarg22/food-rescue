class ChatSystem {
    constructor() {
        this.userId = <?= $_SESSION['user_id'] ?>;
        this.currentChatId = null;
        this.lastMessageId = 0;
        this.pollInterval = 2000;
        this.pollTimeout = null;
        
        this.initElements();
        this.initEventListeners();
        this.startPolling();
    }
    
    initElements() {
        this.chatContainer = document.getElementById('chatContainer');
        this.messagesList = document.getElementById('messagesList');
        this.messageForm = document.getElementById('messageForm');
        this.messageInput = document.getElementById('messageInput');
        this.conversationsList = document.getElementById('conversationsList');
        this.typingIndicator = document.getElementById('typingIndicator');
    }
    
    initEventListeners() {
        this.messageForm.addEventListener('submit', (e) => this.handleSendMessage(e));
        this.messageInput.addEventListener('input', () => this.handleTyping());
        
        // For conversation list clicks
        if (this.conversationsList) {
            this.conversationsList.addEventListener('click', (e) => {
                const conversationItem = e.target.closest('.conversation-item');
                if (conversationItem) {
                    const chatId = conversationItem.dataset.chatId;
                    const otherUserId = conversationItem.dataset.userId;
                    this.loadChat(chatId, otherUserId);
                }
            });
        }
    }
    
    startPolling() {
        if (this.pollTimeout) clearTimeout(this.pollTimeout);
        
        if (this.currentChatId) {
            this.fetchNewMessages();
        }
        
        this.pollTimeout = setTimeout(() => this.startPolling(), this.pollInterval);
    }
    
    async fetchNewMessages() {
        try {
            const response = await fetch(`message_receive.php?last_message_id=${this.lastMessageId}`);
            const data = await response.json();
            
            if (data.success && data.messages.length > 0) {
                this.appendMessages(data.messages);
                this.lastMessageId = data.last_message_id;
                this.scrollToBottom();
            }
        } catch (error) {
            console.error('Error fetching messages:', error);
        }
    }
    
    async loadChat(conversationId, otherUserId) {
        this.currentChatId = conversationId;
        this.lastMessageId = 0;
        
        // Update UI to show loading state
        this.messagesList.innerHTML = '<div class="text-center py-4">Loading messages...</div>';
        
        // Fetch conversation messages
        try {
            const response = await fetch(`get_messages.php?conversation_id=${conversationId}`);
            const data = await response.json();
            
            if (data.success) {
                this.messagesList.innerHTML = '';
                if (data.messages.length > 0) {
                    this.appendMessages(data.messages);
                    this.lastMessageId = data.messages[data.messages.length - 1].id;
                    this.scrollToBottom();
                } else {
                    this.messagesList.innerHTML = '<div class="text-center py-4">No messages yet. Start the conversation!</div>';
                }
            }
        } catch (error) {
            console.error('Error loading chat:', error);
        }
    }
    
    async handleSendMessage(e) {
        e.preventDefault();
        
        const message = this.messageInput.value.trim();
        const receiverId = this.currentChatId ? this.currentChatId.split('_').find(id => id != this.userId) : null;
        
        if (!message || !receiverId) return;
        
        // Show sending state
        const tempId = `temp-${Date.now()}`;
        this.appendMessage({
            id: tempId,
            sender_id: this.userId,
            message: message,
            created_at: new Date().toISOString(),
            is_read: false,
            temp: true
        });
        
        this.messageInput.value = '';
        this.scrollToBottom();
        
        try {
            const response = await fetch('message_send.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `receiver_id=${receiverId}&message=${encodeURIComponent(message)}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Replace temp message with real one
                const tempElement = document.querySelector(`[data-id="${tempId}"]`);
                if (tempElement) {
                    tempElement.outerHTML = this.createMessageElement({
                        id: data.message_id,
                        sender_id: this.userId,
                        message: message,
                        created_at: new Date().toISOString(),
                        is_read: false
                    });
                }
                
                this.lastMessageId = data.message_id;
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }
    
    appendMessages(messages) {
        messages.forEach(msg => {
            this.appendMessage(msg);
        });
    }
    
    appendMessage(message) {
        const messageElement = this.createMessageElement(message);
        
        if (!this.messagesList) {
            this.messagesList = document.createElement('div');
            this.messagesList.id = 'messagesList';
            this.messagesList.className = 'space-y-4';
            this.chatContainer.appendChild(this.messagesList);
        }
        
        this.messagesList.appendChild(messageElement);
    }
    
    createMessageElement(message) {
        const isSender = message.sender_id == this.userId;
        const messageTime = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        const element = document.createElement('div');
        element.className = `message-item flex ${isSender ? 'justify-end' : 'justify-start'}`;
        element.dataset.id = message.id;
        
        if (message.temp) {
            element.innerHTML = `
                <div class="bg-green-100 rounded-lg p-3 max-w-xs md:max-w-md lg:max-w-lg shadow animate-pulse">
                    <p class="text-gray-800">${this.escapeHtml(message.message)}</p>
                    <p class="text-xs text-gray-500 mt-1 text-right">Sending...</p>
                </div>
            `;
        } else {
            element.innerHTML = `
                <div class="${isSender ? 'bg-green-100' : 'bg-white'} rounded-lg p-3 max-w-xs md:max-w-md lg:max-w-lg shadow">
                    <p class="text-gray-800">${this.escapeHtml(message.message)}</p>
                    <p class="text-xs text-gray-500 mt-1 text-right">
                        ${messageTime}
                        ${isSender ? `
                            ${message.is_read ? `
                                <svg class="w-3 h-3 inline-block ml-1 text-blue-500" viewBox="0 0 20 20">
                                    <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                </svg>
                            ` : `
                                <svg class="w-3 h-3 inline-block ml-1 text-gray-400" viewBox="0 0 20 20">
                                    <path fill="currentColor" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                </svg>
                            `}
                        ` : ''}
                    </p>
                </div>
            `;
        }
        
        return element;
    }
    
    scrollToBottom() {
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }
    
    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    handleTyping() {
        // Implement typing indicator if needed
    }
}

// Initialize chat when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const chatSystem = new ChatSystem();
    
    // Set interval to update online status
    setInterval(() => {
        fetch('update_online_status.php')
            .catch(error => console.error('Error updating online status:', error));
    }, 30000);
});