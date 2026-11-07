<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$cid     = intval($data['channel_id'] ?? 0);
$minRole = in_array($data['min_role']??'', ['member','admin','owner']) ? $data['min_role'] : 'member';

if (!$cid) { http_response_code(400); echo json_encode(['error'=>'Missing channel_id']); exit; }

// Verify requester is admin/owner of the server
$stmt = $pdo->prepare("SELECT sm.role FROM server_members sm JOIN channels c ON c.server_id=sm.server_id WHERE c.id=? AND sm.user_id=?");
$stmt->execute([$cid, $me['id']]);
$mem = $stmt->fetch();
if (!$mem || $mem['role'] === 'member') { http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS channel_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL UNIQUE,
    min_role ENUM('member','admin','owner') DEFAULT 'member',
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->prepare("INSERT INTO channel_permissions (channel_id, min_role) VALUES(?,?) ON DUPLICATE KEY UPDATE min_role=VALUES(min_role)")->execute([$cid, $minRole]);
echo json_encode(['success'=>true,'min_role'=>$minRole]);
?>
