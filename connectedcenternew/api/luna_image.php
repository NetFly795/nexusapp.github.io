<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../luna_lib.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id = preg_replace('/[^a-f0-9]/', '', strtolower($_GET['id'] ?? ''));
if (strlen($id) !== 32) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Invalid image id');
}

$pdo = ccDB();
$row = lunaFetchCachedImageRow($pdo, $id);
if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Image not found');
}

$exp = (int)($_GET['exp'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');
$signedOk = lunaVerifyImageSignature($id, $exp, $sig);
$sessionOk = !empty($_SESSION['cc_user_id']) && (int)$row['user_id'] === (int)$_SESSION['cc_user_id'];

if (!$signedOk && !$sessionOk) {
    if ($exp > 0) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Image link expired or invalid');
    }
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Sign in required');
}

lunaServeCachedImage($row);
