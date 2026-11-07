<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../luna_lib.php';
ccJsonHeader();
$me = ccApiAuth();
$pdo = ccDB();
$userId = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = lunaListConversations($pdo, $userId);
    $conversations = array_map(static function (array $row): array {
        $preview = trim((string)($row['preview'] ?? ''));
        if ($preview !== '' && strlen($preview) > 80) {
            $preview = (function_exists('mb_substr') ? mb_substr($preview, 0, 77) : substr($preview, 0, 77)) . '…';
        }
        return [
            'id'         => (int)$row['id'],
            'title'      => $row['title'],
            'preview'    => $preview,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }, $rows);
    echo json_encode(['success' => true, 'conversations' => $conversations]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = json_decode(file_get_contents('php://input'), true) ?: [];
    $title = trim($raw['title'] ?? '') ?: 'New chat';
    $conv = lunaCreateConversation($pdo, $userId, $title);
    echo json_encode([
        'success'      => true,
        'conversation' => [
            'id'         => (int)$conv['id'],
            'title'      => $conv['title'],
            'created_at' => $conv['created_at'],
            'updated_at' => $conv['updated_at'],
        ],
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
