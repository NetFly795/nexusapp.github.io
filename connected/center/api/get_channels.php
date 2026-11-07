<?php
session_start();
require_once '../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$server_id = intval($_GET['server_id'] ?? 0);
if (!$server_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing server_id']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Verify user is a member
    $stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    $membership = $stmt->fetch();
    if (!$membership) {
        http_response_code(403);
        echo json_encode(['error' => 'Not a member of this server']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM channel_messages WHERE channel_id = c.id) as message_count,
               (SELECT MAX(created_at) FROM channel_messages WHERE channel_id = c.id) as last_activity
        FROM channels c
        WHERE c.server_id = ?
        ORDER BY c.position ASC, c.created_at ASC
    ");
    $stmt->execute([$server_id]);
    echo json_encode([
        'channels' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'user_role' => $membership['role']
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
