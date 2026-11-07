<?php
session_start();

// Include shared configuration
require_once '../config.php';

// Get database connection
$pdo = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

// Get selected conversation
$conversation_id = $_GET['conversation'] ?? null;

if (!$conversation_id) {
    echo '<div class="welcome-screen">
            <div class="welcome-content">
                <h2>💬 Welcome to Nexus Chat</h2>
                <p>Select a conversation to start messaging</p>
            </div>
          </div>';
    exit;
}

// Verify user is participant in this conversation
$stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
$stmt->execute([$conversation_id, $_SESSION['user_id']]);
if ($stmt->fetchColumn() == 0) {
    echo "Access denied";
    exit;
}

// Get conversation details
$stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
$stmt->execute([$conversation_id]);
$conversation = $stmt->fetch();

// Get messages
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.display_name, u.profile_picture
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll();

// Get participants
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.display_name, u.profile_picture, cp.joined_at
    FROM conversation_participants cp
    JOIN users u ON cp.user_id = u.id
    WHERE cp.conversation_id = ?
    ORDER BY cp.joined_at ASC
");
$stmt->execute([$conversation_id]);
$participants = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            height: 100vh;
            overflow: hidden;
        }
        
        .messages-container {
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
        }
        
        .message.sent {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
            background-size: cover;
            background-position: center;
            flex-shrink: 0;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }
        
        .message.sent .message-bubble {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .message.received .message-bubble {
            background: white;
            color: #333;
            border: 1px solid #e1e5e9;
        }
        
        .message-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .message-sender {
            font-weight: 600;
            font-size: 12px;
            color: #666;
        }
        
        .message-time {
            font-size: 11px;
            color: #999;
        }
        
        .welcome-screen {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            text-align: center;
        }
        
        .welcome-content h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .welcome-content p {
            color: #666;
            font-size: 16px;
        }
        
        /* Auto-scroll to bottom */
        .messages-container {
            scroll-behavior: smooth;
        }
    </style>
</head>
<body>
    <div class="messages-container" id="messagesContainer">
        <?php if (empty($messages)): ?>
            <div class="welcome-screen">
                <div class="welcome-content">
                    <h2>💬 <?php echo htmlspecialchars($conversation['name'] ?? 'New Conversation'); ?></h2>
                    <p>No messages yet. Start the conversation!</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <?php $is_sent = $message['sender_id'] == $_SESSION['user_id']; ?>
                <div class="message <?php echo $is_sent ? 'sent' : 'received'; ?>">
                    <div class="message-avatar" style="<?php echo $message['profile_picture'] ? 'background-image: url(' . htmlspecialchars($message['profile_picture']) . ')' : ''; ?>">
                        <?php if (!$message['profile_picture']): ?>
                            <?php echo strtoupper(substr($message['display_name'] ?: $message['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="message-content">
                        <?php if (!$is_sent): ?>
                            <div class="message-info">
                                <span class="message-sender"><?php echo htmlspecialchars($message['display_name'] ?: $message['username']); ?></span>
                                <span class="message-time"><?php echo date('g:i A', strtotime($message['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="message-bubble">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                        <?php if ($is_sent): ?>
                            <div class="message-info" style="justify-content: flex-end;">
                                <span class="message-time"><?php echo date('g:i A', strtotime($message['created_at'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Auto-scroll to bottom
        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            container.scrollTop = container.scrollHeight;
        }
        
        // Scroll to bottom on load
        window.onload = function() {
            scrollToBottom();
        };
        
        // Auto-refresh every second
        setInterval(function() {
            location.reload();
        }, 1000);
    </script>
</body>
</html> 