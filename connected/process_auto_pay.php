<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Get all active Auto Pay records that are due
    $stmt = $pdo->prepare("
        SELECT ap.*, a.balance as account_balance, a.account_type, u.username as from_username
        FROM auto_pay ap
        JOIN accounts a ON ap.from_account_id = a.id
        JOIN users u ON ap.user_id = u.id
        WHERE ap.is_active = 1 
        AND ap.next_payment_date <= NOW()
        AND u.role != 'banned'
    ");
    $stmt->execute();
    $autoPayments = $stmt->fetchAll();
    
    $processed = 0;
    $errors = [];
    
    foreach ($autoPayments as $autoPay) {
        try {
            // Check if account has sufficient balance
            if ($autoPay['account_balance'] < $autoPay['amount']) {
                $errors[] = "Insufficient balance for Auto Pay ID {$autoPay['id']}";
                continue;
            }
            
            // Get target user's checking account
            $stmt = $pdo->prepare("
                SELECT a.id, a.user_id, u.username 
                FROM accounts a 
                JOIN users u ON a.user_id = u.id 
                WHERE u.username = ? AND a.account_type = 'checking' AND u.role != 'banned'
            ");
            $stmt->execute([$autoPay['to_username']]);
            $targetAccount = $stmt->fetch();
            
            if (!$targetAccount) {
                $errors[] = "Target user {$autoPay['to_username']} not found or no checking account";
                continue;
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Deduct from source account
            $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$autoPay['amount'], $autoPay['from_account_id']]);
            
            // Add to target account
            $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$autoPay['amount'], $targetAccount['id']]);
            
            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (from_account_id, to_account_id, amount, transaction_type, description, created_at)
                VALUES (?, ?, ?, 'transfer', ?, NOW())
            ");
            $description = "Auto Pay: " . ($autoPay['description'] ?: "Scheduled payment");
            $stmt->execute([$autoPay['from_account_id'], $targetAccount['id'], $autoPay['amount'], $description]);
            
            // Update Auto Pay record
            $nextPaymentDate = date('Y-m-d H:i:s');
            switch ($autoPay['frequency']) {
                case 'daily':
                    $nextPaymentDate = date('Y-m-d H:i:s', strtotime('+1 day'));
                    break;
                case 'weekly':
                    $nextPaymentDate = date('Y-m-d H:i:s', strtotime('+1 week'));
                    break;
                case 'monthly':
                    $nextPaymentDate = date('Y-m-d H:i:s', strtotime('+1 month'));
                    break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE auto_pay 
                SET last_payment_date = NOW(), next_payment_date = ? 
                WHERE id = ?
            ");
            $stmt->execute([$nextPaymentDate, $autoPay['id']]);
            
            $pdo->commit();
            $processed++;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error processing Auto Pay ID {$autoPay['id']}: " . $e->getMessage();
        }
    }
    
    echo "Auto Pay processing completed.\n";
    echo "Processed: $processed payments\n";
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 