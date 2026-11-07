<?php
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
        echo "Description column added successfully!<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Description column already exists!<br>";
        } else {
            echo "Description column error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Auto Pay table created successfully!<br>";
    
    // Add indexes for better performance (with error handling for existing indexes)
    try {
        $pdo->exec("CREATE INDEX idx_auto_pay_user ON auto_pay(user_id)");
        echo "User index created successfully!<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "User index already exists!<br>";
        } else {
            echo "User index error: " . $e->getMessage() . "<br>";
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_auto_pay_next_payment ON auto_pay(next_payment_date)");
        echo "Next payment index created successfully!<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "Next payment index already exists!<br>";
        } else {
            echo "Next payment index error: " . $e->getMessage() . "<br>";
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_auto_pay_active ON auto_pay(is_active)");
        echo "Active index created successfully!<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "Active index already exists!<br>";
        } else {
            echo "Active index error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "Auto Pay indexes processed successfully!<br>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 