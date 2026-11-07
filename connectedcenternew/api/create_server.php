<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true);
$name  = trim($data['name'] ?? '');
$desc  = trim($data['description'] ?? '');
$color = trim($data['icon_color'] ?? '#5865f2');
if (strlen($name) < 2 || strlen($name) > 50) { http_response_code(400); echo json_encode(['error'=>'Name must be 2–50 characters']); exit; }
try {
    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO servers (name,description,icon_color,owner_id) VALUES(?,?,?,?)")->execute([$name,$desc,$color,$me['id']]);
    $sid = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO server_members (server_id,user_id,role) VALUES(?,?,'owner')")->execute([$sid,$me['id']]);
    $pdo->prepare("INSERT INTO channels (server_id,name,type,position) VALUES(?,?,?,?)")->execute([$sid,'general','text',0]);
    $pdo->commit();
    echo json_encode(['success'=>true,'server_id'=>$sid]);
} catch (PDOException $e) { $pdo->rollBack(); http_response_code(500); echo json_encode(['error'=>'Could not create server']); }
?>
