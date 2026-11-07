<?php
session_start();

// Check if config exists
if (!file_exists('config.php')) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration not found']);
    exit;
}

require_once 'config.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'not_logged_in']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if user still exists and is not banned
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND role != 'banned'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User has been deleted or banned
        session_destroy();
        echo json_encode(['status' => 'inactive']);
    } else {
        // User is still active
        echo json_encode(['status' => 'active', 'role' => $user['role']]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?> 