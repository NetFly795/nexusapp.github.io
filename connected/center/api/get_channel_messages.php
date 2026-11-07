<?php
session_start();
require_once '../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$channel_id = intval($_GET['channel_id'] ?? 0);
$after_id   = intval($_GET['after_id'] ?? 0); // for incremental polling
$limit      = min(intval($_GET['limit'] ?? 50), 100);

if (!$channel_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing channel_id']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Verify the user is a member of the server that owns this channel
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

    if ($after_id > 0) {
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.content, cm.created_at,
                   u.id as user_id, u.username, u.display_name, u.profile_picture
            FROM channel_messages cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.channel_id = ? AND cm.id > ?
            ORDER BY cm.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$channel_id, $after_id, $limit]);
    } else {
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.content, cm.created_at,
                   u.id as user_id, u.username, u.display_name, u.profile_picture
            FROM channel_messages cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.channel_id = ?
            ORDER BY cm.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$channel_id, $limit]);
    }

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$after_id) {
        $messages = array_reverse($messages);
    }
    echo json_encode(['messages' => $messages]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
