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

if (!isset($_GET['conversation_id'])) {
    echo json_encode(['success' => false, 'message' => 'Conversation ID required']);
    exit;
}

$conversation_id = $_GET['conversation_id'];

try {
    // Get conversation details
    $stmt = $pdo->prepare("
        SELECT c.* FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        WHERE c.id = ? AND cp.user_id = ?
    ");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => 'Conversation not found']);
        exit;
    }
    
    // Get participants
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.profile_picture, cp.joined_at
        FROM conversation_participants cp
        JOIN users u ON cp.user_id = u.id
        WHERE cp.conversation_id = ?
        ORDER BY cp.joined_at ASC
    ");
    $stmt->execute([$conversation_id]);
    $participants = $stmt->fetchAll();
    
    $conversation['participants'] = $participants;
    
    echo json_encode(['success' => true, 'conversation' => $conversation]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 