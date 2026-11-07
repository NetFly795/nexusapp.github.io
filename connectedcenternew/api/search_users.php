<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me = ccApiAuth();
$pdo = ccDB();
$q = trim($_GET['q'] ?? '');
$exSrv = intval($_GET['exclude_server'] ?? 0);
if (!$q) { echo json_encode(['users'=>[]]); exit; }
try {
    if ($exSrv) {
        $stmt = $pdo->prepare("SELECT id,username,display_name,profile_picture FROM users WHERE (username LIKE ? OR display_name LIKE ?) AND id!=? AND status!='banned' AND id NOT IN (SELECT user_id FROM server_members WHERE server_id=?) ORDER BY username LIMIT 15");
        $stmt->execute(['%'.$q.'%','%'.$q.'%',$me['id'],$exSrv]);
    } else {
        $stmt = $pdo->prepare("SELECT id,username,display_name,profile_picture FROM users WHERE (username LIKE ? OR display_name LIKE ?) AND id!=? AND status!='banned' ORDER BY username LIMIT 15");
        $stmt->execute(['%'.$q.'%','%'.$q.'%',$me['id']]);
    }
    echo json_encode(['users'=>$stmt->fetchAll()]);
} catch (PDOException $e) { http_response_code(500); echo json_encode(['error'=>'Search failed']); }
?>
