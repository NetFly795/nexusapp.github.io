<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../luna_lib.php';
ccJsonHeader();

if (empty($_SESSION['cc_user_id'])) {
    echo json_encode(['logged_in' => false]);
    exit;
}

$pdo = ccDB();
$stmt = $pdo->prepare("SELECT id, username, display_name FROM users WHERE id = ? AND status != 'banned'");
$stmt->execute([$_SESSION['cc_user_id']]);
$user = $stmt->fetch();
if (!$user) {
    echo json_encode(['logged_in' => false]);
    exit;
}

echo json_encode([
    'logged_in' => true,
    'user'      => [
        'username'     => $user['username'],
        'display_name' => $user['display_name'] ?: $user['username'],
    ],
    'quota'     => lunaImageQuota($pdo, (int)$user['id']),
]);
