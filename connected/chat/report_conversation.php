<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include shared configuration
require_once '../config.php';

// Get database connection
$pdo = getDBConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversation_id = $input['conversation_id'] ?? null;
    $reason = trim($input['reason'] ?? '');
    
    if (!$conversation_id || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Missing conversation ID or reason']);
        exit;
    }
    
    // Check if user is participant in this conversation
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Not a participant in this conversation']);
        exit;
    }
    
    // Check if user has already reported this conversation
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reported_chats WHERE conversation_id = ? AND reported_by = ?");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reported this conversation']);
        exit;
    }
    
    // Insert report
    $stmt = $pdo->prepare("INSERT INTO reported_chats (conversation_id, reported_by, reason) VALUES (?, ?, ?)");
    $stmt->execute([$conversation_id, $_SESSION['user_id'], $reason]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 