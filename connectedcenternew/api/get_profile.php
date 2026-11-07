<?php
require_once __DIR__ . '/../auth.php';
ccJsonHeader();
$me = ccApiAuth();
echo json_encode([
    'id'              => (int)$me['id'],
    'username'        => $me['username'],
    'display_name'    => $me['display_name'],
    'email'           => $me['email'],
    'profile_picture' => $me['profile_picture'] ?? '',
    'role'            => $me['role'],
]);
?>
