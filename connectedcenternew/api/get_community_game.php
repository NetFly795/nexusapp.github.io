<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../community_games_lib.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();

ccEnsureCommunityGamesTable($pdo);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid game id']);
    exit;
}

$row = ccFetchCommunityGame($pdo, $id);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Game not found']);
    exit;
}

if (!ccCanViewGame($row, (int) $me['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'This game is private']);
    exit;
}

$game = ccFormatCommunityGame(array_merge($row, ['is_mine' => (int) $row['user_id'] === (int) $me['id']]));
echo json_encode(['game' => $game]);
