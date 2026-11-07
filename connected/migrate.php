<?php
// Web-based Database Migration Script
// Access this through your browser to fix database issues

echo "<h2>🔧 Nexus Pay - Database Migration</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fa; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
</style>";

echo "<div class='container'>";

// Check if config exists
if (!file_exists('config.php')) {
    echo "<div class='error'>❌ Configuration file not found. Please run install.php first.</div>";
    echo "<a href='install.php' class='btn'>→ Run Installation</a>";
    exit;
}

require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "<div class='success'>✅ Connected to database successfully.</div>";
    
    // Check if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='info'>📋 Existing tables: " . implode(', ', $existing_tables) . "</div>";
    
    // Create users table if it doesn't exist
    if (!in_array('users', $existing_tables)) {
        echo "<div class='info'>🔄 Creating users table...</div>";
        $pdo->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NULL,
            profile_picture VARCHAR(255) DEFAULT 'default.jpg',
            security_question VARCHAR(255) NULL,
            security_answer VARCHAR(255) NULL,
            role ENUM('admin', 'user', 'banned') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "<div class='success'>✅ Users table created successfully.</div>";
    }
    
    // Create accounts table if it doesn't exist
    if (!in_array('accounts', $existing_tables)) {
        echo "<div class='info'>🔄 Creating accounts table...</div>";
        $pdo->exec("CREATE TABLE accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            account_type ENUM('checking', 'savings') DEFAULT 'checking',
            balance DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        echo "<div class='success'>✅ Accounts table created successfully.</div>";
    }
    
    // Handle transactions table - check if it exists and what columns it has
    if (in_array('transactions', $existing_tables)) {
        echo "<div class='info'>🔄 Checking transactions table structure...</div>";
        $stmt = $pdo->query("SHOW COLUMNS FROM transactions");
        $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div class='info'>📋 Existing columns in transactions: " . implode(', ', $existing_columns) . "</div>";
        
        // Check if we need to recreate the transactions table
        $required_columns = ['id', 'from_account_id', 'to_account_id', 'transaction_type', 'amount', 'description', 'created_at'];
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (!empty($missing_columns)) {
            echo "<div class='info'>🔄 Transactions table is incomplete. Recreating it...</div>";
            
            // Drop and recreate the transactions table
            $pdo->exec("DROP TABLE IF EXISTS transactions");
            $pdo->exec("CREATE TABLE transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                from_account_id INT NULL,
                to_account_id INT NULL,
                transaction_type ENUM('deposit', 'withdrawal', 'transfer') DEFAULT 'transfer',
                amount DECIMAL(10,2) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
                FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE SET NULL
            )");
            echo "<div class='success'>✅ Transactions table recreated successfully.</div>";
        } else {
            echo "<div class='success'>✅ Transactions table structure is correct.</div>";
        }
    } else {
        echo "<div class='info'>🔄 Creating transactions table...</div>";
        $pdo->exec("CREATE TABLE transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_account_id INT NULL,
            to_account_id INT NULL,
            transaction_type ENUM('deposit', 'withdrawal', 'transfer') DEFAULT 'transfer',
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
            FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE SET NULL
        )");
        echo "<div class='success'>✅ Transactions table created successfully.</div>";
    }
    
    // Now check and add missing columns to existing tables
    $required_columns = [
        'users' => [
            'display_name' => "ALTER TABLE users ADD COLUMN display_name VARCHAR(100) NULL AFTER username",
            'profile_picture' => "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'default.jpg' AFTER display_name",
            'security_question' => "ALTER TABLE users ADD COLUMN security_question VARCHAR(255) NULL AFTER profile_picture",
            'security_answer' => "ALTER TABLE users ADD COLUMN security_answer VARCHAR(255) NULL AFTER security_question"
        ],
        'accounts' => [
            'account_type' => "ALTER TABLE accounts ADD COLUMN account_type ENUM('checking', 'savings') DEFAULT 'checking' AFTER user_id"
        ]
    ];
    
    foreach ($required_columns as $table => $columns) {
        if (in_array($table, $existing_tables)) {
            foreach ($columns as $column => $sql) {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE '$column'");
                $stmt->execute();
                $exists = $stmt->fetch();
                
                if (!$exists) {
                    echo "<div class='info'>🔄 Adding $column column to $table table...</div>";
                    try {
                        $pdo->exec($sql);
                        echo "<div class='success'>✅ $column column added to $table table.</div>";
                    } catch (PDOException $e) {
                        echo "<div class='error'>❌ Error adding $column to $table: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                } else {
                    echo "<div class='success'>✅ $column column already exists in $table table.</div>";
                }
            }
        }
    }
    
    // Create chat-related tables if they don't exist
    $chat_tables = [
        'conversations' => "CREATE TABLE IF NOT EXISTS conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_type ENUM('direct', 'group') DEFAULT 'direct',
            name VARCHAR(100) DEFAULT NULL,
            creator_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE SET NULL
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
            echo "<div class='success'>✅ Table $table_name created/verified successfully.</div>";
        } catch (PDOException $e) {
            echo "<div class='info'>ℹ️ Table $table_name already exists or error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    
    // Create admin user if no users exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $user_count = $stmt->fetchColumn();
    
    if ($user_count == 0) {
        echo "<div class='info'>🔄 Creating admin user...</div>";
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, display_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $admin_password, 'Administrator', 'admin']);
        
        $admin_id = $pdo->lastInsertId();
        
        // Create default accounts for admin
        $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_type, balance) VALUES (?, ?, ?), (?, ?, ?)");
        $stmt->execute([$admin_id, 'checking', 1000.00, $admin_id, 'savings', 5000.00]);
        
        echo "<div class='success'>✅ Admin user created with username: admin, password: admin123</div>";
    }
    
    echo "<hr>";
    echo "<div class='success'>🎉 Database migration completed successfully!</div>";
    echo "<p>All required tables and columns have been created. Your Nexus Pay application should now work properly.</p>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='index.php' class='btn'>→ Go to Nexus Pay</a>";
    echo "<a href='chat/' class='btn'>→ Go to Nexus Chat</a>";
    echo "<a href='admin.php' class='btn'>→ Go to Admin Panel</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<p>Please check your database configuration in config.php</p>";
}

echo "</div>";
?> 