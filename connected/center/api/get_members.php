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

    // Verify requester is a member
    $stmt = $pdo->prepare("SELECT id FROM server_members WHERE server_id = ? AND user_id = ?");
    $stmt->execute([$server_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.display_name, u.profile_picture, sm.role, sm.joined_at
        FROM server_members sm
        JOIN users u ON sm.user_id = u.id
        WHERE sm.server_id = ?
        ORDER BY FIELD(sm.role, 'owner', 'admin', 'member'), u.username ASC
    ");
    $stmt->execute([$server_id]);
    echo json_encode(['members' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
