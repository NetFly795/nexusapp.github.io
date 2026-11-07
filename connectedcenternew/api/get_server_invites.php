<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_helpers.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
ccEnsureServerInvitesTable($pdo);

$stmt = $pdo->prepare("
    SELECT si.id, si.server_id, si.created_at,
           s.name AS server_name, s.icon_color,
           u.display_name AS from_name, u.username AS from_username
    FROM server_invites si
    JOIN servers s ON s.id = si.server_id
    JOIN users u ON u.id = si.from_user_id
    WHERE si.to_user_id = ? AND si.status = 'pending'
    ORDER BY si.created_at DESC
    LIMIT 10
");
$stmt->execute([$me['id']]);
echo json_encode(['invites' => $stmt->fetchAll()]);
