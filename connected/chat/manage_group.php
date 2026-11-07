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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversation_id = $input['conversation_id'] ?? null;
    $name = trim($input['name'] ?? '');
    $participant_ids = $input['participant_ids'] ?? [];
    
    if (!$conversation_id || !$name) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    try {
        // Verify user is participant in this conversation
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$conversation_id, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Not a participant in this conversation');
        }
        
        // Get conversation details
        $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND conversation_type = 'group'");
        $stmt->execute([$conversation_id]);
        $conversation = $stmt->fetch();
        
        if (!$conversation) {
            throw new Exception('Invalid group conversation');
        }
        
        // Get current participants
        $stmt = $pdo->prepare("SELECT user_id FROM conversation_participants WHERE conversation_id = ?");
        $stmt->execute([$conversation_id]);
        $current_participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Update group name (any participant can do this)
        $stmt = $pdo->prepare("UPDATE conversations SET name = ? WHERE id = ?");
        $stmt->execute([$name, $conversation_id]);
        
        // Handle participant changes
        $participants_to_add = array_diff($participant_ids, $current_participants);
        $participants_to_remove = array_diff($current_participants, $participant_ids);
        
        // Add new participants
        foreach ($participants_to_add as $user_id) {
            // Verify user exists and is not banned
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role != 'banned'");
            $stmt->execute([$user_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
                $stmt->execute([$conversation_id, $user_id]);
            }
        }
        
        // Remove participants (only creator can do this)
        if (!empty($participants_to_remove)) {
            if ($conversation['creator_id'] != $_SESSION['user_id']) {
                throw new Exception('Only the group creator can remove participants');
            }
            
            foreach ($participants_to_remove as $user_id) {
                // Don't allow removing the creator
                if ($user_id == $conversation['creator_id']) {
                    throw new Exception('Cannot remove the group creator');
                }
                
                $stmt = $pdo->prepare("DELETE FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
                $stmt->execute([$conversation_id, $user_id]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Group updated successfully']);
        
    } catch (Exception $e) {
        $pdo->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 