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
    
    if (!$conversation_id) {
        echo json_encode(['success' => false, 'message' => 'Missing conversation ID']);
        exit;
    }
    
    // Check if user is participant in this conversation
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Not a participant in this conversation']);
        exit;
    }
    
    // Remove user from conversation (this will cascade delete messages if they're the only participant)
    $stmt = $pdo->prepare("DELETE FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    // Check if conversation has any remaining participants
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ?");
    $stmt->execute([$conversation_id]);
    
    if ($stmt->fetchColumn() == 0) {
        // No participants left, delete the conversation and all messages
        $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ?");
        $stmt->execute([$conversation_id]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 