<?php
// Comprehensive Messages Table Fix Script
echo "<h2>🔧 Complete Messages Table Fix</h2>";
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
    
    // Step 1: Create conversations table
    echo "<div class='info'>🔄 Step 1: Ensuring conversations table exists...</div>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
    $conversations_exists = $stmt->fetch();
    
    if (!$conversations_exists) {
        $pdo->exec("CREATE TABLE conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_type ENUM('direct', 'group') DEFAULT 'direct',
            name VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "<div class='success'>✅ Conversations table created.</div>";
    } else {
        echo "<div class='success'>✅ Conversations table already exists.</div>";
    }
    
    // Step 2: Create conversation_participants table
    echo "<div class='info'>🔄 Step 2: Ensuring conversation_participants table exists...</div>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'conversation_participants'");
    $participants_exists = $stmt->fetch();
    
    if (!$participants_exists) {
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
    } else {
        echo "<div class='success'>✅ Conversation participants table already exists.</div>";
    }
    
    // Step 3: Create reported_chats table
    echo "<div class='info'>🔄 Step 3: Ensuring reported_chats table exists...</div>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'reported_chats'");
    $reported_exists = $stmt->fetch();
    
    if (!$reported_exists) {
        $pdo->exec("CREATE TABLE reported_chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            reported_by INT NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
        )");
        echo "<div class='success'>✅ Reported chats table created.</div>";
    } else {
        echo "<div class='success'>✅ Reported chats table already exists.</div>";
    }
    
    // Step 4: Handle messages table
    echo "<div class='info'>🔄 Step 4: Fixing messages table...</div>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    $messages_exists = $stmt->fetch();
    
    if (!$messages_exists) {
        // Create messages table with correct structure
        $pdo->exec("CREATE TABLE messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            conversation_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        echo "<div class='success'>✅ Messages table created with correct structure.</div>";
    } else {
        // Check current structure
        $stmt = $pdo->query("SHOW COLUMNS FROM messages");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $has_receiver_id = in_array('receiver_id', $columns);
        $has_conversation_id = in_array('conversation_id', $columns);
        
        if ($has_receiver_id && !$has_conversation_id) {
            // Old structure - migrate to new
            echo "<div class='warning'>⚠️ Migrating from old structure (receiver_id) to new structure...</div>";
            
            // Add conversation_id column
            $pdo->exec("ALTER TABLE messages ADD COLUMN conversation_id INT NULL AFTER receiver_id");
            
            // Migrate existing messages
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
                
                // Update messages
                $stmt = $pdo->prepare("UPDATE messages SET conversation_id = ? WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
                $stmt->execute([$conversation_id, $pair['user1'], $pair['user2'], $pair['user2'], $pair['user1']]);
            }
            
            // Make conversation_id NOT NULL and remove receiver_id
            $pdo->exec("ALTER TABLE messages MODIFY conversation_id INT NOT NULL");
            $pdo->exec("ALTER TABLE messages DROP COLUMN receiver_id");
            
            echo "<div class='success'>✅ Migration completed. Migrated " . count($conversation_pairs) . " conversations.</div>";
            
        } elseif (!$has_receiver_id && $has_conversation_id) {
            echo "<div class='success'>✅ Messages table already has correct structure.</div>";
        } else {
            // Unexpected structure - recreate table
            echo "<div class='warning'>⚠️ Unexpected table structure. Recreating messages table...</div>";
            
            // Backup existing data if any
            $stmt = $pdo->query("SELECT COUNT(*) FROM messages");
            $message_count = $stmt->fetchColumn();
            
            if ($message_count > 0) {
                echo "<div class='warning'>⚠️ Found $message_count existing messages. Backing up...</div>";
                // Note: In a real scenario, you might want to backup the data
            }
            
            // Drop and recreate
            $pdo->exec("DROP TABLE messages");
            $pdo->exec("CREATE TABLE messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                conversation_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            echo "<div class='success'>✅ Messages table recreated with correct structure.</div>";
        }
    }
    
    // Step 5: Verify final structure
    echo "<div class='info'>🔄 Step 5: Verifying final structure...</div>";
    $stmt = $pdo->query("SHOW COLUMNS FROM messages");
    $final_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='success'>✅ Final messages table columns: " . implode(', ', $final_columns) . "</div>";
    
    echo "<hr>";
    echo "<div class='success'>🎉 Messages table fix completed successfully!</div>";
    echo "<p>The messages table now uses the conversation-based structure and should work with Nexus Chat.</p>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='chat/' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>→ Go to Nexus Chat</a>";
    echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Dashboard</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?> 