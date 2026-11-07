<?php
session_start();

// Database configuration
$host = 'sql100.infinityfree.com';
$dbname = 'if0_39947505_connectedcenter';
$username = 'if0_39947505';
$password = ''; // Set locally; do not commit real credentials

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    $pdo->exec("USE $dbname");
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        role ENUM('user', 'admin', 'banned') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        display_name VARCHAR(100) DEFAULT NULL,
        profile_picture VARCHAR(255) DEFAULT NULL,
        security_question1 VARCHAR(255) DEFAULT NULL,
        security_answer1 VARCHAR(255) DEFAULT NULL,
        security_question2 VARCHAR(255) DEFAULT NULL,
        security_answer2 VARCHAR(255) DEFAULT NULL
    )");
    
    // Create accounts table
    $pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        account_type ENUM('checking', 'savings') NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create transactions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_account_id INT,
        to_account_id INT,
        amount DECIMAL(10,2) NOT NULL,
        transaction_type ENUM('deposit', 'withdrawal', 'transfer') NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
        FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE SET NULL
    )");
    
    // Create messages table
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        conversation_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // Create conversations table
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_type ENUM('direct', 'group') DEFAULT 'direct',
        name VARCHAR(100) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create conversation_participants table
    $pdo->exec("CREATE TABLE IF NOT EXISTS conversation_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_participant (conversation_id, user_id)
    )");
    
    // Create reported_chats table
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
    
    // Create admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $adminPassword, 'admin@nexuspay.com', 'admin']);
    
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Nexus Pay - Installation Complete</title>
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
            .credentials {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin: 20px 0;
                text-align: left;
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
            <h1>🎉 Installation Complete!</h1>
            <div class='success'>Nexus Pay has been successfully installed!</div>
            
            <div class='credentials'>
                <h3>Admin Credentials:</h3>
                <p><strong>Username:</strong> admin</p>
                <p><strong>Password:</strong> admin123</p>
                <p><strong>Email:</strong> admin@nexuspay.com</p>
            </div>
            
            <a href='index.php' class='btn'>Go to Dashboard</a>
            <a href='admin.php' class='btn'>Admin Panel</a>
        </div>
    </body>
    </html>";
    
} catch(PDOException $e) {
    echo "Installation failed: " . $e->getMessage();
}
?> 