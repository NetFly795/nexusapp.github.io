<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
$sid = intval($_GET['server_id'] ?? 0);
if (!$sid) { http_response_code(400); echo json_encode(['error'=>'Missing server_id']); exit; }
$stmt = $pdo->prepare("SELECT id FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid,$me['id']]);
if (!$stmt->fetch()) { http_response_code(403); echo json_encode(['error'=>'Access denied']); exit; }
$stmt = $pdo->prepare("SELECT u.id,u.username,u.display_name,u.profile_picture,sm.role,sm.joined_at FROM server_members sm JOIN users u ON sm.user_id=u.id WHERE sm.server_id=? ORDER BY FIELD(sm.role,'owner','admin','member'),u.username ASC");
$stmt->execute([$sid]);
echo json_encode(['members'=>$stmt->fetchAll()]);
?>
