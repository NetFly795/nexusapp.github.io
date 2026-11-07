<?php
// Script to fix messages table structure
echo "<h2>🔧 Fix Messages Table</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fa; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
</style>";

echo "<div class='container'>";

require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "<div class='success'>✅ Connected to database successfully.</div>";
    
    // Check if messages table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<div class='info'>🔄 Messages table doesn't exist. Creating it...</div>";
        $pdo->exec("CREATE TABLE messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            conversation_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        echo "<div class='success'>✅ Messages table created successfully.</div>";
    } else {
        echo "<div class='info'>📋 Messages table exists. Checking structure...</div>";
        
        // Check columns in messages table
        $stmt = $pdo->query("SHOW COLUMNS FROM messages");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div class='info'>📋 Current columns: " . implode(', ', $columns) . "</div>";
        
        // Check if conversation_id column exists
        if (!in_array('conversation_id', $columns)) {
            echo "<div class='info'>🔄 conversation_id column missing. Adding it...</div>";
            $pdo->exec("ALTER TABLE messages ADD COLUMN conversation_id INT NOT NULL AFTER sender_id");
            echo "<div class='success'>✅ conversation_id column added successfully.</div>";
        } else {
            echo "<div class='success'>✅ conversation_id column already exists.</div>";
        }
        
        // Check if other required columns exist
        $required_columns = ['id', 'sender_id', 'message', 'created_at'];
        foreach ($required_columns as $column) {
            if (!in_array($column, $columns)) {
                echo "<div class='error'>❌ Missing required column: $column</div>";
            }
        }
    }
    
    // Check if conversations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
    $conversations_exists = $stmt->fetch();
    
    if (!$conversations_exists) {
        echo "<div class='info'>🔄 Conversations table doesn't exist. Creating it...</div>";
        $pdo->exec("CREATE TABLE conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_type ENUM('direct', 'group') DEFAULT 'direct',
            name VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "<div class='success'>✅ Conversations table created successfully.</div>";
    }
    
    // Check if conversation_participants table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'conversation_participants'");
    $participants_exists = $stmt->fetch();
    
    if (!$participants_exists) {
        echo "<div class='info'>🔄 Conversation participants table doesn't exist. Creating it...</div>";
        $pdo->exec("CREATE TABLE conversation_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            user_id INT NOT NULL,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_participant (conversation_id, user_id)
        )");
        echo "<div class='success'>✅ Conversation participants table created successfully.</div>";
    }
    
    // Check if reported_chats table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'reported_chats'");
    $reported_exists = $stmt->fetch();
    
    if (!$reported_exists) {
        echo "<div class='info'>🔄 Reported chats table doesn't exist. Creating it...</div>";
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
        echo "<div class='success'>✅ Reported chats table created successfully.</div>";
    }
    
    echo "<hr>";
    echo "<div class='success'>🎉 Messages table check completed!</div>";
    echo "<p>The messages table should now have the correct structure with conversation_id column.</p>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='chat/' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Go to Nexus Chat</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?> 