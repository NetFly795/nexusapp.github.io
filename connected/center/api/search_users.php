<?php
session_start();
require_once '../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$exclude_server = intval($_GET['exclude_server'] ?? 0);

if (strlen($query) < 1) {
    echo json_encode(['users' => []]);
    exit;
}

try {
    $pdo = getDBConnection();
    if ($exclude_server) {
        $stmt = $pdo->prepare("
            SELECT id, username, display_name, profile_picture
            FROM users
            WHERE (username LIKE ? OR display_name LIKE ?)
            AND id != ?
            AND role != 'banned'
            AND id NOT IN (SELECT user_id FROM server_members WHERE server_id = ?)
            ORDER BY username LIMIT 10
        ");
        $stmt->execute(['%'.$query.'%', '%'.$query.'%', $_SESSION['user_id'], $exclude_server]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, username, display_name, profile_picture
            FROM users
            WHERE (username LIKE ? OR display_name LIKE ?)
            AND id != ?
            AND role != 'banned'
            ORDER BY username LIMIT 10
        ");
        $stmt->execute(['%'.$query.'%', '%'.$query.'%', $_SESSION['user_id']]);
    }
    echo json_encode(['users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Search failed']);
}
?>
