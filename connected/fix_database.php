<?php
// Database configuration
$host = 'localhost';
$dbname = 'redwoods';
$username = 'redwoods';
$password = '123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // Check if to_account_id column exists in transactions table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM transactions LIKE 'to_account_id'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        echo "Adding to_account_id column to transactions table...\n";
        $pdo->exec("ALTER TABLE transactions ADD COLUMN to_account_id INT NULL AFTER from_account_id");
        $pdo->exec("ALTER TABLE transactions ADD FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE SET NULL");
        echo "to_account_id column added successfully.\n";
    } else {
        echo "to_account_id column already exists.\n";
    }
    
    // Check if other required columns exist
    $required_columns = [
        'users' => ['display_name', 'profile_picture', 'security_question', 'security_answer'],
        'accounts' => ['account_type'],
        'transactions' => ['transaction_type']
    ];
    
    foreach ($required_columns as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE '$column'");
            $stmt->execute();
            $exists = $stmt->fetch();
            
            if (!$exists) {
                echo "Adding $column column to $table table...\n";
                switch ($table) {
                    case 'users':
                        if ($column === 'display_name') {
                            $pdo->exec("ALTER TABLE users ADD COLUMN display_name VARCHAR(100) NULL AFTER username");
                        } elseif ($column === 'profile_picture') {
                            $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'default.jpg' AFTER display_name");
                        } elseif ($column === 'security_question') {
                            $pdo->exec("ALTER TABLE users ADD COLUMN security_question VARCHAR(255) NULL AFTER profile_picture");
                        } elseif ($column === 'security_answer') {
                            $pdo->exec("ALTER TABLE users ADD COLUMN security_answer VARCHAR(255) NULL AFTER security_question");
                        }
                        break;
                    case 'accounts':
                        if ($column === 'account_type') {
                            $pdo->exec("ALTER TABLE accounts ADD COLUMN account_type ENUM('checking', 'savings') DEFAULT 'checking' AFTER user_id");
                        }
                        break;
                    case 'transactions':
                        if ($column === 'transaction_type') {
                            $pdo->exec("ALTER TABLE transactions ADD COLUMN transaction_type ENUM('deposit', 'withdrawal', 'transfer') DEFAULT 'transfer' AFTER to_account_id");
                        }
                        break;
                }
                echo "$column column added to $table table.\n";
            }
        }
    }
    
    // Create chat-related tables if they don't exist
    $chat_tables = [
        'conversations' => "CREATE TABLE IF NOT EXISTS conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_type ENUM('direct', 'group') DEFAULT 'direct',
            name VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        'conversation_participants' => "CREATE TABLE IF NOT EXISTS conversation_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            user_id INT NOT NULL,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_participant (conversation_id, user_id)
        )",
        'messages' => "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            conversation_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        'reported_chats' => "CREATE TABLE IF NOT EXISTS reported_chats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            reported_by INT NOT NULL,
            reason TEXT NOT NULL,
            status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];
    
    foreach ($chat_tables as $table_name => $sql) {
        try {
            $pdo->exec($sql);
            echo "Table $table_name created/verified successfully.\n";
        } catch (PDOException $e) {
            echo "Table $table_name already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nDatabase migration completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
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

p {
    margin: 10px 0;
    padding: 10px;
    background: white;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

hr {
    border: none;
    border-top: 2px solid #e1e5e9;
    margin: 30px 0;
}

h3 {
    color: #1e3c72;
}
</style> 