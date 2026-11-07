<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../luna_lib.php';
ccJsonHeader();
$me = ccApiAuth();

$raw = json_decode(file_get_contents('php://input'), true) ?: [];
$messages = lunaParseClientMessages($raw['messages'] ?? []);
if (!count($messages)) {
    http_response_code(400);
    echo json_encode(['error' => 'No messages provided.']);
    exit;
}

if (end($messages)['role'] !== 'user') {
    http_response_code(400);
    echo json_encode(['error' => 'Last message must be from the user.']);
    exit;
}

if (count($messages) > 40) {
    $messages = array_slice($messages, -40);
}

try {
    $pdo = ccDB();
    $copilotGame = isset($raw['copilot_game']) ? trim(substr((string)$raw['copilot_game'], 0, 120)) : '';
    $result = lunaProcessChat($pdo, (int)$me['id'], $messages, $copilotGame);
    echo json_encode([
        'success' => true,
        'reply'   => $result['reply'],
        'images'  => $result['images'],
        'quota'   => $result['quota'],
        'user'    => [
            'username'     => $me['username'],
            'display_name' => $me['display_name'] ?: $me['username'],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['error' => 'Luna could not respond right now. Please try again in a moment.']);
}
