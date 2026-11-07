<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../community_games_lib.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();

ccEnsureCommunityGamesTable($pdo);

$stmt = $pdo->prepare("
    SELECT " . ccCommunityGameSelectColumns('g') . ", u.username, u.display_name, 1 AS is_mine
    FROM community_games g
    JOIN users u ON g.user_id = u.id
    WHERE g.user_id = ?
    ORDER BY g.updated_at DESC, g.created_at DESC
    LIMIT 100
");
$stmt->execute([$me['id']]);
$rows = $stmt->fetchAll();

echo json_encode(['games' => array_map('ccFormatCommunityGame', $rows)]);
