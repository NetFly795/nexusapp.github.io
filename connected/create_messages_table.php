<?php
// Create Messages Table Script for Bank of the Redwoods
// Run this if you get a "messages table doesn't exist" error

if (!file_exists('config.php')) {
    die("Please run install.php first to set up the database.");
}

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🏦 Bank of the Redwoods - Create Messages Table</h2>";
    
    // Check if messages table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'messages'");
    $table_exists = $stmt->fetch();
    
    if ($table_exists) {
        echo "<p>✅ Messages table already exists!</p>";
    } else {
        // Create messages table
        $pdo->exec("
            CREATE TABLE messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        echo "<p>✅ Messages table created successfully!</p>";
    }
    
    // Verify the table structure
    $stmt = $pdo->query("DESCRIBE messages");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Messages Table Structure:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column['Field']}</strong> - {$column['Type']} - {$column['Null']} - {$column['Key']}</li>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<h3>🎉 Messages table setup complete!</h3>";
    echo "<p>You can now use Nexus Chat without errors.</p>";
    echo "<p><a href='chat/launcher.php' style='background: #007AFF; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Go to Nexus Chat</a></p>";
    echo "<p><a href='index.php' style='background: #1e3c72; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Back to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h3>❌ Error creating messages table</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f7fa;
}

h2 {
    color: #1e3c72;
    text-align: center;
    margin-bottom: 30px;
}

h3 {
    color: #1e3c72;
    margin-top: 20px;
}

p {
    margin: 10px 0;
    padding: 10px;
    background: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

ul {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

li {
    margin: 10px 0;
    padding: 5px 0;
    border-bottom: 1px solid #e1e5e9;
}

li:last-child {
    border-bottom: none;
}

hr {
    border: none;
    border-top: 2px solid #e1e5e9;
    margin: 30px 0;
}
</style> 