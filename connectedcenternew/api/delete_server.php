<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$sid  = intval($data['server_id'] ?? 0);
if (!$sid) { http_response_code(400); echo json_encode(['error'=>'Missing server_id']); exit; }
$stmt = $pdo->prepare("SELECT owner_id FROM servers WHERE id=?");
$stmt->execute([$sid]);
$srv = $stmt->fetch();
if (!$srv || ($srv['owner_id'] != $me['id'] && $me['role'] !== 'admin')) { http_response_code(403); echo json_encode(['error'=>'Only the owner can delete this server']); exit; }
$pdo->beginTransaction();
$pdo->prepare("DELETE cm FROM channel_messages cm JOIN channels c ON cm.channel_id=c.id WHERE c.server_id=?")->execute([$sid]);
$pdo->prepare("DELETE FROM channels WHERE server_id=?")->execute([$sid]);
$pdo->prepare("DELETE FROM server_members WHERE server_id=?")->execute([$sid]);
$pdo->prepare("DELETE FROM servers WHERE id=?")->execute([$sid]);
$pdo->commit();
echo json_encode(['success'=>true]);
?>
