<?php
// Sample Data Generator for Bank of the Redwoods
// Run this after installation to populate with demo data

if (!file_exists('config.php')) {
    die("Please run install.php first to set up the database.");
}

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🏦 Bank of the Redwoods - Sample Data Generator</h2>";
    
    // Get admin user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $stmt->execute();
    $admin_user = $stmt->fetch();
    
    if (!$admin_user) {
        die("No admin user found. Please complete the installation first.");
    }
    
    $admin_id = $admin_user['id'];
    
    // Create sample accounts
    $accounts = [
        ['checking', '1001-2345-6789-0123', 5420.50],
        ['savings', '2001-3456-7890-1234', 12500.75],
        ['credit', '3001-4567-8901-2345', -1250.00]
    ];
    
    $account_ids = [];
    
    foreach ($accounts as $account) {
        $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, account_type, balance) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_id, $account[1], $account[0], $account[2]]);
        $account_ids[] = $pdo->lastInsertId();
        
        echo "<p>✅ Created {$account[0]} account: ****" . substr($account[1], -4) . " - $" . number_format($account[2], 2) . "</p>";
    }
    
    // Create sample transactions
    $transactions = [
        // Checking account transactions
        [$account_ids[0], 'deposit', 2500.00, 'Salary deposit'],
        [$account_ids[0], 'withdrawal', 150.00, 'ATM withdrawal'],
        [$account_ids[0], 'withdrawal', 89.50, 'Grocery store purchase'],
        [$account_ids[0], 'transfer', 500.00, 'Transfer to savings'],
        [$account_ids[0], 'deposit', 1200.00, 'Freelance payment'],
        
        // Savings account transactions
        [$account_ids[1], 'deposit', 500.00, 'Transfer from checking'],
        [$account_ids[1], 'deposit', 1000.00, 'Monthly savings contribution'],
        [$account_ids[1], 'withdrawal', 250.00, 'Emergency fund withdrawal'],
        
        // Credit account transactions
        [$account_ids[2], 'withdrawal', 500.00, 'Online purchase'],
        [$account_ids[2], 'withdrawal', 750.00, 'Restaurant payment'],
        [$account_ids[2], 'deposit', 1000.00, 'Credit card payment']
    ];
    
    foreach ($transactions as $transaction) {
        $stmt = $pdo->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, ?, ?, ?)");
        $stmt->execute($transaction);
        
        $type_icon = $transaction[1] === 'deposit' ? '💰' : ($transaction[1] === 'withdrawal' ? '💸' : '🔄');
        echo "<p>{$type_icon} Added {$transaction[1]} transaction: $" . number_format($transaction[2], 2) . " - {$transaction[3]}</p>";
    }
    
    // Update account balances based on transactions
    foreach ($account_ids as $account_id) {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
                SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
            FROM transactions 
            WHERE account_id = ?
        ");
        $stmt->execute([$account_id]);
        $totals = $stmt->fetch();
        
        $new_balance = ($totals['total_deposits'] ?? 0) - ($totals['total_withdrawals'] ?? 0);
        
        $stmt = $pdo->prepare("UPDATE accounts SET balance = ? WHERE id = ?");
        $stmt->execute([$new_balance, $account_id]);
    }
    
    echo "<hr>";
    echo "<h3>🎉 Sample data generation complete!</h3>";
    echo "<p>Your bank dashboard now has:</p>";
    echo "<ul>";
    echo "<li>3 sample accounts (checking, savings, credit)</li>";
    echo "<li>11 sample transactions</li>";
    echo "<li>Updated account balances</li>";
    echo "</ul>";
    echo "<p><a href='index.php' style='background: #1e3c72; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Go to Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h3>❌ Error generating sample data</h3>";
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

ul {
    background: white;
    padding: 20px;
    border-radius: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

li {
    margin: 10px 0;
}
</style> 