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

    // Owner cannot leave — they must delete or transfer
    $stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    $membership = $stmt->fetch();
    if (!$membership) {
        echo json_encode(['success' => true, 'already_left' => true]);
        exit;
    }
    if ($membership['role'] === 'owner') {
        http_response_code(400);
        echo json_encode(['error' => 'Owner cannot leave. Delete the server instead.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not leave server']);
}
?>
