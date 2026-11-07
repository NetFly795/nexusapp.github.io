<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$sid  = intval($data['server_id'] ?? 0);
$name = strtolower(trim(preg_replace('/[^a-z0-9\-]/','',strtolower(trim($data['name']??''))),'-'));
$type = in_array($data['type']??'text',['text','announcement'])?($data['type']??'text'):'text';
if (!$sid || !$name) { http_response_code(400); echo json_encode(['error'=>'Invalid input']); exit; }
$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid,$me['id']]);
$mem = $stmt->fetch();
if (!$mem || $mem['role']==='member') { http_response_code(403); echo json_encode(['error'=>'Only admins can add channels']); exit; }
$pos = $pdo->prepare("SELECT COALESCE(MAX(position),-1)+1 FROM channels WHERE server_id=?");
$pos->execute([$sid]); $p=$pos->fetchColumn();
$pdo->prepare("INSERT INTO channels (server_id,name,type,position) VALUES(?,?,?,?)")->execute([$sid,$name,$type,$p]);
echo json_encode(['success'=>true,'channel_id'=>$pdo->lastInsertId()]);
?>
