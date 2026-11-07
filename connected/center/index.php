<?php
session_start();
require_once '../config.php';

// are you logged in. no? leave.
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?redirect=center');
    exit;
}

$pdo = getDBConnection();

// banned users get yeeted. harsh but fair
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'banned'");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    session_destroy();
    header('Location: ../index.php?message=account_inactive');
    exit;
}

// bootstrap db tables. CREATE IF NOT EXISTS because deploy day was chaos
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS servers (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(100) NOT NULL,
        description TEXT,
        icon_color  VARCHAR(20)  NOT NULL DEFAULT '#5865f2',
        owner_id    INT          NOT NULL,
        created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS server_members (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        server_id  INT NOT NULL,
        user_id    INT NOT NULL,
        role       ENUM('owner','admin','member') DEFAULT 'member',
        joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
        UNIQUE KEY unique_membership (server_id, user_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS channels (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        server_id  INT NOT NULL,
        name       VARCHAR(100) NOT NULL,
        type       ENUM('text','announcement') DEFAULT 'text',
        position   INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (server_id) REFERENCES servers(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS channel_messages (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        channel_id INT  NOT NULL,
        user_id    INT  NOT NULL,
        content    TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
    )");
} catch (PDOException $e) { /* tables probably already exist. probably. */ }

// load this user's servers. their kingdoms. their problem
$stmt = $pdo->prepare("
    SELECT s.*, sm.role
    FROM servers s
    JOIN server_members sm ON s.id = sm.server_id
    WHERE sm.user_id = ?
    ORDER BY sm.joined_at ASC
");
$stmt->execute([$me['id']]);
$myServers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// dm conversations. reusing chat tables like a responsible gremlin
$stmt = $pdo->prepare("
    SELECT c.id, c.conversation_type, c.name,
           (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT MAX(created_at) FROM messages WHERE conversation_id = c.id) as last_time
    FROM conversations c
    JOIN conversation_participants cp ON c.id = cp.conversation_id
    WHERE cp.user_id = ?
    ORDER BY last_time DESC
    LIMIT 30
");
$stmt->execute([$me['id']]);
$dmConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$displayName = $me['display_name'] ?: $me['username'];
$avatarLetter = strtoupper(substr($displayName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connected Center</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --rail-bg:     #1e1f22;
      --sidebar-bg:  #2b2d31;
      --chat-bg:     #313338;
      --input-bg:    #383a40;
      --text-main:   #dcddde;
      --text-muted:  #949ba4;
      --text-header: #f2f3f5;
      --accent:      #5865f2;
      --accent-hover:#4752c4;
      --green:       #23a559;
      --red:         #f23f43;
      --border:      rgba(255,255,255,0.06);
      --msg-hover:   rgba(4,4,5,0.07);
      --pill-r:      16px;
    }

    html, body { height: 100%; overflow: hidden; font-family: 'Inter', sans-serif; background: var(--chat-bg); color: var(--text-main); }

    /* layout. server rail + sidebar + chat. discord walked so we could stumble */
    #app {
      display: flex;
      height: 100vh;
      overflow: hidden;
    }

    /* Server Rail */
    #serverRail {
      width: 72px;
      min-width: 72px;
      background: var(--rail-bg);
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 12px 0;
      gap: 8px;
      overflow-y: auto;
      overflow-x: hidden;
      scrollbar-width: none;
    }
    #serverRail::-webkit-scrollbar { display: none; }

    .rail-divider {
      width: 32px;
      height: 2px;
      background: var(--border);
      border-radius: 1px;
      margin: 4px 0;
    }

    .rail-btn {
      position: relative;
      width: 48px;
      height: 48px;
      border-radius: var(--pill-r);
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 15px;
      transition: border-radius 0.15s ease, background 0.15s ease, transform 0.1s ease;
      flex-shrink: 0;
      color: var(--text-main);
      text-decoration: none;
      overflow: hidden;
    }
    .rail-btn:hover, .rail-btn.active {
      border-radius: 14px;
      transform: scale(1.03);
    }
    .rail-btn::before {
      content: '';
      position: absolute;
      left: -4px;
      width: 4px;
      height: 0;
      background: #fff;
      border-radius: 0 4px 4px 0;
      transition: height 0.15s ease;
    }
    .rail-btn.active::before { height: 40px; }
    .rail-btn:hover:not(.active)::before { height: 20px; }

    .rail-btn-dm  { background: var(--sidebar-bg); }
    .rail-btn-add { background: var(--sidebar-bg); color: var(--green); font-size: 24px; }
    .rail-btn-add:hover { background: var(--green); color: #fff; }

    .server-icon-bg {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      font-weight: 700;
      letter-spacing: -0.5px;
      color: #fff;
    }

    /* Sidebar */
    #sidebar {
      width: 240px;
      min-width: 240px;
      background: var(--sidebar-bg);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    #sidebarHeader {
      height: 48px;
      padding: 0 16px;
      display: flex;
      align-items: center;
      border-bottom: 1px solid var(--border);
      font-weight: 700;
      font-size: 15px;
      color: var(--text-header);
      flex-shrink: 0;
      cursor: default;
      box-shadow: 0 1px 0 rgba(4,4,5,0.2);
    }

    #sidebarBody {
      flex: 1;
      overflow-y: auto;
      padding: 8px 0;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,0.1) transparent;
    }

    .section-label {
      padding: 16px 8px 4px 16px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .section-label button {
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 18px;
      line-height: 1;
      padding: 0 4px;
      border-radius: 4px;
    }
    .section-label button:hover { color: var(--text-header); background: rgba(255,255,255,0.1); }

    .sidebar-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 6px 8px 6px 16px;
      margin: 1px 8px;
      border-radius: 4px;
      cursor: pointer;
      color: var(--text-muted);
      font-size: 15px;
      font-weight: 500;
      transition: background 0.1s, color 0.1s;
      user-select: none;
    }
    .sidebar-item:hover { background: rgba(255,255,255,0.06); color: var(--text-header); }
    .sidebar-item.active { background: rgba(255,255,255,0.1); color: var(--text-header); }
    .sidebar-item .ch-hash { color: var(--text-muted); font-weight: 500; flex-shrink: 0; }
    .sidebar-item.active .ch-hash { color: var(--text-header); }

    .dm-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      flex-shrink: 0;
      overflow: hidden;
    }
    .dm-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .dm-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    #sidebarFooter {
      height: 52px;
      background: #232428;
      display: flex;
      align-items: center;
      padding: 0 8px;
      gap: 8px;
      flex-shrink: 0;
    }
    #myAvatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 13px;
      color: #fff;
      flex-shrink: 0;
      overflow: hidden;
    }
    #myAvatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    #myName { font-size: 13px; font-weight: 600; color: var(--text-header); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #myStatus { font-size: 11px; color: var(--text-muted); }

    /* Main chat area */
    #chatMain {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      background: var(--chat-bg);
    }

    #chatHeader {
      height: 48px;
      padding: 0 16px;
      display: flex;
      align-items: center;
      gap: 8px;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
      box-shadow: 0 1px 0 rgba(4,4,5,0.2);
    }
    #chatHeaderName {
      font-weight: 700;
      font-size: 15px;
      color: var(--text-header);
    }
    #chatHeaderName .hash { color: var(--text-muted); margin-right: 2px; }
    #chatHeaderDesc {
      font-size: 13px;
      color: var(--text-muted);
      flex: 1;
    }

    /* Members panel toggle */
    #membersToggleBtn {
      background: none;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      padding: 4px;
      border-radius: 4px;
      font-size: 18px;
      line-height: 1;
    }
    #membersToggleBtn:hover { color: var(--text-header); background: rgba(255,255,255,0.1); }

    #messagesWrap {
      flex: 1;
      overflow-y: auto;
      padding: 16px 0;
      display: flex;
      flex-direction: column;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,0.1) transparent;
    }

    .msg-group {
      display: flex;
      gap: 16px;
      padding: 2px 16px;
      border-radius: 4px;
      transition: background 0.05s;
    }
    .msg-group:hover { background: var(--msg-hover); }

    .msg-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
      color: #fff;
      flex-shrink: 0;
      margin-top: 2px;
      overflow: hidden;
    }
    .msg-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .msg-body { flex: 1; min-width: 0; }
    .msg-meta { display: flex; align-items: baseline; gap: 8px; margin-bottom: 2px; }
    .msg-author { font-weight: 600; font-size: 15px; color: var(--text-header); }
    .msg-time   { font-size: 11px; color: var(--text-muted); }
    .msg-text   { font-size: 15px; line-height: 1.5; color: var(--text-main); word-break: break-word; white-space: pre-wrap; }

    .msg-continue { padding: 1px 16px 1px 72px; }
    .msg-continue:hover { background: var(--msg-hover); }
    .msg-continue .msg-text { font-size: 15px; line-height: 1.5; }

    .welcome-banner {
      padding: 32px 16px 16px;
      color: var(--text-muted);
      font-size: 14px;
    }
    .welcome-banner h2 { font-size: 32px; font-weight: 800; color: var(--text-header); margin-bottom: 8px; }

    #inputArea {
      padding: 0 16px 16px;
      flex-shrink: 0;
    }
    #inputWrap {
      background: var(--input-bg);
      border-radius: 8px;
      display: flex;
      align-items: flex-end;
      gap: 8px;
      padding: 11px 16px;
    }
    #msgInput {
      flex: 1;
      background: none;
      border: none;
      outline: none;
      color: var(--text-main);
      font-family: 'Inter', sans-serif;
      font-size: 15px;
      resize: none;
      max-height: 160px;
      line-height: 1.5;
    }
    #msgInput::placeholder { color: var(--text-muted); }
    #sendBtn {
      background: var(--accent);
      border: none;
      border-radius: 6px;
      padding: 6px 12px;
      color: #fff;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      transition: background 0.15s;
      flex-shrink: 0;
    }
    #sendBtn:hover { background: var(--accent-hover); }
    #sendBtn:disabled { opacity: 0.5; cursor: default; }

    /* Empty / loading state */
    #chatEmpty {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--text-muted);
      gap: 12px;
      font-size: 15px;
    }
    #chatEmpty .big-icon { font-size: 64px; opacity: 0.4; }
    #chatEmpty h3 { font-size: 20px; color: var(--text-header); }

    /* Members panel */
    #membersPanel {
      width: 240px;
      min-width: 240px;
      background: var(--sidebar-bg);
      overflow-y: auto;
      display: none;
      flex-direction: column;
      padding: 16px 0;
      scrollbar-width: thin;
    }
    #membersPanel.open { display: flex; }
    .member-section-label {
      padding: 8px 8px 4px 16px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--text-muted);
    }
    .member-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 8px 6px 16px;
      margin: 1px 8px;
      border-radius: 4px;
      cursor: default;
      transition: background 0.1s;
    }
    .member-item:hover { background: rgba(255,255,255,0.06); }
    .member-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 12px;
      color: #fff;
      flex-shrink: 0;
      overflow: hidden;
    }
    .member-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
    .member-name { font-size: 14px; font-weight: 500; color: var(--text-muted); }
    .member-role-owner  { font-size: 10px; color: #faa81a; background: rgba(250,168,26,0.15); border-radius: 3px; padding: 1px 4px; }
    .member-role-admin  { font-size: 10px; color: #ed4245; background: rgba(237,66,69,0.15); border-radius: 3px; padding: 1px 4px; }

    /* Modals */
    .modal-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.7);
      display: flex; align-items: center; justify-content: center;
      z-index: 9000;
      animation: fadeIn 0.15s ease;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .modal {
      background: #313338;
      border-radius: 8px;
      width: 440px;
      max-width: 95vw;
      padding: 24px;
      animation: slideUp 0.2s cubic-bezier(0.22,1,0.36,1);
    }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    .modal h2 { font-size: 20px; font-weight: 800; color: var(--text-header); margin-bottom: 4px; }
    .modal p.sub { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }

    .modal label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); margin-bottom: 6px; margin-top: 16px; }
    .modal input, .modal textarea, .modal select {
      width: 100%;
      background: #1e1f22;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 4px;
      color: var(--text-main);
      font-family: 'Inter', sans-serif;
      font-size: 15px;
      padding: 10px 12px;
      outline: none;
      transition: border-color 0.15s;
    }
    .modal input:focus, .modal textarea:focus { border-color: var(--accent); }
    .modal textarea { resize: vertical; min-height: 64px; }

    .color-swatches { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
    .swatch {
      width: 28px; height: 28px; border-radius: 50%; cursor: pointer;
      border: 3px solid transparent;
      transition: transform 0.1s, border-color 0.1s;
    }
    .swatch:hover { transform: scale(1.15); }
    .swatch.selected { border-color: #fff; }

    .modal-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 24px;
    }
    .btn-secondary {
      background: none;
      border: none;
      color: var(--text-main);
      font-size: 14px;
      font-weight: 500;
      padding: 10px 16px;
      border-radius: 4px;
      cursor: pointer;
    }
    .btn-secondary:hover { text-decoration: underline; }
    .btn-primary {
      background: var(--accent);
      border: none;
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      padding: 10px 20px;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.15s;
    }
    .btn-primary:hover { background: var(--accent-hover); }
    .btn-danger { background: var(--red); }
    .btn-danger:hover { background: #a32020; }

    /* Toast */
    #toast {
      position: fixed;
      bottom: 24px;
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: #232428;
      color: var(--text-header);
      border-radius: 8px;
      padding: 10px 20px;
      font-size: 14px;
      font-weight: 500;
      box-shadow: 0 4px 24px rgba(0,0,0,0.4);
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s, transform 0.2s;
      z-index: 9999;
    }
    #toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

    /* Invite search */
    .user-search-result {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 8px;
      border-radius: 4px;
      cursor: pointer;
      transition: background 0.1s;
    }
    .user-search-result:hover { background: rgba(255,255,255,0.08); }
    .user-search-result .us-name { font-size: 14px; font-weight: 600; color: var(--text-header); }
    .user-search-result .us-user { font-size: 12px; color: var(--text-muted); }

    /* Responsive */
    @media (max-width: 700px) {
      #serverRail { width: 56px; min-width: 56px; }
      #sidebar { width: 200px; min-width: 200px; }
      #membersPanel { display: none !important; }
    }
    @media (max-width: 520px) {
      #sidebar { display: none; }
      #sidebar.mobile-open { display: flex; position: fixed; left: 56px; top: 0; bottom: 0; z-index: 800; }
    }
  </style>
</head>
<body>
<div id="app">

  <!-- ── Server Rail ────────────────────────────────────────── -->
  <nav id="serverRail">
    <button class="rail-btn rail-btn-dm" id="railDms" title="Direct Messages" onclick="selectDMs()">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/>
      </svg>
    </button>
    <div class="rail-divider"></div>

    <?php foreach ($myServers as $srv): ?>
    <button class="rail-btn" id="rail-<?= $srv['id'] ?>"
            title="<?= htmlspecialchars($srv['name']) ?>"
            style="background:<?= htmlspecialchars($srv['icon_color']) ?>"
            onclick="selectServer(<?= $srv['id'] ?>, <?= json_encode($srv['name']) ?>, <?= json_encode($srv['role']) ?>)">
      <span class="server-icon-bg">
        <?= htmlspecialchars(mb_strtoupper(mb_substr($srv['name'], 0, 2))) ?>
      </span>
    </button>
    <?php endforeach; ?>

    <div class="rail-divider"></div>
    <button class="rail-btn rail-btn-add" title="Create a Server" onclick="openCreateServer()">+</button>
    <button class="rail-btn rail-btn-add" title="Explore Servers" style="font-size:18px; background: var(--sidebar-bg); color: var(--text-muted);" onclick="openExploreServers()">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20A10 10 0 0012 2zm1 14.93V15h-2v1.93A8.001 8.001 0 014.07 13H6v-2H4.07A8.001 8.001 0 0111 4.07V6h2V4.07A8.001 8.001 0 0119.93 11H18v2h1.93A8.001 8.001 0 0113 16.93zM12 11a1 1 0 100 2 1 1 0 000-2z"/></svg>
    </button>
  </nav>

  <!-- ── Sidebar ────────────────────────────────────────────── -->
  <aside id="sidebar">
    <div id="sidebarHeader">Direct Messages</div>
    <div id="sidebarBody">
      <!-- populated by JS -->
    </div>
    <div id="sidebarFooter">
      <div id="myAvatar">
        <?php if (!empty($me['profile_picture'])): ?>
          <img src="<?= htmlspecialchars($me['profile_picture']) ?>" alt="" />
        <?php else: ?>
          <?= $avatarLetter ?>
        <?php endif; ?>
      </div>
      <div>
        <div id="myName"><?= htmlspecialchars($displayName) ?></div>
        <div id="myStatus"><?= htmlspecialchars($me['username']) ?></div>
      </div>
    </div>
  </aside>

  <!-- ── Chat Main ──────────────────────────────────────────── -->
  <main id="chatMain">
    <div id="chatHeader" style="display:none;">
      <div id="chatHeaderName"></div>
      <div id="chatHeaderDesc"></div>
      <button id="membersToggleBtn" title="Toggle member list" onclick="toggleMembers()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
      </button>
    </div>

    <div id="chatEmpty">
      <div class="big-icon">💬</div>
      <h3>Welcome to Connected Center</h3>
      <p>Select a server or start a direct message to get chatting.</p>
    </div>

    <div id="messagesWrap" style="display:none;"></div>

    <div id="inputArea" style="display:none;">
      <div id="inputWrap">
        <textarea id="msgInput" rows="1" placeholder="Message #general" maxlength="2000"></textarea>
        <button id="sendBtn" onclick="sendMessage()">Send</button>
      </div>
    </div>
  </main>

  <!-- ── Members Panel ─────────────────────────────────────── -->
  <aside id="membersPanel"></aside>
</div>

<!-- Modals -->
<div id="modalContainer"></div>
<div id="toast"></div>

<script>
/* global state. dm or server mode. do not mutate while sleepy */
var ME_ID    = <?= json_encode($me['id']) ?>;
var ME_NAME  = <?= json_encode($displayName) ?>;
var ME_USER  = <?= json_encode($me['username']) ?>;
var ME_PIC   = <?= json_encode($me['profile_picture'] ?? '') ?>;

var state = {
  mode:         'dm',    // 'dm' | 'server'. pick your fighter
  serverId:     null,
  serverName:   '',
  userRole:     'member',
  channelId:    null,
  channelName:  '',
  convId:       null,
  lastMsgId:    0,
  pollTimer:    null,
  membersOpen:  false,
};

/* toast notifications. fleeting dopamine hits */
var _toastTimer = null;
function showToast(msg, duration) {
  duration = duration || 3000;
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(function() { t.classList.remove('show'); }, duration);
}

/* ── Avatar helper ──────────────────────────────────────────────────────── */
function avatarHtml(pic, name, size, extraClass) {
  size = size || 40;
  extraClass = extraClass || '';
  var letter = (name || '?').charAt(0).toUpperCase();
  var colors = ['#5865f2','#23a559','#ed4245','#f0b132','#4fdc7c','#00b0f4','#eb459e'];
  var color  = colors[name.charCodeAt(0) % colors.length];
  if (pic) {
    return '<div class="msg-avatar ' + extraClass + '" style="width:' + size + 'px;height:' + size + 'px;"><img src="' + escHtml(pic) + '" alt="" loading="lazy" /></div>';
  }
  return '<div class="msg-avatar ' + extraClass + '" style="width:' + size + 'px;height:' + size + 'px;background:' + color + ';">' + letter + '</div>';
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function formatTime(ts) {
  var d = new Date(ts.replace(' ','T') + 'Z');
  var now = new Date();
  var isToday = d.toDateString() === now.toDateString();
  if (isToday) return 'Today at ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
  return d.toLocaleDateString([], {month:'short',day:'numeric'}) + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
}

/* ── Rail active ────────────────────────────────────────────────────────── */
function setRailActive(id) {
  document.querySelectorAll('.rail-btn').forEach(function(b) { b.classList.remove('active'); });
  if (id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('active');
  }
}

/* ── DM Mode ────────────────────────────────────────────────────────────── */
var dmConvs = <?= json_encode($dmConvs) ?>;

function selectDMs() {
  state.mode = 'dm';
  state.serverId = null;
  state.channelId = null;
  setRailActive('railDms');
  renderDMSidebar();
  showEmptyChat('Your direct messages appear here. Select a conversation or start a new one.');
}

function renderDMSidebar() {
  document.getElementById('sidebarHeader').textContent = 'Direct Messages';
  var body = document.getElementById('sidebarBody');

  var html = '<div class="section-label">Direct Messages'
    + '<button title="New DM" onclick="openNewDM()">+</button></div>';

  if (!dmConvs.length) {
    html += '<div style="padding:16px;color:var(--text-muted);font-size:13px;">No conversations yet.</div>';
  }

  dmConvs.forEach(function(c) {
    var name = c.name || ('Conversation #' + c.id);
    var letter = name.charAt(0).toUpperCase();
    html += '<div class="sidebar-item' + (state.convId == c.id ? ' active' : '')
      + '" onclick="selectConversation(' + c.id + ', ' + JSON.stringify(escHtml(name)) + ')">'
      + '<div class="dm-avatar">' + letter + '</div>'
      + '<span class="dm-name">' + escHtml(name) + '</span></div>';
  });

  body.innerHTML = html;
}

function selectConversation(convId, convName) {
  state.convId = convId;
  state.channelId = null;
  stopPolling();
  updateChatHeader('#' + convName, '');
  document.getElementById('msgInput').placeholder = 'Message @' + convName;
  loadDMMessages(convId);
  state.pollTimer = setInterval(function() { pollDMMessages(convId); }, 2000);
  renderDMSidebar();
}

function loadDMMessages(convId) {
  fetch('../../connected/chat/get_messages.php?conversation_id=' + convId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      state.lastMsgId = 0;
      renderMessages(data.messages || [], false);
    });
}

function pollDMMessages(convId) {
  if (state.convId !== convId) return;
  fetch('../../connected/chat/get_messages.php?conversation_id=' + convId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var msgs = data.messages || [];
      if (!msgs.length) return;
      var latest = parseInt(msgs[msgs.length - 1].id);
      if (latest > state.lastMsgId) {
        renderMessages(msgs, false);
      }
    });
}

/* ── Server Mode ────────────────────────────────────────────────────────── */
function selectServer(serverId, serverName, role) {
  state.mode     = 'server';
  state.serverId = serverId;
  state.serverName = serverName;
  state.userRole  = role;
  state.channelId = null;
  state.convId    = null;
  stopPolling();
  setRailActive('rail-' + serverId);
  loadChannels(serverId, serverName, role);
  showEmptyChat('Select a channel to start chatting.');
}

function loadChannels(serverId, serverName, role) {
  fetch('api/get_channels.php?server_id=' + serverId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.error) { showToast(data.error); return; }
      renderServerSidebar(serverName, data.channels || [], data.user_role);
    });
}

function renderServerSidebar(serverName, channels, role) {
  document.getElementById('sidebarHeader').textContent = serverName;
  var body = document.getElementById('sidebarBody');
  var canManage = (role === 'owner' || role === 'admin');

  var html = '<div class="section-label">Text Channels'
    + (canManage ? '<button title="Add channel" onclick="openAddChannel()">+</button>' : '') + '</div>';

  channels.forEach(function(ch) {
    var icon = ch.type === 'announcement' ? '📢' : '#';
    html += '<div class="sidebar-item' + (state.channelId == ch.id ? ' active' : '')
      + '" onclick="selectChannel(' + ch.id + ', ' + JSON.stringify(escHtml(ch.name)) + ')">'
      + '<span class="ch-hash">' + icon + '</span>'
      + '<span>' + escHtml(ch.name) + '</span></div>';
  });

  if (canManage) {
    html += '<div class="section-label" style="margin-top:8px;">Server'
      + '</div>'
      + '<div class="sidebar-item" onclick="openInviteToServer()" style="font-size:13px;">'
      + '<span class="ch-hash">➕</span><span>Invite People</span></div>'
      + '<div class="sidebar-item" onclick="confirmDeleteServer()" style="color:var(--red);font-size:13px;">'
      + '<span class="ch-hash" style="color:var(--red);">🗑</span><span>Delete Server</span></div>';
  } else {
    html += '<div class="sidebar-item" onclick="confirmLeaveServer()" style="color:var(--red);margin-top:8px;font-size:13px;">'
      + '<span class="ch-hash" style="color:var(--red);">🚪</span><span>Leave Server</span></div>';
  }

  body.innerHTML = html;
}

function selectChannel(channelId, channelName) {
  state.channelId  = channelId;
  state.channelName = channelName;
  state.lastMsgId   = 0;
  stopPolling();
  updateChatHeader('<span class="hash">#</span>' + channelName, '');
  document.getElementById('msgInput').placeholder = 'Message #' + channelName;
  loadChannelMessages(channelId, true);
  state.pollTimer = setInterval(function() { pollChannelMessages(channelId); }, 2000);
  // Refresh sidebar active state
  renderServerSidebar(state.serverName,
    Array.from(document.querySelectorAll('.sidebar-item[onclick^="selectChannel"]')).map(function(el) {
      var m = el.getAttribute('onclick').match(/selectChannel\((\d+),\s*"([^"]+)"\)/);
      return m ? { id: parseInt(m[1]), name: decodeURIComponent(m[2]), type: 'text' } : null;
    }).filter(Boolean), state.userRole);
  // Refresh channel list properly
  loadChannels(state.serverId, state.serverName, state.userRole);
}

function loadChannelMessages(channelId, initial) {
  fetch('api/get_channel_messages.php?channel_id=' + channelId + '&limit=50')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var msgs = data.messages || [];
      renderMessages(msgs, true);
      if (msgs.length) state.lastMsgId = parseInt(msgs[msgs.length - 1].id);
    });
}

function pollChannelMessages(channelId) {
  if (state.channelId !== channelId) return;
  fetch('api/get_channel_messages.php?channel_id=' + channelId + '&after_id=' + state.lastMsgId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var msgs = data.messages || [];
      if (!msgs.length) return;
      appendMessages(msgs);
      state.lastMsgId = parseInt(msgs[msgs.length - 1].id);
    });
}

/* ── Message Rendering ──────────────────────────────────────────────────── */
function renderMessages(msgs, isChannel) {
  var wrap = document.getElementById('messagesWrap');
  document.getElementById('chatEmpty').style.display = 'none';
  wrap.style.display = 'flex';
  document.getElementById('inputArea').style.display = 'block';
  document.getElementById('chatHeader').style.display = 'flex';

  var html = '<div class="welcome-banner"><h2>' + (isChannel ? '#' + escHtml(state.channelName) : 'Direct Messages') + '</h2>'
    + '<p style="font-size:13px;color:var(--text-muted);">This is the beginning of the conversation.</p></div>';

  var prevAuthor = null, prevTime = null;
  msgs.forEach(function(m) {
    html += buildMessageHtml(m, prevAuthor, prevTime);
    prevAuthor = m.username || m.sender_id;
    prevTime   = m.created_at;
  });

  wrap.innerHTML = html;
  wrap.scrollTop = wrap.scrollHeight;
}

function appendMessages(msgs) {
  var wrap = document.getElementById('messagesWrap');
  var atBottom = wrap.scrollHeight - wrap.clientHeight - wrap.scrollTop < 60;
  var frag = document.createDocumentFragment();
  msgs.forEach(function(m) {
    var div = document.createElement('div');
    div.innerHTML = buildMessageHtml(m, null, null);
    while (div.firstChild) frag.appendChild(div.firstChild);
  });
  wrap.appendChild(frag);
  if (atBottom) wrap.scrollTop = wrap.scrollHeight;
}

function buildMessageHtml(m, prevAuthor, prevTime) {
  var name    = m.display_name || m.username || ('User ' + (m.user_id || m.sender_id));
  var uname   = m.username || '';
  var pic     = m.profile_picture || '';
  var content = escHtml(m.content || m.message || '');
  var time    = m.created_at;
  var sameGroup = (prevAuthor === uname) && prevTime && ((new Date(time.replace(' ','T')) - new Date(prevTime.replace(' ','T'))) < 5 * 60000);

  if (sameGroup) {
    return '<div class="msg-continue"><span class="msg-text">' + content + '</span></div>';
  }
  var colors = ['#5865f2','#23a559','#ed4245','#f0b132','#4fdc7c','#00b0f4','#eb459e'];
  var bgColor = colors[(name || '?').charCodeAt(0) % colors.length];
  var avatarDiv = pic
    ? '<div class="msg-avatar"><img src="' + escHtml(pic) + '" alt="" loading="lazy"></div>'
    : '<div class="msg-avatar" style="background:' + bgColor + ';">' + name.charAt(0).toUpperCase() + '</div>';

  return '<div class="msg-group">' + avatarDiv
    + '<div class="msg-body">'
    + '<div class="msg-meta"><span class="msg-author">' + escHtml(name) + '</span>'
    + '<span class="msg-time">' + escHtml(formatTime(time)) + '</span></div>'
    + '<div class="msg-text">' + content + '</div>'
    + '</div></div>';
}

/* ── Send Message ───────────────────────────────────────────────────────── */
function sendMessage() {
  var input = document.getElementById('msgInput');
  var content = input.value.trim();
  if (!content) return;

  var btn = document.getElementById('sendBtn');
  btn.disabled = true;

  var url, body;
  if (state.mode === 'server' && state.channelId) {
    url  = 'api/send_channel_message.php';
    body = JSON.stringify({ channel_id: state.channelId, content: content });
  } else if (state.mode === 'dm' && state.convId) {
    url  = '../../connected/chat/send_message.php';
    body = JSON.stringify({ conversation_id: state.convId, message: content });
  } else {
    btn.disabled = false;
    return;
  }

  input.value = '';
  input.style.height = 'auto';

  fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      if (data.error) showToast(data.error);
      else {
        // Immediately show own message
        if (state.mode === 'server') pollChannelMessages(state.channelId);
        else pollDMMessages(state.convId);
      }
    })
    .catch(function() { btn.disabled = false; });
}

/* ── Members Panel ──────────────────────────────────────────────────────── */
function toggleMembers() {
  state.membersOpen = !state.membersOpen;
  var panel = document.getElementById('membersPanel');
  if (state.membersOpen && state.serverId) {
    panel.classList.add('open');
    loadMembers(state.serverId);
  } else {
    panel.classList.remove('open');
  }
}

function loadMembers(serverId) {
  fetch('api/get_members.php?server_id=' + serverId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      var members = data.members || [];
      var owners  = members.filter(function(m) { return m.role === 'owner'; });
      var admins  = members.filter(function(m) { return m.role === 'admin'; });
      var regular = members.filter(function(m) { return m.role === 'member'; });

      function memberHtml(m) {
        var name = m.display_name || m.username;
        var colors = ['#5865f2','#23a559','#ed4245','#f0b132','#4fdc7c'];
        var bg = colors[name.charCodeAt(0) % colors.length];
        var avt = m.profile_picture
          ? '<div class="member-avatar"><img src="' + escHtml(m.profile_picture) + '" alt="" loading="lazy"></div>'
          : '<div class="member-avatar" style="background:' + bg + ';">' + name.charAt(0).toUpperCase() + '</div>';
        var badge = m.role === 'owner' ? '<span class="member-role-owner">Owner</span>'
                  : m.role === 'admin' ? '<span class="member-role-admin">Admin</span>' : '';
        return '<div class="member-item">' + avt + '<div><div class="member-name">' + escHtml(name) + ' ' + badge + '</div></div></div>';
      }

      var html = '';
      if (owners.length)  html += '<div class="member-section-label">Owner — ' + owners.length  + '</div>' + owners.map(memberHtml).join('');
      if (admins.length)  html += '<div class="member-section-label">Admin — ' + admins.length  + '</div>' + admins.map(memberHtml).join('');
      if (regular.length) html += '<div class="member-section-label">Members — ' + regular.length + '</div>' + regular.map(memberHtml).join('');
      document.getElementById('membersPanel').innerHTML = html;
    });
}

/* ── Chat header / empty ────────────────────────────────────────────────── */
function updateChatHeader(nameHtml, desc) {
  document.getElementById('chatHeader').style.display = 'flex';
  document.getElementById('chatHeaderName').innerHTML = nameHtml;
  document.getElementById('chatHeaderDesc').textContent = desc;
  document.getElementById('membersToggleBtn').style.display = (state.mode === 'server') ? '' : 'none';
}

function showEmptyChat(msg) {
  stopPolling();
  state.channelId = null;
  state.convId    = null;
  document.getElementById('messagesWrap').style.display = 'none';
  document.getElementById('inputArea').style.display    = 'none';
  document.getElementById('chatHeader').style.display   = 'none';
  var empty = document.getElementById('chatEmpty');
  empty.style.display = 'flex';
  empty.querySelector('p').textContent = msg;
}

function stopPolling() {
  if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
}

/* ── Modals ─────────────────────────────────────────────────────────────── */
function openModal(html) {
  var container = document.getElementById('modalContainer');
  container.innerHTML = '<div class="modal-overlay" id="activeModalOverlay" onclick="closeModalOnBackdrop(event)">' + html + '</div>';
}
function closeModal() {
  document.getElementById('modalContainer').innerHTML = '';
}
function closeModalOnBackdrop(e) {
  if (e.target.id === 'activeModalOverlay') closeModal();
}

/* Create Server */
var selectedColor = '#5865f2';
var swatchColors  = ['#5865f2','#57f287','#fee75c','#ed4245','#eb459e','#faa81a','#00b0f4','#1abc9c'];

function openCreateServer() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Create a Server</h2>'
    + '<p class="sub">Give your server a name and personality. You can always change it later.</p>'
    + '<label>Server Name</label>'
    + '<input type="text" id="newServerName" placeholder="My Server" maxlength="50" />'
    + '<label>Description (optional)</label>'
    + '<textarea id="newServerDesc" placeholder="What\'s this server about?" maxlength="300"></textarea>'
    + '<label>Icon Color</label>'
    + '<div class="color-swatches" id="swatchWrap">'
    + swatchColors.map(function(c) {
        return '<div class="swatch' + (c === selectedColor ? ' selected' : '')
          + '" style="background:' + c + '" onclick="pickSwatch(\'' + c + '\')" title="' + c + '"></div>';
      }).join('')
    + '</div>'
    + '<div class="modal-actions">'
    + '<button class="btn-secondary" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-primary" onclick="submitCreateServer()">Create</button>'
    + '</div></div>');
}

function pickSwatch(color) {
  selectedColor = color;
  document.querySelectorAll('.swatch').forEach(function(s) {
    s.classList.toggle('selected', s.style.background === color || s.title === color);
  });
}

function submitCreateServer() {
  var name = (document.getElementById('newServerName').value || '').trim();
  var desc = (document.getElementById('newServerDesc').value || '').trim();
  if (name.length < 2) { showToast('Server name must be at least 2 characters.'); return; }
  fetch('api/create_server.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ name: name, description: desc, icon_color: selectedColor })
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.error) { showToast(d.error); return; }
      closeModal();
      showToast('Server "' + name + '" created!');
      reloadServerList();
    });
}

/* Add Channel */
function openAddChannel() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Add a Channel</h2>'
    + '<p class="sub">Channels are where conversations happen. Use lowercase, no spaces.</p>'
    + '<label>Channel Name</label>'
    + '<input type="text" id="newChannelName" placeholder="new-channel" maxlength="50" />'
    + '<label>Type</label>'
    + '<select id="newChannelType"><option value="text">Text Channel</option><option value="announcement">Announcement</option></select>'
    + '<div class="modal-actions">'
    + '<button class="btn-secondary" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-primary" onclick="submitAddChannel()">Create</button>'
    + '</div></div>');
}

function submitAddChannel() {
  var name = (document.getElementById('newChannelName').value || '').trim().toLowerCase().replace(/[^a-z0-9\-]/g,'-');
  var type = document.getElementById('newChannelType').value;
  if (!name) { showToast('Enter a channel name.'); return; }
  fetch('api/create_channel.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: state.serverId, name: name, type: type })
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.error) { showToast(d.error); return; }
      closeModal();
      showToast('Channel #' + name + ' created!');
      loadChannels(state.serverId, state.serverName, state.userRole);
    });
}

/* Invite people to server */
function openInviteToServer() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Invite to ' + escHtml(state.serverName) + '</h2>'
    + '<p class="sub">Search for a user to add to this server.</p>'
    + '<label>Search Users</label>'
    + '<input type="text" id="inviteUserSearch" placeholder="Type a username…" oninput="searchInviteUsers()" />'
    + '<div id="inviteUserResults" style="margin-top:8px;max-height:240px;overflow-y:auto;"></div>'
    + '<div class="modal-actions"><button class="btn-secondary" onclick="closeModal()">Done</button></div>'
    + '</div>');
}

var _inviteSearchTimer = null;
function searchInviteUsers() {
  clearTimeout(_inviteSearchTimer);
  _inviteSearchTimer = setTimeout(function() {
    var q = (document.getElementById('inviteUserSearch') || {}).value;
    if (!q) { document.getElementById('inviteUserResults').innerHTML = ''; return; }
    fetch('api/search_users.php?q=' + encodeURIComponent(q) + '&exclude_server=' + state.serverId)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var results = document.getElementById('inviteUserResults');
        if (!results) return;
        var users = d.users || [];
        if (!users.length) { results.innerHTML = '<div style="color:var(--text-muted);font-size:13px;padding:8px">No users found.</div>'; return; }
        results.innerHTML = users.map(function(u) {
          var name = u.display_name || u.username;
          var colors = ['#5865f2','#23a559','#ed4245','#f0b132'];
          var bg = colors[name.charCodeAt(0) % colors.length];
          var avt = u.profile_picture
            ? '<div class="member-avatar"><img src="' + escHtml(u.profile_picture) + '" alt="" loading="lazy"></div>'
            : '<div class="member-avatar" style="background:' + bg + ';">' + name.charAt(0).toUpperCase() + '</div>';
          return '<div class="user-search-result" onclick="inviteUserToServer(' + u.id + ', ' + JSON.stringify(escHtml(name)) + ')">'
            + avt + '<div><div class="us-name">' + escHtml(name) + '</div><div class="us-user">@' + escHtml(u.username) + '</div></div>'
            + '<button class="btn-primary" style="margin-left:auto;padding:4px 12px;font-size:12px;">Add</button>'
            + '</div>';
        }).join('');
      });
  }, 300);
}

function inviteUserToServer(userId, userName) {
  fetch('api/invite_to_server.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: state.serverId, user_id: userId })
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.error) showToast(d.error);
      else showToast(userName + ' added to ' + state.serverName + '!');
    });
}

/* Leave / Delete server */
function confirmLeaveServer() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Leave "' + escHtml(state.serverName) + '"?</h2>'
    + '<p class="sub">You will not be able to rejoin unless re-invited.</p>'
    + '<div class="modal-actions">'
    + '<button class="btn-secondary" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-primary btn-danger" onclick="doLeaveServer()">Leave Server</button>'
    + '</div></div>');
}

function doLeaveServer() {
  fetch('api/leave_server.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: state.serverId })
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.error) { showToast(d.error); return; }
      closeModal();
      showToast('Left server.');
      reloadServerList();
    });
}

function confirmDeleteServer() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Delete "' + escHtml(state.serverName) + '"?</h2>'
    + '<p class="sub" style="color:var(--red);">This action is permanent. All channels and messages will be deleted.</p>'
    + '<div class="modal-actions">'
    + '<button class="btn-secondary" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-primary btn-danger" onclick="doDeleteServer()">Delete Server</button>'
    + '</div></div>');
}

function doDeleteServer() {
  fetch('api/delete_server.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: state.serverId })
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.error) { showToast(d.error); return; }
      closeModal();
      showToast('Server deleted.');
      reloadServerList();
    });
}

/* New DM */
function openNewDM() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>New Message</h2>'
    + '<p class="sub">Start a direct message with someone on Nexus.</p>'
    + '<label>Find a User</label>'
    + '<input type="text" id="dmUserSearch" placeholder="Type a username…" oninput="searchDMUsers()" />'
    + '<div id="dmUserResults" style="margin-top:8px;max-height:240px;overflow-y:auto;"></div>'
    + '<div class="modal-actions"><button class="btn-secondary" onclick="closeModal()">Cancel</button></div>'
    + '</div>');
}

var _dmSearchTimer = null;
function searchDMUsers() {
  clearTimeout(_dmSearchTimer);
  _dmSearchTimer = setTimeout(function() {
    var q = (document.getElementById('dmUserSearch') || {}).value;
    if (!q) { document.getElementById('dmUserResults').innerHTML = ''; return; }
    fetch('api/search_users.php?q=' + encodeURIComponent(q))
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var results = document.getElementById('dmUserResults');
        if (!results) return;
        var users = d.users || [];
        if (!users.length) { results.innerHTML = '<div style="color:var(--text-muted);font-size:13px;padding:8px">No users found.</div>'; return; }
        results.innerHTML = users.map(function(u) {
          var name = u.display_name || u.username;
          var colors = ['#5865f2','#23a559','#ed4245','#f0b132'];
          var bg = colors[name.charCodeAt(0) % colors.length];
          var avt = u.profile_picture
            ? '<div class="member-avatar"><img src="' + escHtml(u.profile_picture) + '" alt="" loading="lazy"></div>'
            : '<div class="member-avatar" style="background:' + bg + ';">' + name.charAt(0).toUpperCase() + '</div>';
          return '<div class="user-search-result" onclick="startDMWith(' + u.id + ')">'
            + avt + '<div><div class="us-name">' + escHtml(name) + '</div><div class="us-user">@' + escHtml(u.username) + '</div></div>'
            + '</div>';
        }).join('');
      });
  }, 300);
}

function startDMWith(userId) {
  fetch('../../connected/chat/create_conversation.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ type: 'direct', user_ids: [userId] })
  }).then(function(r) { return r.json(); })
    .then(function(d) {
      closeModal();
      if (d.error) { showToast(d.error); return; }
      // Reload DM conversations list then select
      loadDMConversations(function() { selectConversation(d.conversation_id, 'Direct Message'); });
    });
}

function loadDMConversations(cb) {
  fetch('../../connected/chat/get_messages.php?list=1')
    .catch(function() {})
    .finally(function() { if (cb) cb(); });
}

/* Explore Servers */
function openExploreServers() {
  fetch('api/get_servers.php')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var servers = d.servers || [];
      openModal('<div class="modal" style="width:500px;" onclick="event.stopPropagation()">'
        + '<h2>Your Servers</h2>'
        + '<p class="sub">Servers you have joined.</p>'
        + '<div style="max-height:300px;overflow-y:auto;margin-top:12px;">'
        + (servers.length ? servers.map(function(s) {
            return '<div style="display:flex;align-items:center;gap:12px;padding:8px;border-radius:6px;cursor:pointer;" onclick="selectServer(' + s.id + ', ' + JSON.stringify(escHtml(s.name)) + ', ' + JSON.stringify(s.role) + ');closeModal();">'
              + '<div style="width:36px;height:36px;border-radius:10px;background:' + escHtml(s.icon_color) + ';display:flex;align-items:center;justify-content:font-size:12px;font-weight:700;color:#fff;align-items:center;justify-content:center;">'
              + escHtml(s.name.substring(0,2).toUpperCase()) + '</div>'
              + '<div><div style="font-weight:600;font-size:14px;color:var(--text-header)">' + escHtml(s.name) + '</div>'
              + '<div style="font-size:12px;color:var(--text-muted);">' + s.member_count + ' members · ' + s.role + '</div></div>'
              + '</div>';
          }).join('') : '<div style="color:var(--text-muted);font-size:14px;padding:12px;">You haven\'t joined any servers yet.</div>')
        + '</div>'
        + '<div class="modal-actions"><button class="btn-secondary" onclick="closeModal()">Close</button>'
        + '<button class="btn-primary" onclick="closeModal();openCreateServer()">+ Create Server</button></div>'
        + '</div>');
    });
}

/* Reload server list (after create/leave/delete) */
function reloadServerList() {
  fetch('api/get_servers.php')
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var servers = d.servers || [];
      // Rebuild rail buttons (keep DMs + dividers + add btns)
      var rail = document.getElementById('serverRail');
      // Remove old server buttons
      var oldBtns = rail.querySelectorAll('.rail-btn:not(.rail-btn-dm):not(.rail-btn-add)');
      oldBtns.forEach(function(b) { b.remove(); });
      // Remove old dividers after DMs, before add btn
      var dividers = Array.from(rail.querySelectorAll('.rail-divider'));
      if (dividers.length > 1) dividers.slice(1, -1).forEach(function(d) { d.remove(); });

      // Insert new server buttons before last divider
      var lastDivider = rail.querySelectorAll('.rail-divider')[1];
      servers.forEach(function(srv) {
        var btn = document.createElement('button');
        btn.className = 'rail-btn';
        btn.id = 'rail-' + srv.id;
        btn.title = srv.name;
        btn.style.background = srv.icon_color;
        btn.innerHTML = '<span class="server-icon-bg">' + escHtml(srv.name.substring(0,2).toUpperCase()) + '</span>';
        btn.onclick = function() { selectServer(srv.id, srv.name, srv.role); };
        rail.insertBefore(btn, lastDivider);
      });

      selectDMs();
    });
}

/* ── Input auto-resize + Enter to send ─────────────────────────────────── */
var msgInput = document.getElementById('msgInput');
msgInput.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 160) + 'px';
});
msgInput.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
});

/* ── Boot ────────────────────────────────────────────────────────────────── */
selectDMs();
</script>
</body>
</html>
