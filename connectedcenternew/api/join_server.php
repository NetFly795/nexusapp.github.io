<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$sid  = intval($data['server_id'] ?? 0);
if (!$sid) { http_response_code(400); echo json_encode(['error'=>'Missing server_id']); exit; }
if (!$pdo->prepare("SELECT id FROM servers WHERE id=?")->execute([$sid])) { http_response_code(404); echo json_encode(['error'=>'Server not found']); exit; }
$pdo->prepare("INSERT IGNORE INTO server_members (server_id,user_id,role) VALUES(?,?,'member')")->execute([$sid,$me['id']]);
echo json_encode(['success'=>true]);
?>
