<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_helpers.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
ccEnsureDmReadState($pdo);

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM direct_messages dm
    JOIN conversation_participants cp
      ON cp.conversation_id = dm.conversation_id AND cp.user_id = ?
    WHERE dm.sender_id != ?
      AND dm.created_at > COALESCE(cp.last_read_at, '1970-01-01 00:00:00')
");
$stmt->execute([$me['id'], $me['id']]);
$count = (int)($stmt->fetch()['cnt'] ?? 0);

$latest = null;
if ($count > 0) {
    $stmt = $pdo->prepare("
        SELECT dm.content, u.display_name, u.username
        FROM direct_messages dm
        JOIN conversation_participants cp
          ON cp.conversation_id = dm.conversation_id AND cp.user_id = ?
        JOIN users u ON u.id = dm.sender_id
        WHERE dm.sender_id != ?
          AND dm.created_at > COALESCE(cp.last_read_at, '1970-01-01 00:00:00')
        ORDER BY dm.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$me['id'], $me['id']]);
    $latest = $stmt->fetch() ?: null;
}

echo json_encode([
    'count'  => $count,
    'latest' => $latest ? [
        'name'    => $latest['display_name'] ?: $latest['username'],
        'preview' => mb_substr($latest['content'], 0, 80)
    ] : null
]);
