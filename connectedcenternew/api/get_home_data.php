<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_helpers.php';
ccJsonHeader();
$me  = ccApiAuth();
$pdo = ccDB();
ccEnsureServerInvitesTable($pdo);

// Auto-create shortcuts table if missing
$pdo->exec("CREATE TABLE IF NOT EXISTS shortcuts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    type       ENUM('server','conversation') DEFAULT 'server',
    target_id  INT NOT NULL,
    label      VARCHAR(100) NOT NULL,
    icon_color VARCHAR(20)  DEFAULT '#2196f3',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sc (user_id, type, target_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Pinned shortcuts ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM shortcuts WHERE user_id=? ORDER BY sort_order ASC, created_at ASC LIMIT 12");
$stmt->execute([$me['id']]);
$shortcuts = $stmt->fetchAll();

// ── Recent activity (messages from last 48h in joined servers/convs) ──────────
$stmt = $pdo->prepare("
    SELECT cm.id, cm.content, cm.created_at,
           ch.name as channel_name, ch.id as channel_id,
           s.name as server_name, s.id as server_id, s.icon_color,
           u.display_name, u.username, u.profile_picture
    FROM channel_messages cm
    JOIN channels ch ON cm.channel_id = ch.id
    JOIN servers s ON ch.server_id = s.id
    JOIN server_members sm ON s.id = sm.server_id AND sm.user_id = ?
    JOIN users u ON cm.user_id = u.id
    WHERE cm.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
      AND cm.user_id != ?
    ORDER BY cm.created_at DESC
    LIMIT 20
");
$stmt->execute([$me['id'], $me['id']]);
$recentChannel = $stmt->fetchAll();

// ── Recent DMs ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT dm.id, dm.content, dm.created_at, dm.conversation_id,
           u.display_name, u.username, u.profile_picture
    FROM direct_messages dm
    JOIN conversation_participants cp ON dm.conversation_id = cp.conversation_id AND cp.user_id = ?
    JOIN users u ON dm.sender_id = u.id
    WHERE dm.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
      AND dm.sender_id != ?
    ORDER BY dm.created_at DESC
    LIMIT 10
");
$stmt->execute([$me['id'], $me['id']]);
$recentDMs = $stmt->fetchAll();

// ── User's servers (for shortcut modal) ──────────────────────────────────────
$stmt = $pdo->prepare("SELECT s.id, s.name, s.icon_color, sm.role FROM servers s JOIN server_members sm ON s.id=sm.server_id WHERE sm.user_id=? ORDER BY sm.joined_at LIMIT 20");
$stmt->execute([$me['id']]);
$servers = $stmt->fetchAll();

// ── Pending server invites ───────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT si.id, si.server_id, si.created_at,
           s.name AS server_name, s.icon_color,
           u.display_name AS from_name, u.username AS from_username
    FROM server_invites si
    JOIN servers s ON s.id = si.server_id
    JOIN users u ON u.id = si.from_user_id
    WHERE si.to_user_id = ? AND si.status = 'pending'
    ORDER BY si.created_at DESC
    LIMIT 5
");
$stmt->execute([$me['id']]);
$serverInvites = $stmt->fetchAll();

echo json_encode([
    'shortcuts'      => $shortcuts,
    'recent_channel' => $recentChannel,
    'recent_dms'     => $recentDMs,
    'servers'        => $servers,
    'server_invites' => $serverInvites,
]);
?>
