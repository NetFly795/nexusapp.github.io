<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    $accountId = $_POST['accountId'] ?? '';
    $toUsername = $_POST['toUsername'] ?? '';
    $amount = $_POST['amount'] ?? '';
    $frequency = $_POST['frequency'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate inputs
    if (empty($accountId) || empty($toUsername) || empty($amount) || empty($frequency)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }
    
    if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid frequency']);
        exit;
    }
    
    if (!is_numeric($amount) || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid amount']);
        exit;
    }
    
    // Verify the account belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$accountId, $_SESSION['user_id']]);
    $account = $stmt->fetch();
    
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'Invalid account']);
        exit;
    }
    
    // Verify the target user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role != 'banned'");
    $stmt->execute([$toUsername]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        echo json_encode(['success' => false, 'message' => 'Target user not found or is banned']);
        exit;
    }
    
    if ($targetUser['id'] == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot set up Auto Pay to yourself']);
        exit;
    }
    
    // Calculate next payment date
    $nextPaymentDate = date('Y-m-d H:i:s');
    switch ($frequency) {
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
    
    // Insert Auto Pay record
    $stmt = $pdo->prepare("
        INSERT INTO auto_pay (user_id, from_account_id, to_username, amount, frequency, description, next_payment_date, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $accountId, $toUsername, $amount, $frequency, $description, $nextPaymentDate]);
    
    echo json_encode(['success' => true, 'message' => 'Auto Pay setup successfully']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?> 