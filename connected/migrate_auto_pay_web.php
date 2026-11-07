<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Pay Migration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #152f5b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Auto Pay Migration</h1>
        
        <?php
        if (!file_exists('config.php')) {
            echo '<div class="error">Config file not found. Please run the installation first.</div>';
            echo '<a href="install.php" class="btn">Go to Installation</a>';
            exit;
        }
        
        require_once 'config.php';
        
        try {
            $pdo = getDBConnection();
            
            // Create auto_pay table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS auto_pay (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    from_account_id INT NOT NULL,
                    to_username VARCHAR(50) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
                    description TEXT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    last_payment_date DATETIME NULL,
                    next_payment_date DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE CASCADE
                )
            ");
            
            // Add description column if it doesn't exist (for existing tables)
            try {
                $pdo->exec("ALTER TABLE auto_pay ADD COLUMN description TEXT NULL AFTER frequency");
                echo '<div class="success">✅ Description column added successfully!</div>';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    echo '<div class="success">✅ Description column already exists!</div>';
                } else {
                    echo '<div class="error">⚠️ Description column error: ' . $e->getMessage() . '</div>';
                }
            }
            
            echo '<div class="success">✅ Auto Pay table created successfully!</div>';
            
            // Add indexes for better performance (with error handling for existing indexes)
            try {
                $pdo->exec("CREATE INDEX idx_auto_pay_user ON auto_pay(user_id)");
                echo '<div class="success">✅ User index created successfully!</div>';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo '<div class="success">✅ User index already exists!</div>';
                } else {
                    echo '<div class="error">⚠️ User index error: ' . $e->getMessage() . '</div>';
                }
            }
            
            try {
                $pdo->exec("CREATE INDEX idx_auto_pay_next_payment ON auto_pay(next_payment_date)");
                echo '<div class="success">✅ Next payment index created successfully!</div>';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo '<div class="success">✅ Next payment index already exists!</div>';
                } else {
                    echo '<div class="error">⚠️ Next payment index error: ' . $e->getMessage() . '</div>';
                }
            }
            
            try {
                $pdo->exec("CREATE INDEX idx_auto_pay_active ON auto_pay(is_active)");
                echo '<div class="success">✅ Active index created successfully!</div>';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo '<div class="success">✅ Active index already exists!</div>';
                } else {
                    echo '<div class="error">⚠️ Active index error: ' . $e->getMessage() . '</div>';
                }
            }
            
            echo '<div class="success">✅ Auto Pay indexes created successfully!</div>';
            
            echo '<div class="success">🎉 Auto Pay migration completed successfully!</div>';
            echo '<p>The Auto Pay feature is now ready to use. You can:</p>';
            echo '<ul>';
            echo '<li>Set up Auto Pay from your Connected Accounts</li>';
            echo '<li>Manage Auto Pay settings from the dashboard</li>';
            echo '<li>Run the process_auto_pay.php script to process payments</li>';
            echo '</ul>';
            
        } catch (PDOException $e) {
            echo '<div class="error">❌ Error: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <a href="index.php" class="btn">Go to Dashboard</a>
        <a href="auto_pay_management.php" class="btn">Auto Pay Management</a>
    </div>
</body>
</html> 