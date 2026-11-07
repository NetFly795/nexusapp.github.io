<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../community_games_lib.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();

ccEnsureCommunityGamesTable($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$gameId = (int) ($_POST['game_id'] ?? 0);
if ($gameId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid game id']);
    exit;
}

$row = ccFetchCommunityGame($pdo, $gameId);
if (!$row || (int) $row['user_id'] !== (int) $me['id']) {
    http_response_code(403);
    echo json_encode(['error' => 'You can only update your own games']);
    exit;
}

$title = trim($_POST['title'] ?? $row['title']);
if (!$title) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}
$title = mb_substr($title, 0, 120);
$visibility = ccNormalizeVisibility(trim($_POST['visibility'] ?? ($row['visibility'] ?? 'public')));

if (!empty($_FILES['project']) && $_FILES['project']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['project'];
    if ($file['size'] > 50 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Project file is too large (max 50 MB).']);
        exit;
    }
    if (!ccIsValidSb3($file['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid project file. Please upload a .sb3 file.']);
        exit;
    }
    $saved = ccStoreCommunityGameProject($pdo, $gameId, $file['tmp_name']);
    if (!$saved['ok']) {
        http_response_code(500);
        echo json_encode($saved);
        exit;
    }
    $path = ccCommunityGameAbsPath($row['file_path'] ?? '');
    if ($path && is_file($path)) {
        @unlink($path);
    }
}

$pdo->prepare("UPDATE community_games SET title=?, visibility=?, updated_at=NOW() WHERE id=? AND user_id=?")
    ->execute([$title, $visibility, $gameId, $me['id']]);

$stmt = $pdo->prepare("
    SELECT " . ccCommunityGameSelectColumns('g') . ", u.username, u.display_name, 1 AS is_mine
    FROM community_games g
    JOIN users u ON g.user_id = u.id
    WHERE g.id = ?
");
$stmt->execute([$gameId]);
$updated = $stmt->fetch();

echo json_encode([
    'success' => true,
    'game'    => ccFormatCommunityGame($updated),
]);
