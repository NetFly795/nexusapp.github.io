<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_helpers.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
ccEnsureServerInvitesTable($pdo);
$data = json_decode(file_get_contents('php://input'), true);
$sid  = intval($data['server_id'] ?? 0);
$uid  = intval($data['user_id']   ?? 0);
if (!$sid || !$uid) { http_response_code(400); echo json_encode(['error'=>'Missing fields']); exit; }

$stmt = $pdo->prepare("SELECT role FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid, $me['id']]);
if (!$stmt->fetch()) { http_response_code(403); echo json_encode(['error'=>'Not a member']); exit; }

$stmt = $pdo->prepare("SELECT id FROM server_members WHERE server_id=? AND user_id=?");
$stmt->execute([$sid, $uid]);
if ($stmt->fetch()) { echo json_encode(['error'=>'User is already in this server']); exit; }

$stmt = $pdo->prepare("SELECT id FROM server_invites WHERE server_id=? AND to_user_id=? AND status='pending'");
$stmt->execute([$sid, $uid]);
if ($stmt->fetch()) { echo json_encode(['success'=>true, 'already_invited'=>true]); exit; }

$pdo->prepare("INSERT INTO server_invites (server_id, from_user_id, to_user_id) VALUES (?,?,?)")
    ->execute([$sid, $me['id'], $uid]);
echo json_encode(['success'=>true]);
