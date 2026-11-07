<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
if (empty($_SESSION['cc_user_id'])) { echo json_encode([]); exit; }
try {
    $pdo  = ccDB();
    $stmt = $pdo->prepare("
        SELECT gi.id, gi.game_name, gi.game_path, gi.created_at,
               u.username as from_username, u.display_name as from_display_name, u.profile_picture as from_avatar
        FROM game_invites gi
        JOIN users u ON gi.from_user_id=u.id
        WHERE gi.to_user_id=? AND gi.status='pending' AND gi.created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ORDER BY gi.created_at DESC LIMIT 5
    ");
    $stmt->execute([$_SESSION['cc_user_id']]);
    $invites = $stmt->fetchAll();
    foreach ($invites as &$inv) $inv['from_name'] = $inv['from_display_name'] ?: $inv['from_username'];
    echo json_encode($invites);
} catch (PDOException $e) { echo json_encode([]); }
?>
