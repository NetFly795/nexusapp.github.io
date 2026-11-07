<?php
require_once __DIR__ . '/auth.php';
$me = ccAuthRequired('index.php');

$pdo = ccDB();

$stmt = $pdo->prepare("
    SELECT s.*, sm.role,
           (SELECT COUNT(*) FROM server_members WHERE server_id = s.id) as member_count
    FROM servers s
    JOIN server_members sm ON s.id = sm.server_id
    WHERE sm.user_id = ?
    ORDER BY sm.joined_at ASC
");
$stmt->execute([$me['id']]);
$myServers = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.id, c.type, c.name,
           (SELECT content FROM direct_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT MAX(created_at) FROM direct_messages WHERE conversation_id = c.id) as last_time,
           (SELECT GROUP_CONCAT(COALESCE(u2.display_name, u2.username) ORDER BY u2.id SEPARATOR ', ')
            FROM conversation_participants cp2
            JOIN users u2 ON cp2.user_id = u2.id
            WHERE cp2.conversation_id = c.id AND cp2.user_id != ?) as participant_names
    FROM conversations c
    JOIN conversation_participants cp ON c.id = cp.conversation_id
    WHERE cp.user_id = ?
    ORDER BY last_time DESC
    LIMIT 30
");
$stmt->execute([$me['id'], $me['id']]);
$dmConvs = $stmt->fetchAll();

$displayName  = $me['display_name'] ?: $me['username'];
$avatarLetter = strtoupper(mb_substr($displayName, 0, 1));
$swatchColors = ['#2196f3','#4caf50','#e53935','#ff9800','#9c27b0','#00bcd4','#f06292','#795548'];

$ccBasePath   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/connectedcenternew'), '/');
$ccUploadApi  = $ccBasePath . '/api/upload_community_game.php';
$ccDownloadApi = $ccBasePath . '/api/download_community_game.php';
$ccEditorPath = 'https://connectedcommedit.sync.nexus/editor.html';
$ccEmbedPath  = 'https://connectedcommedit.sync.nexus/embed.html';
$ccCloudHost  = 'wss://connectedwss.sync.nexus/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Connected Center</title>
<script src="../theme-sync.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body, input, button, select, textarea, div, p, span, a {
    font-family: 'Avenir Next Regular', 'Avenir Next', 'Avenir', sans-serif;
    font-weight: 400;
  }
  h1, h2, h3, h4, h5, h6, strong, b, .bold {
    font-family: 'Avenir Next Medium', 'Avenir Next', 'Avenir', sans-serif !important;
    font-weight: 500 !important;
  }

  body {
    margin: 0;
    padding: 0;
    background: var(--theme-bg, #f0f4f9);
    color: var(--theme-text-primary, #1a1a2e);
    overflow: hidden;
    height: 100vh;
  }

  /* Embedded in Nexus shell — no zoom (causes bottom clipping) */
  html.nexus-embedded #homeView {
    padding-bottom: 48px;
  }
  html.nexus-embedded #app {
    min-height: 0;
  }

  /* Aurora blur-ball background */
  .blur-bg {
    position: fixed; z-index: 0; inset: 0;
    pointer-events: none; overflow: hidden;
  }
  .blur-ball {
    position: absolute; border-radius: 50%;
    filter: blur(80px); opacity: 0;
    mix-blend-mode: lighten;
    animation: ballFloat 18s ease-in-out infinite alternate,
               ballIn 1.2s cubic-bezier(.4,0,.2,1) forwards;
  }
  .bb1 { width: 420px; height: 420px; background: var(--theme-ball-1, rgba(130,180,255,0.5)); left: -80px; top: -60px; animation-delay: 0s, 0.1s; }
  .bb2 { width: 340px; height: 340px; background: var(--theme-ball-2, rgba(200,160,255,0.45)); right: -60px; top: 10vh; animation-delay: 3s, 0.3s; }
  .bb3 { width: 280px; height: 280px; background: var(--theme-ball-3, rgba(130,230,200,0.4)); left: 15vw; bottom: 5vh; animation-delay: 6s, 0.5s; }
  .bb4 { width: 220px; height: 220px; background: var(--theme-ball-4, rgba(255,200,130,0.4)); right: 15vw; bottom: 8vh; animation-delay: 1.5s, 0.7s; }
  @keyframes ballFloat { 0% { transform: translateY(0) scale(1); } 100% { transform: translateY(-28px) scale(1.06); } }
  @keyframes ballIn { 0% { opacity: 0; } 100% { opacity: 0.55; } }

  /* ── App layout ─────────────────────────────────────────── */
  #app {
    position: relative; z-index: 1;
    display: flex; height: 100vh;
  }

  /* ── Server Rail ────────────────────────────────────────── */
  #rail {
    width: 60px; min-width: 60px;
    background: var(--theme-glass-tint, rgba(255,255,255,0.55));
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border-right: 1px solid var(--theme-glass-border, rgba(255,255,255,0.6));
    display: flex; flex-direction: column; align-items: center;
    padding: 10px 0; gap: 6px;
    overflow-y: auto; overflow-x: hidden; scrollbar-width: none;
  }
  #rail::-webkit-scrollbar { display: none; }
  .rail-sep {
    width: 28px; height: 1.5px;
    background: var(--theme-glass-border, rgba(0,0,0,0.08));
    border-radius: 1px; margin: 2px 0; flex-shrink: 0;
  }
  .rail-btn {
    position: relative; width: 44px; height: 44px;
    border-radius: 22px; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; letter-spacing: -0.3px;
    transition: border-radius 0.2s cubic-bezier(0.22,1,0.36,1), transform 0.15s, box-shadow 0.2s;
    flex-shrink: 0; color: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
  }
  .rail-btn:hover { border-radius: 14px; transform: scale(1.06); box-shadow: 0 4px 16px rgba(0,0,0,0.18); }
  .rail-btn.active { border-radius: 14px; box-shadow: 0 4px 16px rgba(0,0,0,0.2); }
  .rail-btn::before {
    content: ''; position: absolute; left: -4px; width: 3px; height: 0;
    background: var(--theme-accent, #2196f3); border-radius: 0 3px 3px 0;
    transition: height 0.2s;
  }
  .rail-btn.active::before { height: 32px; }
  .rail-btn:hover:not(.active)::before { height: 16px; }
  .rail-dm {
    background: var(--theme-tint, rgba(255,255,255,0.8));
    color: var(--theme-text-primary, #1a1a2e);
    font-size: 19px;
    border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.6));
  }
  .rail-add {
    background: var(--theme-tint, rgba(255,255,255,0.8));
    color: var(--theme-accent, #2196f3); font-size: 22px;
    border: 1px dashed var(--theme-accent, #2196f3);
  }
  .rail-add:hover { background: var(--theme-accent, #2196f3); color: #fff; border-style: solid; }
  .rail-admin {
    background: rgba(255,200,50,0.15);
    color: #c07000; font-size: 17px;
    border: 1px solid rgba(255,180,0,0.3);
  }
  .rail-admin:hover { background: #ffa000; color: #fff; }

  /* ── Sidebar ────────────────────────────────────────────── */
  #sidebar {
    width: 230px; min-width: 230px;
    background: var(--theme-glass-tint, rgba(255,255,255,0.5));
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    border-right: 1px solid var(--theme-glass-border, rgba(255,255,255,0.55));
    display: flex; flex-direction: column; overflow: hidden;
  }
  #sidebarHead {
    height: 50px; padding: 0 12px 0 16px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    font-size: 14px; font-weight: 500; flex-shrink: 0;
    color: var(--theme-text-primary, #1a1a2e);
  }
  #sidebarHead button {
    width: 28px; height: 28px; border-radius: 8px;
    background: none; border: none; cursor: pointer;
    font-size: 18px; color: var(--theme-text-secondary, #666);
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
  }
  #sidebarHead button:hover {
    background: rgba(0,0,0,0.06);
    color: var(--theme-text-primary, #1a1a2e);
  }
  #sidebarBody {
    flex: 1; overflow-y: auto; padding: 6px 6px;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.12) transparent;
  }
  .sec-label {
    padding: 10px 4px 3px 10px; font-size: 10px; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.7px;
    color: var(--theme-text-secondary, #888);
    display: flex; align-items: center; justify-content: space-between;
  }
  .sec-label button {
    background: none; border: none; cursor: pointer;
    font-size: 16px; color: var(--theme-text-secondary, #888);
    border-radius: 4px; padding: 0 4px; line-height: 1;
    transition: background 0.1s;
  }
  .sec-label button:hover { background: rgba(0,0,0,0.06); color: var(--theme-text-primary, #1a1a2e); }

  .s-item {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 8px; margin: 1px 0;
    border-radius: 10px; cursor: pointer;
    color: var(--theme-text-secondary, #666);
    font-size: 13.5px; font-weight: 400;
    transition: background 0.12s, color 0.12s; user-select: none;
  }
  .s-item:hover {
    background: rgba(0,0,0,0.05);
    color: var(--theme-text-primary, #1a1a2e);
  }
  .s-item.active {
    background: var(--theme-accent, #2196f3);
    color: #fff;
  }
  .s-item.active .s-hash { color: rgba(255,255,255,0.75); }
  .s-hash { font-size: 14px; flex-shrink: 0; color: var(--theme-text-secondary, #aaa); }
  .s-item-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .dm-av {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; color: #fff; flex-shrink: 0; overflow: hidden;
  }
  .dm-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

  /* Sidebar footer */
  #sidebarFoot {
    height: 54px;
    background: var(--theme-hover, rgba(255,255,255,0.6));
    border-top: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    display: flex; align-items: center; padding: 0 10px; gap: 8px; flex-shrink: 0;
  }
  .foot-av {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 13px; color: #fff; flex-shrink: 0; overflow: hidden;
    background: var(--theme-accent, #2196f3);
  }
  .foot-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .foot-info { flex: 1; overflow: hidden; }
  .foot-name {
    font-size: 13px; font-weight: 500;
    color: var(--theme-text-primary, #1a1a2e);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .foot-user { font-size: 11px; color: var(--theme-text-secondary, #888); }
  .foot-actions { display: flex; gap: 2px; }
  .foot-btn {
    background: none; border: none; cursor: pointer;
    padding: 5px; border-radius: 6px; font-size: 14px;
    color: var(--theme-text-secondary, #888);
    transition: background 0.12s;
  }
  .foot-btn:hover { background: rgba(0,0,0,0.07); }

  /* ── Chat main ──────────────────────────────────────────── */
  #chatMain {
    flex: 1; display: flex; flex-direction: column; overflow: hidden;
    background: transparent;
  }
  #chatHead {
    height: 50px; padding: 0 16px;
    display: flex; align-items: center; gap: 8px;
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    background: var(--theme-glass-tint, rgba(255,255,255,0.45));
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    flex-shrink: 0;
  }
  #chatHeadName {
    font-size: 14px; font-weight: 500;
    color: var(--theme-text-primary, #1a1a2e);
  }
  #chatHeadName .ch-hash { color: var(--theme-text-secondary, #aaa); margin-right: 2px; }
  #chatHeadDesc { flex: 1; font-size: 12px; color: var(--theme-text-secondary, #888); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .head-btn {
    background: none; border: none; cursor: pointer;
    padding: 5px; border-radius: 8px; font-size: 16px;
    color: var(--theme-text-secondary, #888);
    transition: background 0.12s;
  }
  .head-btn:hover { background: rgba(0,0,0,0.07); }

  #msgWrap {
    flex: 1; overflow-y: auto; padding: 12px 0;
    display: flex; flex-direction: column;
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.1) transparent;
  }
  .welcome-msg {
    padding: 24px 20px 16px;
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    margin-bottom: 8px;
  }
  .welcome-msg h3 { font-size: 20px; color: var(--theme-text-primary, #1a1a2e); margin-bottom: 4px; }
  .welcome-msg p { font-size: 13px; color: var(--theme-text-secondary, #888); }

  .msg-group {
    display: flex; gap: 12px; padding: 3px 16px;
    border-radius: 8px; transition: background 0.08s;
  }
  .msg-group:hover { background: rgba(0,0,0,0.025); }
  .msg-av {
    width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; color: #fff;
    flex-shrink: 0; margin-top: 2px; overflow: hidden;
  }
  .msg-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .msg-body { flex: 1; min-width: 0; }
  .msg-meta { display: flex; align-items: baseline; gap: 8px; margin-bottom: 2px; }
  .msg-author { font-size: 13.5px; font-weight: 500; color: var(--theme-text-primary, #1a1a2e); }
  .msg-time { font-size: 10.5px; color: var(--theme-text-secondary, #aaa); }
  .msg-text { font-size: 13.5px; line-height: 1.55; color: var(--theme-text-primary, #1a1a2e); word-break: break-word; white-space: pre-wrap; }

  .msg-cont { padding: 2px 16px 2px 64px; }
  .msg-cont:hover { background: rgba(0,0,0,0.025); }
  .msg-cont .msg-text { font-size: 13.5px; line-height: 1.55; color: var(--theme-text-primary, #1a1a2e); word-break: break-word; white-space: pre-wrap; }

  #inputArea { padding: 0 14px 14px; flex-shrink: 0; }
  html.nexus-embedded #inputArea {
    padding-bottom: 78px;
  }
  #inputWrap {
    background: var(--theme-glass-tint, rgba(255,255,255,0.7));
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.7));
    border-radius: 16px;
    display: flex; align-items: flex-end; gap: 8px; padding: 10px 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
  }
  #msgInput {
    flex: 1; background: none; border: none; outline: none;
    color: var(--theme-text-primary, #1a1a2e);
    font-family: inherit; font-size: 14px;
    resize: none; max-height: 160px; line-height: 1.5;
  }
  #msgInput::placeholder { color: var(--theme-text-secondary, #aaa); }
  #sendBtn {
    background: var(--theme-accent, #2196f3);
    border: none; border-radius: 10px;
    padding: 7px 16px; color: #fff;
    font-family: inherit; font-size: 13px; font-weight: 500;
    cursor: pointer; transition: background 0.15s, transform 0.1s;
    flex-shrink: 0;
  }
  #sendBtn:hover { filter: brightness(1.1); }
  #sendBtn:active { transform: scale(0.97); }
  #sendBtn:disabled { opacity: 0.45; cursor: default; }

  /* Empty state */
  #chatEmpty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 10px; padding: 32px;
  }
  #chatEmpty .ic { font-size: 56px; opacity: 0.25; }
  #chatEmpty h3 { font-size: 18px; color: var(--theme-text-primary, #1a1a2e); }
  #chatEmpty p { font-size: 13px; color: var(--theme-text-secondary, #888); text-align: center; max-width: 280px; }

  /* ── Members panel ──────────────────────────────────────── */
  #membersPanel {
    width: 220px; min-width: 220px;
    background: var(--theme-glass-tint, rgba(255,255,255,0.5));
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-left: 1px solid var(--theme-glass-border, rgba(255,255,255,0.55));
    overflow-y: auto; display: none; flex-direction: column; padding: 10px 0;
    scrollbar-width: thin;
  }
  #membersPanel.open { display: flex; }
  .mem-sec {
    padding: 10px 6px 3px 14px; font-size: 10px; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.7px;
    color: var(--theme-text-secondary, #888); margin-top: 6px;
  }
  .mem-item {
    display: flex; align-items: center; gap: 10px;
    padding: 5px 10px 5px 14px; margin: 1px 6px;
    border-radius: 8px; cursor: default;
    transition: background 0.1s;
  }
  .mem-item:hover { background: rgba(0,0,0,0.04); }
  .mem-av {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 600; color: #fff; flex-shrink: 0; overflow: hidden;
  }
  .mem-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .mem-name { font-size: 13px; color: var(--theme-text-secondary, #666); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .badge-owner { font-size: 9px; color: #c07000; background: rgba(255,180,0,0.15); border-radius: 3px; padding: 1px 4px; margin-left: 4px; }
  .badge-madmin { font-size: 9px; color: #c0392b; background: rgba(192,57,43,0.12); border-radius: 3px; padding: 1px 4px; margin-left: 4px; }

  /* ── Modals / Overlay ───────────────────────────────────── */
  .overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.35);
    backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center;
    z-index: 9000; animation: ovIn .18s ease;
  }
  @keyframes ovIn { from { opacity: 0; } to { opacity: 1; } }
  .modal {
    background: var(--theme-bg, #f5f7fa);
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.1));
    border-radius: 22px;
    box-shadow: 0 16px 60px rgba(0,0,0,0.18);
    width: 420px; max-width: 95vw;
    padding: 28px; animation: mUp .22s cubic-bezier(0.22,1,0.36,1);
  }
  @keyframes mUp { from { transform: translateY(20px) scale(0.97); opacity: 0; } to { transform: none; opacity: 1; } }
  .modal h2 { font-size: 19px; color: var(--theme-text-primary, #1a1a2e); margin-bottom: 4px; }
  .modal .sub { font-size: 13px; color: var(--theme-text-secondary, #888); margin-bottom: 20px; }
  .modal label {
    display: block; font-size: 10px; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.7px;
    color: var(--theme-text-secondary, #888);
    margin-bottom: 6px; margin-top: 16px;
  }
  .modal input, .modal textarea, .modal select {
    width: 100%;
    background: var(--theme-bg, #fff);
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.12));
    border-radius: 10px; color: var(--theme-text-primary, #1a1a2e);
    font-family: inherit; font-size: 14px;
    padding: 10px 12px; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  .modal input:focus, .modal textarea:focus {
    border-color: var(--theme-accent, #2196f3);
    box-shadow: 0 0 0 3px rgba(33,150,243,0.12);
  }
  .modal textarea { resize: vertical; min-height: 56px; }
  .swatches { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
  .sw { width: 28px; height: 28px; border-radius: 50%; cursor: pointer; border: 3px solid transparent; transition: transform 0.12s, border-color 0.12s; }
  .sw:hover { transform: scale(1.18); }
  .sw.sel { border-color: var(--theme-text-primary, #1a1a2e); }
  .modal-btns { display: flex; gap: 8px; justify-content: flex-end; margin-top: 22px; }
  .btn-ghost {
    background: none; border: none;
    color: var(--theme-text-primary, #1a1a2e); font-size: 13px; font-weight: 400;
    padding: 9px 16px; border-radius: 10px; cursor: pointer; font-family: inherit;
    transition: background 0.12s;
  }
  .btn-ghost:hover { background: rgba(0,0,0,0.06); }
  .btn-prim {
    background: var(--theme-accent, #2196f3); border: none; color: #fff;
    font-size: 13px; font-weight: 500; padding: 9px 20px; border-radius: 10px;
    cursor: pointer; font-family: inherit; transition: filter 0.15s, transform 0.1s;
  }
  .btn-prim:hover { filter: brightness(1.1); }
  .btn-prim:active { transform: scale(0.97); }
  .btn-danger { background: #e53935 !important; }
  .btn-danger:hover { filter: brightness(1.1) !important; }

  /* User search results */
  .usr {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; border-radius: 10px; cursor: pointer;
    transition: background 0.1s;
  }
  .usr:hover { background: rgba(0,0,0,0.05); }
  .usr-av {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 600; color: #fff; flex-shrink: 0; overflow: hidden;
  }
  .usr-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .usr-name { font-size: 13.5px; font-weight: 500; color: var(--theme-text-primary, #1a1a2e); }
  .usr-tag  { font-size: 11.5px; color: var(--theme-text-secondary, #888); }

  /* Toast */
  #toast {
    position: fixed; bottom: 24px; left: 50%;
    transform: translateX(-50%) translateY(12px);
    background: var(--theme-glass-tint, rgba(255,255,255,0.88));
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.7));
    color: var(--theme-text-primary, #1a1a2e);
    border-radius: 12px; padding: 10px 20px; font-size: 13px; font-weight: 500;
    box-shadow: 0 4px 20px rgba(0,0,0,0.12); opacity: 0;
    pointer-events: none; transition: opacity .2s, transform .2s; z-index: 9999;
  }
  #toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

  /* Danger list item */
  .s-item.danger { color: #e53935; }
  .s-item.danger:hover { background: rgba(229,57,53,0.08); color: #c62828; }
  .s-item.danger .s-hash { color: #e53935; }

  /* ── Animation keyframes ────────────────────────────────── */

  /* Rail ripple: the clicked server bounces in */
  @keyframes railBounce {
    0%   { transform: scale(1); }
    22%  { transform: scale(0.82); }
    55%  { transform: scale(1.18); }
    75%  { transform: scale(0.94); }
    90%  { transform: scale(1.05); }
    100% { transform: scale(1); }
  }
  /* Rail ripple: nearby servers wobble like water ripples */
  @keyframes railWobble {
    0%   { transform: translateY(0) scale(1); }
    18%  { transform: translateY(-6px) scale(1.06) rotate(-4deg); }
    38%  { transform: translateY(3px) scale(0.94) rotate(3deg); }
    58%  { transform: translateY(-2px) scale(1.03) rotate(-1.5deg); }
    78%  { transform: translateY(1px) scale(0.98); }
    100% { transform: translateY(0) scale(1); }
  }
  .rail-btn.anim-bounce {
    animation: railBounce 0.5s cubic-bezier(0.36,0.07,0.19,0.97) both;
  }
  .rail-btn.anim-wobble {
    animation: railWobble 0.65s cubic-bezier(0.36,0.07,0.19,0.97) both;
  }

  /* Chat main transitions */
  @keyframes chatSlideFromLeft {
    from { opacity: 0; transform: translateX(-22px) scale(0.98); }
    to   { opacity: 1; transform: none; }
  }
  @keyframes chatSlideFromRight {
    from { opacity: 0; transform: translateX(22px) scale(0.98); }
    to   { opacity: 1; transform: none; }
  }
  @keyframes chatScaleUp {
    from { opacity: 0; transform: scale(0.94) translateY(10px); }
    to   { opacity: 1; transform: none; }
  }
  #chatMain.anim-from-left  { animation: chatSlideFromLeft  0.34s cubic-bezier(0.22,1,0.36,1) both; }
  #chatMain.anim-from-right { animation: chatSlideFromRight 0.34s cubic-bezier(0.22,1,0.36,1) both; }
  #chatMain.anim-scale-up   { animation: chatScaleUp        0.36s cubic-bezier(0.22,1,0.36,1) both; }

  /* DM selection burst: the clicked item expands toward chat */
  @keyframes dmSelectPulse {
    0%   { transform: scale(1); box-shadow: none; }
    35%  { transform: scale(1.06) translateX(6px); box-shadow: 0 0 0 4px rgba(33,150,243,0.18); }
    65%  { transform: scale(0.97); }
    100% { transform: scale(1); }
  }
  .s-item.anim-select { animation: dmSelectPulse 0.38s cubic-bezier(0.22,1,0.36,1); }

  /* Sidebar items stagger in */
  @keyframes sItemIn {
    from { opacity: 0; transform: translateX(-10px); }
    to   { opacity: 1; transform: none; }
  }
  .s-item.stagger { animation: sItemIn 0.22s cubic-bezier(0.22,1,0.36,1) both; }

  /* New messages slide up */
  @keyframes msgSlideUp {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: none; }
  }
  .msg-new { animation: msgSlideUp 0.3s cubic-bezier(0.22,1,0.36,1) both; }

  /* Initial message load fades in */
  @keyframes msgFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: none; }
  }
  .msg-load { animation: msgFadeIn 0.25s cubic-bezier(0.22,1,0.36,1) both; }

  /* Welcome / empty state pops in */
  @keyframes emptyIn {
    from { opacity: 0; transform: scale(0.88) translateY(20px); }
    to   { opacity: 1; transform: none; }
  }
  #chatEmpty .ic  { animation: emptyIn 0.55s cubic-bezier(0.22,1,0.36,1) 0.05s both; }
  #chatEmpty h3   { animation: emptyIn 0.5s  cubic-bezier(0.22,1,0.36,1) 0.12s both; }
  #chatEmpty p    { animation: emptyIn 0.5s  cubic-bezier(0.22,1,0.36,1) 0.18s both; }

  /* Header slide down */
  @keyframes headIn {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: none; }
  }
  #chatHead.anim-head { animation: headIn 0.28s cubic-bezier(0.22,1,0.36,1) both; }

  /* Input area rises in */
  @keyframes inputRiseIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: none; }
  }
  #inputArea.anim-rise { animation: inputRiseIn 0.3s cubic-bezier(0.22,1,0.36,1) 0.08s both; }

  /* Send button success flash */
  @keyframes sendFlash {
    0%   { transform: scale(1); }
    40%  { transform: scale(0.88); }
    70%  { transform: scale(1.1); }
    100% { transform: scale(1); }
  }
  #sendBtn.anim-send { animation: sendFlash 0.3s cubic-bezier(0.36,0.07,0.19,0.97); }

  /* Modal */
  @keyframes mUp {
    from { transform: translateY(20px) scale(0.96); opacity: 0; }
    to   { transform: none; opacity: 1; }
  }
  .modal { animation: mUp 0.26s cubic-bezier(0.22,1,0.36,1) both; }

  /* Footer profile icon subtle pulse on load */
  @keyframes avPop {
    0%   { transform: scale(0); }
    65%  { transform: scale(1.12); }
    100% { transform: scale(1); }
  }
  #myAv { animation: avPop 0.5s cubic-bezier(0.22,1,0.36,1) 0.3s both; }

  /* Foot action buttons wiggle on hover */
  .foot-btn { transition: background 0.12s, transform 0.15s; }
  .foot-btn:hover { transform: scale(1.18) rotate(-8deg); }

  /* ── Refined animations ─────────────────────────────────── */
  /* Spring physics for rail ripple */
  @keyframes railBounce {
    0%   { transform: scale(1) rotate(0deg); }
    18%  { transform: scale(0.76) rotate(-3deg); }
    42%  { transform: scale(1.22) rotate(2deg); }
    62%  { transform: scale(0.91) rotate(-1deg); }
    78%  { transform: scale(1.07) rotate(0.5deg); }
    90%  { transform: scale(0.97); }
    100% { transform: scale(1) rotate(0deg); }
  }
  @keyframes railWobble {
    0%   { transform: translateY(0) scale(1) rotate(0); }
    15%  { transform: translateY(-8px) scale(1.07) rotate(-5deg); }
    35%  { transform: translateY(4px) scale(0.93) rotate(3.5deg); }
    55%  { transform: translateY(-3px) scale(1.04) rotate(-2deg); }
    72%  { transform: translateY(1.5px) scale(0.98) rotate(0.8deg); }
    86%  { transform: translateY(-0.5px) scale(1.01); }
    100% { transform: translateY(0) scale(1) rotate(0); }
  }
  /* Smoother message appear */
  @keyframes msgSlideUp {
    0%   { opacity: 0; transform: translateY(18px) scale(0.97); }
    60%  { opacity: 1; }
    100% { opacity: 1; transform: translateY(0) scale(1); }
  }
  /* Chat transitions with overshoot */
  @keyframes chatSlideFromLeft {
    0%   { opacity: 0; transform: translateX(-28px) scale(0.96); }
    65%  { opacity: 1; }
    85%  { transform: translateX(4px) scale(1.005); }
    100% { transform: translateX(0) scale(1); }
  }
  @keyframes chatScaleUp {
    0%   { opacity: 0; transform: scale(0.92) translateY(14px); }
    60%  { opacity: 1; }
    80%  { transform: scale(1.02) translateY(-2px); }
    100% { transform: scale(1) translateY(0); }
  }
  /* Member context menu */
  .ctx-menu {
    position: fixed; z-index: 8000;
    background: var(--theme-glass-tint, rgba(255,255,255,0.92));
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.7));
    border-radius: 12px; padding: 6px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.16);
    min-width: 180px;
    animation: ctxIn 0.18s cubic-bezier(0.22,1,0.36,1) both;
  }
  @keyframes ctxIn {
    from { opacity: 0; transform: scale(0.88) translateY(-8px); }
    to   { opacity: 1; transform: none; }
  }
  .ctx-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 8px; cursor: pointer;
    font-size: 13px; color: var(--theme-text-primary, #1a1a2e);
    transition: background 0.1s;
  }
  .ctx-item:hover { background: rgba(0,0,0,0.06); }
  .ctx-item.danger { color: #e53935; }
  .ctx-item.danger:hover { background: rgba(229,57,53,0.08); }
  .ctx-sep { height: 1px; background: var(--theme-glass-border, rgba(0,0,0,0.08)); margin: 4px 0; }

  /* ── Home view ───────────────────────────────────────────── */
  /* ── Connected Home (App-Store style) ──────────────────────── */
  #homeView {
    flex: 1; overflow-y: auto; overflow-x: hidden; display: none;
    flex-direction: column;
    scrollbar-width: thin; scrollbar-color: rgba(0,0,0,0.1) transparent;
  }
  #homeView.visible { display: flex; }

  /* Search bar */
  .hv-search {
    display: flex; align-items: center; gap: 9px;
    padding: 18px 22px 0;
    animation: emptyIn 0.4s cubic-bezier(0.22,1,0.36,1) 0s both;
  }
  .hv-search-bar {
    flex: 1; display: flex; align-items: center; gap: 9px;
    background: var(--theme-glass-tint, rgba(255,255,255,0.65));
    border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.65));
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border-radius: 14px; padding: 0 14px; height: 40px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  }
  .hv-search-input {
    flex: 1; border: none; background: transparent; outline: none;
    font-size: 14px; color: var(--theme-text-primary, #1a1a2e);
    font-family: inherit;
  }
  .hv-search-input::placeholder { color: var(--theme-text-secondary, #aaa); }
  .hv-search-icon { font-size: 15px; opacity: 0.4; }
  .hv-search-placeholder { font-size: 14px; color: var(--theme-text-secondary, #aaa); }
  .hv-greet-name {
    font-size: 13.5px; font-weight: 500; color: var(--theme-text-primary, #1a1a2e);
    white-space: nowrap;
    animation: emptyIn 0.4s cubic-bezier(0.22,1,0.36,1) 0.06s both;
  }

  /* Server invite notifications on home */
  #homeInviteBanner { padding: 0 22px 14px; display: none; flex-direction: column; gap: 10px; }
  #homeInviteBanner.visible { display: flex; animation: emptyIn 0.4s cubic-bezier(0.22,1,0.36,1) both; }
  .hv-invite-card {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; border-radius: 14px;
    background: var(--theme-bg, #fff);
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.1));
    box-shadow: 0 4px 18px rgba(0,0,0,0.06);
  }
  .hv-invite-icon {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0;
  }
  .hv-invite-body { flex: 1; min-width: 0; }
  .hv-invite-title { font-size: 14px; font-weight: 600; }
  .hv-invite-sub { font-size: 12px; color: var(--theme-text-secondary, #888); margin-top: 2px; }
  .hv-invite-actions { display: flex; gap: 8px; flex-shrink: 0; }
  .hv-invite-btn {
    border: none; border-radius: 10px; padding: 8px 14px;
    font-family: inherit; font-size: 12px; font-weight: 600; cursor: pointer;
  }
  .hv-invite-accept { background: var(--theme-accent, #2196f3); color: #fff; }
  .hv-invite-decline { background: rgba(0,0,0,0.06); color: var(--theme-text-primary, #1a1a2e); }
  [data-dark-mode="true"] .hv-invite-card { background: #2c2d34; border-color: rgba(255,255,255,0.08); }
  [data-dark-mode="true"] .hv-invite-decline { background: rgba(255,255,255,0.08); color: #e0e0e0; }

  /* Section wrapper */
  .hv-section {
    padding: 22px 0 0;
    animation: emptyIn 0.45s cubic-bezier(0.22,1,0.36,1) both;
  }
  .hv-section:nth-child(2) { animation-delay: 0.06s; }
  .hv-section:nth-child(3) { animation-delay: 0.12s; }
  .hv-section:nth-child(4) { animation-delay: 0.17s; }
  .hv-section:nth-child(5) { animation-delay: 0.22s; }
  .hv-section:nth-child(6) { animation-delay: 0.27s; }
  .hv-section:last-child { padding-bottom: 36px; }

  .hv-sec-head {
    display: flex; align-items: baseline; justify-content: space-between;
    padding: 0 22px; margin-bottom: 12px;
  }
  .hv-sec-title {
    font-size: 17px; font-weight: 600; letter-spacing: -0.2px;
    color: var(--theme-text-primary, #1a1a2e);
  }
  .hv-sec-all {
    font-size: 13px; color: var(--theme-accent, #2196f3);
    background: none; border: none; cursor: pointer; font-family: inherit; padding: 0;
  }
  .hv-sec-all:hover { text-decoration: underline; }
  .hv-sec-sub {
    font-size: 12px; color: var(--theme-text-secondary, #888);
    padding: 0 22px; margin-top: -8px; margin-bottom: 12px;
  }
  /* Horizontal divider below sections */
  .hv-section + .hv-section { border-top: 1px solid var(--theme-glass-border, rgba(0,0,0,0.07)); }

  /* ── Hero featured carousel ────────────────────────────────── */
  .hv-hero {
    margin: 0 22px; border-radius: 20px; overflow: hidden; position: relative;
    height: 200px; cursor: pointer;
    box-shadow: 0 8px 32px rgba(0,0,0,0.15);
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .hv-hero:hover { transform: scale(1.008); box-shadow: 0 14px 44px rgba(0,0,0,0.2); }
  .hv-hero:active { transform: scale(0.995); }
  .hv-hero-slides { display: flex; width: 100%; height: 100%; transition: transform 0.55s cubic-bezier(0.4,0,0.2,1); }
  .hv-hero-slide {
    min-width: 100%; height: 100%; position: relative;
    display: flex; align-items: flex-end; padding: 20px 22px;
  }
  .hv-hero-art {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    font-size: 80px; pointer-events: none;
  }
  .hv-hero-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.65) 0%, rgba(0,0,0,0.1) 55%, transparent 100%);
  }
  .hv-hero-text { position: relative; z-index: 1; }
  .hv-hero-eyebrow {
    font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
    color: rgba(255,255,255,0.7); margin-bottom: 3px;
  }
  .hv-hero-title { font-size: 20px; font-weight: 700; color: #fff; line-height: 1.15; }
  .hv-hero-desc  { font-size: 12px; color: rgba(255,255,255,0.75); margin-top: 3px; }
  /* Hero dots */
  .hv-hero-dots {
    position: absolute; bottom: 10px; right: 14px; z-index: 2;
    display: flex; gap: 5px;
  }
  .hv-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: rgba(255,255,255,0.45); transition: background 0.25s, transform 0.25s;
    cursor: pointer;
  }
  .hv-dot.active { background: #fff; transform: scale(1.3); }

  /* ── Quick access (icon row) ───────────────────────────────── */
  .hv-scroll {
    display: flex; gap: 0;
    overflow-x: auto; overflow-y: visible;
    scroll-snap-type: x mandatory;
    padding: 4px 22px 8px;
    scrollbar-width: none;
  }
  .hv-scroll::-webkit-scrollbar { display: none; }

  .qa-item {
    display: flex; flex-direction: column; align-items: center;
    gap: 6px; flex-shrink: 0; width: 76px; cursor: pointer;
    scroll-snap-align: start;
    transition: transform 0.15s;
  }
  .qa-item:hover { transform: translateY(-3px); }
  .qa-item:active { transform: scale(0.93); }
  .qa-icon {
    width: 54px; height: 54px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; font-weight: 700; color: #fff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.15);
    transition: box-shadow 0.15s;
    position: relative; overflow: hidden;
  }
  .qa-icon::after {
    content: ''; position: absolute; inset: 0; border-radius: 14px;
    background: linear-gradient(to bottom, rgba(255,255,255,0.18) 0%, transparent 60%);
    pointer-events: none;
  }
  .qa-item:hover .qa-icon { box-shadow: 0 6px 18px rgba(0,0,0,0.2); }
  .qa-label {
    font-size: 11px; color: var(--theme-text-primary, #1a1a2e);
    text-align: center; max-width: 70px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .qa-add-icon {
    width: 54px; height: 54px; border-radius: 14px;
    border: 1.5px dashed var(--theme-accent, #2196f3);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: var(--theme-accent, #2196f3);
    transition: background 0.14s;
  }
  .qa-item:hover .qa-add-icon { background: rgba(33,150,243,0.07); }

  /* ── App-store game cards (horizontal scroll) ──────────────── */
  .as-scroll {
    display: flex; gap: 14px;
    overflow-x: auto; overflow-y: visible;
    scroll-snap-type: x mandatory;
    padding: 4px 22px 12px;
    scrollbar-width: none;
  }
  .as-scroll::-webkit-scrollbar { display: none; }

  .as-card {
    flex-shrink: 0; width: 145px;
    scroll-snap-align: start; cursor: pointer;
    transition: transform 0.18s, opacity 0.18s;
    animation: emptyIn 0.38s cubic-bezier(0.22,1,0.36,1) both;
  }
  .as-card:hover { transform: translateY(-3px); opacity: 0.93; }
  .as-card:active { transform: scale(0.95); }
  .as-art {
    width: 145px; height: 145px; border-radius: 18px; overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    font-size: 52px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.13);
    position: relative;
  }
  .as-art::after {
    content: ''; position: absolute; inset: 0; border-radius: 18px;
    background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, transparent 55%);
    pointer-events: none;
  }
  .as-name { font-size: 12.5px; font-weight: 500; color: var(--theme-text-primary, #1a1a2e); margin-top: 8px; }
  .as-by   { font-size: 11px; color: var(--theme-text-secondary, #999); margin-top: 1px; }
  .as-row  { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; }
  .as-stars { font-size: 10px; color: #f5a623; letter-spacing: -1px; }
  .as-get {
    font-size: 11.5px; font-weight: 600; color: var(--theme-accent, #2196f3);
    background: rgba(33,150,243,0.1); border: none; border-radius: 20px;
    padding: 3px 13px; cursor: pointer; font-family: inherit;
    transition: background 0.12s;
  }
  .as-get:hover { background: rgba(33,150,243,0.18); }

  .as-card.as-create .as-art {
    background: transparent !important;
    border: 2px dashed var(--theme-accent, #2196f3);
    font-size: 42px;
    color: var(--theme-accent, #2196f3);
    box-shadow: none;
  }
  .as-card.as-create:hover .as-art { background: rgba(33,150,243,0.07) !important; }
  .as-card.as-create .as-by { color: var(--theme-accent, #2196f3); }

  /* ── Nexus Blocks / game player overlays ─────────────────── */
  .cc-fullscreen-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: var(--theme-bg, #f0f4f9);
    flex-direction: column;
  }
  .cc-fullscreen-overlay.open { display: flex; }
  .cc-overlay-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 18px;
    background: var(--theme-tint, rgba(255,255,255,0.88));
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.08));
    flex-shrink: 0;
    backdrop-filter: blur(12px);
  }
  .cc-overlay-title {
    font-size: 15px;
    font-weight: 500;
    color: var(--theme-text-primary, #1a1a2e);
    flex: 1;
    min-width: 0;
  }
  .cc-overlay-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }
  .cc-overlay-mod-btn {
    border: none;
    background: rgba(198, 40, 40, 0.12);
    color: #c62828;
    font-size: 12px;
    font-weight: 500;
    padding: 7px 12px;
    border-radius: 8px;
    cursor: pointer;
    white-space: nowrap;
  }
  .cc-overlay-mod-btn:hover { background: rgba(198, 40, 40, 0.22); }
  [data-dark-mode="true"] .cc-overlay-mod-btn { color: #ef9a9a; background: rgba(239, 83, 80, 0.18); }
  .as-mod-del {
    border: none;
    background: rgba(198, 40, 40, 0.1);
    color: #c62828;
    font-size: 10px;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 6px;
    cursor: pointer;
    margin-left: 4px;
  }
  .as-mod-del:hover { background: rgba(198, 40, 40, 0.2); }
  .cc-overlay-close {
    border: none;
    background: rgba(0,0,0,0.06);
    color: var(--theme-text-primary, #1a1a2e);
    width: 34px;
    height: 34px;
    border-radius: 10px;
    font-size: 18px;
    cursor: pointer;
    line-height: 1;
    transition: background 0.12s;
  }
  .cc-overlay-close:hover { background: rgba(0,0,0,0.1); }
  .cc-game-embed-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 24px 28px;
    min-height: 0;
    overflow: auto;
    background: var(--theme-bg, #f0f4f9);
  }
  .cc-game-embed-frame {
    border: none;
    width: min(964px, calc(100vw - 48px));
    height: min(calc(100vh - 96px), calc(min(964px, calc(100vw - 48px)) * 412 / 482));
    max-width: 100%;
    border-radius: 14px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.14);
    background: transparent;
    color-scheme: auto;
  }
  [data-dark-mode="true"] .cc-game-embed-wrap { background: var(--theme-bg, #1a1a2e); }
  [data-dark-mode="true"] .cc-game-embed-frame { box-shadow: 0 12px 40px rgba(0, 0, 0, 0.45); }
  .cc-overlay-frame {
    flex: 1;
    border: none;
    width: 100%;
    min-height: 0;
    background: #111;
  }
  [data-dark-mode="true"] .cc-overlay-bar { background: rgba(40,42,48,0.92); border-color: rgba(255,255,255,0.08); }
  [data-dark-mode="true"] .cc-overlay-close { background: rgba(255,255,255,0.08); color: #e0e0e0; }

  /* ── Activity list ─────────────────────────────────────────── */
  .act-list { display: flex; flex-direction: column; gap: 1px; padding: 0 22px; }
  .act-row {
    display: flex; align-items: center; gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    cursor: pointer; transition: background 0.1s;
    border-radius: 0;
  }
  .act-row:last-child { border-bottom: none; }
  .act-row:hover { background: rgba(0,0,0,0.03); margin: 0 -10px; padding: 10px; border-radius: 10px; }
  .act-av2 {
    width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 600; color: #fff; overflow: hidden;
  }
  .act-av2 img { width: 100%; height: 100%; object-fit: cover; }
  .act-mid { flex: 1; min-width: 0; }
  .act-mid-who   { font-size: 13px; font-weight: 500; color: var(--theme-text-primary, #1a1a2e); }
  .act-mid-where { font-size: 11.5px; color: var(--theme-text-secondary, #999); margin-top: 1px; }
  .act-mid-text  { font-size: 12px; color: var(--theme-text-secondary, #777); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .act-right { text-align: right; flex-shrink: 0; }
  .act-right-time { font-size: 11px; color: var(--theme-text-secondary, #bbb); }

  /* ── What's New (update list) ──────────────────────────────── */
  .wn-list { display: flex; flex-direction: column; gap: 0; padding: 0 22px; }
  .wn-item {
    display: flex; gap: 14px; align-items: flex-start;
    padding: 14px 0;
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
  }
  .wn-item:last-child { border-bottom: none; }
  .wn-icon {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
  }
  .wn-body { flex: 1; min-width: 0; }
  .wn-tag {
    display: inline-block; padding: 1.5px 7px; border-radius: 5px;
    font-size: 9.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
    background: rgba(33,150,243,0.12); color: var(--theme-accent, #2196f3);
    margin-bottom: 4px;
  }
  .wn-title { font-size: 13.5px; font-weight: 500; color: var(--theme-text-primary, #1a1a2e); }
  .wn-desc  { font-size: 12px; color: var(--theme-text-secondary, #777); margin-top: 3px; line-height: 1.5; }
  .wn-date  { font-size: 11px; color: var(--theme-text-secondary, #bbb); margin-top: 5px; }

  /* Rail home button */
  .rail-home {
    background: var(--theme-tint, rgba(255,255,255,0.8));
    color: var(--theme-accent, #2196f3); font-size: 19px;
    border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.6));
  }

  /* Dark mode for home */
  [data-dark-mode="true"] .hv-search-bar { background: rgba(40,42,48,0.8); border-color: rgba(255,255,255,0.07); }
  [data-dark-mode="true"] .hv-section + .hv-section { border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] .act-row { border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] .act-row:hover { background: rgba(255,255,255,0.04); }
  [data-dark-mode="true"] .wn-item { border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] .ctx-menu { background: rgba(44,45,52,0.95); border-color: rgba(255,255,255,0.09); }
  [data-dark-mode="true"] .ctx-item:hover { background: rgba(255,255,255,0.07); }

  /* Dark mode overrides */
  [data-dark-mode="true"] #app { --dm: 1; }
  [data-dark-mode="true"] #rail { background: rgba(30,31,35,0.85); border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] #sidebar { background: rgba(40,42,48,0.85); border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] #sidebarHead { border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] #sidebarFoot { background: rgba(30,31,35,0.6); border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] #chatHead { background: rgba(40,42,48,0.7); border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] #inputWrap { background: rgba(40,42,48,0.8); border-color: rgba(255,255,255,0.08); }
  [data-dark-mode="true"] #membersPanel { background: rgba(40,42,48,0.85); border-color: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] .s-item:hover { background: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] .modal { background: #2c2d34; border-color: rgba(255,255,255,0.09); }
  [data-dark-mode="true"] .modal input, [data-dark-mode="true"] .modal textarea, [data-dark-mode="true"] .modal select { background: #1e1f24; border-color: rgba(255,255,255,0.1); color: #e0e0e0; }
  [data-dark-mode="true"] .rail-dm { background: rgba(255,255,255,0.07); color: #e0e0e0; border-color: rgba(255,255,255,0.1); }
  [data-dark-mode="true"] .rail-add { background: rgba(255,255,255,0.05); border-color: var(--theme-accent, #2196f3); }
  [data-dark-mode="true"] #toast { background: rgba(44,45,52,0.92); border-color: rgba(255,255,255,0.1); color: #e0e0e0; }
  [data-dark-mode="true"] .msg-group:hover { background: rgba(255,255,255,0.03); }
  [data-dark-mode="true"] .msg-cont:hover { background: rgba(255,255,255,0.03); }
  [data-dark-mode="true"] .usr:hover { background: rgba(255,255,255,0.06); }
  [data-dark-mode="true"] .mem-item:hover { background: rgba(255,255,255,0.05); }

  @media (max-width: 680px) {
    #rail { width: 52px; min-width: 52px; }
    #sidebar { width: 195px; min-width: 195px; }
    #membersPanel { display: none !important; }
  }
</style>
</head>
<body>
<!-- Aurora background -->
<div class="blur-bg">
  <div class="blur-ball bb1"></div>
  <div class="blur-ball bb2"></div>
  <div class="blur-ball bb3"></div>
  <div class="blur-ball bb4"></div>
</div>

<div id="app">

  <!-- ── Rail ─────────────────────────────────────────────── -->
  <nav id="rail">
    <button class="rail-btn rail-home" id="railHome" title="Connected Home" onclick="selectHome()">🏠</button>
    <button class="rail-btn rail-dm"   id="railDMs"  title="Direct Messages" onclick="selectDMs()">💬</button>
    <div class="rail-sep"></div>

    <?php foreach ($myServers as $s): ?>
    <button class="rail-btn" id="rail-<?= $s['id'] ?>"
            title="<?= ccEsc($s['name']) ?>"
            style="background:<?= ccEsc($s['icon_color']) ?>"
            onclick="selectServer(<?= $s['id'] ?>,<?= ccEsc(json_encode($s['name'])) ?>,<?= ccEsc(json_encode($s['role'])) ?>)">
      <?= ccEsc(mb_strtoupper(mb_substr($s['name'],0,2))) ?>
    </button>
    <?php endforeach; ?>

    <div class="rail-sep"></div>
    <button class="rail-btn rail-add" title="Create Server" onclick="openCreateServer()">+</button>
    <?php if ($me['role'] === 'admin'): ?>
    <button class="rail-btn rail-admin" title="Admin Panel" onclick="window.location='admin.php'">⚙</button>
    <?php endif; ?>
  </nav>

  <!-- ── Sidebar ───────────────────────────────────────────── -->
  <aside id="sidebar">
    <div id="sidebarHead">
      <span id="sidebarTitle">Direct Messages</span>
      <button id="sidebarAction" onclick="openNewDM()" title="New DM">+</button>
    </div>
    <div id="sidebarBody"></div>
    <div id="sidebarFoot">
      <div class="foot-av" id="myAv" style="background:<?= ccEsc($swatchColors[ord($avatarLetter) % count($swatchColors)]) ?>">
        <?php if ($me['profile_picture']): ?>
        <img src="<?= ccEsc($me['profile_picture']) ?>" alt="" />
        <?php else: ?>
        <?= $avatarLetter ?>
        <?php endif; ?>
      </div>
      <div class="foot-info">
        <div class="foot-name"><?= ccEsc($displayName) ?></div>
        <div class="foot-user">@<?= ccEsc($me['username']) ?></div>
      </div>
    </div>
  </aside>

  <!-- ── Chat ─────────────────────────────────────────────── -->
  <main id="chatMain">
    <div id="chatHead" style="display:none;">
      <div id="chatHeadName"></div>
      <div id="chatHeadDesc"></div>
      <button class="head-btn" id="memberToggle" title="Members" onclick="toggleMembers()" style="display:none;">👥</button>
    </div>
    <div id="chatEmpty" style="display:none;">
      <div class="ic">💬</div>
      <h3>Connected Center</h3>
      <p>Select a server channel or open a direct message to start chatting.</p>
    </div>

    <!-- ── Connected Home (App Store style) ─────────────────── -->
    <div id="homeView">

      <div id="homeInviteBanner"></div>

      <!-- Search bar -->
      <div class="hv-search">
        <div class="hv-search-bar">
          <span class="hv-search-icon">🔍</span>
          <input type="text" class="hv-search-input" id="homeSearchInput" placeholder="Search games by ID, servers, people…" onkeydown="if(event.key==='Enter')runHomeSearch()" />
        </div>
        <div class="hv-greet-name" id="homeGreeting">Hey!</div>
      </div>

      <!-- Hero carousel — recommended community games -->
      <div class="hv-section" style="padding-top:18px;">
        <div class="hv-sec-head" style="margin-bottom:10px;">
          <span class="hv-sec-title">Recommended Community Games:</span>
        </div>
        <div style="position:relative;">
          <div class="hv-hero" id="hvHero" onclick="heroAction()">
            <div class="hv-hero-slides" id="hvSlides">
              <div class="hv-hero-slide" style="background:linear-gradient(135deg,#1a237e 0%,#7b1fa2 100%)">
                <div class="hv-hero-art">🎮</div>
                <div class="hv-hero-overlay"></div>
                <div class="hv-hero-text">
                  <div class="hv-hero-eyebrow">Community</div>
                  <div class="hv-hero-title">Loading games…</div>
                  <div class="hv-hero-desc">Fetching the latest community uploads.</div>
                </div>
              </div>
            </div>
            <div class="hv-hero-dots" id="hvDots">
              <div class="hv-dot active"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Access (pinned shortcuts as icon row) -->
      <div class="hv-section">
        <div class="hv-sec-head">
          <span class="hv-sec-title">Quick Access</span>
          <button class="hv-sec-all" onclick="openAddShortcut()">+ Add</button>
        </div>
        <div class="hv-scroll" id="shortcutsRow">
          <div class="qa-item" onclick="openAddShortcut()">
            <div class="qa-add-icon">+</div>
            <span class="qa-label">Add</span>
          </div>
        </div>
      </div>

      <!-- Community Games -->
      <div class="hv-section">
        <div class="hv-sec-head">
          <span class="hv-sec-title">Community Games</span>
          <span class="hv-sec-all" id="communityGamesCount" style="color:var(--theme-text-secondary,#aaa);cursor:default"></span>
        </div>
        <p class="hv-sec-sub">Games built by Nexus users — create in Nexus Blocks and publish with one click.</p>
        <div class="as-scroll" id="communityGamesRow">
          <div style="padding:8px 0;font-size:12.5px;color:var(--theme-text-secondary,#aaa);">Loading games…</div>
        </div>
      </div>

      <!-- Catch Up (activity) -->
      <div class="hv-section">
        <div class="hv-sec-head">
          <span class="hv-sec-title">Catch Up</span>
        </div>
        <div class="act-list" id="activityList">
          <div style="padding:10px 0;font-size:12.5px;color:var(--theme-text-secondary,#aaa);">Loading activity…</div>
        </div>
      </div>

      <!-- What's New -->
      <div class="hv-section">
        <div class="hv-sec-head">
          <span class="hv-sec-title">What's New</span>
        </div>
        <div class="wn-list">
          <?php
          $whatsnew = [
            ['🎉','rgba(33,150,243,0.12)','New','Connected Center is here!','Servers, channels, direct messages, game invites, and more — your community hub inside Nexus.','June 17, 2026'],
            ['📩','rgba(76,175,80,0.12)', 'Feature','Game Invites','Invite friends from any game. They get a notification with a Play Now button right on their screen.','June 15, 2026'],
            ['🛡','rgba(156,39,176,0.12)','Feature','Server Roles','Owners can promote members to admin. Admins can kick or ban members from their servers.','June 14, 2026'],
          ];
          foreach ($whatsnew as [$em,$bg,$tag,$title,$desc,$date]):
          ?>
          <div class="wn-item">
            <div class="wn-icon" style="background:<?= $bg ?>"><?= $em ?></div>
            <div class="wn-body">
              <div class="wn-tag"><?= $tag ?></div>
              <div class="wn-title"><?= $title ?></div>
              <div class="wn-desc"><?= $desc ?></div>
              <div class="wn-date"><?= $date ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <div id="msgWrap" style="display:none;"></div>
    <div id="inputArea" style="display:none;">
      <div id="inputWrap">
        <textarea id="msgInput" rows="1" placeholder="Message..." maxlength="2000"></textarea>
        <button id="sendBtn" onclick="sendMsg()">Send</button>
      </div>
    </div>
  </main>

  <!-- ── Members Panel ─────────────────────────────────────── -->
  <aside id="membersPanel"></aside>
</div>

<div id="modalWrap"></div>
<div id="toast"></div>

<div id="editorOverlay" class="cc-fullscreen-overlay">
  <div class="cc-overlay-bar">
    <span class="cc-overlay-title">Nexus Blocks — Create your game</span>
    <button type="button" class="cc-overlay-close" onclick="closeNexusBlocksEditor()" title="Close editor">✕</button>
  </div>
  <iframe id="editorFrame" class="cc-overlay-frame" title="Nexus Blocks Editor" allow="microphone; camera"></iframe>
</div>

<div id="gameOverlay" class="cc-fullscreen-overlay">
  <div class="cc-overlay-bar">
    <span class="cc-overlay-title" id="gameOverlayTitle">Playing game</span>
    <div class="cc-overlay-actions">
      <button type="button" id="gameModerationDelete" class="cc-overlay-mod-btn" style="display:none" onclick="moderationDeletePlayingGame()">(Moderation) Delete Project</button>
      <button type="button" class="cc-overlay-close" onclick="closeCommunityGame()" title="Close game">✕</button>
    </div>
  </div>
  <div class="cc-game-embed-wrap">
    <iframe
      id="gameFrame"
      class="cc-game-embed-frame"
      title="Community Game Player"
      allowtransparency="true"
      frameborder="0"
      scrolling="no"
      allowfullscreen
      allow="autoplay; fullscreen; gamepad; keyboard-lock; pointer-lock"
    ></iframe>
  </div>
</div>

<script>
var ME = {
  id:   <?= json_encode($me['id']) ?>,
  name: <?= json_encode($displayName) ?>,
  user: <?= json_encode($me['username']) ?>,
  pic:  <?= json_encode($me['profile_picture'] ?? '') ?>,
  role: <?= json_encode($me['role']) ?>,
};
var SWATCHES = <?= json_encode($swatchColors) ?>;
var CC = {
  base:       <?= json_encode($ccBasePath) ?>,
  uploadApi:  <?= json_encode($ccUploadApi) ?>,
  downloadApi:<?= json_encode($ccDownloadApi) ?>,
  editorPath: <?= json_encode($ccEditorPath) ?>,
  embedPath:  <?= json_encode($ccEmbedPath) ?>,
  cloudHost:  <?= json_encode($ccCloudHost) ?>,
};

/* ── Helpers ──────────────────────────────────────────────── */
var _toastT = null;
function toast(msg, ms) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(_toastT);
  _toastT = setTimeout(function(){ t.classList.remove('show'); }, ms || 3000);
}
/* Safe HTML escape */
function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
/* Escape a value for use inside an HTML onclick="..." attribute.
   JSON.stringify produces "..." (double quotes) which would break the attribute,
   so we also HTML-escape the result. */
function escAttr(v) { return esc(JSON.stringify(v)); }

function fmtTime(ts) {
  if (!ts) return '';
  var d = new Date(ts.replace(' ','T')+'Z'), now = new Date();
  var today = d.toDateString() === now.toDateString();
  var t = d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
  return today ? t : d.toLocaleDateString([],{month:'short',day:'numeric'})+' '+t;
}
var _AV_COLORS = ['#2196f3','#4caf50','#e53935','#ff9800','#9c27b0','#00bcd4','#f06292','#795548','#607d8b'];
function avColor(name){ return _AV_COLORS[((name||'?').charCodeAt(0))%_AV_COLORS.length]; }
function avEl(pic, name, size, cls) {
  size = size || 36; cls = cls || 'msg-av';
  var n = name || '?'; var l = n.charAt(0).toUpperCase();
  var bg = avColor(n);
  if (pic) return '<div class="'+cls+'" style="width:'+size+'px;height:'+size+'px"><img src="'+esc(pic)+'" alt="" loading="lazy"></div>';
  return '<div class="'+cls+'" style="width:'+size+'px;height:'+size+'px;background:'+bg+'">'+l+'</div>';
}

/* ── State ────────────────────────────────────────────────── */
var st = {
  mode: 'home', serverId: null, serverName: '', userRole: 'member',
  channelId: null, channelName: '', convId: null,
  lastId: 0, poll: null, membersOpen: false,
  polling: false,
  msgIds: {},
  playingGameId: null,
  playingGameTitle: ''
};
function stopPoll(){ if(st.poll){ clearInterval(st.poll); st.poll = null; } st.polling = false; }

/* ── Rail active + ripple ─────────────────────────────────── */
function railActive(id) {
  document.querySelectorAll('.rail-btn').forEach(function(b){ b.classList.remove('active'); });
  var el = id ? document.getElementById(id) : null;
  if (el) el.classList.add('active');
}
function rippleRail(clickedId) {
  var rail = document.getElementById('rail');
  var btns = Array.from(rail.querySelectorAll('.rail-btn'));
  var clickedEl = document.getElementById(clickedId);
  if (!clickedEl) return;
  var ci = btns.indexOf(clickedEl);
  btns.forEach(function(btn, i) {
    // Strip any running animation first
    btn.classList.remove('anim-bounce', 'anim-wobble');
    void btn.offsetWidth; // force reflow
    var dist = Math.abs(i - ci);
    if (dist === 0) {
      btn.classList.add('anim-bounce');
      btn.addEventListener('animationend', function(){ btn.classList.remove('anim-bounce'); }, { once: true });
    } else if (dist <= 4) {
      btn.style.animationDelay = (dist * 55) + 'ms';
      btn.classList.add('anim-wobble');
      btn.addEventListener('animationend', function(){
        btn.classList.remove('anim-wobble');
        btn.style.animationDelay = '';
      }, { once: true });
    }
  });
}

/* ── Chat transition helpers ──────────────────────────────── */
function animateChatMain(cls) {
  var m = document.getElementById('chatMain');
  m.classList.remove('anim-from-left', 'anim-from-right', 'anim-scale-up');
  void m.offsetWidth;
  m.classList.add(cls);
  m.addEventListener('animationend', function(){ m.classList.remove(cls); }, { once: true });
}
function animateHead() {
  var h = document.getElementById('chatHead');
  h.classList.remove('anim-head'); void h.offsetWidth;
  h.classList.add('anim-head');
  h.addEventListener('animationend', function(){ h.classList.remove('anim-head'); }, { once: true });
}
function animateInput() {
  var ia = document.getElementById('inputArea');
  ia.classList.remove('anim-rise'); void ia.offsetWidth;
  ia.classList.add('anim-rise');
  ia.addEventListener('animationend', function(){ ia.classList.remove('anim-rise'); }, { once: true });
}
function staggerSidebar() {
  var items = document.querySelectorAll('#sidebarBody .s-item');
  items.forEach(function(el, i) {
    el.classList.remove('stagger'); void el.offsetWidth;
    el.style.animationDelay = (i * 28) + 'ms';
    el.classList.add('stagger');
    el.addEventListener('animationend', function(){
      el.classList.remove('stagger');
      el.style.animationDelay = '';
    }, { once: true });
  });
}

/* ── DM sidebar ───────────────────────────────────────────── */
var dmConvs = <?= json_encode(array_values($dmConvs)) ?>;

function renderDMSidebar() {
  document.getElementById('sidebarTitle').textContent = 'Direct Messages';
  var sa = document.getElementById('sidebarAction');
  sa.textContent = '+'; sa.title = 'New DM'; sa.onclick = openNewDM;

  var html = '<div class="sec-label">Messages <button onclick="openNewDM()">+</button></div>';
  if (!dmConvs.length) {
    html += '<div style="padding:12px 10px;font-size:12.5px;color:var(--theme-text-secondary,#888);">No messages yet.<br><br><a href="#" onclick="openNewDM();return false;" style="color:var(--theme-accent,#2196f3);">Start one →</a></div>';
  }
  dmConvs.forEach(function(c) {
    var name = c.participant_names || c.name || 'Conversation';
    var isActive = st.convId == c.id;
    var bg = avColor(name);
    html += '<div class="s-item' + (isActive ? ' active' : '') + '" onclick="selectConv(' + c.id + ',' + escAttr(name) + ')">'
      + '<div class="dm-av" style="background:' + bg + '">' + esc(name.charAt(0).toUpperCase()) + '</div>'
      + '<span class="s-item-name">' + esc(name) + '</span></div>';
  });
  document.getElementById('sidebarBody').innerHTML = html;
  staggerSidebar();
}

function renderServerSidebar(channels, role) {
  var body = document.getElementById('sidebarBody');
  var canManage = role === 'owner' || role === 'admin';
  var html = '<div class="sec-label">Channels'
    + (canManage ? '<button onclick="openAddChannel()">+</button>' : '') + '</div>';
  channels.forEach(function(ch) {
    var icon = ch.type === 'announcement' ? '📢' : '#';
    var isActive = st.channelId == ch.id;
    html += '<div class="s-item' + (isActive ? ' active' : '') + '" onclick="selectChannel(' + ch.id + ',' + escAttr(ch.name) + ')">'
      + '<span class="s-hash">' + icon + '</span><span class="s-item-name">' + esc(ch.name) + '</span></div>';
  });
  if (canManage) {
    html += '<div class="sec-label" style="margin-top:10px">Server</div>'
      + '<div class="s-item" onclick="openInvitePeople()"><span class="s-hash">➕</span><span>Invite People</span></div>'
      + '<div class="s-item danger" onclick="confirmDeleteServer()"><span class="s-hash">🗑</span><span>Delete Server</span></div>';
  } else {
    html += '<div class="s-item danger" style="margin-top:8px" onclick="confirmLeave()"><span class="s-hash">🚪</span><span>Leave Server</span></div>';
  }
  body.innerHTML = html;
}

/* ── Select DMs mode ──────────────────────────────────────── */
function selectDMs() {
  st.mode = 'dm'; st.serverId = null; st.channelId = null;
  stopPoll(); railActive('railDMs');
  renderDMSidebar(); showEmpty();
}

/* ── Select a DM conversation ─────────────────────────────── */
function selectConv(convId, name) {
  st.convId = convId; st.channelId = null; st.lastId = 0; st.msgIds = {}; stopPoll();
  updateHead('@' + esc(name), '');
  document.getElementById('msgInput').placeholder = 'Message @' + name;
  document.getElementById('memberToggle').style.display = 'none';
  animateChatMain('anim-from-left');
  animateHead();
  animateInput();
  showChat();
  loadDMs(convId);
  st.poll = setInterval(function(){ pollDMs(convId); }, 2500);
  renderDMSidebar();
  // pulse the selected item once sidebar re-renders
  setTimeout(function() {
    var active = document.querySelector('#sidebarBody .s-item.active');
    if (active) {
      active.classList.add('anim-select');
      active.addEventListener('animationend', function(){ active.classList.remove('anim-select'); }, { once: true });
    }
  }, 10);
}

function loadDMs(cid) {
  st.polling = true;
  fetch('api/get_dm_messages.php?conv_id=' + cid + '&limit=60')
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.error) { toast(d.error); return; }
      if (st.convId !== cid) return;
      var msgs = d.messages || [];
      renderMsgs(msgs, false);
      if (msgs.length) st.lastId = parseInt(msgs[msgs.length-1].id);
    }).catch(function(){ toast('Could not load messages.'); })
    .finally(function(){ st.polling = false; });
}
function pollDMs(cid) {
  if (st.convId !== cid || st.polling) return;
  st.polling = true;
  fetch('api/get_dm_messages.php?conv_id=' + cid + '&after_id=' + st.lastId)
    .then(function(r){ return r.json(); })
    .then(function(d) {
      st.polling = false;
      if (st.convId !== cid) return;
      var m = d.messages || [];
      if (m.length) { appendMsgs(m); st.lastId = parseInt(m[m.length-1].id); }
    }).catch(function(){ st.polling = false; });
}

/* ── Select a server ──────────────────────────────────────── */
function selectServer(sid, sname, role) {
  st.mode = 'server'; st.serverId = sid; st.serverName = sname; st.userRole = role;
  st.channelId = null; st.convId = null; stopPoll();
  railActive('rail-' + sid);
  document.getElementById('sidebarTitle').textContent = sname;
  document.getElementById('sidebarAction').textContent = '';
  document.getElementById('sidebarAction').onclick = null;
  fetch('api/get_channels.php?server_id=' + sid)
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.error) { toast(d.error); return; }
      st.userRole = d.user_role;
      renderServerSidebar(d.channels || [], d.user_role);
      staggerSidebar();
    }).catch(function(){ toast('Could not load channels.'); });
  showEmpty();
}

/* ── Select a channel ─────────────────────────────────────── */
function selectChannel(cid, cname) {
  st.channelId = cid; st.channelName = cname; st.lastId = 0; st.msgIds = {}; stopPoll();
  updateHead('<span class="ch-hash">#</span>' + esc(cname), '');
  document.getElementById('msgInput').placeholder = 'Message #' + cname;
  document.getElementById('memberToggle').style.display = '';
  animateChatMain('anim-scale-up');
  animateHead();
  animateInput();
  showChat();
  if (st.membersOpen) loadMembers(st.serverId);
  loadChannel(cid);
  st.poll = setInterval(function(){ pollChannel(cid); }, 2000);
  // Re-render sidebar to mark active channel
  fetch('api/get_channels.php?server_id=' + st.serverId)
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (!d.error) {
        renderServerSidebar(d.channels || [], d.user_role || st.userRole);
        staggerSidebar();
      }
    })
    .catch(function(){});
}

function loadChannel(cid) {
  st.polling = true;
  fetch('api/get_channel_messages.php?channel_id=' + cid + '&limit=60')
    .then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.error) { toast(d.error); return; }
      if (st.channelId !== cid) return;
      var msgs = d.messages || [];
      renderMsgs(msgs, true);
      if (msgs.length) st.lastId = parseInt(msgs[msgs.length-1].id);
    }).catch(function(){ toast('Could not load messages.'); })
    .finally(function(){ st.polling = false; });
}
function pollChannel(cid) {
  if (st.channelId !== cid || st.polling) return;
  st.polling = true;
  fetch('api/get_channel_messages.php?channel_id=' + cid + '&after_id=' + st.lastId)
    .then(function(r){ return r.json(); })
    .then(function(d) {
      st.polling = false;
      if (st.channelId !== cid) return;
      var m = d.messages || [];
      if (m.length) { appendMsgs(m); st.lastId = parseInt(m[m.length-1].id); }
    }).catch(function(){ st.polling = false; });
}

/* ── Message rendering ────────────────────────────────────── */
function showChat() {
  stopHeroTimer();
  document.getElementById('chatEmpty').style.display = 'none';
  document.getElementById('homeView').classList.remove('visible');
  document.getElementById('msgWrap').style.display = 'flex';
  document.getElementById('inputArea').style.display = 'block';
  document.getElementById('chatHead').style.display = 'flex';
}
function renderMsgs(msgs, isChannel) {
  showChat();
  st.msgIds = {};
  var wrap = document.getElementById('msgWrap');
  var prevA = null, prevT = null;
  var label = isChannel ? ('<span class="ch-hash">#</span>' + esc(st.channelName)) : 'Direct Message';
  var html = '<div class="welcome-msg"><h3>' + label + '</h3><p>Beginning of this conversation.</p></div>';
  msgs.forEach(function(m) {
    st.msgIds[parseInt(m.id)] = true;
    html += buildMsg(m, prevA, prevT);
    prevA = m.username || m.sender_username; prevT = m.created_at;
  });
  wrap.innerHTML = html;
  wrap.scrollTop = wrap.scrollHeight;
  // Stagger message load-in animation
  var msgEls = wrap.querySelectorAll('.msg-group, .msg-cont');
  var total = msgEls.length;
  msgEls.forEach(function(el, i) {
    // Only animate the last 12 messages so it doesn't feel excessive on long chats
    var fromEnd = total - i;
    if (fromEnd > 12) return;
    el.classList.add('msg-load');
    el.style.animationDelay = ((fromEnd - 1) * -18 + (i * 18)) + 'ms';
    el.addEventListener('animationend', function(){
      el.classList.remove('msg-load');
      el.style.animationDelay = '';
    }, { once: true });
  });
}
function appendMsgs(msgs) {
  var fresh = [];
  msgs.forEach(function(m) {
    var id = parseInt(m.id);
    if (st.msgIds[id]) return;
    st.msgIds[id] = true;
    fresh.push(m);
  });
  if (!fresh.length) return;

  var wrap = document.getElementById('msgWrap');
  var atBot = wrap.scrollHeight - wrap.clientHeight - wrap.scrollTop < 80;
  var prevA = null, prevT = null;
  fresh.forEach(function(m, i) {
    var div = document.createElement('div');
    div.innerHTML = buildMsg(m, prevA, prevT);
    var children = [];
    while (div.firstChild) {
      var child = div.firstChild;
      children.push(child);
      wrap.appendChild(child);
    }
    // Animate the new elements in with a staggered slide-up
    children.forEach(function(el, j) {
      if (el.nodeType !== 1) return;
      el.classList.add('msg-new');
      el.style.animationDelay = (i * 40 + j * 20) + 'ms';
      el.addEventListener('animationend', function(){
        el.classList.remove('msg-new');
        el.style.animationDelay = '';
      }, { once: true });
    });
    prevA = m.username || m.sender_username; prevT = m.created_at;
  });
  if (atBot) wrap.scrollTop = wrap.scrollHeight;
}
function buildMsg(m, prevA, prevT) {
  var name  = m.display_name || m.sender_display_name || m.username || m.sender_username || 'User';
  var uname = m.username || m.sender_username || '';
  var pic   = m.profile_picture || m.sender_pic || '';
  var cnt   = esc(m.content || '');
  var ts    = m.created_at;
  var same  = prevA === uname && prevT &&
    (new Date(ts.replace(' ','T')) - new Date(prevT.replace(' ','T'))) < 5 * 60000;
  if (same) return '<div class="msg-cont"><div class="msg-text">' + cnt + '</div></div>';
  return '<div class="msg-group">' + avEl(pic, name, 36, 'msg-av')
    + '<div class="msg-body"><div class="msg-meta">'
    + '<span class="msg-author">' + esc(name) + '</span>'
    + '<span class="msg-time">' + fmtTime(ts) + '</span></div>'
    + '<div class="msg-text">' + cnt + '</div></div></div>';
}

/* ── Send message ─────────────────────────────────────────── */
function sendMsg() {
  var input = document.getElementById('msgInput');
  var content = input.value.trim();
  if (!content) return;
  var btn = document.getElementById('sendBtn'); btn.disabled = true;
  var url, body;
  if (st.mode === 'server' && st.channelId) {
    url = 'api/send_channel_message.php';
    body = JSON.stringify({ channel_id: st.channelId, content: content });
  } else if (st.mode === 'dm' && st.convId) {
    url = 'api/send_dm.php';
    body = JSON.stringify({ conv_id: st.convId, content: content });
  } else { btn.disabled = false; return; }
  input.value = ''; input.style.height = 'auto';
  // Animate send button
  btn.classList.remove('anim-send'); void btn.offsetWidth;
  btn.classList.add('anim-send');
  btn.addEventListener('animationend', function(){ btn.classList.remove('anim-send'); }, { once: true });

  fetch(url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: body })
    .then(function(r){ return r.json(); })
    .then(function(d) {
      btn.disabled = false;
      if (d.error) { toast(d.error); return; }
      // Let the interval pick up the new message — do NOT call poll manually here,
      // as that creates a race with the interval and causes double-message renders.
      // Reset the poll lock so the next interval tick fires immediately.
      st.polling = false;
    }).catch(function(){ btn.disabled = false; toast('Failed to send.'); });
}

/* ── Members panel ────────────────────────────────────────── */
function toggleMembers() {
  st.membersOpen = !st.membersOpen;
  var p = document.getElementById('membersPanel');
  if (st.membersOpen && st.serverId) { p.classList.add('open'); loadMembers(st.serverId); }
  else p.classList.remove('open');
}
function loadMembers(sid) {
  fetch('api/get_members.php?server_id=' + sid)
    .then(function(r){ return r.json(); })
    .then(function(d) {
      var owners = [], admins = [], members = [];
      (d.members || []).forEach(function(m) {
        if (m.role === 'owner') owners.push(m);
        else if (m.role === 'admin') admins.push(m);
        else members.push(m);
      });
      function row(m) {
        var n = m.display_name || m.username;
        var badge = m.role === 'owner' ? '<span class="badge-owner">Owner</span>'
          : m.role === 'admin' ? '<span class="badge-madmin">Admin</span>' : '';
        var canCtx = m.id !== ME.id && (st.userRole === 'owner' || st.userRole === 'admin');
        return '<div class="mem-item" '
          + (canCtx ? 'oncontextmenu="memberCtx(event,' + m.id + ',' + escAttr(n) + ',' + escAttr(m.role) + ');return false;" title="Right-click for options"' : '')
          + '>' + avEl(m.profile_picture, n, 30, 'mem-av')
          + '<div class="mem-name">' + esc(n) + badge + '</div></div>';
      }
      var html = '';
      if (owners.length)  html += '<div class="mem-sec">Owner — ' + owners.length  + '</div>' + owners.map(row).join('');
      if (admins.length)  html += '<div class="mem-sec">Admins — ' + admins.length + '</div>' + admins.map(row).join('');
      if (members.length) html += '<div class="mem-sec">Members — ' + members.length + '</div>' + members.map(row).join('');
      document.getElementById('membersPanel').innerHTML = html;
    }).catch(function(){});
}

/* ── Head / empty state ───────────────────────────────────── */
function updateHead(nameHtml, desc) {
  document.getElementById('chatHead').style.display = 'flex';
  document.getElementById('chatHeadName').innerHTML = nameHtml;
  document.getElementById('chatHeadDesc').textContent = desc;
}
function showEmpty() {
  stopPoll(); stopHeroTimer(); st.channelId = null; st.convId = null;
  document.getElementById('msgWrap').style.display = 'none';
  document.getElementById('inputArea').style.display = 'none';
  document.getElementById('chatHead').style.display = 'none';
  document.getElementById('homeView').classList.remove('visible');
  document.getElementById('membersPanel').classList.remove('open');
  var ce = document.getElementById('chatEmpty');
  ce.style.display = 'none'; void ce.offsetWidth;
  ce.style.display = 'flex';
}

/* ── Connected Home ───────────────────────────────────────── */
function selectHome() {
  st.mode = 'home'; st.serverId = null; st.channelId = null; st.convId = null;
  stopPoll(); railActive('railHome');
  document.getElementById('sidebarTitle').textContent = 'Connected Center';
  document.getElementById('sidebarAction').textContent = '';
  document.getElementById('sidebarAction').onclick = null;
  document.getElementById('msgWrap').style.display = 'none';
  document.getElementById('inputArea').style.display = 'none';
  document.getElementById('chatHead').style.display = 'none';
  document.getElementById('chatEmpty').style.display = 'none';
  document.getElementById('membersPanel').classList.remove('open');

  var hv = document.getElementById('homeView');
  hv.classList.remove('visible'); void hv.offsetWidth;
  hv.classList.add('visible');

  // Personalized greeting (short chip, search bar is prominent)
  var hour = new Date().getHours();
  var greet = hour < 12 ? '👋 Morning' : hour < 17 ? '👋 Hey' : '👋 Evening';
  document.getElementById('homeGreeting').textContent = greet + ', ' + ME.name.split(' ')[0] + '!';

  var hero = document.getElementById('hvHero');
  if (hero) {
    hero.onmouseenter = stopHeroTimer;
    hero.onmouseleave = startHeroTimer;
  }

  // Load data
  fetch('api/get_home_data.php')
    .then(function(r){ return r.json(); })
    .then(function(d) {
      renderShortcuts(d.shortcuts || [], d.servers || []);
      renderActivity(d.recent_channel || [], d.recent_dms || []);
      renderServerInvites(d.server_invites || []);
    }).catch(function(){});

  loadCommunityGames();

  // Sidebar: show DM list but keep home title
  renderDMSidebar();
  document.getElementById('sidebarTitle').textContent = 'Connected Home';
  staggerSidebar();
}

function renderServerInvites(invites) {
  var banner = document.getElementById('homeInviteBanner');
  if (!banner) return;
  if (!invites.length) {
    banner.classList.remove('visible');
    banner.innerHTML = '';
    return;
  }
  banner.innerHTML = invites.map(function(inv) {
    var from = inv.from_name || inv.from_username || 'Someone';
    var color = inv.icon_color || '#5865f2';
    var initials = (inv.server_name || 'S').substring(0, 2).toUpperCase();
    return '<div class="hv-invite-card">'
      + '<div class="hv-invite-icon" style="background:' + esc(color) + '">' + esc(initials) + '</div>'
      + '<div class="hv-invite-body">'
      + '<div class="hv-invite-title">Server invite: ' + esc(inv.server_name) + '</div>'
      + '<div class="hv-invite-sub">' + esc(from) + ' invited you to join</div>'
      + '</div>'
      + '<div class="hv-invite-actions">'
      + '<button type="button" class="hv-invite-btn hv-invite-decline" onclick="respondServerInvite(' + inv.id + ',\'decline\')">Decline</button>'
      + '<button type="button" class="hv-invite-btn hv-invite-accept" onclick="respondServerInvite(' + inv.id + ',\'accept\',' + inv.server_id + ',' + escAttr(inv.server_name) + ')">Accept</button>'
      + '</div></div>';
  }).join('');
  banner.classList.add('visible');
}

function respondServerInvite(inviteId, action, serverId, serverName) {
  fetch('api/respond_server_invite.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ invite_id: inviteId, action: action })
  }).then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.error) { toast(d.error); return; }
      if (action === 'accept') {
        toast('Joined ' + (serverName || 'server') + '!');
        reloadServers();
        if (serverId) selectServer(serverId, serverName || 'Server', 'member');
      } else {
        toast('Invite declined.');
      }
      fetch('api/get_home_data.php').then(function(r){ return r.json(); })
        .then(function(hd) { renderServerInvites(hd.server_invites || []); })
        .catch(function(){});
    }).catch(function(){ toast('Could not respond to invite.'); });
}

function ccAbsoluteUrl(path) {
  if (!path) return '';
  if (path.indexOf('http://') === 0 || path.indexOf('https://') === 0) return path;
  var base = window.location.origin + (CC.base || '');
  if (path.charAt(0) === '/') return window.location.origin + path;
  return base + (path.charAt(0) === '/' ? '' : '/') + path.replace(/^\//, '');
}

function loadCommunityGames() {
  fetch('api/get_community_games.php')
    .then(function(r){ return r.json(); })
    .then(function(d) {
      var games = d.games || [];
      renderCommunityGames(games);
      renderHeroSlides(pickRandomHeroGames(games));
    })
    .catch(function() {
      var row = document.getElementById('communityGamesRow');
      if (row) row.innerHTML = '<div style="padding:8px 0;font-size:12.5px;color:var(--theme-text-secondary,#aaa);">Could not load games.</div>';
      renderHeroSlides([]);
    });
}

function pickRandomHeroGames(games) {
  var pool = games.slice(0, 10);
  for (var i = pool.length - 1; i > 0; i--) {
    var j = Math.floor(Math.random() * (i + 1));
    var tmp = pool[i];
    pool[i] = pool[j];
    pool[j] = tmp;
  }
  return pool.slice(0, Math.min(3, pool.length));
}

function renderHeroSlides(games) {
  _heroGames = games || [];
  _heroLen = Math.max(_heroGames.length, 1);
  var slidesEl = document.getElementById('hvSlides');
  var dotsEl = document.getElementById('hvDots');
  if (!slidesEl || !dotsEl) return;

  if (!_heroGames.length) {
    slidesEl.innerHTML = '<div class="hv-hero-slide" style="background:linear-gradient(135deg,#1a237e 0%,#7b1fa2 100%)">'
      + '<div class="hv-hero-art">🎮</div><div class="hv-hero-overlay"></div>'
      + '<div class="hv-hero-text"><div class="hv-hero-eyebrow">Community</div>'
      + '<div class="hv-hero-title">No games yet</div>'
      + '<div class="hv-hero-desc">Create the first one with Nexus Blocks!</div></div></div>';
    dotsEl.innerHTML = '<div class="hv-dot active"></div>';
    heroGo(0);
    stopHeroTimer();
    return;
  }

  var html = '';
  _heroGames.forEach(function(g) {
    var plays = g.play_count > 0 ? (g.play_count + ' play' + (g.play_count === 1 ? '' : 's')) : 'New upload';
    var desc = 'By ' + (g.author || 'Unknown') + ' · ' + plays;
    html += '<div class="hv-hero-slide" style="background:' + esc(g.gradient) + '">'
      + '<div class="hv-hero-art">' + esc(g.emoji) + '</div>'
      + '<div class="hv-hero-overlay"></div>'
      + '<div class="hv-hero-text">'
      + '<div class="hv-hero-eyebrow">Recommended</div>'
      + '<div class="hv-hero-title">' + esc(g.title) + '</div>'
      + '<div class="hv-hero-desc">' + esc(desc) + '</div>'
      + '</div></div>';
  });
  slidesEl.innerHTML = html;
  dotsEl.innerHTML = _heroGames.map(function(_, i) {
    return '<div class="hv-dot' + (i === 0 ? ' active' : '') + '" onclick="event.stopPropagation();heroGo(' + i + ')"></div>';
  }).join('');
  heroGo(0);
  startHeroTimer();
}

function adminDeleteCommunityGame(gameId, title, opts) {
  opts = opts || {};
  if (ME.role !== 'admin') return;
  var label = title || ('Game #' + gameId);
  if (!window.confirm('Delete project "' + label + '"? This cannot be undone.')) return;

  fetch('api/delete_community_game.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include',
    body: JSON.stringify({ game_id: gameId })
  })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.error) {
        toast(d.error);
        return;
      }
      toast('Project deleted.');
      if (opts.closeOverlay) closeCommunityGame();
      loadCommunityGames();
    })
    .catch(function() { toast('Could not delete project.'); });
}

function moderationDeletePlayingGame() {
  if (!st.playingGameId) return;
  adminDeleteCommunityGame(st.playingGameId, st.playingGameTitle, { closeOverlay: true });
}

function renderCommunityGames(games) {
  var row = document.getElementById('communityGamesRow');
  var countEl = document.getElementById('communityGamesCount');
  if (!row) return;

  if (countEl) {
    countEl.textContent = games.length ? (games.length + ' game' + (games.length === 1 ? '' : 's')) : '';
  }

  var html = '';
  html += '<div class="as-card as-create" style="animation-delay:0s" onclick="openNexusBlocksEditor()" title="Open Nexus Blocks editor">'
    + '<div class="as-art">✨</div>'
    + '<div class="as-name">Create your own</div>'
    + '<div class="as-by">Nexus Blocks</div>'
    + '<div class="as-row"><span class="as-stars"></span>'
    + '<button class="as-get" onclick="event.stopPropagation();openNexusBlocksEditor()">Open</button>'
    + '</div></div>';

  games.forEach(function(g, i) {
    var plays = g.play_count > 0 ? (g.play_count + ' play' + (g.play_count === 1 ? '' : 's')) : 'New';
    html += '<div class="as-card" style="animation-delay:' + ((i + 1) * 0.04) + 's" onclick="playCommunityGame(' + g.id + ',' + escAttr(g.title) + ')" title="' + esc(g.title) + ' by ' + esc(g.author) + '">'
      + '<div class="as-art" style="background:' + esc(g.gradient) + '">' + esc(g.emoji) + '</div>'
      + '<div class="as-name">' + esc(g.title) + '</div>'
      + '<div class="as-by">' + esc(g.author) + '</div>'
      + '<div class="as-row">'
      + '<span class="as-stars" style="color:var(--theme-text-secondary,#999);letter-spacing:0;font-size:10px;">' + esc(plays) + '</span>'
      + '<span style="display:flex;gap:4px;align-items:center;">'
      + '<button class="as-get" onclick="event.stopPropagation();playCommunityGame(' + g.id + ',' + escAttr(g.title) + ')">Play</button>'
      + (ME.role === 'admin'
        ? '<button class="as-mod-del" onclick="event.stopPropagation();adminDeleteCommunityGame(' + g.id + ',' + escAttr(g.title) + ')" title="Moderation delete">Mod</button>'
        : '')
      + '</span>'
      + '</div></div>';
  });

  if (!games.length) {
    html += '<div style="align-self:center;padding:12px 8px;font-size:12.5px;color:var(--theme-text-secondary,#aaa);">No community games yet — be the first!</div>';
  }

  row.innerHTML = html;
}

function ccIsEditorFrameSource(e) {
  var frame = document.getElementById('editorFrame');
  if (frame && frame.contentWindow && e.source === frame.contentWindow) return true;
  if (!e.data || !e.data.type || String(e.data.type).indexOf('cc-') !== 0) return false;
  try {
    var editorOrigin = new URL(CC.editorPath, window.location.href).origin;
    if (editorOrigin && e.origin === editorOrigin) return true;
  } catch (err) {}
  return false;
}

function nexusDebugLog(source, message, meta, level) {
  var ts = new Date().toLocaleTimeString();
  var line = ts + ' [' + source + '] ' + message;
  if (meta) {
    try { line += ' ' + JSON.stringify(meta); } catch (e) { line += ' [meta]'; }
  }
  if (typeof console !== 'undefined') console.log(line);
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({
        type: 'nexus-debug-log',
        source: source,
        message: message,
        meta: meta || null,
        level: level || 'info',
        line: line,
        ts: Date.now()
      }, '*');
    }
  } catch (err) {}
}

function ccFetchJson(url, opts) {
  return fetch(url, opts).then(function(r) {
    return r.text().then(function(text) {
      var data;
      try { data = JSON.parse(text); } catch (e) {
        data = { error: 'Non-JSON response (HTTP ' + r.status + '): ' + text.substring(0, 180) };
      }
      return { ok: r.ok, status: r.status, data: data };
    });
  });
}

function ccReplyEditor(e, payload) {
  if (e.source) {
    e.source.postMessage(payload, '*');
  }
}

function ccNotifyShellEditorOpen(open) {
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({
        type: open ? 'nexus-blocks-editor-open' : 'nexus-blocks-editor-close'
      }, '*');
    }
  } catch (err) {}
}

function openNexusBlocksEditor(gameId, gameTitle) {
  var overlay = document.getElementById('editorOverlay');
  var frame = document.getElementById('editorFrame');
  if (!overlay || !frame) return;

  var uploadUrl = ccAbsoluteUrl(CC.uploadApi);
  var src = CC.editorPath
    + (CC.editorPath.indexOf('?') >= 0 ? '&' : '?')
    + 'connected_center=1'
    + '&nexus_embed=1'
    + '&cc_upload=' + encodeURIComponent(uploadUrl);
  if (gameId) {
    var projectUrl = ccAbsoluteUrl(CC.downloadApi + '?id=' + gameId);
    src += '&cc_game_id=' + encodeURIComponent(gameId)
      + '&project_url=' + encodeURIComponent(projectUrl);
    if (gameTitle) {
      src += '&cc_game_title=' + encodeURIComponent(gameTitle);
    }
  }
  frame.src = src;
  overlay.classList.add('open');
  ccNotifyShellEditorOpen(true);
  nexusDebugLog('ConnectedCenter', 'Opened Nexus Blocks editor', {
    gameId: gameId || null,
    editorUrl: src.split('?')[0],
    uploadApi: uploadUrl
  });
}

function closeNexusBlocksEditor() {
  var overlay = document.getElementById('editorOverlay');
  var frame = document.getElementById('editorFrame');
  if (overlay) overlay.classList.remove('open');
  if (frame) frame.src = 'about:blank';
  ccNotifyShellEditorOpen(false);
}

function ccBuildTurboWarpEmbedUrl(projectUrl, gameId) {
  var params = new URLSearchParams();
  params.set('project_url', projectUrl);
  params.set('cc_game_id', String(gameId));
  params.set('autoplay', '');
  params.set('cloud_host', CC.cloudHost);
  return CC.embedPath + '?' + params.toString();
}

function playCommunityGame(id, title) {
  var overlay = document.getElementById('gameOverlay');
  var frame = document.getElementById('gameFrame');
  var titleEl = document.getElementById('gameOverlayTitle');
  var modBtn = document.getElementById('gameModerationDelete');
  if (!overlay || !frame) return;

  st.playingGameId = id;
  st.playingGameTitle = title || '';

  var projectUrl = ccAbsoluteUrl(CC.downloadApi + '?id=' + id);
  var src = ccBuildTurboWarpEmbedUrl(projectUrl, id);
  if (titleEl) titleEl.textContent = title || 'Playing game';
  if (modBtn) modBtn.style.display = (ME.role === 'admin') ? '' : 'none';
  frame.onload = function() {
    nexusDebugLog('ConnectedCenter', 'Game embed iframe loaded', {
      gameId: id,
      src: src,
      ccGameId: String(id),
      cloudHost: CC.cloudHost
    });
  };
  frame.src = src;
  overlay.classList.add('open');
  nexusDebugLog('ConnectedCenter', 'Playing community game (TurboWarp embed)', {
    gameId: id,
    title: title || null,
    embedUrl: CC.embedPath,
    projectUrl: projectUrl,
    cloudProjectId: 'sync.nexus/cg/' + id,
    cloudHost: CC.cloudHost
  });
}

function closeCommunityGame() {
  var overlay = document.getElementById('gameOverlay');
  var frame = document.getElementById('gameFrame');
  var modBtn = document.getElementById('gameModerationDelete');
  if (overlay) overlay.classList.remove('open');
  if (frame) frame.src = 'about:blank';
  if (modBtn) modBtn.style.display = 'none';
  st.playingGameId = null;
  st.playingGameTitle = '';
}

function ccDecodeBridgeProject(d) {
  if (d.projectBase64) {
    var binary = atob(d.projectBase64);
    var len = binary.length;
    var bytes = new Uint8Array(len);
    for (var i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
    return new Blob([bytes], { type: 'application/octet-stream' });
  }
  if (d.project instanceof Blob) return d.project;
  if (d.project) return new Blob([d.project], { type: 'application/octet-stream' });
  return null;
}

function ccHandlePublishFromEditor(e, d) {
  var blob = ccDecodeBridgeProject(d);
  if (!blob || !blob.size) {
    nexusDebugLog('ConnectedCenter', 'Publish rejected — empty project payload', {
      requestId: d.requestId,
      byteLength: d.byteLength || 0,
      hasBase64: !!d.projectBase64
    }, 'error');
    ccReplyEditor(e, {
      type: 'cc-publish-game-result',
      requestId: d.requestId,
      error: 'Project file was empty after transfer'
    });
    return;
  }
  nexusDebugLog('ConnectedCenter', 'Received publish request from editor', {
    requestId: d.requestId,
    title: d.title,
    filename: d.filename,
    gameId: d.gameId || null,
    bytes: blob.size,
    expectedBytes: d.byteLength || null
  });
  var form = new FormData();
  form.append('project', blob, d.filename || 'project.sb3');
  form.append('title', d.title || 'Untitled Game');
  var url = d.gameId ? 'api/update_community_game.php' : 'api/upload_community_game.php';
  if (d.gameId) form.append('game_id', String(d.gameId));
  else form.append('visibility', d.visibility || 'public');
  nexusDebugLog('ConnectedCenter', 'POST ' + url, { requestId: d.requestId, blobSize: blob.size });
  ccFetchJson(url, { method: 'POST', body: form, credentials: 'include' })
    .then(function(res) {
      nexusDebugLog('ConnectedCenter', 'Publish API response', {
        requestId: d.requestId,
        httpStatus: res.status,
        ok: res.ok,
        body: res.data
      }, res.ok && !res.data.error ? 'info' : 'error');
      if (!res.ok || res.data.error) {
        ccReplyEditor(e, {
          type: 'cc-publish-game-result',
          requestId: d.requestId,
          error: (res.data && res.data.error) || 'Upload failed'
        });
        return;
      }
      ccReplyEditor(e, {
        type: 'cc-publish-game-result',
        requestId: d.requestId,
        data: res.data
      });
      toast('Published to Community Games!');
      loadCommunityGames();
    })
    .catch(function(err) {
      nexusDebugLog('ConnectedCenter', 'Publish fetch failed', {
        requestId: d.requestId,
        error: err && err.message ? err.message : String(err)
      }, 'error');
      ccReplyEditor(e, {
        type: 'cc-publish-game-result',
        requestId: d.requestId,
        error: 'Network error'
      });
    });
}

window.addEventListener('message', function(e) {
  if (!e.data) return;

  if (e.data.type === 'nexus-debug-log') {
    nexusDebugLog(e.data.source || 'Editor', e.data.message || '', e.data.meta, e.data.level);
    return;
  }

  if (e.data.type && String(e.data.type).indexOf('cc-') === 0 && !ccIsEditorFrameSource(e)) {
    nexusDebugLog('ConnectedCenter', 'Ignored editor message (source/origin mismatch)', {
      type: e.data.type,
      origin: e.origin,
      requestId: e.data.requestId || null
    }, 'error');
    return;
  }

  if (ccIsEditorFrameSource(e)) {
    if (e.data.type === 'cc-publish-game') {
      ccHandlePublishFromEditor(e, e.data);
      return;
    }

    if (e.data.type === 'cc-api-fetch') {
      var req = e.data;
      nexusDebugLog('ConnectedCenter', 'API fetch from editor', {
        requestId: req.requestId,
        endpoint: req.endpoint,
        method: req.method || 'GET'
      });
      var opts = { method: req.method || 'GET', credentials: 'include' };
      if (req.jsonBody) {
        opts.headers = { 'Content-Type': 'application/json' };
        opts.body = JSON.stringify(req.jsonBody);
      }
      ccFetchJson('api/' + req.endpoint, opts)
        .then(function(res) {
          nexusDebugLog('ConnectedCenter', 'API fetch response', {
            requestId: req.requestId,
            httpStatus: res.status,
            ok: res.ok,
            body: res.data
          }, res.ok && !(res.data && res.data.error) ? 'info' : 'error');
          if (!res.ok || (res.data && res.data.error)) {
            ccReplyEditor(e, {
              type: 'cc-api-fetch-result',
              requestId: req.requestId,
              error: (res.data && res.data.error) || 'Request failed',
              data: res.data
            });
            return;
          }
          ccReplyEditor(e, {
            type: 'cc-api-fetch-result',
            requestId: req.requestId,
            data: res.data
          });
        })
        .catch(function(err) {
          nexusDebugLog('ConnectedCenter', 'API fetch failed', {
            requestId: req.requestId,
            error: err && err.message ? err.message : String(err)
          }, 'error');
          ccReplyEditor(e, {
            type: 'cc-api-fetch-result',
            requestId: req.requestId,
            error: 'Network error'
          });
        });
      return;
    }

    if (e.data.type === 'cc-api-form') {
      var fReq = e.data;
      nexusDebugLog('ConnectedCenter', 'API form from editor', {
        requestId: fReq.requestId,
        endpoint: fReq.endpoint,
        fields: fReq.fields
      });
      var fForm = new FormData();
      var fields = fReq.fields || {};
      Object.keys(fields).forEach(function(k) { fForm.append(k, fields[k]); });
      ccFetchJson('api/' + fReq.endpoint, { method: 'POST', body: fForm, credentials: 'include' })
        .then(function(res) {
          nexusDebugLog('ConnectedCenter', 'API form response', {
            requestId: fReq.requestId,
            httpStatus: res.status,
            body: res.data
          }, res.ok && !(res.data && res.data.error) ? 'info' : 'error');
          if (!res.ok || (res.data && res.data.error)) {
            ccReplyEditor(e, {
              type: 'cc-api-form-result',
              requestId: fReq.requestId,
              error: (res.data && res.data.error) || 'Request failed',
              data: res.data
            });
            return;
          }
          ccReplyEditor(e, {
            type: 'cc-api-form-result',
            requestId: fReq.requestId,
            data: res.data
          });
        })
        .catch(function(err) {
          nexusDebugLog('ConnectedCenter', 'API form failed', {
            requestId: fReq.requestId,
            error: err && err.message ? err.message : String(err)
          }, 'error');
          ccReplyEditor(e, {
            type: 'cc-api-form-result',
            requestId: fReq.requestId,
            error: 'Network error'
          });
        });
      return;
    }
  }

  if (e.data.type === 'cc-game-published') {
    toast('Published to Community Games!');
    loadCommunityGames();
  }
  if (e.data.type === 'cc-game-deleted') {
    loadCommunityGames();
  }
});

function runHomeSearch() {
  var input = document.getElementById('homeSearchInput');
  if (!input) return;
  var q = (input.value || '').trim();
  if (!q) return;

  var gameId = parseInt(q.replace(/^#/, ''), 10);
  if (Number.isFinite(gameId) && gameId > 0 && String(gameId) === q.replace(/^#/, '')) {
    fetch('api/get_community_game.php?id=' + gameId)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.error || !d.game) { toast(d.error || 'Game not found'); return; }
        if (d.game.visibility === 'private' && !d.game.is_mine) {
          toast('This game is private');
          return;
        }
        playCommunityGame(d.game.id, d.game.title);
      })
      .catch(function(){ toast('Could not find game.'); });
    return;
  }

  toast('Enter a game ID number to open unlisted games.');
}

function renderShortcuts(shortcuts, servers) {
  var row = document.getElementById('shortcutsRow');
  var html = '';
  shortcuts.forEach(function(sc, i) {
    var l = (sc.label||'?').substring(0,2).toUpperCase();
    html += '<div class="qa-item" style="animation-delay:' + (i*0.04) + 's" onclick="activateShortcut(' + escAttr({type:sc.type, id:sc.target_id, label:sc.label}) + ')">'
      + '<div class="qa-icon" style="background:' + esc(sc.icon_color) + '" title="Unpin: right-click">'
      + esc(l)
      + '</div>'
      + '<span class="qa-label">' + esc(sc.label) + '</span>'
      + '</div>';
  });
  // Add button always last
  html += '<div class="qa-item" onclick="openAddShortcut()">'
    + '<div class="qa-add-icon">+</div>'
    + '<span class="qa-label">Add</span></div>';
  row.innerHTML = html;
  // Right-click to unpin
  row.querySelectorAll('.qa-item[onclick*="activateShortcut"]').forEach(function(el, i) {
    var sc = shortcuts[i];
    el.addEventListener('contextmenu', function(e) {
      e.preventDefault();
      openModal('<div class="modal" onclick="event.stopPropagation()">'
        + '<h2>Unpin "' + esc(sc.label) + '"?</h2>'
        + '<p class="sub">Remove this shortcut from Quick Access.</p>'
        + '<div class="modal-btns"><button class="btn-ghost" onclick="closeModal()">Cancel</button>'
        + '<button class="btn-prim" onclick="unpinShortcut(' + sc.id + ',' + escAttr(sc.label) + ')">Unpin</button></div></div>');
    });
  });
}

function activateShortcut(sc) {
  if (sc.type === 'server') {
    // Find server in rail and click
    var btn = document.getElementById('rail-' + sc.id);
    if (btn) btn.click();
    else toast('Server not found — you may have left it.');
  } else {
    // Open DM conversation
    selectConv(sc.id, sc.label);
  }
}

function unpinShortcut(id, label) {
  fetch('api/toggle_shortcut.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action: 'unpin', target_id: id, type: 'server' })
  }).then(function(){ toast(label + ' unpinned.'); selectHome(); });
}

function openAddShortcut() {
  fetch('api/get_home_data.php')
    .then(function(r){ return r.json(); })
    .then(function(d) {
      var servers = d.servers || [];
      var opts = servers.map(function(s, i) {
        return '<div class="usr" onclick="pinServer(' + s.id + ',' + escAttr(s.name) + ',' + escAttr(s.icon_color) + ')" style="animation-delay:' + (i*0.03) + 's">'
          + '<div class="usr-av" style="background:' + esc(s.icon_color) + ';border-radius:10px;">' + esc(s.name.substring(0,2).toUpperCase()) + '</div>'
          + '<div><div class="usr-name">' + esc(s.name) + '</div><div class="usr-tag">' + esc(s.role) + '</div></div>'
          + '</div>';
      }).join('');
      openModal('<div class="modal" onclick="event.stopPropagation()">'
        + '<h2>Pin a Shortcut</h2><p class="sub">Choose a server to pin on your home screen.</p>'
        + '<div style="max-height:280px;overflow-y:auto;margin-top:8px;">' + (opts||'<div style="color:var(--theme-text-secondary,#888);font-size:13px;padding:8px">No servers to pin.</div>') + '</div>'
        + '<div class="modal-btns"><button class="btn-ghost" onclick="closeModal()">Done</button></div></div>');
    });
}
function pinServer(sid, sname, color) {
  fetch('api/toggle_shortcut.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ action: 'pin', type: 'server', target_id: sid, label: sname, icon_color: color })
  }).then(function(){ closeModal(); toast(sname + ' pinned!'); selectHome(); });
}

function renderActivity(channelMsgs, dms) {
  var list = document.getElementById('activityList');
  var all = [];
  channelMsgs.forEach(function(m) { all.push({ ts: m.created_at, type: 'channel', data: m }); });
  dms.forEach(function(m)         { all.push({ ts: m.created_at, type: 'dm',      data: m }); });
  all.sort(function(a,b){ return new Date(b.ts) - new Date(a.ts); });
  all = all.slice(0, 10);

  if (!all.length) {
    list.innerHTML = '<div style="padding:10px 0;font-size:12.5px;color:var(--theme-text-secondary,#aaa);">No recent activity. Join a server or start a conversation!</div>';
    return;
  }
  list.innerHTML = all.map(function(item, i) {
    var m    = item.data;
    var name = m.display_name || m.username || 'User';
    var pic  = m.profile_picture || '';
    var where = item.type === 'channel'
      ? esc(m.server_name) + ' · #' + esc(m.channel_name)
      : 'Direct Message';
    var onclick = item.type === 'channel'
      ? 'selectServerChannel(' + m.server_id + ',' + escAttr(m.server_name) + ',' + m.channel_id + ',' + escAttr(m.channel_name) + ')'
      : 'selectConv(' + m.conversation_id + ',' + escAttr(name) + ')';
    return '<div class="act-row" onclick="' + onclick + '">'
      + avEl(pic, name, 38, 'act-av2')
      + '<div class="act-mid">'
      + '<div class="act-mid-who">' + esc(name) + '</div>'
      + '<div class="act-mid-where">' + where + '</div>'
      + '<div class="act-mid-text">' + esc((m.content||'').substring(0,90)) + '</div>'
      + '</div>'
      + '<div class="act-right"><div class="act-right-time">' + fmtTime(m.created_at) + '</div></div>'
      + '</div>';
  }).join('');
}

// Helper: go directly to a specific channel in a server
function selectServerChannel(sid, sname, cid, cname) {
  selectServer(sid, sname, 'member');
  setTimeout(function() { selectChannel(cid, cname); }, 400);
}

/* ── Hero Carousel ─────────────────────────────────────────── */
var _heroIdx = 0, _heroTimer = null, _heroLen = 1, _heroGames = [];
function heroGo(idx) {
  if (_heroLen < 1) _heroLen = 1;
  _heroIdx = ((idx % _heroLen) + _heroLen) % _heroLen;
  var slides = document.getElementById('hvSlides');
  if (slides) slides.style.transform = 'translateX(-' + (_heroIdx * 100) + '%)';
  document.querySelectorAll('.hv-dot').forEach(function(d, i) {
    d.classList.toggle('active', i === _heroIdx);
  });
}
function heroNext() {
  if (_heroGames.length < 2) return;
  heroGo(_heroIdx + 1);
}
function heroAction() {
  if (_heroGames.length && _heroGames[_heroIdx]) {
    playCommunityGame(_heroGames[_heroIdx].id, _heroGames[_heroIdx].title);
    return;
  }
  openNexusBlocksEditor();
}
function startHeroTimer() {
  _heroTimer && clearInterval(_heroTimer);
  _heroTimer = setInterval(heroNext, 4500);
}
function stopHeroTimer() {
  _heroTimer && clearInterval(_heroTimer);
  _heroTimer = null;
}

/* ── Member context menu ──────────────────────────────────── */
function memberCtx(e, uid, uname, urole) {
  document.querySelector('.ctx-menu') && document.querySelector('.ctx-menu').remove();
  var canPromote = st.userRole === 'owner';
  var canKick    = st.userRole === 'owner' || st.userRole === 'admin';
  var items = '';
  if (canPromote) {
    if (urole === 'member') {
      items += '<div class="ctx-item" onclick="doPromote(' + uid + ',' + escAttr(uname) + ',\'admin\')">⬆️ Promote to Admin</div>';
    } else if (urole === 'admin') {
      items += '<div class="ctx-item" onclick="doPromote(' + uid + ',' + escAttr(uname) + ',\'member\')">⬇️ Remove Admin</div>';
    }
  }
  if (canKick) {
    if (items) items += '<div class="ctx-sep"></div>';
    items += '<div class="ctx-item danger" onclick="doKick(' + uid + ',' + escAttr(uname) + ',false)">👢 Kick</div>';
    items += '<div class="ctx-item danger" onclick="doKick(' + uid + ',' + escAttr(uname) + ',true)">🔨 Ban</div>';
  }
  if (!items) return;
  var menu = document.createElement('div');
  menu.className = 'ctx-menu';
  menu.innerHTML = items;
  menu.style.left = Math.min(e.clientX, window.innerWidth - 200) + 'px';
  menu.style.top  = Math.min(e.clientY, window.innerHeight - 150) + 'px';
  document.body.appendChild(menu);
  function dismiss(ev) { if (!menu.contains(ev.target)) { menu.remove(); document.removeEventListener('click', dismiss); } }
  setTimeout(function(){ document.addEventListener('click', dismiss); }, 10);
}
function doPromote(uid, uname, newRole) {
  document.querySelector('.ctx-menu') && document.querySelector('.ctx-menu').remove();
  fetch('api/promote_member.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: st.serverId, user_id: uid, role: newRole })
  }).then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.error) toast(d.error);
      else { toast(uname + ' is now ' + (newRole === 'admin' ? 'an admin' : 'a member') + '.'); loadMembers(st.serverId); }
    });
}
function doKick(uid, uname, ban) {
  document.querySelector('.ctx-menu') && document.querySelector('.ctx-menu').remove();
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>' + (ban ? 'Ban' : 'Kick') + ' ' + esc(uname) + '?</h2>'
    + '<p class="sub">' + (ban ? 'They will be permanently banned from this server.' : 'They will be removed from the server but can be re-invited.') + '</p>'
    + '<div class="modal-btns"><button class="btn-ghost" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-prim btn-danger" onclick="confirmKick(' + uid + ',' + escAttr(uname) + ',' + ban + ')">'
    + (ban ? 'Ban' : 'Kick') + '</button></div></div>');
}
function confirmKick(uid, uname, ban) {
  fetch('api/kick_member.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: st.serverId, user_id: uid, action: ban ? 'ban' : 'kick' })
  }).then(function(r){ return r.json(); })
    .then(function(d) {
      closeModal();
      if (d.error) toast(d.error);
      else { toast(uname + ' was ' + (ban?'banned':'kicked') + '.'); loadMembers(st.serverId); }
    });
}

/* ── Modals ───────────────────────────────────────────────── */
function openModal(html) {
  document.getElementById('modalWrap').innerHTML =
    '<div class="overlay" id="mOverlay" onclick="closeMOverlay(event)">' + html + '</div>';
}
function closeModal() { document.getElementById('modalWrap').innerHTML = ''; }
function closeMOverlay(e) { if (e.target.id === 'mOverlay') closeModal(); }

/* Create Server */
var _selColor = '#2196f3';
function openCreateServer() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Create a Server</h2><p class="sub">Give your community a name and look.</p>'
    + '<label>Server Name</label><input id="nsName" type="text" placeholder="My Server" maxlength="50" />'
    + '<label>Description (optional)</label><textarea id="nsDesc" placeholder="What\'s this server about?" maxlength="300"></textarea>'
    + '<label>Color</label>'
    + '<div class="swatches">' + SWATCHES.map(function(c) {
        return '<div class="sw' + (c === _selColor ? ' sel' : '') + '" style="background:' + c + '" '
          + 'onclick="pickSrvColor(\'' + c + '\')" title="' + c + '"></div>';
      }).join('') + '</div>'
    + '<div class="modal-btns">'
    + '<button class="btn-ghost" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-prim" onclick="doCreateServer()">Create</button>'
    + '</div></div>');
}
function pickSrvColor(c) {
  _selColor = c;
  document.querySelectorAll('.sw').forEach(function(s) {
    s.classList.toggle('sel', s.style.background === c || s.title === c);
  });
}
function doCreateServer() {
  var n = (document.getElementById('nsName').value || '').trim();
  var d = (document.getElementById('nsDesc').value || '').trim();
  if (n.length < 2) { toast('Name must be at least 2 characters.'); return; }
  fetch('api/create_server.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ name: n, description: d, icon_color: _selColor })
  }).then(function(r){ return r.json(); })
    .then(function(d) { if (d.error) toast(d.error); else { closeModal(); toast('Server created!'); reloadServers(); } })
    .catch(function(){ toast('Failed to create server.'); });
}

/* Add channel */
function openAddChannel() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Add Channel</h2><p class="sub">Channels are where conversations happen.</p>'
    + '<label>Channel Name</label><input id="chName" type="text" placeholder="new-channel" maxlength="50" />'
    + '<label>Type</label>'
    + '<select id="chType"><option value="text">Text Channel</option><option value="announcement">Announcement</option></select>'
    + '<div class="modal-btns">'
    + '<button class="btn-ghost" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-prim" onclick="doAddChannel()">Create</button>'
    + '</div></div>');
}
function doAddChannel() {
  var n = (document.getElementById('chName').value || '').trim().toLowerCase().replace(/[^a-z0-9\-]/g,'-');
  if (!n) { toast('Enter a channel name.'); return; }
  fetch('api/create_channel.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: st.serverId, name: n, type: document.getElementById('chType').value })
  }).then(function(r){ return r.json(); })
    .then(function(d) { if (d.error) toast(d.error); else { closeModal(); toast('#' + n + ' created!'); selectServer(st.serverId, st.serverName, st.userRole); } })
    .catch(function(){ toast('Failed to create channel.'); });
}

/* Invite people */
function openInvitePeople() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Invite People</h2><p class="sub">Search for users to add to <strong>' + esc(st.serverName) + '</strong>.</p>'
    + '<label>Search Users</label>'
    + '<input id="invS" type="text" placeholder="Type a username…" autocomplete="off" oninput="doInvSearch()" />'
    + '<div id="invRes" style="margin-top:10px;max-height:220px;overflow-y:auto;"></div>'
    + '<div class="modal-btns"><button class="btn-ghost" onclick="closeModal()">Done</button></div>'
    + '</div>');
}
var _invT = null;
function doInvSearch() {
  clearTimeout(_invT);
  _invT = setTimeout(function() {
    var inp = document.getElementById('invS');
    if (!inp) return;
    var q = inp.value.trim();
    if (!q) { var r = document.getElementById('invRes'); if(r) r.innerHTML = ''; return; }
    fetch('api/search_users.php?q=' + encodeURIComponent(q) + '&exclude_server=' + st.serverId)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        var res = document.getElementById('invRes'); if (!res) return;
        var users = d.users || [];
        if (!users.length) {
          res.innerHTML = '<div style="color:var(--theme-text-secondary,#888);font-size:13px;padding:8px 10px">No users found.</div>';
          return;
        }
        res.innerHTML = users.map(function(u) {
          var n = u.display_name || u.username;
          return '<div class="usr" data-invite-uid="' + u.id + '">'
            + avEl(u.profile_picture, n, 34, 'usr-av')
            + '<div><div class="usr-name">' + esc(n) + '</div><div class="usr-tag">@' + esc(u.username) + '</div></div>'
            + '<button class="btn-prim inv-add-btn" style="margin-left:auto;padding:5px 14px;font-size:12px;min-width:88px;" '
            + 'onclick="doAddMember(' + u.id + ',' + escAttr(n) + ', this)">Add</button>'
            + '</div>';
        }).join('');
      }).catch(function(){});
  }, 300);
}
function doAddMember(uid, uname, btnEl) {
  fetch('api/invite_to_server.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: st.serverId, user_id: uid })
  }).then(function(r){ return r.json(); })
    .then(function(d) {
      if (d.error) { toast(d.error); return; }
      if (btnEl) {
        btnEl.textContent = 'Invite Sent!';
        btnEl.disabled = true;
        btnEl.style.opacity = '0.85';
        btnEl.style.cursor = 'default';
      } else {
        toast('Invite sent to ' + uname + '!');
      }
    })
    .catch(function(){ toast('Could not send invite.'); });
}

/* Leave / Delete server */
function confirmLeave() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Leave ' + esc(st.serverName) + '?</h2>'
    + '<p class="sub">You cannot rejoin unless re-invited by a member.</p>'
    + '<div class="modal-btns"><button class="btn-ghost" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-prim btn-danger" onclick="doLeave()">Leave</button></div></div>');
}
function doLeave() {
  fetch('api/leave_server.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: st.serverId })
  }).then(function(r){ return r.json(); })
    .then(function(d) { if (d.error) toast(d.error); else { closeModal(); toast('Left server.'); reloadServers(); } });
}
function confirmDeleteServer() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>Delete ' + esc(st.serverName) + '?</h2>'
    + '<p class="sub" style="color:#e53935">This is permanent. All channels and messages will be deleted.</p>'
    + '<div class="modal-btns"><button class="btn-ghost" onclick="closeModal()">Cancel</button>'
    + '<button class="btn-prim btn-danger" onclick="doDelete()">Delete Forever</button></div></div>');
}
function doDelete() {
  fetch('api/delete_server.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ server_id: st.serverId })
  }).then(function(r){ return r.json(); })
    .then(function(d) { if (d.error) toast(d.error); else { closeModal(); toast('Server deleted.'); reloadServers(); } });
}

/* New DM */
function openNewDM() {
  openModal('<div class="modal" onclick="event.stopPropagation()">'
    + '<h2>New Message</h2><p class="sub">Start a direct message with another user.</p>'
    + '<label>Find User</label>'
    + '<input id="dmS" type="text" placeholder="Type a username…" autocomplete="off" oninput="doDMSearch()" />'
    + '<div id="dmRes" style="margin-top:10px;max-height:220px;overflow-y:auto;"></div>'
    + '<div class="modal-btns"><button class="btn-ghost" onclick="closeModal()">Cancel</button></div></div>');
}
var _dmT = null;
function doDMSearch() {
  clearTimeout(_dmT);
  _dmT = setTimeout(function() {
    var inp = document.getElementById('dmS'); if (!inp) return;
    var q = inp.value.trim();
    if (!q) { var r = document.getElementById('dmRes'); if(r) r.innerHTML = ''; return; }
    fetch('api/search_users.php?q=' + encodeURIComponent(q))
      .then(function(r){ return r.json(); })
      .then(function(d) {
        var res = document.getElementById('dmRes'); if (!res) return;
        var users = d.users || [];
        if (!users.length) {
          res.innerHTML = '<div style="color:var(--theme-text-secondary,#888);font-size:13px;padding:8px 10px">No users found.</div>';
          return;
        }
        res.innerHTML = users.map(function(u) {
          var n = u.display_name || u.username;
          return '<div class="usr" onclick="doStartDM(' + u.id + ',' + escAttr(n) + ')">'
            + avEl(u.profile_picture, n, 34, 'usr-av')
            + '<div><div class="usr-name">' + esc(n) + '</div><div class="usr-tag">@' + esc(u.username) + '</div></div>'
            + '</div>';
        }).join('');
      }).catch(function(){});
  }, 300);
}
function doStartDM(uid, uname) {
  fetch('api/create_conversation.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ type: 'direct', user_id: uid })
  }).then(function(r){ return r.json(); })
    .then(function(d) {
      closeModal();
      if (d.error) { toast(d.error); return; }
      fetch('api/get_conversations.php')
        .then(function(r){ return r.json(); })
        .then(function(r2) {
          dmConvs = r2.conversations || [];
          selectConv(d.conv_id, uname || 'Direct Message');
        });
    }).catch(function(){ toast('Could not start conversation.'); });
}

/* Reload server list in rail */
function reloadServers() {
  fetch('api/get_servers.php')
    .then(function(r){ return r.json(); })
    .then(function(d) {
      var servers = d.servers || [];
      var rail = document.getElementById('rail');
      rail.querySelectorAll('.rail-btn:not(.rail-dm):not(.rail-add):not(.rail-admin)').forEach(function(b){ b.remove(); });
      var sep = rail.querySelectorAll('.rail-sep')[1];
      servers.forEach(function(s, idx) {
        var btn = document.createElement('button');
        btn.className = 'rail-btn'; btn.id = 'rail-' + s.id;
        btn.title = s.name; btn.style.background = s.icon_color;
        btn.textContent = s.name.substring(0,2).toUpperCase();
        btn.onclick = function() { selectServer(s.id, s.name, s.role); };
        btn.style.animation = 'railBounce 0.4s cubic-bezier(0.22,1,0.36,1) ' + (idx * 60) + 'ms both';
        rail.insertBefore(btn, sep);
      });
      selectDMs();
    });
}

/* ── Input helpers ────────────────────────────────────────── */
var inp = document.getElementById('msgInput');
inp.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 160) + 'px';
});
inp.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
});

/* Boot */
if (window.self !== window.top) {
  document.documentElement.classList.add('nexus-embedded');
}
selectHome();
</script>
</body>
</html>
