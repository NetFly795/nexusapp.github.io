<?php
/**
 * Shared helpers for Community Games (Nexus Blocks uploads).
 */
require_once __DIR__ . '/config.php';

const CC_GAME_EMOJIS = ['🎮', '🧩', '⚔️', '🏰', '🚀', '🐉', '🎯', '🌟', '🎲', '🏎️', '🎨', '🦊'];

const CC_GAME_GRADIENTS = [
    'linear-gradient(135deg,#1565c0,#7b1fa2)',
    'linear-gradient(135deg,#2e7d32,#00695c)',
    'linear-gradient(135deg,#c62828,#ef6c00)',
    'linear-gradient(135deg,#4e342e,#e65100)',
    'linear-gradient(135deg,#0d47a1,#006064)',
    'linear-gradient(135deg,#880e4f,#b71c1c)',
    'linear-gradient(135deg,#4527a0,#283593)',
    'linear-gradient(135deg,#00695c,#0277bd)',
];

function ccEnsureCommunityGamesTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS community_games (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        user_id     INT          NOT NULL,
        title       VARCHAR(120) NOT NULL,
        emoji       VARCHAR(10)  DEFAULT '🎮',
        gradient    VARCHAR(200) DEFAULT 'linear-gradient(135deg,#1565c0,#7b1fa2)',
        visibility  ENUM('public','unlisted','private') DEFAULT 'public',
        file_path   VARCHAR(500) NOT NULL,
        project_data LONGBLOB NULL,
        play_count  INT          DEFAULT 0,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    try {
        $pdo->exec("ALTER TABLE community_games ADD COLUMN visibility ENUM('public','unlisted','private') DEFAULT 'public' AFTER gradient");
    } catch (PDOException $e) {
        // column already exists
    }

    try {
        $pdo->exec("ALTER TABLE community_games ADD COLUMN project_data LONGBLOB NULL AFTER file_path");
    } catch (PDOException $e) {
        // column already exists
    }
}

/** Columns for list/detail APIs — excludes project_data blob. */
function ccCommunityGameSelectColumns(string $alias = 'g'): string {
    return "{$alias}.id, {$alias}.user_id, {$alias}.title, {$alias}.emoji, {$alias}.gradient,
            {$alias}.visibility, {$alias}.file_path, {$alias}.play_count,
            {$alias}.created_at, {$alias}.updated_at";
}

function ccIsValidSb3Data(string $data): bool {
    return strlen($data) >= 2 && substr($data, 0, 2) === 'PK';
}

/** Read uploaded tmp file into memory. */
function ccReadUploadedProject(string $tmpPath): array {
    $data = @file_get_contents($tmpPath);
    if ($data === false || strlen($data) < 22) {
        return ['ok' => false, 'error' => 'Could not read project file.', 'size' => $data === false ? 0 : strlen($data)];
    }
    if (!ccIsValidSb3Data($data)) {
        return ['ok' => false, 'error' => 'Invalid project file. Please upload a .sb3 file.'];
    }
    return ['ok' => true, 'data' => $data];
}

/** Store project bytes — database first (no disk permissions needed), disk optional. */
function ccStoreCommunityGameProject(PDO $pdo, int $gameId, string $tmpPath): array {
    $read = ccReadUploadedProject($tmpPath);
    if (!$read['ok']) {
        return $read;
    }
    $data = $read['data'];

    try {
        $pdo->prepare("UPDATE community_games SET project_data = ?, file_path = 'db' WHERE id = ?")
            ->execute([$data, $gameId]);
        return ['ok' => true, 'storage' => 'database', 'bytes' => strlen($data)];
    } catch (PDOException $e) {
        return ['ok' => false, 'error' => 'Failed to save project to database.', 'detail' => $e->getMessage()];
    }
}

/** Load project bytes from database or legacy disk file. */
function ccLoadCommunityGameProject(array $row): ?string {
    if (!empty($row['project_data'])) {
        return is_string($row['project_data']) ? $row['project_data'] : (string) $row['project_data'];
    }
    $path = ccCommunityGameAbsPath($row['file_path'] ?? '');
    if ($path && is_file($path)) {
        $data = @file_get_contents($path);
        return ($data !== false && ccIsValidSb3Data($data)) ? $data : null;
    }
    return null;
}

/** Relative path segment for game files (created by PHP on the server, not via FTP). */
const CC_COMMUNITY_GAMES_REL = 'storage/community_games';

function ccEnsureWritableDir(string $dir): bool {
    if (!is_dir($dir)) {
        $oldUmask = umask(0);
        $created = @mkdir($dir, 0775, true);
        umask($oldUmask);
        if (!$created && !is_dir($dir)) {
            return false;
        }
    }
    if (!is_writable($dir)) {
        @chmod($dir, 0775);
    }
    return is_writable($dir);
}

function ccCommunityGamesUploadDir(): string {
    $dir = __DIR__ . '/' . CC_COMMUNITY_GAMES_REL . '/';
    ccEnsureWritableDir($dir);
    return $dir;
}

/** CORS for Nexus Blocks player/editor fetching project files cross-origin. */
function ccSendDownloadCorsHeaders(): void {
    $allowed = [
        'https://connectedcommedit.sync.nexus',
        'http://connectedcommedit.sync.nexus',
        'https://sync.nexus',
        'http://sync.nexus',
        'http://127.0.0.1',
        'http://localhost',
    ];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }
}

function ccHandleDownloadPreflight(): void {
    ccSendDownloadCorsHeaders();
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function ccCommunityGameRelPath(string $filename): string {
    return CC_COMMUNITY_GAMES_REL . '/' . $filename;
}

function ccCommunityGameAbsPath(string $relPath): string {
    $relPath = str_replace('\\', '/', $relPath);
    if ($relPath === '' || strpos($relPath, '..') !== false) {
        return '';
    }
    return __DIR__ . '/' . ltrim($relPath, '/');
}

/** Move/copy uploaded tmp file into permanent storage. */
function ccSaveUploadedProjectFile(string $tmpPath, string $destPath): array {
    $dir = dirname($destPath);
    if (!ccEnsureWritableDir($dir)) {
        return [
            'ok'       => false,
            'error'    => 'Upload directory is not writable.',
            'dir'      => $dir,
            'writable' => is_writable($dir),
            'exists'   => is_dir($dir),
            'hint'     => 'Let PHP create storage/community_games automatically, or run: chown -R www:www connectedcenternew/storage && chmod -R 775 connectedcenternew/storage',
        ];
    }

    if (is_uploaded_file($tmpPath) && @move_uploaded_file($tmpPath, $destPath)) {
        @chmod($destPath, 0644);
        return ['ok' => true];
    }
    if (is_uploaded_file($tmpPath) && @copy($tmpPath, $destPath)) {
        @unlink($tmpPath);
        @chmod($destPath, 0644);
        return ['ok' => true];
    }

    $data = @file_get_contents($tmpPath);
    if ($data !== false && strlen($data) > 0 && @file_put_contents($destPath, $data, LOCK_EX) !== false) {
        @chmod($destPath, 0644);
        if (is_uploaded_file($tmpPath)) {
            @unlink($tmpPath);
        }
        return ['ok' => true];
    }

    return [
        'ok'        => false,
        'error'     => 'Failed to save project file.',
        'dir'       => $dir,
        'writable'  => is_writable($dir),
        'exists'    => is_dir($dir),
        'hint'      => 'Ensure the web server can write to connectedcenternew/storage/community_games (chown www:www, chmod 775).',
    ];
}

function ccFormatCommunityGame(array $row): array {
    return [
        'id'         => (int) $row['id'],
        'title'      => $row['title'],
        'emoji'      => $row['emoji'] ?: '🎮',
        'gradient'   => $row['gradient'],
        'visibility' => $row['visibility'] ?? 'public',
        'play_count' => (int) $row['play_count'],
        'created_at' => $row['created_at'],
        'author'     => $row['display_name'] ?: $row['username'],
        'author_id'  => (int) $row['user_id'],
        'is_mine'    => !empty($row['is_mine']),
    ];
}

function ccRandomGameEmoji(): string {
    return CC_GAME_EMOJIS[array_rand(CC_GAME_EMOJIS)];
}

function ccRandomGameGradient(): string {
    return CC_GAME_GRADIENTS[array_rand(CC_GAME_GRADIENTS)];
}

function ccIsValidSb3(string $path): bool {
    $fh = @fopen($path, 'rb');
    if (!$fh) {
        return false;
    }
    $magic = fread($fh, 2);
    fclose($fh);
    return $magic === 'PK';
}

function ccNormalizeVisibility(string $v): string {
    return in_array($v, ['public', 'unlisted', 'private'], true) ? $v : 'public';
}

function ccCanViewGame(array $game, int $userId): bool {
    $vis = $game['visibility'] ?? 'public';
    if ((int) $game['user_id'] === $userId) {
        return true;
    }
    if ($vis === 'private') {
        return false;
    }
    return true;
}

function ccCanDownloadGame(array $game, int $userId): bool {
    return ccCanViewGame($game, $userId);
}

function ccFetchCommunityGame(PDO $pdo, int $id, bool $withProject = false): ?array {
    $cols = $withProject ? 'g.*' : ccCommunityGameSelectColumns('g');
    $stmt = $pdo->prepare("
        SELECT {$cols}, u.username, u.display_name
        FROM community_games g
        JOIN users u ON g.user_id = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}
