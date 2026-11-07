<?php
session_start();

// Include shared configuration
require_once '../config.php';

// Get database connection
$pdo = getDBConnection();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Check if user still exists and is not banned
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'banned'");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if (!$current_user) {
    session_destroy();
    header('Location: ../index.php?message=account_inactive');
    exit;
}

// Create tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_type ENUM('direct', 'group') DEFAULT 'direct',
        name VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversation_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_participant (conversation_id, user_id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        conversation_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS reported_chats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        reported_by INT NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Tables might already exist
}

// Get user's conversations (excluding conversations with blocked users)
$stmt = $pdo->prepare("
    SELECT c.*, 
           cp.joined_at,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
           (SELECT MAX(created_at) FROM messages WHERE conversation_id = c.id) as last_message_time,
           (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
    FROM conversations c
    JOIN conversation_participants cp ON c.id = cp.conversation_id
    WHERE cp.user_id = ?
    AND c.id NOT IN (
        SELECT DISTINCT c2.id 
        FROM conversations c2
        JOIN conversation_participants cp2 ON c2.id = cp2.conversation_id
        WHERE cp2.user_id IN (
            SELECT blocked_id FROM blocked_users WHERE blocker_id = ?
        )
        AND c2.conversation_type = 'direct'
    )
    ORDER BY last_message_time DESC, c.created_at DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$conversations = $stmt->fetchAll();

// Get selected conversation
$selected_conversation = null;
if (isset($_GET['conversation'])) {
    $stmt = $pdo->prepare("
        SELECT c.* FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        WHERE c.id = ? AND cp.user_id = ?
    ");
    $stmt->execute([$_GET['conversation'], $_SESSION['user_id']]);
    $selected_conversation = $stmt->fetch();
}

// Get messages for selected conversation
$messages = [];
$participants = [];
if ($selected_conversation) {
    // Get messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.username, u.display_name, u.profile_picture
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$selected_conversation['id']]);
    $messages = $stmt->fetchAll();
    
    // Get participants
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.profile_picture, cp.joined_at
        FROM conversation_participants cp
        JOIN users u ON cp.user_id = u.id
        WHERE cp.conversation_id = ?
        ORDER BY cp.joined_at ASC
    ");
    $stmt->execute([$selected_conversation['id']]);
    $participants = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Chat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f0f0;
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: 100vh;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            width: 350px;
            background: #f8f9fa;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin: 10px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: #f8f9fa;
            color: #333;
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .user-info {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
            background-size: cover;
            background-position: center;
        }
        
        .user-details h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .user-details p {
            font-size: 14px;
            color: #666;
        }
        
        .new-conversation-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin: 15px;
            transition: transform 0.2s;
        }
        
        .new-conversation-btn:hover {
            transform: translateY(-2px);
        }
        
        .blocked-users-btn {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e1e5e9;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            margin: 0 15px 15px 15px;
            transition: all 0.2s;
        }
        
        .blocked-users-btn:hover {
            background: #e9ecef;
            color: #333;
            transform: translateY(-1px);
        }
        
        .conversations {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e1e5e9;
            cursor: pointer;
            transition: background-color 0.2s;
            position: relative;
        }
        
        .conversation-item:hover {
            background: #f8f9fa;
        }
        
        .conversation-item.active {
            background: #e3f2fd;
            border-left: 4px solid #667eea;
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .conversation-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .conversation-time {
            font-size: 12px;
            color: #666;
        }
        
        .conversation-preview {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-actions {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .conversation-item:hover .conversation-actions {
            opacity: 1;
        }
        
        .action-dots {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            color: #666;
            padding: 6px 8px;
            transition: all 0.2s;
        }
        
        .action-dots:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .main-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chat-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .chat-participants {
            font-size: 14px;
            color: #666;
        }
        
        .manage-group-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .manage-group-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .messages-container {
            flex: 1;
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
        
        .message-text {
            word-wrap: break-word;
        }
        

        
        .input-container {
            background: white;
            padding: 20px;
            border-top: 1px solid #e1e5e9;
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 20px;
            font-size: 14px;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
        }
        
        .message-input:focus {
            border-color: #667eea;
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
        }
        
        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .emoji-btn {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 20px;
            padding: 8px 12px;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 8px;
        }
        
        .emoji-btn:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .welcome-screen {
            flex: 1;
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
        
        /* Context Menu */
        .context-menu {
            position: fixed;
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 150px;
            display: none;
        }
        
        .context-menu-item {
            padding: 10px 15px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.2s;
        }
        
        .context-menu-item:hover {
            background: #f8f9fa;
        }
        
        .context-menu-item.danger {
            color: #dc3545;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .modal-content {
            background-color: white;
            margin: 20px auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-content h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .participants-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 10px;
        }
        
        .participant-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .participant-item:hover {
            background: #f8f9fa;
        }
        
        .participant-item.selected {
            background: #e3f2fd;
        }
        
        .participant-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 10px;
            background-size: cover;
            background-position: center;
        }
        
        .participant-info {
            flex: 1;
            margin-left: 10px;
        }
        
        .participant-name {
            font-weight: 600;
            color: #333;
        }
        
        .participant-role {
            font-size: 12px;
            color: #667eea;
            font-weight: 500;
        }
        
        .remove-participant-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-left: auto;
        }
        
        .remove-participant-btn:hover {
            background: #c82333;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                display: none;
            }
            
            .sidebar.active {
                display: flex;
            }
            
            .main-chat {
                display: none;
            }
            
            .main-chat.active {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="sidebar">
            <div class="header">
                <h1>Connected Chat</h1>
            </div>
            
            <div class="user-info">
                <div class="user-avatar" style="background-image: <?php echo $current_user['profile_picture'] ? 'url(' . htmlspecialchars($current_user['profile_picture']) . ')' : 'none'; ?>">
                    <?php if (!$current_user['profile_picture']): ?>
                        <?php echo strtoupper(substr($current_user['display_name'] ?: $current_user['username'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($current_user['display_name'] ?: $current_user['username']); ?></h3>
                    <p>@<?php echo htmlspecialchars($current_user['username']); ?></p>
                </div>
            </div>
            
            <button class="new-conversation-btn" onclick="openNewConversationModal()">+ New Conversation</button>
            <button class="blocked-users-btn" onclick="openBlockedUsersModal()">🚫 Blocked Users</button>
            
            <div class="conversations">
                <?php foreach ($conversations as $conversation): ?>
                    <div class="conversation-item <?php echo ($selected_conversation && $selected_conversation['id'] == $conversation['id']) ? 'active' : ''; ?>" 
                         data-conversation-id="<?php echo $conversation['id']; ?>"
                         oncontextmenu="if (!event.target.closest('.conversation-actions')) showContextMenu(event, <?php echo $conversation['id']; ?>)">
                        <div class="conversation-header">
                            <div class="conversation-name">
                                <?php if ($conversation['conversation_type'] === 'group'): ?>
                                    👥 <?php echo htmlspecialchars($conversation['name']); ?>
                                <?php else: ?>
                                    <?php
                                    // Get other participant name for direct conversations
                                    $stmt = $pdo->prepare("
                                        SELECT u.display_name, u.username 
                                        FROM conversation_participants cp
                                        JOIN users u ON cp.user_id = u.id
                                        WHERE cp.conversation_id = ? AND cp.user_id != ?
                                    ");
                                    $stmt->execute([$conversation['id'], $_SESSION['user_id']]);
                                    $other_user = $stmt->fetch();
                                    echo htmlspecialchars($other_user['display_name'] ?: $other_user['username']);
                                    ?>
                                <?php endif; ?>
                            </div>
                            <div class="conversation-time">
                                <?php echo $conversation['last_message_time'] ? date('M j', strtotime($conversation['last_message_time'])) : ''; ?>
                            </div>
                        </div>
                        <div class="conversation-preview">
                            <?php echo htmlspecialchars($conversation['last_message'] ?: 'No messages yet'); ?>
                        </div>
                        <div class="conversation-actions">
                            <button class="action-dots" onclick="showContextMenu(event, <?php echo $conversation['id']; ?>)" oncontextmenu="showContextMenu(event, <?php echo $conversation['id']; ?>); return false;">⋯</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="main-chat" <?php if (!$selected_conversation): ?>style="display: none;"<?php endif; ?>>
            <div class="chat-header">
                <div class="chat-title">
                    <?php echo htmlspecialchars($selected_conversation['name'] ?? 'Conversation'); ?>
                </div>
                <div class="chat-participants">
                    <?php if ($selected_conversation): ?>
                        <?php foreach ($participants as $p): ?>
                            <span><?php echo htmlspecialchars($p['display_name'] ?: $p['username']); ?></span><?php if (next($participants)) echo ', '; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if ($selected_conversation && $selected_conversation['conversation_type'] === 'group'): ?>
                    <button class="manage-group-btn" onclick="openManageGroupModal()">Manage Group</button>
                <?php endif; ?>
            </div>
            <div class="messages-container" id="messagesContainer"></div>
            <div class="input-container">
                <textarea class="message-input" id="messageInput" placeholder="Type a message..." rows="1"></textarea>
                <button class="send-btn" id="sendBtn" onclick="sendMessage()">Send</button>
            </div>
        </div>
        
        <div class="welcome-screen" <?php if ($selected_conversation): ?>style="display: none;"<?php endif; ?>>
            <div class="welcome-content">
                <h2>Welcome to Nexus Chat!</h2>
                <p>Select a conversation or start a new one to begin chatting.</p>
            </div>
        </div>
    </div>
    

    
    <!-- Context Menu -->
    <div class="context-menu" id="contextMenu">
        <div class="context-menu-item" onclick="openConversation()">Open Conversation</div>
        <div class="context-menu-item" onclick="reportConversation()">Report Chat</div>
        <div class="context-menu-item" onclick="blockUser()">Block User</div>
        <div class="context-menu-item" onclick="leaveGroup()">Leave Group</div>
        <div class="context-menu-item danger" onclick="deleteConversation()">Delete Conversation</div>
    </div>
    
    <!-- New Conversation Modal -->
    <div class="modal" id="newConversationModal">
        <div class="modal-content">
            <span class="close" onclick="closeNewConversationModal()">&times;</span>
            <h2>New Conversation</h2>
            
            <div class="form-group">
                <label>Conversation Type:</label>
                <select id="conversationType" onchange="toggleConversationType()">
                    <option value="direct">Direct Message</option>
                    <option value="group">Group Chat</option>
                </select>
            </div>
            
            <div id="directMessageSection">
                <div class="form-group">
                    <label>Search Users:</label>
                    <input type="text" id="userSearch" placeholder="Type to search users..." oninput="searchUsers(this.value)">
                </div>
                <div id="userSearchResults" class="participants-list" style="display: none;"></div>
            </div>
            
            <div id="groupChatSection" style="display: none;">
                <div class="form-group">
                    <label>Group Name:</label>
                    <input type="text" id="groupName" placeholder="Enter group name">
                </div>
                <div class="form-group">
                    <label>Add Participants:</label>
                    <input type="text" id="groupUserSearch" placeholder="Type to search users..." oninput="searchGroupUsers(this.value)">
                </div>
                <div id="groupUserSearchResults" class="participants-list" style="display: none;"></div>
                <div id="selectedParticipants" class="participants-list" style="display: none;"></div>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="btn" onclick="createConversation()">Create Conversation</button>
                <button class="btn btn-secondary" onclick="closeNewConversationModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Manage Group Modal -->
    <div class="modal" id="manageGroupModal">
        <div class="modal-content">
            <span class="close" onclick="closeManageGroupModal()">&times;</span>
            <h2>Manage Group</h2>
            
            <div class="form-group">
                <label for="manageGroupName">Group Name:</label>
                <input type="text" id="manageGroupName" value="<?php echo htmlspecialchars($selected_conversation['name'] ?? ''); ?>" placeholder="Enter group name">
            </div>
            
            <div class="form-group">
                <label>Add Participants:</label>
                <input type="text" id="addUserSearch" placeholder="Type to search users..." oninput="searchAddUsers(this.value)">
            </div>
            <div id="addUserSearchResults" class="participants-list" style="display: none;"></div>
            
            <div class="form-group">
                <label>Current Participants:</label>
                <div id="currentParticipants" class="participants-list">
                    <?php foreach ($participants as $p): ?>
                        <div class="participant-item" data-user-id="<?php echo $p['id']; ?>">
                            <div class="participant-avatar" style="<?php echo $p['profile_picture'] ? 'background-image: url(' . htmlspecialchars($p['profile_picture']) . ')' : ''; ?>">
                                <?php if (!$p['profile_picture']): ?>
                                    <?php echo strtoupper(substr($p['display_name'] ?: $p['username'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="participant-info">
                                <div class="participant-name"><?php echo htmlspecialchars($p['display_name'] ?: $p['username']); ?></div>
                                <?php if (isset($selected_conversation['creator_id']) && $p['id'] == $selected_conversation['creator_id']): ?>
                                    <div class="participant-role">Creator</div>
                                <?php endif; ?>
                            </div>
                            <?php if ($p['id'] != $_SESSION['user_id'] && isset($selected_conversation['creator_id']) && $selected_conversation['creator_id'] == $_SESSION['user_id']): ?>
                                <button class="remove-participant-btn" onclick="removeParticipant(<?php echo $p['id']; ?>)">Remove</button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="btn" onclick="saveGroupChanges()">Save Changes</button>
                <button class="btn btn-secondary" onclick="closeManageGroupModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Report Modal -->
    <div class="modal" id="reportModal">
        <div class="modal-content">
            <span class="close" onclick="closeReportModal()">&times;</span>
            <h2>Report Conversation</h2>
            
            <div class="form-group">
                <label>Reason for reporting:</label>
                <textarea id="reportReason" rows="4" placeholder="Please describe the issue..."></textarea>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="btn btn-danger" onclick="submitReport()">Submit Report</button>
                <button class="btn btn-secondary" onclick="closeReportModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Blocked Users Modal -->
    <div class="modal" id="blockedUsersModal">
        <div class="modal-content">
            <span class="close" onclick="closeBlockedUsersModal()">&times;</span>
            <h2>Blocked Users</h2>
            
            <div id="blockedUsersList" style="max-height: 300px; overflow-y: auto;">
                <div style="text-align: center; padding: 20px; color: #666;">
                    Loading blocked users...
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button class="btn btn-secondary" onclick="closeBlockedUsersModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentConversationId = <?php echo $selected_conversation ? $selected_conversation['id'] : 'null'; ?>;
        let selectedUsers = [];
        let selectedGroupUsers = [];
        let contextMenuConversationId = null;
        let lastMessageCount = 0;
        let autoScroll = true;
        let currentConversationData = null;

        function loadConversation(conversationId) {
            // Show welcome screen while loading
            const mainChat = document.querySelector('.main-chat');
            const welcomeScreen = document.querySelector('.welcome-screen');
            
            if (welcomeScreen) {
                welcomeScreen.style.display = 'none';
            }
            
            if (mainChat) {
                mainChat.style.display = 'flex';
            }
            
            // Load conversation details and messages
            Promise.all([
                fetch(`get_conversation_details.php?conversation_id=${conversationId}`).then(r => r.json()),
                fetch(`get_messages.php?conversation_id=${conversationId}`).then(r => r.json())
            ]).then(([conversationData, messagesData]) => {
                if (conversationData.success && messagesData.success) {
                    currentConversationData = conversationData.conversation;
                    
                    // Update chat header
                    const chatTitle = document.querySelector('.chat-title');
                    const chatParticipants = document.querySelector('.chat-participants');
                    const manageGroupBtn = document.querySelector('.manage-group-btn');
                    
                    if (chatTitle) {
                        chatTitle.textContent = currentConversationData.name || 'Conversation';
                    }
                    
                    if (chatParticipants) {
                        chatParticipants.innerHTML = currentConversationData.participants.map(p => 
                            p.display_name || p.username
                        ).join(', ');
                    }
                    
                    if (manageGroupBtn) {
                        manageGroupBtn.style.display = currentConversationData.conversation_type === 'group' ? 'block' : 'none';
                    }
                    
                    // Render messages
                    renderMessages(messagesData.messages);
                    lastMessageCount = messagesData.messages.length;
                }
            });
        }
        
        function renderMessages(messages) {
            const container = document.getElementById('messagesContainer');
            const wasAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 10;
            container.innerHTML = '';
            messages.forEach(message => {
                const isSent = message.sender_id == <?php echo $_SESSION['user_id']; ?>;
                const msgDiv = document.createElement('div');
                msgDiv.className = 'message ' + (isSent ? 'sent' : 'received');
                msgDiv.innerHTML = `
                    <div class="message-avatar" style="${message.profile_picture ? 'background-image: url(' + message.profile_picture + ')' : ''}">
                        ${!message.profile_picture ? (message.display_name || message.username).charAt(0).toUpperCase() : ''}
                    </div>
                    <div class="message-bubble">
                        ${!isSent ? `<div class="message-info"><span class="message-sender">${message.display_name || message.username}</span> <span class="message-time">${new Date(message.created_at).toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'})}</span></div>` : ''}
                        <div class="message-text">${codeToEmojiStr(message.message).replace(/\n/g, '<br>')}</div>
                        ${isSent ? `<div class="message-time" style="text-align: right; margin-top: 5px; font-size: 11px; color: rgba(255,255,255,0.7);">${new Date(message.created_at).toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'})}</div>` : ''}
                    </div>
                `;
                container.appendChild(msgDiv);
            });
            if (autoScroll || wasAtBottom) {
                container.scrollTop = container.scrollHeight;
            }
        }

        function pollMessages() {
            if (!currentConversationId) return;
            fetch(`get_messages.php?conversation_id=${currentConversationId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.messages.length !== lastMessageCount) {
                            renderMessages(data.messages);
                            lastMessageCount = data.messages.length;
                        }
                    }
                });
        }

        // Initial load and start polling
        if (currentConversationId) {
            pollMessages();
            // Poll for new messages every second
            setInterval(pollMessages, 1000);
        } else {
            // Show welcome screen if no conversation is selected
            const mainChat = document.querySelector('.main-chat');
            const welcomeScreen = document.querySelector('.welcome-screen');
            
            if (mainChat) mainChat.style.display = 'none';
            if (welcomeScreen) welcomeScreen.style.display = 'flex';
        }

        // Auto-scroll if user is at bottom
        const messagesContainer = document.getElementById('messagesContainer');
        messagesContainer?.addEventListener('scroll', function() {
            autoScroll = messagesContainer.scrollTop + messagesContainer.clientHeight >= messagesContainer.scrollHeight - 10;
        });
        
        // Auto-resize textarea
        document.getElementById('messageInput')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });
        
        // Send message on Enter
        document.getElementById('messageInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Click conversation
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', function() {
                const conversationId = this.dataset.conversationId;
                if (conversationId !== currentConversationId) {
                    // Update active state
                    document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update current conversation
                    currentConversationId = conversationId;
                    lastMessageCount = 0;
                    
                    // Load conversation details and messages
                    loadConversation(conversationId);
                }
            });
        });
        
        // Context menu
        function showContextMenu(event, conversationId) {
            event.preventDefault();
            event.stopPropagation();
            contextMenuConversationId = conversationId;
            
            const menu = document.getElementById('contextMenu');
            menu.style.display = 'block';
            menu.style.left = event.pageX + 'px';
            menu.style.top = event.pageY + 'px';
        }
        
        // Hide context menu when clicking elsewhere
        document.addEventListener('click', function() {
            document.getElementById('contextMenu').style.display = 'none';
        });
        
        function openConversation() {
            if (contextMenuConversationId) {
                const conversationId = contextMenuConversationId;
                if (conversationId !== currentConversationId) {
                    // Update active state
                    document.querySelectorAll('.conversation-item').forEach(i => i.classList.remove('active'));
                    document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');
                    
                    // Update current conversation
                    currentConversationId = conversationId;
                    lastMessageCount = 0;
                    
                    // Load conversation details and messages
                    loadConversation(conversationId);
                }
                document.getElementById('contextMenu').style.display = 'none';
            }
        }
        
        function reportConversation() {
            if (contextMenuConversationId) {
                document.getElementById('reportModal').style.display = 'block';
            }
        }
        
        function deleteConversation() {
            if (contextMenuConversationId && confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
                fetch('delete_conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        conversation_id: contextMenuConversationId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        function blockUser() {
            if (!contextMenuConversationId) return;
            
            // Get the other user in the conversation
            const conversationItem = document.querySelector(`[data-conversation-id="${contextMenuConversationId}"]`);
            const conversationName = conversationItem.querySelector('.conversation-name').textContent.trim();
            
            // For direct messages, get the other user's name
            if (!conversationName.startsWith('👥')) {
                const userName = conversationName;
                if (confirm(`Are you sure you want to block ${userName}? You won't be able to see their messages or start new conversations with them.`)) {
                    // We need to get the user ID from the conversation
                    fetch(`get_conversation_details.php?conversation_id=${contextMenuConversationId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.conversation.conversation_type === 'direct') {
                                const otherUser = data.conversation.participants.find(p => p.id != <?php echo $_SESSION['user_id']; ?>);
                                if (otherUser) {
                                    fetch('block_user.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                        },
                                        body: JSON.stringify({
                                            action: 'block',
                                            user_id: otherUser.id
                                        })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert('User blocked successfully');
                                            location.reload();
                                        } else {
                                            alert('Error: ' + data.message);
                                        }
                                    });
                                }
                            } else {
                                alert('Can only block users in direct messages');
                            }
                        });
                }
            } else {
                alert('Can only block users in direct messages');
            }
            document.getElementById('contextMenu').style.display = 'none';
        }
        
        function leaveGroup() {
            if (!contextMenuConversationId) return;
            
            const conversationItem = document.querySelector(`[data-conversation-id="${contextMenuConversationId}"]`);
            const conversationName = conversationItem.querySelector('.conversation-name').textContent.trim();
            
            // Only allow leaving group chats
            if (conversationName.startsWith('👥')) {
                const groupName = conversationName.replace('👥 ', '');
                if (confirm(`Are you sure you want to leave the group "${groupName}"?`)) {
                    fetch('leave_group.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            conversation_id: contextMenuConversationId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Left group successfully');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    });
                }
            } else {
                alert('Can only leave group conversations');
            }
            document.getElementById('contextMenu').style.display = 'none';
        }
        
        function closeReportModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        function openBlockedUsersModal() {
            document.getElementById('blockedUsersModal').style.display = 'block';
            loadBlockedUsers();
        }
        
        function closeBlockedUsersModal() {
            document.getElementById('blockedUsersModal').style.display = 'none';
        }
        
        function loadBlockedUsers() {
            const listContainer = document.getElementById('blockedUsersList');
            
            fetch('get_blocked_users.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.blocked_users.length === 0) {
                            listContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #666;">No blocked users</div>';
                        } else {
                            let html = '';
                            data.blocked_users.forEach(user => {
                                const displayName = user.display_name || user.username;
                                const blockedDate = new Date(user.blocked_at).toLocaleDateString();
                                html += `
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px; border-bottom: 1px solid #e1e5e9;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 14px; background-size: cover; background-position: center;" style="${user.profile_picture ? 'background-image: url(' + user.profile_picture + ')' : ''}">
                                                ${!user.profile_picture ? displayName.charAt(0).toUpperCase() : ''}
                                            </div>
                                            <div>
                                                <div style="font-weight: 500; color: #333;">${displayName}</div>
                                                <div style="font-size: 12px; color: #666;">@${user.username} • Blocked ${blockedDate}</div>
                                            </div>
                                        </div>
                                        <button onclick="unblockUser(${user.id}, '${displayName}')" style="background: #dc3545; color: white; border: none; border-radius: 4px; padding: 6px 12px; font-size: 12px; cursor: pointer; transition: background 0.2s;">Unblock</button>
                                    </div>
                                `;
                            });
                            listContainer.innerHTML = html;
                        }
                    } else {
                        listContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Error loading blocked users</div>';
                    }
                })
                .catch(error => {
                    listContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: #dc3545;">Error loading blocked users</div>';
                });
        }
        
        function unblockUser(userId, userName) {
            if (confirm(`Are you sure you want to unblock ${userName}?`)) {
                fetch('block_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'unblock',
                        user_id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User unblocked successfully');
                        loadBlockedUsers(); // Refresh the list
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error unblocking user');
                });
            }
        }
        
        function openManageGroupModal() {
            document.getElementById('manageGroupModal').style.display = 'block';
        }
        
        function closeManageGroupModal() {
            document.getElementById('manageGroupModal').style.display = 'none';
        }
        
        function searchAddUsers(query) {
            if (query.length < 2) {
                document.getElementById('addUserSearchResults').style.display = 'none';
                return;
            }
            
            fetch('search_users.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('addUserSearchResults');
                    resultsDiv.innerHTML = '';
                    
                    if (data.error) {
                        console.error('Search error:', data.error);
                        return;
                    }
                    
                    // Filter out users who are already participants
                    const currentParticipantIds = Array.from(document.querySelectorAll('#currentParticipants .participant-item')).map(item => parseInt(item.dataset.userId));
                    const availableUsers = data.users.filter(user => !currentParticipantIds.includes(user.id));
                    
                    availableUsers.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'participant-item';
                        div.onclick = () => addParticipant(user);
                        div.innerHTML = `
                            <div class="participant-avatar" style="background-image: ${user.profile_picture ? 'url(' + user.profile_picture + ')' : 'none'}">
                                ${user.profile_picture ? '' : (user.display_name || user.username).charAt(0).toUpperCase()}
                            </div>
                            <div>${user.display_name || user.username}</div>
                        `;
                        resultsDiv.appendChild(div);
                    });
                    
                    resultsDiv.style.display = availableUsers.length > 0 ? 'block' : 'none';
                });
        }
        
        function addParticipant(user) {
            const currentParticipants = document.getElementById('currentParticipants');
            const participantDiv = document.createElement('div');
            participantDiv.className = 'participant-item';
            participantDiv.dataset.userId = user.id;
            participantDiv.innerHTML = `
                <div class="participant-avatar" style="background-image: ${user.profile_picture ? 'url(' + user.profile_picture + ')' : 'none'}">
                    ${user.profile_picture ? '' : (user.display_name || user.username).charAt(0).toUpperCase()}
                </div>
                <div class="participant-info">
                    <div class="participant-name">${user.display_name || user.username}</div>
                </div>
                <button class="remove-participant-btn" onclick="removeParticipant(${user.id})">Remove</button>
            `;
            currentParticipants.appendChild(participantDiv);
            
            document.getElementById('addUserSearch').value = '';
            document.getElementById('addUserSearchResults').style.display = 'none';
        }
        
        function removeParticipant(userId) {
            const participantItem = document.querySelector(`#currentParticipants .participant-item[data-user-id="${userId}"]`);
            if (participantItem) {
                participantItem.remove();
            }
        }
        
        function saveGroupChanges() {
            const groupName = document.getElementById('manageGroupName').value.trim();
            if (!groupName) {
                alert('Please enter a group name.');
                return;
            }
            
            const currentParticipantIds = Array.from(document.querySelectorAll('#currentParticipants .participant-item')).map(item => parseInt(item.dataset.userId));
            
            fetch('manage_group.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: currentConversationId,
                    name: groupName,
                    participant_ids: currentParticipantIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeManageGroupModal();
                    location.reload(); // Refresh to show changes
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error saving group changes');
            });
        }
        
        function submitReport() {
            const reason = document.getElementById('reportReason').value;
            if (!reason.trim()) {
                alert('Please provide a reason for reporting.');
                return;
            }
            
            fetch('report_conversation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: contextMenuConversationId,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Report submitted successfully.');
                    closeReportModal();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        // New conversation modal
        function openNewConversationModal() {
            document.getElementById('newConversationModal').style.display = 'block';
        }
        
        function closeNewConversationModal() {
            document.getElementById('newConversationModal').style.display = 'none';
            document.getElementById('userSearch').value = '';
            document.getElementById('groupName').value = '';
            document.getElementById('userSearchResults').style.display = 'none';
            document.getElementById('groupUserSearchResults').style.display = 'none';
            document.getElementById('selectedParticipants').style.display = 'none';
            selectedUsers = [];
            selectedGroupUsers = [];
        }
        
        function toggleConversationType() {
            const type = document.getElementById('conversationType').value;
            const directSection = document.getElementById('directMessageSection');
            const groupSection = document.getElementById('groupChatSection');
            
            if (type === 'direct') {
                directSection.style.display = 'block';
                groupSection.style.display = 'none';
            } else {
                directSection.style.display = 'none';
                groupSection.style.display = 'block';
            }
        }
        
        function searchUsers(query) {
            if (query.length < 2) {
                document.getElementById('userSearchResults').style.display = 'none';
                return;
            }
            
            fetch('search_users.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('userSearchResults');
                    resultsDiv.innerHTML = '';
                    
                    if (data.error) {
                        console.error('Search error:', data.error);
                        return;
                    }
                    
                    data.users.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'participant-item';
                        div.onclick = () => selectUser(user);
                        div.innerHTML = `
                            <div class="participant-avatar" style="background-image: ${user.profile_picture ? 'url(' + user.profile_picture + ')' : 'none'}">
                                ${user.profile_picture ? '' : (user.display_name || user.username).charAt(0).toUpperCase()}
                            </div>
                            <div>${user.display_name || user.username}</div>
                        `;
                        resultsDiv.appendChild(div);
                    });
                    
                    resultsDiv.style.display = data.users.length > 0 ? 'block' : 'none';
                });
        }
        
        function selectUser(user) {
            selectedUsers = [user];
            document.getElementById('userSearch').value = user.display_name || user.username;
            document.getElementById('userSearchResults').style.display = 'none';
        }
        
        function searchGroupUsers(query) {
            if (query.length < 2) {
                document.getElementById('groupUserSearchResults').style.display = 'none';
                return;
            }
            
            fetch('search_users.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('groupUserSearchResults');
                    resultsDiv.innerHTML = '';
                    
                    if (data.error) {
                        console.error('Search error:', data.error);
                        return;
                    }
                    
                    data.users.forEach(user => {
                        if (!selectedGroupUsers.find(u => u.id === user.id)) {
                            const div = document.createElement('div');
                            div.className = 'participant-item';
                            div.onclick = () => addGroupUser(user);
                            div.innerHTML = `
                                <div class="participant-avatar" style="background-image: ${user.profile_picture ? 'url(' + user.profile_picture + ')' : 'none'}">
                                    ${user.profile_picture ? '' : (user.display_name || user.username).charAt(0).toUpperCase()}
                                </div>
                                <div>${user.display_name || user.username}</div>
                            `;
                            resultsDiv.appendChild(div);
                        }
                    });
                    
                    resultsDiv.style.display = data.users.length > 0 ? 'block' : 'none';
                });
        }
        
        function addGroupUser(user) {
            if (!selectedGroupUsers.find(u => u.id === user.id)) {
                selectedGroupUsers.push(user);
                updateSelectedParticipants();
            }
            document.getElementById('groupUserSearch').value = '';
            document.getElementById('groupUserSearchResults').style.display = 'none';
        }
        
        function updateSelectedParticipants() {
            const div = document.getElementById('selectedParticipants');
            div.innerHTML = '';
            
            selectedGroupUsers.forEach(user => {
                const userDiv = document.createElement('div');
                userDiv.className = 'participant-item';
                userDiv.innerHTML = `
                    <div class="participant-avatar" style="background-image: ${user.profile_picture ? 'url(' + user.profile_picture + ')' : 'none'}">
                        ${user.profile_picture ? '' : (user.display_name || user.username).charAt(0).toUpperCase()}
                    </div>
                    <div>${user.display_name || user.username}</div>
                    <button onclick="removeGroupUser(${user.id})" style="margin-left: auto; background: #dc3545; color: white; border: none; border-radius: 4px; padding: 4px 8px; font-size: 12px;">Remove</button>
                `;
                div.appendChild(userDiv);
            });
            
            div.style.display = selectedGroupUsers.length > 0 ? 'block' : 'none';
        }
        
        function removeGroupUser(userId) {
            selectedGroupUsers = selectedGroupUsers.filter(u => u.id !== userId);
            updateSelectedParticipants();
        }
        
        function createConversation() {
            const type = document.getElementById('conversationType').value;
            
            if (type === 'direct') {
                if (selectedUsers.length === 0) {
                    alert('Please select a user for the direct message.');
                    return;
                }
                
                fetch('create_conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: 'direct',
                        user_id: selectedUsers[0].id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal and redirect to new conversation
                        closeNewConversationModal();
                        window.location.href = `?conversation=${data.conversation_id}`;
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            } else {
                const groupName = document.getElementById('groupName').value.trim();
                if (!groupName) {
                    alert('Please enter a group name.');
                    return;
                }
                
                if (selectedGroupUsers.length === 0) {
                    alert('Please add at least one participant to the group.');
                    return;
                }
                
                fetch('create_conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: 'group',
                        name: groupName,
                        user_ids: selectedGroupUsers.map(u => u.id)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal and redirect to new conversation
                        closeNewConversationModal();
                        window.location.href = `?conversation=${data.conversation_id}`;
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        function sendMessage() {
            const input = document.getElementById('messageInput');
            let message = input.value.trim();
            
            if (!message || !currentConversationId) return;
            message = emojiToCode(message);
            
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            
            fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    conversation_id: currentConversationId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    input.style.height = 'auto';
                    // Force refresh messages to show the new message
                    lastMessageCount = 0;
                    pollMessages();
                } else {
                    alert('Error: ' + data.message);
                }
                sendBtn.disabled = false;
            })
            .catch(error => {
                alert('Error sending message');
                sendBtn.disabled = false;
            });
        }
        

        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        let emojiMap = {
            '😀': 'SmileyFace', '😁': 'Grin', '😂': 'TearsOfJoy', '🤣': 'ROFL', '😃': 'SmileBig', '😄': 'SmileEyes', '😅': 'SweatSmile', '😆': 'Laugh', '😉': 'Wink', '😊': 'Blush',
            '😋': 'Yum', '😎': 'Sunglasses', '❤️': 'Heart', '😘': 'Kiss', '🥰': 'InLove', '😗': 'KissFace', '😙': 'KissSmile', '😚': 'KissClosed', '🙂': 'SlightSmile', '🤗': 'Hug',
            '🤩': 'StarStruck', '🤔': 'Thinking', '🤨': 'RaisedBrow', '😐': 'Neutral', '😑': 'Expressionless', '😶': 'NoMouth', '🙄': 'EyeRoll', '😏': 'Smirk', '😣': 'Persevere', '😥': 'SadButRelieved',
            '😮': 'OpenMouth', '🤐': 'ZipperMouth', '😯': 'Hushed', '😪': 'Sleepy', '😫': 'Tired', '🥱': 'Yawn', '😴': 'Sleeping', '😌': 'Relieved', '😛': 'Tongue', '😜': 'WinkTongue',
            '😝': 'SquintTongue', '🤤': 'Drool', '😒': 'Unamused', '😓': 'ColdSweat', '😔': 'Downcast', '😕': 'Confused', '🙃': 'UpsideDown', '🤑': 'MoneyMouth', '😲': 'Astonished', '☹️': 'Frown',
        };
        let codeToEmoji = {};
        for (let [emoji, code] of Object.entries(emojiMap)) codeToEmoji[code] = emoji;
        codeToEmoji['Unsupported'] = '⌧';

        function emojiToCode(str) {
            // Replace each emoji with code, unsupported with Unsupported
            return str.replace(/([\u{1F600}-\u{1F64F}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{1F900}-\u{1F9FF}\u{1FA70}-\u{1FAFF}\u{1F1E6}-\u{1F1FF}])/gu, (m) => {
                if (emojiMap[m]) return `[EMOJINAME="${emojiMap[m]}"END]`;
                return `[EMOJINAME="Unsupported"END]`;
            });
        }
        function codeToEmojiStr(str) {
            return str.replace(/\[EMOJINAME="([A-Za-z0-9]+)"END\]/g, (m, code) => codeToEmoji[code] || '⌧');
        }

        // Emoji Picker
        function openEmojiPicker() {
            document.getElementById('emojiPicker').style.display = 'block';
        }
        function closeEmojiPicker() {
            document.getElementById('emojiPicker').style.display = 'none';
        }
        function insertEmoji(emoji) {
            const input = document.getElementById('messageInput');
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            input.value = text.slice(0, start) + emoji + text.slice(end);
            input.focus();
            input.selectionStart = input.selectionEnd = start + emoji.length;
            closeEmojiPicker();
            input.dispatchEvent(new Event('input'));
        }

        // Add emoji picker HTML
        document.addEventListener('DOMContentLoaded', function() {
            const emojiBtn = document.createElement('button');
            emojiBtn.type = 'button';
            emojiBtn.className = 'emoji-btn';
            emojiBtn.innerText = '😊';
            emojiBtn.onclick = openEmojiPicker;
            document.querySelector('.input-container').insertBefore(emojiBtn, document.getElementById('messageInput'));
            // Emoji picker popup
            const picker = document.createElement('div');
            picker.id = 'emojiPicker';
            picker.style.display = 'none';
            picker.style.position = 'absolute';
            picker.style.bottom = '60px';
            picker.style.left = '20px';
            picker.style.background = '#fff';
            picker.style.border = '1px solid #e1e5e9';
            picker.style.borderRadius = '10px';
            picker.style.boxShadow = '0 4px 20px rgba(0,0,0,0.1)';
            picker.style.padding = '10px';
            picker.style.zIndex = '1001';
            picker.style.maxWidth = '320px';
            picker.style.maxHeight = '200px';
            picker.style.overflowY = 'auto';
            let html = '<div style="display: flex; flex-wrap: wrap; gap: 6px;">';
            for (let emoji of Object.keys(emojiMap)) {
                html += `<button type='button' style='font-size:22px; padding:4px 6px; background:none; border:none; cursor:pointer;' onclick='insertEmoji("${emoji}")'>${emoji}</button>`;
            }
            html += '</div>';
            html += '<div style="margin-top:8px; text-align:right;"><button type="button" onclick="closeEmojiPicker()" style="background:#eee; border:none; border-radius:4px; padding:4px 10px; cursor:pointer;">Close</button></div>';
            picker.innerHTML = html;
            document.body.appendChild(picker);
        });
        // Hide emoji picker on click outside
        document.addEventListener('mousedown', function(e) {
            const picker = document.getElementById('emojiPicker');
            if (picker && picker.style.display === 'block' && !picker.contains(e.target) && !e.target.classList.contains('emoji-btn')) {
                picker.style.display = 'none';
            }
        });
    </script>
</body>
</html> 