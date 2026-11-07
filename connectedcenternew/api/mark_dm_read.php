<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_helpers.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
ccEnsureDmReadState($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$conv = intval($data['conv_id'] ?? 0);

if ($conv) {
    $stmt = $pdo->prepare("
        UPDATE conversation_participants
        SET last_read_at = NOW()
        WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$conv, $me['id']]);
} else {
    $stmt = $pdo->prepare("
        UPDATE conversation_participants cp
        SET last_read_at = NOW()
        WHERE cp.user_id = ?
    ");
    $stmt->execute([$me['id']]);
}

echo json_encode(['success' => true]);
