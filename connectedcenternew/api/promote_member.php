<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$sid    = intval($data['server_id'] ?? 0);
$uid    = intval($data['user_id']   ?? 0);
$newRole = in_array($data['role']??'', ['admin','member']) ? $data['role'] : 'member';

if (!$sid || !$uid) { http_response_code(400); echo json_encode(['error'=>'Missing fields']); exit; }
if ($uid === $me['id']) { http_response_code(400); echo json_encode(['error'=>'Cannot change your own role']); exit; }

// Only owner can promote/demote
$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid, $me['id']]);
$myMem = $stmt->fetch();
if (!$myMem || $myMem['role'] !== 'owner') { http_response_code(403); echo json_encode(['error'=>'Only server owner can promote/demote']); exit; }

// Cannot demote owner
$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid, $uid]);
$target = $stmt->fetch();
if (!$target) { echo json_encode(['error'=>'User not in server']); exit; }
if ($target['role'] === 'owner') { http_response_code(403); echo json_encode(['error'=>'Cannot change owner role']); exit; }

$pdo->prepare("UPDATE server_members SET role=? WHERE server_id=? AND user_id=?")->execute([$newRole,$sid,$uid]);
echo json_encode(['success'=>true,'new_role'=>$newRole]);
?>
