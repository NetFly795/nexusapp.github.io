<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    // Not logged in — return empty silently (used by polling)
    echo json_encode([]);
    exit;
}

try {
    $pdo = getDBConnection();

    // Table may not exist yet if no invite was ever sent
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

    // Fetch pending invites for the current user, not older than 10 minutes
    $stmt = $pdo->prepare("
        SELECT gi.id, gi.game_name, gi.game_path, gi.created_at,
               u.username as from_username, u.display_name as from_display_name,
               u.profile_picture as from_avatar
        FROM game_invites gi
        JOIN users u ON gi.from_user_id = u.id
        WHERE gi.to_user_id = ?
          AND gi.status = 'pending'
          AND gi.created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY gi.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $invites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Resolve display name
    foreach ($invites as &$inv) {
        $inv['from_name'] = $inv['from_display_name'] ?: $inv['from_username'];
    }

    echo json_encode($invites);
} catch (PDOException $e) {
    echo json_encode([]);
}
?>
