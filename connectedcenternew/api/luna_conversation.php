<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../luna_lib.php';
ccJsonHeader();
$me = ccApiAuth();
$pdo = ccDB();
$userId = (int)$me['id'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid conversation id']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $conv = lunaRequireConversation($pdo, $userId, $id);
        $messages = lunaGetConversationMessages($pdo, $userId, $id);
        echo json_encode([
            'success'      => true,
            'conversation' => [
                'id'         => (int)$conv['id'],
                'title'      => $conv['title'],
                'created_at' => $conv['created_at'],
                'updated_at' => $conv['updated_at'],
            ],
            'messages'     => $messages,
        ]);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation not found']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        lunaDeleteConversation($pdo, $userId, $id);
        echo json_encode(['success' => true]);
    } catch (RuntimeException $e) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation not found']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
