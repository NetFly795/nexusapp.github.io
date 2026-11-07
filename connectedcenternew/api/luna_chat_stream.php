<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../luna_lib.php';

$me = ccApiAuth();
$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$messages = lunaParseClientMessages($raw['messages'] ?? []);

if (!count($messages) || end($messages)['role'] !== 'user') {
    http_response_code(400);
    ccJsonHeader();
    echo json_encode(['error' => 'Last message must be from the user.']);
    exit;
}

$pdo = ccDB();
$userId = (int)$me['id'];
$conversationId = (int)($raw['conversation_id'] ?? 0);
$copilotGame = isset($raw['copilot_game']) ? trim(substr((string)$raw['copilot_game'], 0, 120)) : '';

lunaBeginSse();

try {
    if ($conversationId <= 0) {
        $conv = lunaCreateConversation($pdo, $userId, 'New chat');
        $conversationId = (int)$conv['id'];
        lunaSseEmit(['type' => 'conversation', 'id' => $conversationId, 'title' => $conv['title']]);
    } else {
        lunaRequireConversation($pdo, $userId, $conversationId);
    }

    lunaProcessChatStream($pdo, $userId, $messages, $conversationId, $copilotGame);
} catch (RuntimeException $e) {
    lunaSseEmit(['type' => 'error', 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    lunaSseEmit(['type' => 'error', 'error' => 'Luna could not respond right now. Please try again in a moment.']);
}

lunaSseEmit(['type' => 'end']);
