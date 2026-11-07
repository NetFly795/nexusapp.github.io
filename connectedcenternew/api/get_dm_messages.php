<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
$cid   = intval($_GET['conv_id']  ?? 0);
$after = intval($_GET['after_id'] ?? 0);
$limit = min(intval($_GET['limit'] ?? 60), 100);
if (!$cid) { http_response_code(400); echo json_encode(['error'=>'Missing conv_id']); exit; }
// Verify participant
$stmt = $pdo->prepare("SELECT id FROM conversation_participants WHERE conversation_id=? AND user_id=?");
$stmt->execute([$cid,$me['id']]);
if (!$stmt->fetch()) { http_response_code(403); echo json_encode(['error'=>'Access denied']); exit; }
if ($after > 0) {
    $stmt = $pdo->prepare("SELECT dm.*,u.username as sender_username,u.display_name as sender_display_name,u.profile_picture as sender_pic FROM direct_messages dm JOIN users u ON dm.sender_id=u.id WHERE dm.conversation_id=? AND dm.id>? ORDER BY dm.created_at ASC LIMIT ?");
    $stmt->execute([$cid,$after,$limit]);
    $msgs = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT dm.*,u.username as sender_username,u.display_name as sender_display_name,u.profile_picture as sender_pic FROM direct_messages dm JOIN users u ON dm.sender_id=u.id WHERE dm.conversation_id=? ORDER BY dm.created_at DESC LIMIT ?");
    $stmt->execute([$cid,$limit]);
    $msgs = array_reverse($stmt->fetchAll());
}
echo json_encode(['messages'=>$msgs]);
?>
