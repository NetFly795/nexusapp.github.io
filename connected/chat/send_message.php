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
    $message = trim($input['message'] ?? '');
    
    if (!$conversation_id || !$message) {
        echo json_encode(['success' => false, 'message' => 'Missing conversation ID or message']);
        exit;
    }
    
    // Check if user is participant in this conversation
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    if ($stmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'Not a participant in this conversation']);
        exit;
    }
    
    // Insert message
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, conversation_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $conversation_id, $message]);
    
    echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 