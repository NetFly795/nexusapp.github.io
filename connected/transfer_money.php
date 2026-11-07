<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include shared configuration
require_once 'config.php';

try {
    // Get database connection
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $transferType = $_POST['transferType'] ?? '';
        $amount = floatval($_POST['amount'] ?? 0);
        $description = $_POST['description'] ?? '';
        
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid amount']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        try {
            if ($transferType === 'internal') {
                // Internal transfer between user's own accounts
                $fromAccountId = intval($_POST['fromAccount'] ?? 0);
                $toAccountId = intval($_POST['toAccount'] ?? 0);
                
                if ($fromAccountId === $toAccountId) {
                    throw new Exception('Cannot transfer to the same account');
                }
                
                // Verify accounts belong to user
                $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$fromAccountId, $_SESSION['user_id']]);
                $fromAccount = $stmt->fetch();
                
                $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
                $stmt->execute([$toAccountId, $_SESSION['user_id']]);
                $toAccount = $stmt->fetch();
                
                if (!$fromAccount || !$toAccount) {
                    throw new Exception('Invalid account selection');
                }
                
                if ($fromAccount['balance'] < $amount) {
                    throw new Exception('Insufficient funds');
                }
                
                // Update account balances
                $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $fromAccountId]);
                
                $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $toAccountId]);
                
                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO transactions (from_account_id, to_account_id, amount, transaction_type, description) VALUES (?, ?, ?, 'transfer', ?)");
                $stmt->execute([$fromAccountId, $toAccountId, $amount, $description ?: "Transfer from " . ucfirst($fromAccount['account_type']) . " to " . ucfirst($toAccount['account_type'])]);
                
            } elseif ($transferType === 'external') {
                // External transfer to another user
                $fromAccountId = intval($_POST['fromAccountExternal'] ?? 0);
                $toUsername = $_POST['toUsername'] ?? '';
                $toAccountType = $_POST['toAccountType'] ?? '';
                
                if ($toAccountType !== 'checking') {
                    throw new Exception('External transfers can only be made to checking accounts');
                }
                
                // Verify from account belongs to user and is checking
                $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ? AND account_type = 'checking'");
                $stmt->execute([$fromAccountId, $_SESSION['user_id']]);
                $fromAccount = $stmt->fetch();
                
                if (!$fromAccount) {
                    throw new Exception('Invalid account selection. Only checking accounts can be used for external transfers.');
                }
                
                if ($fromAccount['balance'] < $amount) {
                    throw new Exception('Insufficient funds');
                }
                
                // Find recipient user and their checking account
                $stmt = $pdo->prepare("SELECT u.id, u.username FROM users u WHERE u.username = ? AND u.role != 'banned'");
                $stmt->execute([$toUsername]);
                $recipientUser = $stmt->fetch();
                
                if (!$recipientUser) {
                    throw new Exception('Recipient user not found or is banned');
                }
                
                $stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ? AND account_type = 'checking'");
                $stmt->execute([$recipientUser['id']]);
                $toAccount = $stmt->fetch();
                
                if (!$toAccount) {
                    throw new Exception('Recipient does not have a checking account');
                }
                
                // Update account balances
                $stmt = $pdo->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
                $stmt->execute([$amount, $fromAccountId]);
                
                $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $toAccount['id']]);
                
                // Record transaction
                $stmt = $pdo->prepare("INSERT INTO transactions (from_account_id, to_account_id, amount, transaction_type, description) VALUES (?, ?, ?, 'transfer', ?)");
                $stmt->execute([$fromAccountId, $toAccount['id'], $amount, $description ?: "Transfer to " . $toUsername . "'s checking account"]);
                
            } else {
                throw new Exception('Invalid transfer type');
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Transfer completed successfully']);
            
        } catch (Exception $e) {
            $pdo->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 