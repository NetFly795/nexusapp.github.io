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
$server_id = intval($data['server_id'] ?? 0);

if (!$server_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing server_id']);
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT owner_id FROM servers WHERE id = ?");
    $stmt->execute([$server_id]);
    $server = $stmt->fetch();
    if (!$server || $server['owner_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Only the server owner can delete it']);
        exit;
    }

    // Cascade: channel_messages → channels → server_members → servers
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("
        DELETE cm FROM channel_messages cm
        JOIN channels c ON cm.channel_id = c.id
        WHERE c.server_id = ?
    ");
    $stmt->execute([$server_id]);
    $pdo->prepare("DELETE FROM channels WHERE server_id = ?")->execute([$server_id]);
    $pdo->prepare("DELETE FROM server_members WHERE server_id = ?")->execute([$server_id]);
    $pdo->prepare("DELETE FROM servers WHERE id = ?")->execute([$server_id]);
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not delete server']);
}
?>
