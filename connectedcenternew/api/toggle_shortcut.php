<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$type  = in_array($data['type']??'', ['server','conversation']) ? $data['type'] : 'server';
$tid   = intval($data['target_id'] ?? 0);
$label = trim($data['label'] ?? '');
$color = trim($data['icon_color'] ?? '#2196f3');
$action = $data['action'] ?? 'pin'; // 'pin' or 'unpin'

if (!$tid) { http_response_code(400); echo json_encode(['error'=>'Missing target_id']); exit; }

$pdo->exec("CREATE TABLE IF NOT EXISTS shortcuts (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
    type ENUM('server','conversation') DEFAULT 'server', target_id INT NOT NULL,
    label VARCHAR(100) NOT NULL, icon_color VARCHAR(20) DEFAULT '#2196f3',
    sort_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sc (user_id, type, target_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($action === 'unpin') {
    $pdo->prepare("DELETE FROM shortcuts WHERE user_id=? AND type=? AND target_id=?")->execute([$me['id'],$type,$tid]);
    echo json_encode(['success'=>true,'pinned'=>false]);
} else {
    if (!$label) $label = 'Shortcut';
    $pdo->prepare("INSERT INTO shortcuts (user_id,type,target_id,label,icon_color) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label),icon_color=VALUES(icon_color)")->execute([$me['id'],$type,$tid,$label,$color]);
    echo json_encode(['success'=>true,'pinned'=>true]);
}
?>
