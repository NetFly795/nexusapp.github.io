<?php
session_start();
require_once '../config.php';
$pdo = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$conversation_id = $_GET['conversation_id'] ?? null;
if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => 'Missing conversation ID']);
    exit;
}

// Check if user is participant
$stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
$stmt->execute([$conversation_id, $_SESSION['user_id']]);
if ($stmt->fetchColumn() == 0) {
    echo json_encode(['success' => false, 'message' => 'Not a participant']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.display_name, u.profile_picture
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$conversation_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'messages' => $messages]);

?> 