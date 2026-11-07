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
$action = $input['action'] ?? '';
$user_id = $input['user_id'] ?? null;

if (!$user_id || !in_array($action, ['block', 'unblock'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Prevent blocking yourself
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot block yourself']);
    exit;
}

try {
    if ($action === 'block') {
        // Check if already blocked
        $stmt = $pdo->prepare("SELECT id FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User is already blocked']);
            exit;
        }
        
        // Block user
        $stmt = $pdo->prepare("INSERT INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'User blocked successfully']);
        
    } else { // unblock
        // Remove block
        $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$_SESSION['user_id'], $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'User unblocked successfully']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 