<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
if (empty($_SESSION['cc_user_id'])) { echo json_encode(['status'=>'not_logged_in']); exit; }
$pdo  = ccDB();
$stmt = $pdo->prepare("SELECT id,username,display_name,profile_picture,role FROM users WHERE id=? AND status!='banned'");
$stmt->execute([$_SESSION['cc_user_id']]);
$user = $stmt->fetch();
if (!$user) { echo json_encode(['status'=>'inactive']); exit; }
$pdo->prepare("UPDATE users SET last_seen_at=NOW() WHERE id=?")->execute([$user['id']]);
echo json_encode(['status'=>'active','user'=>$user]);
?>
