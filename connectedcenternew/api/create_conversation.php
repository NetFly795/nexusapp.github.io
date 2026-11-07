<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$type = ($data['type'] ?? 'direct') === 'group' ? 'group' : 'direct';
$uid  = intval($data['user_id'] ?? 0); // for direct

if ($type === 'direct') {
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'Missing user_id']); exit; }
    // Check user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND status!='banned'");
    $stmt->execute([$uid]);
    if (!$stmt->fetch()) { http_response_code(404); echo json_encode(['error'=>'User not found']); exit; }

    // Check if a direct conversation already exists between these two
    $stmt = $pdo->prepare("
        SELECT c.id FROM conversations c
        JOIN conversation_participants cp1 ON c.id=cp1.conversation_id AND cp1.user_id=?
        JOIN conversation_participants cp2 ON c.id=cp2.conversation_id AND cp2.user_id=?
        WHERE c.type='direct'
        LIMIT 1
    ");
    $stmt->execute([$me['id'],$uid]);
    $existing = $stmt->fetch();
    if ($existing) { echo json_encode(['success'=>true,'conv_id'=>$existing['id']]); exit; }

    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO conversations (type) VALUES('direct')")->execute();
    $cid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO conversation_participants (conversation_id,user_id) VALUES(?,?)")->execute([$cid,$me['id']]);
    $pdo->prepare("INSERT INTO conversation_participants (conversation_id,user_id) VALUES(?,?)")->execute([$cid,$uid]);
    $pdo->commit();
    echo json_encode(['success'=>true,'conv_id'=>$cid]);
} else {
    $name = trim($data['name'] ?? 'Group Chat');
    $uids = array_map('intval', $data['user_ids'] ?? []);
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO conversations (type,name) VALUES('group',?)")->execute([$name]);
    $cid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO conversation_participants (conversation_id,user_id) VALUES(?,?)")->execute([$cid,$me['id']]);
    foreach (array_unique($uids) as $u) {
        if ($u !== $me['id']) $pdo->prepare("INSERT IGNORE INTO conversation_participants (conversation_id,user_id) VALUES(?,?)")->execute([$cid,$u]);
    }
    $pdo->commit();
    echo json_encode(['success'=>true,'conv_id'=>$cid]);
}
?>
