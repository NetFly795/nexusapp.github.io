<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'nexus_pay';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Add new columns to users table
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_question1 VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_answer1 VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_question2 VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS security_answer2 VARCHAR(255) DEFAULT NULL");
    
    // Create conversations table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_type ENUM('direct', 'group') DEFAULT 'direct',
        name VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create conversation_participants table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversation_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_participant (conversation_id, user_id)
    )");
    
    // Create reported_chats table if it doesn't exist
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
    
    // Update messages table to use conversation_id instead of receiver_id
    $pdo->exec("ALTER TABLE messages ADD COLUMN IF NOT EXISTS conversation_id INT DEFAULT NULL");
    
    // Create direct conversations for existing message pairs
    $stmt = $pdo->query("SELECT DISTINCT LEAST(sender_id, receiver_id) as user1, GREATEST(sender_id, receiver_id) as user2 FROM messages WHERE conversation_id IS NULL");
    $existingPairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($existingPairs as $pair) {
        // Create conversation
        $stmt = $pdo->prepare("INSERT INTO conversations (conversation_type) VALUES ('direct')");
        $stmt->execute();
        $conversationId = $pdo->lastInsertId();
        
        // Add participants
        $stmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt->execute([$conversationId, $pair['user1'], $conversationId, $pair['user2']]);
        
        // Update messages
        $stmt = $pdo->prepare("UPDATE messages SET conversation_id = ? WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $stmt->execute([$conversationId, $pair['user1'], $pair['user2'], $pair['user2'], $pair['user1']]);
    }
    
    // Remove receiver_id column if it exists
    try {
        $pdo->exec("ALTER TABLE messages DROP COLUMN receiver_id");
    } catch (Exception $e) {
        // Column might not exist, ignore error
    }
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Nexus Pay - Database Updated</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin: 0;
                padding: 20px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
                width: 100%;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            .success {
                color: #28a745;
                font-size: 18px;
                margin-bottom: 30px;
            }
            .btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 30px;
                border: none;
                border-radius: 25px;
                text-decoration: none;
                display: inline-block;
                margin: 10px;
                font-size: 16px;
                transition: transform 0.2s;
            }
            .btn:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>✅ Database Updated!</h1>
            <div class='success'>Your Nexus Pay database has been successfully updated with all new features!</div>
            
            <a href='index.php' class='btn'>Go to Dashboard</a>
            <a href='admin.php' class='btn'>Admin Panel</a>
        </div>
    </body>
    </html>";
    
} catch(PDOException $e) {
    echo "Database update failed: " . $e->getMessage();
}
?> 