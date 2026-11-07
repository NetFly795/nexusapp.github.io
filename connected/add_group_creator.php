<?php
// Add group creator tracking to conversations table
echo "<h2>🔧 Add Group Creator Tracking</h2>";
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
    
    // Check if creator_id column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'creator_id'");
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        echo "<div class='info'>🔄 Adding creator_id column to conversations table...</div>";
        $pdo->exec("ALTER TABLE conversations ADD COLUMN creator_id INT NULL AFTER name");
        echo "<div class='success'>✅ creator_id column added successfully.</div>";
        
        // Add foreign key constraint
        $pdo->exec("ALTER TABLE conversations ADD FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL");
        echo "<div class='success'>✅ Foreign key constraint added.</div>";
    } else {
        echo "<div class='success'>✅ creator_id column already exists.</div>";
    }
    
    // Update existing group chats to set creator_id based on first participant
    echo "<div class='info'>🔄 Updating existing group chats with creator information...</div>";
    $stmt = $pdo->query("SELECT c.id, cp.user_id FROM conversations c JOIN conversation_participants cp ON c.id = cp.conversation_id WHERE c.conversation_type = 'group' AND c.creator_id IS NULL ORDER BY cp.joined_at ASC");
    $groups_to_update = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated_count = 0;
    foreach ($groups_to_update as $group) {
        $stmt = $pdo->prepare("UPDATE conversations SET creator_id = ? WHERE id = ?");
        $stmt->execute([$group['user_id'], $group['id']]);
        $updated_count++;
    }
    
    echo "<div class='success'>✅ Updated $updated_count existing group chats with creator information.</div>";
    
    echo "<hr>";
    echo "<div class='success'>🎉 Group creator tracking setup completed!</div>";
    echo "<p>Group chats now track who created them, enabling proper permission management.</p>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='chat/' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>→ Go to Nexus Chat</a>";
    echo "<a href='index.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Dashboard</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?> 