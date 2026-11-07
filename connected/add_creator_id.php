<?php
// Migration script to add creator_id column to conversations table
// Access this through your browser to fix the missing column

echo "<h2>🔧 Nexus Pay - Add Creator ID Migration</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fa; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
</style>";

echo "<div class='container'>";

require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "<div class='success'>✅ Connected to database successfully.</div>";
    
    // Check if conversations table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<div class='error'>❌ Conversations table does not exist. Please run migrate.php first.</div>";
        echo "<a href='migrate.php' class='btn'>→ Run Full Migration</a>";
        exit;
    }
    
    // Check if creator_id column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM conversations LIKE 'creator_id'");
    $column_exists = $stmt->fetch();
    
    if ($column_exists) {
        echo "<div class='success'>✅ creator_id column already exists in conversations table.</div>";
    } else {
        echo "<div class='info'>🔄 Adding creator_id column to conversations table...</div>";
        
        // Add the creator_id column
        $pdo->exec("ALTER TABLE conversations ADD COLUMN creator_id INT NULL AFTER name");
        
        // Add foreign key constraint
        $pdo->exec("ALTER TABLE conversations ADD CONSTRAINT fk_conversation_creator 
                   FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL");
        
        echo "<div class='success'>✅ creator_id column added successfully to conversations table.</div>";
    }
    
    // Show current table structure
    echo "<div class='info'>📋 Current conversations table structure:</div>";
    $stmt = $pdo->query("DESCRIBE conversations");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr style='background: #f8f9fa;'><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Column</th><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Type</th><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Null</th><th style='border: 1px solid #ddd; padding: 8px; text-align: left;'>Key</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($column['Key']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<div class='success'>🎉 Creator ID migration completed successfully!</div>";
    echo "<p>The conversations table now has the creator_id column needed for group chat management.</p>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='chat/' class='btn'>→ Go to Nexus Chat</a>";
    echo "<a href='index.php' class='btn'>→ Go to Nexus Pay</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";
?> 