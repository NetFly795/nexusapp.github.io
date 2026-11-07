<?php
session_start();
require_once '../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$channel_id = intval($data['channel_id'] ?? 0);
$content    = trim($data['content'] ?? '');

if (!$channel_id || strlen($content) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing channel_id or content']);
    exit;
}
if (strlen($content) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long (max 2000 chars)']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Verify membership
    $stmt = $pdo->prepare("
        SELECT sm.role FROM server_members sm
        JOIN channels c ON c.server_id = sm.server_id
        WHERE c.id = ? AND sm.user_id = ?
    ");
    $stmt->execute([$channel_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO channel_messages (channel_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$channel_id, $_SESSION['user_id'], $content]);
    echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not send message']);
}
?>
