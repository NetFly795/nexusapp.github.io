<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Include shared configuration
require_once 'config.php';

// Get database connection
$pdo = getDBConnection();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $report_id = $input['report_id'] ?? null;
    $status = $input['status'] ?? '';
    
    if (!$report_id || !in_array($status, ['pending', 'reviewed', 'resolved'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    // Update report status
    $stmt = $pdo->prepare("UPDATE reported_chats SET status = ? WHERE id = ?");
    $stmt->execute([$status, $report_id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 