<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
$stmt = $pdo->prepare("
    SELECT c.id, c.type, c.name,
           (SELECT content FROM direct_messages WHERE conversation_id=c.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT MAX(created_at) FROM direct_messages WHERE conversation_id=c.id) as last_time,
           (SELECT GROUP_CONCAT(u2.display_name ORDER BY u2.id SEPARATOR ', ')
            FROM conversation_participants cp2 JOIN users u2 ON cp2.user_id=u2.id
            WHERE cp2.conversation_id=c.id AND cp2.user_id!=?) as participant_names
    FROM conversations c
    JOIN conversation_participants cp ON c.id=cp.conversation_id
    WHERE cp.user_id=?
    ORDER BY last_time DESC LIMIT 40
");
$stmt->execute([$me['id'],$me['id']]);
echo json_encode(['conversations'=>$stmt->fetchAll()]);
?>
