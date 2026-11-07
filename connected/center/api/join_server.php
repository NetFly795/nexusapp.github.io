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

    $stmt = $pdo->prepare("SELECT id FROM servers WHERE id = ?");
    $stmt->execute([$server_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Server not found']);
        exit;
    }

    // Check already a member
    $stmt = $pdo->prepare("SELECT id FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'already_member' => true]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO server_members (server_id, user_id, role) VALUES (?, ?, 'member')");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not join server']);
}
?>
