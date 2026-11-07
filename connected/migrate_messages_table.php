<?php
// Comprehensive Messages Table Migration Script
echo "<h2>🔧 Migrate Messages Table Structure</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fa; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

echo "<div class='container'>";

require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "<div class='success'>✅ Connected to database successfully.</div>";
    
    // Check current messages table structure
    $stmt = $pdo->query("SHOW COLUMNS FROM messages");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='info'>📋 Current messages table columns: " . implode(', ', $columns) . "</div>";
    
    $has_receiver_id = in_array('receiver_id', $columns);
    $has_conversation_id = in_array('conversation_id', $columns);
    
    if ($has_receiver_id && !$has_conversation_id) {
        echo "<div class='warning'>⚠️ Messages table has old structure (receiver_id). Migrating to new structure...</div>";
        
        // Create conversations table if it doesn't exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
        $conversations_exists = $stmt->fetch();
        
        if (!$conversations_exists) {
            echo "<div class='info'>🔄 Creating conversations table...</div>";
            $pdo->exec("CREATE TABLE conversations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_type ENUM('direct', 'group') DEFAULT 'direct',
                name VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            echo "<div class='success'>✅ Conversations table created.</div>";
        }
        
        // Create conversation_participants table if it doesn't exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'conversation_participants'");
        $participants_exists = $stmt->fetch();
        
        if (!$participants_exists) {
            echo "<div class='info'>🔄 Creating conversation_participants table...</div>";
            $pdo->exec("CREATE TABLE conversation_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT NOT NULL,
                user_id INT NOT NULL,
                joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_participant (conversation_id, user_id)
            )");
            echo "<div class='success'>✅ Conversation participants table created.</div>";
        }
        
        // Add conversation_id column to messages table
        echo "<div class='info'>🔄 Adding conversation_id column to messages table...</div>";
        $pdo->exec("ALTER TABLE messages ADD COLUMN conversation_id INT NULL AFTER receiver_id");
        echo "<div class='success'>✅ conversation_id column added.</div>";
        
        // Migrate existing messages to create conversations
        echo "<div class='info'>🔄 Migrating existing messages to conversations...</div>";
        
        // Get unique conversation pairs
        $stmt = $pdo->query("SELECT DISTINCT LEAST(sender_id, receiver_id) as user1, GREATEST(sender_id, receiver_id) as user2 FROM messages WHERE conversation_id IS NULL");
        $conversation_pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($conversation_pairs as $pair) {
            // Create conversation
            $stmt = $pdo->prepare("INSERT INTO conversations (conversation_type, created_at) VALUES ('direct', NOW())");
            $stmt->execute();
            $conversation_id = $pdo->lastInsertId();
            
            // Add participants
            $stmt = $pdo->prepare("INSERT IGNORE INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
            $stmt->execute([$conversation_id, $pair['user1'], $conversation_id, $pair['user2']]);
            
            // Update messages for this conversation
            $stmt = $pdo->prepare("UPDATE messages SET conversation_id = ? WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $stmt->execute([$conversation_id, $pair['user1'], $pair['user2'], $pair['user2'], $pair['user1']]);
        }
        
        echo "<div class='success'>✅ Migrated " . count($conversation_pairs) . " conversations.</div>";
        
        // Make conversation_id NOT NULL
        echo "<div class='info'>🔄 Making conversation_id NOT NULL...</div>";
        $pdo->exec("ALTER TABLE messages MODIFY conversation_id INT NOT NULL");
        
        // Remove receiver_id column
        echo "<div class='info'>🔄 Removing receiver_id column...</div>";
        $pdo->exec("ALTER TABLE messages DROP COLUMN receiver_id");
        
        echo "<div class='success'>✅ Messages table migration completed!</div>";
        
    } elseif (!$has_receiver_id && $has_conversation_id) {
        echo "<div class='success'>✅ Messages table already has the correct structure.</div>";
    } else {
        echo "<div class='error'>❌ Unexpected table structure. Please check the messages table manually.</div>";
    }
    
    // Verify final structure
    $stmt = $pdo->query("SHOW COLUMNS FROM messages");
    $final_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='info'>📋 Final messages table columns: " . implode(', ', $final_columns) . "</div>";
    
    echo "<hr>";
    echo "<div class='success'>🎉 Messages table migration completed!</div>";
    echo "<p>The messages table now uses the conversation-based structure.</p>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='chat/' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>→ Go to Nexus Chat</a>";
    echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Dashboard</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?> 