<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$to   = intval($data['to_user_id'] ?? 0);
$game = trim($data['game_name']    ?? '');
$path = trim($data['game_path']    ?? '');
if (!$to || !$game) { http_response_code(400); echo json_encode(['error'=>'Missing fields']); exit; }
$stmt = $pdo->prepare("SELECT id,username,display_name FROM users WHERE id=? AND status!='banned'");
$stmt->execute([$to]);
$recipient = $stmt->fetch();
if (!$recipient) { http_response_code(404); echo json_encode(['error'=>'User not found']); exit; }
// Dedup: no duplicate pending invite within 5 minutes
$stmt = $pdo->prepare("SELECT id FROM game_invites WHERE from_user_id=? AND to_user_id=? AND game_name=? AND status='pending' AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
$stmt->execute([$me['id'],$to,$game]);
if ($stmt->fetch()) { echo json_encode(['success'=>true,'already_sent'=>true]); exit; }
$pdo->prepare("INSERT INTO game_invites (from_user_id,to_user_id,game_name,game_path) VALUES(?,?,?,?)")->execute([$me['id'],$to,$game,$path]);
echo json_encode(['success'=>true,'invite_id'=>$pdo->lastInsertId(),'recipient'=>$recipient['display_name']??$recipient['username']]);
?>
