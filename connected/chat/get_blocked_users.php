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

try {
    // Get blocked users
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.profile_picture, bu.created_at as blocked_at
        FROM blocked_users bu
        JOIN users u ON bu.blocked_id = u.id
        WHERE bu.blocker_id = ?
        ORDER BY bu.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $blocked_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'blocked_users' => $blocked_users]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?> 