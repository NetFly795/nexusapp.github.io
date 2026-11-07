<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$data      = json_decode(file_get_contents('php://input'), true);
$invite_id = intval($data['invite_id'] ?? 0);
$action    = in_array($data['action'] ?? '', ['accepted', 'ignored']) ? $data['action'] : 'ignored';

if (!$invite_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing invite_id']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        UPDATE game_invites SET status = ?
        WHERE id = ? AND to_user_id = ?
    ");
    $stmt->execute([$action, $invite_id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not update invite']);
}
?>
