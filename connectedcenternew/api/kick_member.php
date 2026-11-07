<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$sid  = intval($data['server_id'] ?? 0);
$uid  = intval($data['user_id']   ?? 0);
$ban  = ($data['action'] ?? 'kick') === 'ban';

if (!$sid || !$uid) { http_response_code(400); echo json_encode(['error'=>'Missing fields']); exit; }
if ($uid === $me['id']) { http_response_code(400); echo json_encode(['error'=>'Cannot kick yourself']); exit; }

// Check requester role
$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid, $me['id']]);
$myMem = $stmt->fetch();
if (!$myMem || $myMem['role'] === 'member') { http_response_code(403); echo json_encode(['error'=>'Admin only']); exit; }

// Cannot kick an owner, and non-owners cannot kick admins
$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid, $uid]);
$target = $stmt->fetch();
if (!$target) { echo json_encode(['error'=>'User not in server']); exit; }
if ($target['role'] === 'owner') { http_response_code(403); echo json_encode(['error'=>'Cannot kick the owner']); exit; }
if ($target['role'] === 'admin' && $myMem['role'] !== 'owner') { http_response_code(403); echo json_encode(['error'=>'Only owner can kick admins']); exit; }

// Remove from server
$pdo->prepare("DELETE FROM server_members WHERE server_id=? AND user_id=?")->execute([$sid, $uid]);

if ($ban) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS server_bans (
        id INT AUTO_INCREMENT PRIMARY KEY, server_id INT NOT NULL,
        user_id INT NOT NULL, banned_by INT NOT NULL,
        reason VARCHAR(500) DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ban (server_id, user_id),
        FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->prepare("INSERT INTO server_bans (server_id,user_id,banned_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE banned_by=VALUES(banned_by),created_at=NOW()")->execute([$sid,$uid,$me['id']]);
}

echo json_encode(['success'=>true,'action'=>$ban?'banned':'kicked']);
?>
