<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$iid  = intval($data['invite_id'] ?? 0);
$act  = in_array($data['action']??'',['accepted','ignored'])?$data['action']:'ignored';
if (!$iid) { http_response_code(400); echo json_encode(['error'=>'Missing invite_id']); exit; }
$pdo->prepare("UPDATE game_invites SET status=? WHERE id=? AND to_user_id=?")->execute([$act,$iid,$me['id']]);
echo json_encode(['success'=>true]);
?>
