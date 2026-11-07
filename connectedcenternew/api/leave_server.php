<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$sid  = intval($data['server_id'] ?? 0);
if (!$sid) { http_response_code(400); echo json_encode(['error'=>'Missing server_id']); exit; }
$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid,$me['id']]);
$mem = $stmt->fetch();
if (!$mem) { echo json_encode(['success'=>true,'already_left'=>true]); exit; }
if ($mem['role'] === 'owner') { http_response_code(400); echo json_encode(['error'=>'Owner cannot leave — delete the server instead.']); exit; }
$pdo->prepare("DELETE FROM server_members WHERE server_id=? AND user_id=?")->execute([$sid,$me['id']]);
echo json_encode(['success'=>true]);
?>
