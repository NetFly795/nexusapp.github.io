<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
$sid = intval($_GET['server_id'] ?? 0);
if (!$sid) { http_response_code(400); echo json_encode(['error'=>'Missing server_id']); exit; }
$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid,$me['id']]);
$mem = $stmt->fetch();
if (!$mem) { http_response_code(403); echo json_encode(['error'=>'Not a member']); exit; }
$stmt = $pdo->prepare("SELECT * FROM channels WHERE server_id=? ORDER BY position ASC, created_at ASC");
$stmt->execute([$sid]);
echo json_encode(['channels'=>$stmt->fetchAll(),'user_role'=>$mem['role']]);
?>
