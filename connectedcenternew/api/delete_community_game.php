<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../community_games_lib.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();

ccEnsureCommunityGamesTable($pdo);

$data   = json_decode(file_get_contents('php://input'), true) ?: [];
$gameId = (int) ($data['game_id'] ?? $_POST['game_id'] ?? 0);

if ($gameId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid game id']);
    exit;
}

$row = ccFetchCommunityGame($pdo, $gameId);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Game not found']);
    exit;
}

$isAdmin = ($me['role'] ?? '') === 'admin';
$isOwner = (int) $row['user_id'] === (int) $me['id'];

if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only delete your own games']);
    exit;
}

$path = ccCommunityGameAbsPath($row['file_path'] ?? '');
if ($path && is_file($path)) {
    @unlink($path);
}

if ($isAdmin) {
    $pdo->prepare("DELETE FROM community_games WHERE id=?")->execute([$gameId]);
} else {
    $pdo->prepare("DELETE FROM community_games WHERE id=? AND user_id=?")->execute([$gameId, $me['id']]);
}

echo json_encode(['success' => true, 'moderation' => $isAdmin && !$isOwner]);
