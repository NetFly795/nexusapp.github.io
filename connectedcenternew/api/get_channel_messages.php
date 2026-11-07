<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
$cid    = intval($_GET['channel_id'] ?? 0);
$after  = intval($_GET['after_id']   ?? 0);
$limit  = min(intval($_GET['limit']  ?? 60), 100);
if (!$cid) { http_response_code(400); echo json_encode(['error'=>'Missing channel_id']); exit; }
// Verify member
$stmt = $pdo->prepare("SELECT sm.role FROM server_members sm JOIN channels c ON c.server_id=sm.server_id WHERE c.id=? AND sm.user_id=?");
$stmt->execute([$cid,$me['id']]);
if (!$stmt->fetch()) { http_response_code(403); echo json_encode(['error'=>'Access denied']); exit; }
if ($after > 0) {
    $stmt = $pdo->prepare("SELECT cm.*,u.username,u.display_name,u.profile_picture FROM channel_messages cm JOIN users u ON cm.user_id=u.id WHERE cm.channel_id=? AND cm.id>? ORDER BY cm.created_at ASC LIMIT ?");
    $stmt->execute([$cid,$after,$limit]);
} else {
    $stmt = $pdo->prepare("SELECT cm.*,u.username,u.display_name,u.profile_picture FROM channel_messages cm JOIN users u ON cm.user_id=u.id WHERE cm.channel_id=? ORDER BY cm.created_at DESC LIMIT ?");
    $stmt->execute([$cid,$limit]);
}
$msgs = $stmt->fetchAll();
if (!$after) $msgs = array_reverse($msgs);
echo json_encode(['messages'=>$msgs]);
?>
