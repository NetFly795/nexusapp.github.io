<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me   = ccApiAuth();
$pdo  = ccDB();
$data = json_decode(file_get_contents('php://input'), true) ?: [];

$display = trim($data['display_name'] ?? '');
$email   = trim($data['email'] ?? '');
$pwNew   = $data['new_password'] ?? '';
$pwConf  = $data['confirm_password'] ?? '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address.']);
    exit;
}
if ($pwNew && strlen($pwNew) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'New password must be at least 8 characters.']);
    exit;
}
if ($pwNew && $pwNew !== $pwConf) {
    http_response_code(400);
    echo json_encode(['error' => 'Passwords do not match.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
$stmt->execute([$email, $me['id']]);
if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'That email is already in use.']);
    exit;
}

$picPath = $me['profile_picture'];
$avatarData = trim($data['avatar_data'] ?? '');
if ($avatarData && substr($avatarData, 0, 23) === 'data:image/jpeg;base64,') {
    $b64 = substr($avatarData, 23);
    $raw = base64_decode($b64, true);
    if ($raw === false || strlen($raw) > 600 * 1024) {
        http_response_code(400);
        echo json_encode(['error' => 'Avatar data is invalid or too large.']);
        exit;
    }
    $dir = __DIR__ . '/../avatars/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = 'av_' . $me['id'] . '_' . time() . '.jpg';
    if (file_put_contents($dir . $fname, $raw) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save avatar.']);
        exit;
    }
    if ($me['profile_picture'] && strpos($me['profile_picture'], 'avatars/') === 0) {
        @unlink(__DIR__ . '/../' . $me['profile_picture']);
    }
    $picPath = 'avatars/' . $fname;
}

if ($pwNew) {
    $pdo->prepare("UPDATE users SET display_name=?,email=?,profile_picture=?,password_hash=? WHERE id=?")
        ->execute([$display ?: null, $email, $picPath, password_hash($pwNew, PASSWORD_DEFAULT), $me['id']]);
} else {
    $pdo->prepare("UPDATE users SET display_name=?,email=?,profile_picture=? WHERE id=?")
        ->execute([$display ?: null, $email, $picPath, $me['id']]);
}

$stmt = $pdo->prepare("SELECT id,username,display_name,email,profile_picture,role FROM users WHERE id=?");
$stmt->execute([$me['id']]);
$user = $stmt->fetch();

echo json_encode(['success' => true, 'user' => $user]);
?>
