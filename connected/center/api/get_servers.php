<?php
session_start();
require_once '../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT s.*, sm.role,
               (SELECT COUNT(*) FROM server_members WHERE server_id = s.id) as member_count,
               (SELECT COUNT(*) FROM channels WHERE server_id = s.id) as channel_count
        FROM servers s
        JOIN server_members sm ON s.id = sm.server_id
        WHERE sm.user_id = ?
        ORDER BY sm.joined_at ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['servers' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
