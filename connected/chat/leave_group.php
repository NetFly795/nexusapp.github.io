<?php
session_start();
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check if user still exists and is not banned
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'banned'");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if (!$current_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Account inactive']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$conversation_id = $input['conversation_id'] ?? null;

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => 'Conversation ID required']);
    exit;
}

try {
    // Check if user is in this conversation and it's a group
    $stmt = $pdo->prepare("
        SELECT c.conversation_type, c.creator_id 
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        WHERE c.id = ? AND cp.user_id = ?
    ");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => 'Conversation not found or you are not a participant']);
        exit;
    }
    
    if ($conversation['conversation_type'] !== 'group') {
        echo json_encode(['success' => false, 'message' => 'Can only leave group conversations']);
        exit;
    }
    
    // Check if user is the creator
    if ($conversation['creator_id'] == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Group creator cannot leave. Transfer ownership or delete the group.']);
        exit;
    }
    
    // Remove user from conversation
    $stmt = $pdo->prepare("DELETE FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Left group successfully']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 