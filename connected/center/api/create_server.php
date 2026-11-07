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
$name = trim($data['name'] ?? '');
$description = trim($data['description'] ?? '');
$icon_color = trim($data['icon_color'] ?? '#5865f2');

if (strlen($name) < 2 || strlen($name) > 50) {
    http_response_code(400);
    echo json_encode(['error' => 'Server name must be 2–50 characters']);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO servers (name, description, icon_color, owner_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $description, $icon_color, $_SESSION['user_id']]);
    $server_id = $pdo->lastInsertId();

    // Owner is automatically a member with role 'owner'
    $stmt = $pdo->prepare("INSERT INTO server_members (server_id, user_id, role) VALUES (?, ?, 'owner')");
    $stmt->execute([$server_id, $_SESSION['user_id']]);

    // Create a default #general channel
    $stmt = $pdo->prepare("INSERT INTO channels (server_id, name, type, position) VALUES (?, 'general', 'text', 0)");
    $stmt->execute([$server_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'server_id' => $server_id]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Could not create server']);
}
?>
