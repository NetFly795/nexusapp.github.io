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
$server_id  = intval($data['server_id'] ?? 0);
$invitee_id = intval($data['user_id'] ?? 0);

if (!$server_id || !$invitee_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing server_id or user_id']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Requester must be a member
    $stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Not a member of this server']);
        exit;
    }

    // Add invitee (idempotent)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO server_members (server_id, user_id, role) VALUES (?, ?, 'member')
    ");
    $stmt->execute([$server_id, $invitee_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not invite user']);
}
?>
