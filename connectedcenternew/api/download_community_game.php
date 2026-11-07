<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../community_games_lib.php';

ccHandleDownloadPreflight();

$me  = ccOptionalApiAuth();
$pdo = ccDB();

ccEnsureCommunityGamesTable($pdo);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    ccSendDownloadCorsHeaders();
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid game id';
    exit;
}

$row = ccFetchCommunityGame($pdo, $id, true);
if (!$row) {
    ccSendDownloadCorsHeaders();
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Game not found';
    exit;
}

$userId = $me ? (int) $me['id'] : 0;
if (!ccCanDownloadGame($row, $userId)) {
    ccSendDownloadCorsHeaders();
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'This game is private';
    exit;
}

$data = ccLoadCommunityGameProject($row);
if (!$data) {
    ccSendDownloadCorsHeaders();
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Project file missing';
    exit;
}

$pdo->prepare("UPDATE community_games SET play_count = play_count + 1 WHERE id = ?")->execute([$id]);

$safeTitle = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $row['title']) ?: 'game';
ccSendDownloadCorsHeaders();
header('Content-Type: application/octet-stream');
header('Content-Disposition: inline; filename="' . $safeTitle . '.sb3"');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . strlen($data));
echo $data;
