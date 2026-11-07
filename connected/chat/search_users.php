<?php
session_start();

// Check if config exists
if (!file_exists('../config.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration not found']);
    exit;
}

require_once '../config.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Check if user still exists and is not banned
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'banned'");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    
    if (!$current_user) {
        http_response_code(401);
        echo json_encode(['error' => 'Account inactive']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

// Get search query
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['users' => []]);
    exit;
}

try {
    // Search for users (excluding current user, banned users, and blocked users)
    $stmt = $pdo->prepare("
        SELECT id, username, display_name, profile_picture
        FROM users 
        WHERE username LIKE ? 
        AND id != ? 
        AND role != 'banned'
        AND id NOT IN (
            SELECT blocked_id FROM blocked_users WHERE blocker_id = ?
        )
        ORDER BY username 
        LIMIT 10
    ");
    $stmt->execute(['%' . $query . '%', $_SESSION['user_id'], $_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['users' => $users]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
?> 