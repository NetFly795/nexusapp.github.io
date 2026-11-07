<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include shared configuration
require_once '../config.php';

// Get database connection
$pdo = getDBConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    
    $pdo->beginTransaction();
    
    try {
        if ($type === 'direct') {
            $user_id = $input['user_id'] ?? null;
            
            if (!$user_id) {
                throw new Exception('Missing user ID');
            }
            
            // Check if user exists and is not banned
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role != 'banned'");
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                throw new Exception('User not found or banned');
            }
            
            // Check if conversation already exists
            $stmt = $pdo->prepare("
                SELECT c.id FROM conversations c
                JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                WHERE c.conversation_type = 'direct'
                AND cp1.user_id = ? AND cp2.user_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $user_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                echo json_encode(['success' => true, 'conversation_id' => $existing['id']]);
                exit;
            }
            
            // Create new conversation
            $stmt = $pdo->prepare("INSERT INTO conversations (conversation_type) VALUES ('direct')");
            $stmt->execute();
            $conversation_id = $pdo->lastInsertId();
            
            // Add participants
            $stmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
            $stmt->execute([$conversation_id, $_SESSION['user_id'], $conversation_id, $user_id]);
            
        } elseif ($type === 'group') {
            $name = trim($input['name'] ?? '');
            $user_ids = $input['user_ids'] ?? [];
            
            if (!$name) {
                throw new Exception('Group name is required');
            }
            
            if (empty($user_ids)) {
                throw new Exception('At least one participant is required');
            }
            
            // Validate all users exist and are not banned
            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND role != 'banned'");
            $stmt->execute($user_ids);
            $valid_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($valid_users) !== count($user_ids)) {
                throw new Exception('One or more users not found or banned');
            }
            
            // Create group conversation
            $stmt = $pdo->prepare("INSERT INTO conversations (conversation_type, name, creator_id) VALUES ('group', ?, ?)");
            $stmt->execute([$name, $_SESSION['user_id']]);
            $conversation_id = $pdo->lastInsertId();
            
            // Add participants (including current user)
            $all_participants = array_merge([$_SESSION['user_id']], $user_ids);
            $stmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
            
            foreach ($all_participants as $user_id) {
                $stmt->execute([$conversation_id, $user_id]);
            }
            
        } else {
            throw new Exception('Invalid conversation type');
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 