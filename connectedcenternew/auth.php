<?php
/**
 * Shared auth helpers for Connected Center.
 * Include this file and call ccAuthRequired() at the top of any protected page.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

function ccAuthRequired(string $redirect = 'index.php'): array {
    if (empty($_SESSION['cc_user_id'])) {
        header('Location: ' . $redirect);
        exit;
    }
    $pdo  = ccDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status != 'banned'");
    $stmt->execute([$_SESSION['cc_user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        header('Location: ' . $redirect . '?msg=account_inactive');
        exit;
    }
    // Bump last_seen
    $pdo->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?")
        ->execute([$user['id']]);
    return $user;
}

function ccAdminRequired(): array {
    $user = ccAuthRequired();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px"><h2>403 Forbidden</h2><p>Admin access required.</p><a href="dashboard.php">Back</a></body></html>';
        exit;
    }
    return $user;
}

function ccApiAuth(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['cc_user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    $pdo  = ccDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status != 'banned'");
    $stmt->execute([$_SESSION['cc_user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        session_destroy();
        http_response_code(401);
        echo json_encode(['error' => 'Account inactive']);
        exit;
    }
    return $user;
}

function ccJsonHeader(): void {
    header('Content-Type: application/json; charset=utf-8');
}

/** Session user for API routes, or null when not logged in. */
function ccOptionalApiAuth(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['cc_user_id'])) {
        return null;
    }
    $pdo  = ccDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status != 'banned'");
    $stmt->execute([$_SESSION['cc_user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function ccEsc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function ccIsEmbed(): bool {
    return isset($_GET['embed']) && $_GET['embed'] === '1';
}

function ccEmitEmbedAuthSuccess(): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><script>'
        . 'try{if(window.parent!==window){window.parent.postMessage({type:"cc-auth-success"},"*");}}catch(e){}'
        . '</script></body></html>';
    exit;
}
?>
