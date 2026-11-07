<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_helpers.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
ccEnsureServerInvitesTable($pdo);
$data = json_decode(file_get_contents('php://input'), true) ?: [];
$id   = intval($data['invite_id'] ?? 0);
$act  = $data['action'] ?? '';

if (!$id || !in_array($act, ['accept', 'decline'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM server_invites WHERE id = ? AND to_user_id = ? AND status = 'pending'");
$stmt->execute([$id, $me['id']]);
$invite = $stmt->fetch();
if (!$invite) {
    http_response_code(404);
    echo json_encode(['error' => 'Invite not found']);
    exit;
}

$status = $act === 'accept' ? 'accepted' : 'declined';
$pdo->prepare("UPDATE server_invites SET status = ? WHERE id = ?")->execute([$status, $id]);

if ($act === 'accept') {
    $pdo->prepare("INSERT IGNORE INTO server_members (server_id, user_id, role) VALUES (?, ?, 'member')")
        ->execute([$invite['server_id'], $me['id']]);
}

echo json_encode(['success' => true, 'server_id' => (int)$invite['server_id']]);
