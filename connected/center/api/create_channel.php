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
$name = strtolower(trim(preg_replace('/[^a-z0-9\-]/', '-', strtolower(trim($data['name'] ?? ''))), '-'));
$type = in_array($data['type'] ?? 'text', ['text', 'announcement']) ? ($data['type'] ?? 'text') : 'text';

if (!$server_id || strlen($name) < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Only owner or admin can create channels
    $stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    $membership = $stmt->fetch();
    if (!$membership || $membership['role'] === 'member') {
        http_response_code(403);
        echo json_encode(['error' => 'Only server admins can create channels']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT MAX(position) as max_pos FROM channels WHERE server_id = ?");
    $stmt->execute([$server_id]);
    $max = $stmt->fetch();
    $position = ($max['max_pos'] ?? -1) + 1;

    $stmt = $pdo->prepare("INSERT INTO channels (server_id, name, type, position) VALUES (?, ?, ?, ?)");
    $stmt->execute([$server_id, $name, $type, $position]);
    echo json_encode(['success' => true, 'channel_id' => $pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create channel']);
}
?>
