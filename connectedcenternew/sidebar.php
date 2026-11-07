<?php
require_once __DIR__ . '/auth.php';
$loggedIn = !empty($_SESSION['cc_user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Connected Messages</title>
<script src="../theme-sync.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Avenir Next', 'Avenir', 'Inter', sans-serif;
    background: var(--theme-bg, #f0f4f9);
    color: var(--theme-text-primary, #1a1a2e);
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  .topbar {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.08));
    background: var(--theme-glass-tint, rgba(255,255,255,0.55));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    flex-shrink: 0;
  }
  .topbar h1 {
    flex: 1;
    font-size: 16px;
    font-weight: 600;
  }
  .topbar-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 13px;
    color: var(--theme-accent, #2196f3);
    padding: 6px 10px;
    border-radius: 8px;
    font-family: inherit;
  }
  .topbar-btn:hover { background: rgba(0,0,0,0.05); }
  .back-btn {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 18px;
    color: var(--theme-text-secondary, #666);
    padding: 4px 8px 4px 0;
    border-radius: 8px;
  }
  .back-btn.visible { display: block; }
  .panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: csPanelIn 0.38s cubic-bezier(0.22, 1, 0.36, 1) both;
  }
  .panel.hidden { display: none; }
  .sec-label {
    padding: 10px 8px 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--theme-text-secondary, #888);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .sec-label button {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    color: var(--theme-accent, #2196f3);
    line-height: 1;
  }
  .item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    margin: 2px 0;
    border-radius: 12px;
    cursor: pointer;
    transition: background 0.18s cubic-bezier(0.22, 1, 0.36, 1), transform 0.18s cubic-bezier(0.22, 1, 0.36, 1);
  }
  .item:hover { background: rgba(0,0,0,0.04); transform: translateX(2px); }
  .item:active { transform: scale(0.98); }
  .item.active { background: rgba(33,150,243,0.12); }
  .scroll {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
    scrollbar-width: thin;
  }
  @keyframes csPanelIn {
    from { opacity: 0; transform: translateX(12px); }
    to { opacity: 1; transform: none; }
  }
  .scroll .item {
    animation: csItemIn 0.32s cubic-bezier(0.22, 1, 0.36, 1) both;
  }
  @keyframes csItemIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: none; }
  }
  #chatView { animation: csChatIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) both; }
  @keyframes csChatIn {
    from { opacity: 0; transform: translateX(18px); }
    to { opacity: 1; transform: none; }
  }
  .msg-group {
    animation: csMsgIn 0.28s cubic-bezier(0.22, 1, 0.36, 1) both;
  }
  @keyframes csMsgIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: none; }
  }
  .av {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    color: #fff;
    flex-shrink: 0;
    overflow: hidden;
  }
  .av img { width: 100%; height: 100%; object-fit: cover; }
  .item-body { flex: 1; min-width: 0; }
  .item-title {
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .item-sub {
    font-size: 12px;
    color: var(--theme-text-secondary, #888);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
  }
  .ch-item { padding-left: 28px; }
  .ch-hash { color: var(--theme-text-secondary, #aaa); margin-right: 2px; }
  .empty {
    padding: 32px 20px;
    text-align: center;
    color: var(--theme-text-secondary, #888);
    font-size: 13px;
    line-height: 1.5;
  }
  .empty a { color: var(--theme-accent, #2196f3); text-decoration: none; }
  #msgWrap {
    flex: 1;
    overflow-y: auto;
    padding: 12px 0;
    scrollbar-width: thin;
  }
  .msg-group {
    display: flex;
    gap: 10px;
    padding: 4px 14px;
  }
  .msg-group:hover { background: rgba(0,0,0,0.025); }
  .msg-av {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    color: #fff;
    flex-shrink: 0;
    overflow: hidden;
  }
  .msg-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .msg-body { flex: 1; min-width: 0; }
  .msg-meta { display: flex; align-items: baseline; gap: 8px; margin-bottom: 2px; }
  .msg-author { font-size: 13px; font-weight: 600; }
  .msg-time { font-size: 10px; color: var(--theme-text-secondary, #aaa); }
  .msg-text { font-size: 13px; line-height: 1.5; word-break: break-word; white-space: pre-wrap; }
  .msg-cont { padding: 2px 14px 2px 56px; }
  .welcome-msg {
    padding: 20px 16px 12px;
    border-bottom: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    margin-bottom: 8px;
  }
  .welcome-msg h3 { font-size: 18px; margin-bottom: 4px; }
  .welcome-msg p { font-size: 12px; color: var(--theme-text-secondary, #888); }
  #inputArea {
    padding: 10px 12px 14px;
    border-top: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    background: var(--theme-glass-tint, rgba(255,255,255,0.45));
    flex-shrink: 0;
  }
  #inputWrap {
    display: flex;
    align-items: flex-end;
    gap: 8px;
    background: var(--theme-glass-tint, rgba(255,255,255,0.7));
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.1));
    border-radius: 14px;
    padding: 8px 10px;
  }
  #msgInput {
    flex: 1;
    border: none;
    outline: none;
    background: none;
    font-family: inherit;
    font-size: 14px;
    color: var(--theme-text-primary, #1a1a2e);
    resize: none;
    max-height: 120px;
    line-height: 1.45;
  }
  #sendBtn {
    background: var(--theme-accent, #2196f3);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 14px;
    cursor: pointer;
    flex-shrink: 0;
  }
  #sendBtn:disabled { opacity: 0.5; cursor: default; }
  .auth-box {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px 24px;
    text-align: center;
    gap: 12px;
  }
  .auth-box h2 { font-size: 20px; }
  .auth-box p { font-size: 14px; color: var(--theme-text-secondary, #888); line-height: 1.5; max-width: 280px; }
  .auth-btn {
    margin-top: 8px;
    background: var(--theme-accent, #2196f3);
    color: #fff;
    border: none;
    border-radius: 12px;
    padding: 12px 22px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
  }
  .footer-link {
    padding: 10px 16px 14px;
    text-align: center;
    font-size: 12px;
    border-top: 1px solid var(--theme-glass-border, rgba(0,0,0,0.06));
    flex-shrink: 0;
  }
  .footer-link a { color: var(--theme-accent, #2196f3); text-decoration: none; font-weight: 600; }
  #toast {
    position: fixed;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%) translateY(8px);
    background: var(--theme-glass-tint, rgba(255,255,255,0.92));
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.1));
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 13px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s, transform 0.2s;
    z-index: 99;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  }
  #toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 50;
    padding: 16px;
  }
  .modal-overlay.open { display: flex; }
  .modal {
    background: var(--theme-bg, #f5f7fa);
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.1));
    border-radius: 16px;
    padding: 20px;
    width: 100%;
    max-width: 360px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
  }
  .modal h2 { font-size: 17px; margin-bottom: 6px; }
  .modal p { font-size: 13px; color: var(--theme-text-secondary, #888); margin-bottom: 12px; }
  .modal input {
    width: 100%;
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.12));
    border-radius: 10px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    background: var(--theme-bg, #fff);
  }
  .modal input:focus { border-color: var(--theme-accent, #2196f3); }
  .modal-results { max-height: 200px; overflow-y: auto; margin-top: 10px; }
  .modal-btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
  .btn-ghost {
    background: none;
    border: none;
    cursor: pointer;
    font-family: inherit;
    font-size: 13px;
    padding: 8px 12px;
    border-radius: 8px;
    color: var(--theme-text-secondary, #666);
  }
</style>
</head>
<body>

<div class="topbar">
  <button type="button" class="back-btn" id="backBtn" onclick="showList()" title="Back">←</button>
  <h1 id="topTitle">Messages</h1>
  <button type="button" class="topbar-btn" id="newDmBtn" onclick="openNewDM()">New DM</button>
</div>

<div id="authView" class="panel<?= $loggedIn ? ' hidden' : '' ?>">
  <div class="auth-box">
    <div style="font-size:48px;opacity:0.35;">💬</div>
    <h2>Sign in to Connected</h2>
    <p>Log in to your Connected account to check messages, send DMs, and chat in server channels.</p>
    <button type="button" class="auth-btn" onclick="requestLogin()">Sign In</button>
  </div>
</div>

<div id="listView" class="panel<?= $loggedIn ? '' : ' hidden' ?>">
  <div class="scroll" id="listScroll"></div>
  <div class="footer-link"><a href="#" onclick="openFullConnected(); return false;">Open Connected Center →</a></div>
</div>

<div id="chatView" class="panel hidden">
  <div id="msgWrap"></div>
  <div id="inputArea">
    <div id="inputWrap">
      <textarea id="msgInput" rows="1" placeholder="Type a message…" maxlength="2000"></textarea>
      <button type="button" id="sendBtn" onclick="sendMsg()">Send</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="dmModal" onclick="if(event.target===this)closeNewDM()">
  <div class="modal" onclick="event.stopPropagation()">
    <h2>New Message</h2>
    <p>Find a user to start a direct message.</p>
    <input type="text" id="dmSearch" placeholder="Search username…" autocomplete="off" oninput="doDmSearch()" />
    <div class="modal-results" id="dmResults"></div>
    <div class="modal-btns"><button type="button" class="btn-ghost" onclick="closeNewDM()">Cancel</button></div>
  </div>
</div>

<div id="toast"></div>

<script>
var ME = null;
var st = {
  mode: null,
  convId: null,
  channelId: null,
  channelName: '',
  chatTitle: '',
  lastId: 0,
  poll: null,
  polling: false,
  expanded: {},
  channelCache: {}
};
var COLORS = ['#2196f3','#4caf50','#e53935','#ff9800','#9c27b0','#00bcd4','#f06292','#795548'];

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function avColor(name) {
  var h = 0;
  for (var i = 0; i < (name || '').length; i++) h = (name.charCodeAt(i) + ((h << 5) - h)) | 0;
  return COLORS[Math.abs(h) % COLORS.length];
}
function avEl(pic, name, size, cls) {
  var n = name || '?';
  if (pic) return '<div class="' + cls + '" style="width:' + size + 'px;height:' + size + 'px"><img src="' + esc(pic) + '" alt=""></div>';
  return '<div class="' + cls + '" style="width:' + size + 'px;height:' + size + 'px;background:' + avColor(n) + '">' + esc(n.charAt(0).toUpperCase()) + '</div>';
}
function fmtTime(ts) {
  if (!ts) return '';
  var d = new Date(String(ts).replace(' ', 'T'));
  if (isNaN(d)) return '';
  return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}
function toast(msg) {
  var el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toast._t);
  toast._t = setTimeout(function() { el.classList.remove('show'); }, 2600);
}
function api(url, opts) {
  opts = opts || {};
  opts.credentials = 'include';
  if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
    opts.headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
    opts.body = JSON.stringify(opts.body);
  }
  return fetch(url, opts).then(function(r) { return r.json(); });
}
function stopPoll() {
  if (st.poll) { clearInterval(st.poll); st.poll = null; }
  st.polling = false;
}
function requestLogin() {
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'nexus-open-connected-auth', mode: 'login' }, '*');
      return;
    }
  } catch (e) {}
  window.location.href = 'index.php';
}
function openFullConnected() {
  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'nexus-open-connected' }, '*');
      return;
    }
  } catch (e) {}
  window.location.href = 'dashboard.php';
}

function showAuth() {
  document.getElementById('authView').classList.remove('hidden');
  document.getElementById('listView').classList.add('hidden');
  document.getElementById('chatView').classList.add('hidden');
  document.getElementById('backBtn').classList.remove('visible');
  document.getElementById('topTitle').textContent = 'Messages';
  document.getElementById('newDmBtn').style.display = 'none';
}
function showList() {
  stopPoll();
  st.mode = null;
  st.convId = null;
  st.channelId = null;
  document.getElementById('authView').classList.add('hidden');
  document.getElementById('listView').classList.remove('hidden');
  document.getElementById('chatView').classList.add('hidden');
  document.getElementById('backBtn').classList.remove('visible');
  document.getElementById('topTitle').textContent = 'Messages';
  document.getElementById('newDmBtn').style.display = '';
  loadList();
}
function showChat(title) {
  document.getElementById('authView').classList.add('hidden');
  document.getElementById('listView').classList.add('hidden');
  document.getElementById('chatView').classList.remove('hidden');
  document.getElementById('backBtn').classList.add('visible');
  document.getElementById('topTitle').textContent = title || 'Chat';
  document.getElementById('newDmBtn').style.display = 'none';
}

function loadList() {
  var scroll = document.getElementById('listScroll');
  scroll.innerHTML = '<div class="empty">Loading…</div>';
  Promise.all([
    api('api/get_conversations.php'),
    api('api/get_servers.php')
  ]).then(function(results) {
    var convs = (results[0].conversations || []);
    var servers = (results[1].servers || []);
    var html = '';

    html += '<div class="sec-label">Direct Messages <button type="button" onclick="openNewDM()">+</button></div>';
    if (!convs.length) {
      html += '<div class="empty">No DMs yet. <a href="#" onclick="openNewDM();return false">Start one →</a></div>';
    } else {
      convs.forEach(function(c, idx) {
        var name = c.participant_names || c.name || 'Conversation';
        var preview = c.last_message ? esc(c.last_message).substring(0, 60) : 'No messages yet';
        html += '<div class="item" style="animation-delay:' + (idx * 28) + 'ms" data-conv-id="' + c.id + '" data-name="' + esc(name) + '" onclick="openDMFromEl(this)">'
          + avEl('', name, 36, 'av')
          + '<div class="item-body"><div class="item-title">' + esc(name) + '</div>'
          + '<div class="item-sub">' + preview + '</div></div></div>';
      });
    }

    html += '<div class="sec-label" style="margin-top:8px;">Servers</div>';
    if (!servers.length) {
      html += '<div class="empty">You haven\'t joined any servers yet.</div>';
    } else {
      servers.forEach(function(s) {
        var color = s.icon_color || avColor(s.name);
        var expanded = !!st.expanded[s.id];
        html += '<div class="item" onclick="toggleServer(' + s.id + ')">'
          + '<div class="av" style="background:' + esc(color) + '">' + esc((s.name || 'S').charAt(0).toUpperCase()) + '</div>'
          + '<div class="item-body"><div class="item-title">' + esc(s.name) + '</div>'
          + '<div class="item-sub">' + (expanded ? 'Hide channels' : 'Show channels') + '</div></div></div>';
        if (expanded && st.channelCache[s.id]) {
          st.channelCache[s.id].forEach(function(ch) {
            html += '<div class="item ch-item" data-channel-id="' + ch.id + '" data-channel="' + esc(ch.name) + '" data-server="' + esc(s.name) + '" onclick="openChannelFromEl(this, event)">'
              + '<div class="item-body"><div class="item-title"><span class="ch-hash">#</span>' + esc(ch.name) + '</div></div></div>';
          });
        } else if (expanded) {
          html += '<div class="empty" style="padding:8px 16px;">Loading channels…</div>';
        }
      });
    }
    scroll.innerHTML = html;

    servers.forEach(function(s) {
      if (st.expanded[s.id] && !st.channelCache[s.id]) {
        api('api/get_channels.php?server_id=' + s.id).then(function(d) {
          st.channelCache[s.id] = d.channels || [];
          loadList();
        }).catch(function() {
          st.channelCache[s.id] = [];
          loadList();
        });
      }
    });
  }).catch(function() {
    scroll.innerHTML = '<div class="empty">Could not load messages.</div>';
  });
}

function toggleServer(sid) {
  st.expanded[sid] = !st.expanded[sid];
  loadList();
}

function openDMFromEl(el) {
  openDM(parseInt(el.dataset.convId, 10), el.dataset.name || 'Conversation');
}
function openChannelFromEl(el, e) {
  if (e) e.stopPropagation();
  openChannel(parseInt(el.dataset.channelId, 10), el.dataset.channel || 'channel', el.dataset.server || '');
}

function markDmRead(convId) {
  api('api/mark_dm_read.php', { method: 'POST', body: convId ? { conv_id: convId } : {} })
    .then(function() {
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage({ type: 'cc-dm-read-changed' }, '*');
        }
      } catch (e) {}
    }).catch(function() {});
}

function openDM(convId, name) {
  st.mode = 'dm';
  st.convId = convId;
  st.channelId = null;
  st.lastId = 0;
  stopPoll();
  showChat(name);
  markDmRead(convId);
  document.getElementById('msgInput').placeholder = 'Message ' + name;
  loadMessages(false);
  st.poll = setInterval(function() { pollMessages(); }, 2500);
}

function openChannel(channelId, channelName, serverName) {
  st.mode = 'channel';
  st.channelId = channelId;
  st.convId = null;
  st.channelName = channelName;
  st.lastId = 0;
  stopPoll();
  showChat('#' + channelName);
  document.getElementById('msgInput').placeholder = 'Message #' + channelName;
  loadMessages(false);
  st.poll = setInterval(function() { pollMessages(); }, 2000);
}

function loadMessages(isPoll) {
  var url;
  if (st.mode === 'dm') url = 'api/get_dm_messages.php?conv_id=' + st.convId + '&limit=50';
  else if (st.mode === 'channel') url = 'api/get_channel_messages.php?channel_id=' + st.channelId + '&limit=50';
  else return;

  api(url).then(function(d) {
    if (d.error) { toast(d.error); return; }
    var msgs = d.messages || [];
    if (msgs.length) st.lastId = parseInt(msgs[msgs.length - 1].id, 10) || st.lastId;
    renderMessages(msgs);
  }).catch(function() { if (!isPoll) toast('Could not load messages.'); });
}

function pollMessages() {
  if (st.polling) return;
  var url;
  if (st.mode === 'dm' && st.convId) url = 'api/get_dm_messages.php?conv_id=' + st.convId + '&after_id=' + st.lastId;
  else if (st.mode === 'channel' && st.channelId) url = 'api/get_channel_messages.php?channel_id=' + st.channelId + '&after_id=' + st.lastId;
  else return;

  st.polling = true;
  api(url).then(function(d) {
    st.polling = false;
    var msgs = d.messages || [];
    if (msgs.length) {
      appendMessages(msgs);
      st.lastId = parseInt(msgs[msgs.length - 1].id, 10) || st.lastId;
    }
  }).catch(function() { st.polling = false; });
}

function buildMsg(m, prevA, prevT) {
  var name = m.display_name || m.sender_display_name || m.username || m.sender_username || 'User';
  var uname = m.username || m.sender_username || '';
  var pic = m.profile_picture || m.sender_pic || '';
  var cnt = esc(m.content || '');
  var ts = m.created_at;
  var same = prevA === uname && prevT &&
    (new Date(String(ts).replace(' ', 'T')) - new Date(String(prevT).replace(' ', 'T'))) < 300000;
  if (same) return '<div class="msg-cont"><div class="msg-text">' + cnt + '</div></div>';
  return '<div class="msg-group">' + avEl(pic, name, 32, 'msg-av')
    + '<div class="msg-body"><div class="msg-meta"><span class="msg-author">' + esc(name) + '</span>'
    + '<span class="msg-time">' + fmtTime(ts) + '</span></div><div class="msg-text">' + cnt + '</div></div></div>';
}

function renderMessages(msgs) {
  var wrap = document.getElementById('msgWrap');
  var label = st.mode === 'channel' ? ('<span class="ch-hash">#</span>' + esc(st.channelName)) : 'Direct Message';
  var html = '<div class="welcome-msg"><h3>' + label + '</h3><p>Beginning of this conversation.</p></div>';
  var prevA = null, prevT = null;
  msgs.forEach(function(m) {
    html += buildMsg(m, prevA, prevT);
    prevA = m.username || m.sender_username;
    prevT = m.created_at;
  });
  wrap.innerHTML = html;
  wrap.scrollTop = wrap.scrollHeight;
}

function appendMessages(msgs) {
  var wrap = document.getElementById('msgWrap');
  var atBot = wrap.scrollHeight - wrap.clientHeight - wrap.scrollTop < 80;
  var prevA = null, prevT = null;
  msgs.forEach(function(m) {
    var div = document.createElement('div');
    div.innerHTML = buildMsg(m, prevA, prevT);
    while (div.firstChild) wrap.appendChild(div.firstChild);
    prevA = m.username || m.sender_username;
    prevT = m.created_at;
  });
  if (atBot) wrap.scrollTop = wrap.scrollHeight;
}

function sendMsg() {
  var input = document.getElementById('msgInput');
  var content = input.value.trim();
  if (!content) return;
  var btn = document.getElementById('sendBtn');
  btn.disabled = true;
  var url, body;
  if (st.mode === 'channel') {
    url = 'api/send_channel_message.php';
    body = { channel_id: st.channelId, content: content };
  } else if (st.mode === 'dm') {
    url = 'api/send_dm.php';
    body = { conv_id: st.convId, content: content };
  } else {
    btn.disabled = false;
    return;
  }
  input.value = '';
  input.style.height = 'auto';
  api(url, { method: 'POST', body: body }).then(function(d) {
    btn.disabled = false;
    if (d.error) { toast(d.error); return; }
    st.polling = false;
  }).catch(function() {
    btn.disabled = false;
    toast('Failed to send.');
  });
}

function openNewDM() {
  document.getElementById('dmModal').classList.add('open');
  document.getElementById('dmSearch').value = '';
  document.getElementById('dmResults').innerHTML = '';
  setTimeout(function() { document.getElementById('dmSearch').focus(); }, 50);
}
function closeNewDM() {
  document.getElementById('dmModal').classList.remove('open');
}
var _dmTimer = null;
function doDmSearch() {
  clearTimeout(_dmTimer);
  _dmTimer = setTimeout(function() {
    var q = document.getElementById('dmSearch').value.trim();
    var res = document.getElementById('dmResults');
    if (!q) { res.innerHTML = ''; return; }
    api('api/search_users.php?q=' + encodeURIComponent(q)).then(function(d) {
      var users = d.users || [];
      if (!users.length) {
        res.innerHTML = '<div class="empty" style="padding:12px;">No users found.</div>';
        return;
      }
      res.innerHTML = users.map(function(u) {
        var n = u.display_name || u.username;
        return '<div class="item" data-user-id="' + u.id + '" data-name="' + esc(n) + '" onclick="startDMFromEl(this)">'
          + avEl(u.profile_picture, n, 36, 'av')
          + '<div class="item-body"><div class="item-title">' + esc(n) + '</div>'
          + '<div class="item-sub">@' + esc(u.username) + '</div></div></div>';
      }).join('');
    });
  }, 280);
}
function startDMFromEl(el) {
  startDM(parseInt(el.dataset.userId, 10), el.dataset.name || 'User');
}
function startDM(uid, name) {
  api('api/create_conversation.php', { method: 'POST', body: { user_id: uid } }).then(function(d) {
    if (d.error) { toast(d.error); return; }
    closeNewDM();
    openDM(d.conv_id, name);
  }).catch(function() { toast('Could not start conversation.'); });
}

document.getElementById('msgInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
});
document.getElementById('msgInput').addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

function boot() {
  api('api/check_status.php').then(function(d) {
    if (d.status === 'active' && d.user) {
      ME = d.user;
      showList();
    } else {
      showAuth();
    }
  }).catch(function() { showAuth(); });
}

window.addEventListener('message', function(e) {
  if (e.data && e.data.type === 'cc-sidebar-refresh') boot();
});

boot();
</script>
</body>
</html>
