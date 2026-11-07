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

if (empty($_FILES['project']) || $_FILES['project']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No project file received.']);
    exit;
}

$file = $_FILES['project'];
if ($file['size'] < 22) {
    http_response_code(400);
    echo json_encode(['error' => 'Project file is empty or too small.', 'size' => $file['size']]);
    exit;
}
if ($file['size'] > 50 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'Project file is too large (max 50 MB).']);
    exit;
}

$title = trim($_POST['title'] ?? '');
if (!$title) {
    $base = pathinfo($file['name'] ?? 'game.sb3', PATHINFO_FILENAME);
    $title = $base ?: 'Untitled Game';
}
$title = mb_substr($title, 0, 120);
$visibility = ccNormalizeVisibility(trim($_POST['visibility'] ?? 'public'));

if (!ccIsValidSb3($file['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid project file. Please upload a .sb3 file.']);
    exit;
}

$emoji    = ccRandomGameEmoji();
$gradient = ccRandomGameGradient();

$pdo->prepare("INSERT INTO community_games (user_id, title, emoji, gradient, visibility, file_path) VALUES (?,?,?,?,?,?)")
    ->execute([$me['id'], $title, $emoji, $gradient, $visibility, 'db']);

$gameId = (int) $pdo->lastInsertId();

$saved = ccStoreCommunityGameProject($pdo, $gameId, $file['tmp_name']);
if (!$saved['ok']) {
    $pdo->prepare("DELETE FROM community_games WHERE id=?")->execute([$gameId]);
    http_response_code(500);
    echo json_encode($saved);
    exit;
}

$stmt = $pdo->prepare("
    SELECT " . ccCommunityGameSelectColumns('g') . ", u.username, u.display_name, 1 AS is_mine
    FROM community_games g
    JOIN users u ON g.user_id = u.id
    WHERE g.id = ?
");
$stmt->execute([$gameId]);
$row = $stmt->fetch();

echo json_encode([
    'success' => true,
    'game'    => ccFormatCommunityGame($row),
    'storage' => $saved['storage'] ?? 'database',
]);
