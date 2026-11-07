<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$cid  = intval($data['conv_id'] ?? 0);
$content = trim($data['content'] ?? '');
if (!$cid || !$content) { http_response_code(400); echo json_encode(['error'=>'Missing fields']); exit; }
if (strlen($content) > 2000) { http_response_code(400); echo json_encode(['error'=>'Message too long']); exit; }
$stmt = $pdo->prepare("SELECT id FROM conversation_participants WHERE conversation_id=? AND user_id=?");
$stmt->execute([$cid,$me['id']]);
if (!$stmt->fetch()) { http_response_code(403); echo json_encode(['error'=>'Access denied']); exit; }
$pdo->prepare("INSERT INTO direct_messages (conversation_id,sender_id,content) VALUES(?,?,?)")->execute([$cid,$me['id'],$content]);
echo json_encode(['success'=>true,'message_id'=>$pdo->lastInsertId()]);
?>
