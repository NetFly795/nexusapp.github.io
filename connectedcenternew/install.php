<?php
/**
 * Connected Center — Database Installer
 * Run this once to create all tables. Delete or protect afterward.
 */
require_once __DIR__ . '/config.php';

$log = [];

function run(PDO $pdo, string $sql, string $label): void {
    global $log;
    try {
        $pdo->exec($sql);
        $log[] = ['ok', $label];
    } catch (PDOException $e) {
        $log[] = ['warn', $label . ': ' . $e->getMessage()];
    }
}

try {
    $pdo = ccDB();
    $log[] = ['ok', 'Connected to database'];
} catch (PDOException $e) {
    die('<pre style="color:red">DB connection failed: ' . htmlspecialchars($e->getMessage()) . '</pre>');
}

// ── Tables ──────────────────────────────────────────────────────────────────

run($pdo, "CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    display_name    VARCHAR(100) DEFAULT NULL,
    email           VARCHAR(200) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    profile_picture VARCHAR(500) DEFAULT NULL,
    role            ENUM('user','admin') DEFAULT 'user',
    status          ENUM('active','banned') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: users");

run($pdo, "CREATE TABLE IF NOT EXISTS servers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT         DEFAULT NULL,
    icon_color  VARCHAR(20)  DEFAULT '#5865f2',
    owner_id    INT          NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: servers");

run($pdo, "CREATE TABLE IF NOT EXISTS server_members (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    server_id  INT NOT NULL,
    user_id    INT NOT NULL,
    role       ENUM('owner','admin','member') DEFAULT 'member',
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_membership (server_id, user_id),
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: server_members");

run($pdo, "ALTER TABLE conversation_participants ADD COLUMN last_read_at TIMESTAMP NULL DEFAULT NULL", "Column: conversation_participants.last_read_at");

run($pdo, "CREATE TABLE IF NOT EXISTS server_invites (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: server_invites");

run($pdo, "CREATE TABLE IF NOT EXISTS channels (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    server_id  INT          NOT NULL,
    name       VARCHAR(100) NOT NULL,
    type       ENUM('text','announcement') DEFAULT 'text',
    topic      VARCHAR(500) DEFAULT NULL,
    position   INT          DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: channels");

run($pdo, "CREATE TABLE IF NOT EXISTS channel_messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT  NOT NULL,
    user_id    INT  NOT NULL,
    content    TEXT NOT NULL,
    edited_at  TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: channel_messages");

run($pdo, "CREATE TABLE IF NOT EXISTS conversations (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    type  ENUM('direct','group') DEFAULT 'direct',
    name  VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: conversations");

run($pdo, "CREATE TABLE IF NOT EXISTS conversation_participants (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    user_id         INT NOT NULL,
    joined_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant (conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: conversation_participants");

run($pdo, "CREATE TABLE IF NOT EXISTS direct_messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT  NOT NULL,
    sender_id       INT  NOT NULL,
    content         TEXT NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)       REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: direct_messages");

run($pdo, "CREATE TABLE IF NOT EXISTS game_invites (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT          NOT NULL,
    to_user_id   INT          NOT NULL,
    game_name    VARCHAR(200) NOT NULL,
    game_path    VARCHAR(500) DEFAULT '',
    status       ENUM('pending','accepted','ignored') DEFAULT 'pending',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: game_invites");

run($pdo, "CREATE TABLE IF NOT EXISTS reports (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT  NOT NULL,
    target_type ENUM('message','user','server') DEFAULT 'message',
    target_id   INT  NOT NULL,
    reason      TEXT NOT NULL,
    status      ENUM('pending','reviewed','resolved') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: reports");

run($pdo, "CREATE TABLE IF NOT EXISTS community_games (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: community_games");

run($pdo, "CREATE TABLE IF NOT EXISTS luna_image_usage (
    user_id     INT NOT NULL,
    usage_date  DATE NOT NULL,
    image_count INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, usage_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: luna_image_usage");

run($pdo, "CREATE TABLE IF NOT EXISTS luna_image_cache (
    id         VARCHAR(32) PRIMARY KEY,
    user_id    INT NOT NULL,
    mime       VARCHAR(64) NOT NULL DEFAULT 'image/jpeg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_luna_cache_user (user_id),
    INDEX idx_luna_cache_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: luna_image_cache");

run($pdo, "CREATE TABLE IF NOT EXISTS luna_conversations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(120) NOT NULL DEFAULT 'New chat',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_luna_conv_user_updated (user_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: luna_conversations");

run($pdo, "CREATE TABLE IF NOT EXISTS luna_messages (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id  INT NOT NULL,
    role             ENUM('user','assistant') NOT NULL,
    content          MEDIUMTEXT NOT NULL,
    images_json      JSON DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES luna_conversations(id) ON DELETE CASCADE,
    INDEX idx_luna_msg_conv (conversation_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", "Table: luna_messages");

try {
    $pdo->exec("ALTER TABLE community_games ADD COLUMN project_data LONGBLOB NULL AFTER file_path");
    $log[] = ['ok', 'Column: community_games.project_data'];
} catch (PDOException $e) {
    // column already exists
}

// ── Check for admin account ──────────────────────────────────────────────────
$adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if (!$adminExists) {
    $log[] = ['info', 'No admin account found. Create one below after install.'];
}

$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Connected Center — Install</title>
<style>
  body { font-family: 'Inter', system-ui, sans-serif; background: #1e1f22; color: #dcddde; max-width: 640px; margin: 60px auto; padding: 0 24px; }
  h1 { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 4px; }
  .sub { color: #949ba4; font-size: 14px; margin-bottom: 32px; }
  .log-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; }
  .badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; flex-shrink: 0; }
  .badge-ok   { background: #23a55920; color: #23a559; }
  .badge-warn { background: #fee75c20; color: #fee75c; }
  .badge-info { background: #5865f220; color: #5865f2; }
  .panel { background: #2b2d31; border-radius: 12px; padding: 24px; margin-top: 32px; }
  .panel h2 { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: #fff; }
  label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #949ba4; margin-bottom: 6px; margin-top: 14px; }
  input { width: 100%; background: #1e1f22; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #dcddde; font-size: 14px; padding: 10px 12px; outline: none; box-sizing: border-box; }
  input:focus { border-color: #5865f2; }
  .btn { background: #5865f2; color: #fff; border: none; border-radius: 6px; padding: 12px 24px; font-size: 14px; font-weight: 700; cursor: pointer; width: 100%; margin-top: 16px; }
  .btn:hover { background: #4752c4; }
  .msg { padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-top: 12px; }
  .msg-ok  { background: #23a55920; color: #23a559; }
  .msg-err { background: #f2232020; color: #f23f43; }
  a { color: #5865f2; }
</style>
</head>
<body>
<h1>Connected Center — Install</h1>
<p class="sub">Database setup complete. Review the log below.</p>

<div style="background:#2b2d31;border-radius:12px;padding:16px;">
<?php foreach ($log as [$type, $msg]): ?>
  <div class="log-item">
    <span class="badge badge-<?= $type ?>"><?= strtoupper($type) ?></span>
    <span><?= htmlspecialchars($msg) ?></span>
  </div>
<?php endforeach; ?>
</div>

<?php if (isset($_POST['create_admin'])): ?>
<?php
  $un  = trim($_POST['username'] ?? '');
  $em  = trim($_POST['email']    ?? '');
  $pw  = $_POST['password']      ?? '';
  $err = null;
  if (strlen($un) < 3)    $err = 'Username must be at least 3 chars.';
  elseif (!filter_var($em, FILTER_VALIDATE_EMAIL)) $err = 'Invalid email.';
  elseif (strlen($pw) < 8) $err = 'Password must be at least 8 chars.';
  else {
      try {
          $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
          $stmt->execute([$un, $em, password_hash($pw, PASSWORD_DEFAULT)]);
          echo '<div class="msg msg-ok">Admin account <strong>' . htmlspecialchars($un) . '</strong> created. <a href="index.php">Log in now →</a></div>';
      } catch (PDOException $e) {
          $err = 'Could not create account: ' . $e->getMessage();
      }
  }
  if ($err) echo '<div class="msg msg-err">' . htmlspecialchars($err) . '</div>';
?>
<?php else: ?>
<div class="panel">
  <h2>Create Admin Account <?= $userCount ? '(Optional — users already exist)' : '' ?></h2>
  <form method="POST">
    <label>Username</label>
    <input type="text" name="username" placeholder="admin" required />
    <label>Email</label>
    <input type="email" name="email" placeholder="admin@example.com" required />
    <label>Password</label>
    <input type="password" name="password" placeholder="Min. 8 characters" required />
    <button class="btn" type="submit" name="create_admin">Create Admin</button>
  </form>
</div>
<?php if ($userCount): ?>
<p style="margin-top:16px;font-size:13px;color:#949ba4;">Or <a href="index.php">go to login</a> if you already have an account.</p>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
