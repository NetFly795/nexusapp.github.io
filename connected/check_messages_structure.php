<?php
// Diagnostic script to check messages table structure
echo "<h2>🔍 Messages Table Structure Diagnostic</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fa; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
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
        echo "<div class='error'>❌ Messages table does not exist!</div>";
        echo "<div class='info'>🔄 Creating messages table with correct structure...</div>";
        
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
        echo "<div class='success'>✅ Messages table exists.</div>";
        
        // Get detailed table structure
        $stmt = $pdo->query("DESCRIBE messages");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='info'>📋 Current messages table structure:</div>";
        echo "<pre>";
        foreach ($columns as $column) {
            echo "Field: {$column['Field']}\n";
            echo "Type: {$column['Type']}\n";
            echo "Null: {$column['Null']}\n";
            echo "Key: {$column['Key']}\n";
            echo "Default: {$column['Default']}\n";
            echo "Extra: {$column['Extra']}\n";
            echo "---\n";
        }
        echo "</pre>";
        
        // Check for specific columns
        $column_names = array_column($columns, 'Field');
        $has_receiver_id = in_array('receiver_id', $column_names);
        $has_conversation_id = in_array('conversation_id', $column_names);
        
        echo "<div class='info'>📊 Column Analysis:</div>";
        echo "<ul>";
        echo "<li>Has receiver_id: " . ($has_receiver_id ? '✅ Yes' : '❌ No') . "</li>";
        echo "<li>Has conversation_id: " . ($has_conversation_id ? '✅ Yes' : '❌ No') . "</li>";
        echo "<li>Has sender_id: " . (in_array('sender_id', $column_names) ? '✅ Yes' : '❌ No') . "</li>";
        echo "<li>Has message: " . (in_array('message', $column_names) ? '✅ Yes' : '❌ No') . "</li>";
        echo "<li>Has created_at: " . (in_array('created_at', $column_names) ? '✅ Yes' : '❌ No') . "</li>";
        echo "</ul>";
        
        // Check table data
        $stmt = $pdo->query("SELECT COUNT(*) FROM messages");
        $message_count = $stmt->fetchColumn();
        echo "<div class='info'>📈 Total messages in table: $message_count</div>";
        
        if ($message_count > 0) {
            echo "<div class='info'>📋 Sample data (first 3 messages):</div>";
            $stmt = $pdo->query("SELECT * FROM messages LIMIT 3");
            $sample_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<pre>";
            print_r($sample_data);
            echo "</pre>";
        }
    }
    
    // Check related tables
    echo "<div class='info'>🔍 Checking related tables...</div>";
    
    $tables_to_check = ['conversations', 'conversation_participants', 'reported_chats'];
    foreach ($tables_to_check as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
        echo "<div>" . ($exists ? "✅" : "❌") . " $table table: " . ($exists ? 'Exists' : 'Missing') . "</div>";
    }
    
    echo "<hr>";
    echo "<div class='success'>🎉 Diagnostic completed!</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='migrate_messages_table.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>→ Run Migration</a>";
    echo "<a href='chat/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>→ Go to Chat</a>";
    echo "<a href='index.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Dashboard</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?> 