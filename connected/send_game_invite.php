<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$to_user_id = intval($data['to_user_id'] ?? 0);
$game_name  = trim($data['game_name'] ?? '');
$game_path  = trim($data['game_path'] ?? '');

if (!$to_user_id || !$game_name) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Bootstrap table
    $pdo->exec("CREATE TABLE IF NOT EXISTS game_invites (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        from_user_id INT NOT NULL,
        to_user_id   INT NOT NULL,
        game_name    VARCHAR(200) NOT NULL,
        game_path    VARCHAR(500) NOT NULL DEFAULT '',
        status       ENUM('pending','accepted','ignored') DEFAULT 'pending',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE CASCADE
    )");

    // Verify recipient exists
    $stmt = $pdo->prepare("SELECT id, username, display_name FROM users WHERE id = ? AND role != 'banned'");
    $stmt->execute([$to_user_id]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$recipient) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Prevent duplicate pending invites to same user for same game
    $stmt = $pdo->prepare("
        SELECT id FROM game_invites
        WHERE from_user_id = ? AND to_user_id = ? AND game_name = ? AND status = 'pending'
        AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$_SESSION['user_id'], $to_user_id, $game_name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => true, 'already_sent' => true]);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO game_invites (from_user_id, to_user_id, game_name, game_path)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $to_user_id, $game_name, $game_path]);

    echo json_encode([
        'success'     => true,
        'invite_id'   => $pdo->lastInsertId(),
        'recipient'   => $recipient['display_name'] ?: $recipient['username'],
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not send invite']);
}
?>
