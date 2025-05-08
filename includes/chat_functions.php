<?php
function getConversationId($user1_id, $user2_id, $conn) {
    // Sort IDs to ensure consistent conversation IDs
    $lower_id = min($user1_id, $user2_id);
    $higher_id = max($user1_id, $user2_id);
    
    // Check if conversation exists
    $stmt = $conn->prepare("SELECT id FROM chat_conversations 
                          WHERE user1_id = ? AND user2_id = ?");
    $stmt->bind_param("ii", $lower_id, $higher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['id'];
    }
    
    // Create new conversation if it doesn't exist
    $stmt = $conn->prepare("INSERT INTO chat_conversations (user1_id, user2_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $lower_id, $higher_id);
    $stmt->execute();
    return $stmt->insert_id;
}

function sendMessage($sender_id, $receiver_id, $message, $conn) {
    $conversation_id = getConversationId($sender_id, $receiver_id, $conn);
    
    $stmt = $conn->prepare("INSERT INTO messages 
                          (conversation_id, sender_id, receiver_id, message) 
                          VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $conversation_id, $sender_id, $receiver_id, $message);
    $stmt->execute();
    
    return [
        'success' => true,
        'message_id' => $stmt->insert_id,
        'conversation_id' => $conversation_id
    ];
}

function getUnreadMessages($user_id, $last_message_id, $conn) {
    $query = "SELECT m.*, u.name as sender_name, u.profile_picture as sender_image
              FROM messages m
              JOIN users u ON m.sender_id = u.id
              WHERE m.receiver_id = ? 
              AND m.is_read = 0
              AND m.id > ?
              ORDER BY m.created_at ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $last_message_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Mark messages as read
    if (!empty($messages)) {
        $message_ids = array_column($messages, 'id');
        $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
        
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($message_ids)), ...$message_ids);
        $stmt->execute();
    }

    return [
        'messages' => $messages,
        'last_message_id' => !empty($messages) ? end($messages)['id'] : $last_message_id
    ];
}

function getConversations($user_id, $conn) {
    $query = "SELECT c.id, 
                     CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END as other_user_id,
                     u.name as other_user_name,
                     u.profile_picture as other_user_image,
                     (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
                     (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                     COUNT(CASE WHEN m.is_read = 0 AND m.receiver_id = ? THEN 1 END) as unread_count
              FROM chat_conversations c
              JOIN users u ON (CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END) = u.id
              LEFT JOIN messages m ON m.conversation_id = c.id
              WHERE c.user1_id = ? OR c.user2_id = ?
              GROUP BY c.id, other_user_id, other_user_name, other_user_image
              ORDER BY last_message_time DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}