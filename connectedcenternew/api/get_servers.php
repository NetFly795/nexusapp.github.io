<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me = ccApiAuth();
$stmt = ccDB()->prepare("SELECT s.*,sm.role,(SELECT COUNT(*) FROM server_members WHERE server_id=s.id) as member_count FROM servers s JOIN server_members sm ON s.id=sm.server_id WHERE sm.user_id=? ORDER BY sm.joined_at ASC");
$stmt->execute([$me['id']]);
echo json_encode(['servers'=>$stmt->fetchAll()]);
?>
