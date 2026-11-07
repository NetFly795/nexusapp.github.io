<?php
/** Runtime schema helpers — safe to call from API routes. */

function ccEnsureDmReadState(PDO $pdo): void {
    try {
        $pdo->exec("ALTER TABLE conversation_participants ADD COLUMN last_read_at TIMESTAMP NULL DEFAULT NULL");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            throw $e;
        }
    }
}

function ccEnsureServerInvitesTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS server_invites (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        server_id    INT NOT NULL,
        from_user_id INT NOT NULL,
        to_user_id   INT NOT NULL,
        status       ENUM('pending','accepted','declined') DEFAULT 'pending',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (server_id)    REFERENCES servers(id) ON DELETE CASCADE,
        FOREIGN KEY (from_user_id) REFERENCES users(id)   ON DELETE CASCADE,
        FOREIGN KEY (to_user_id)   REFERENCES users(id)   ON DELETE CASCADE,
        INDEX idx_invitee_pending (to_user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
