<?php
require_once __DIR__ . '/auth.php';
$me = ccAdminRequired();
$pdo = ccDB();

$msg = '';
$tab = $_GET['tab'] ?? 'users';

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'ban_user') {
        $uid = intval($_POST['user_id']);
        if ($uid !== $me['id']) {
            $pdo->prepare("UPDATE users SET status='banned' WHERE id=?")->execute([$uid]);
            $msg = 'User banned.';
        }
    } elseif ($action === 'unban_user') {
        $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([intval($_POST['user_id'])]);
        $msg = 'User unbanned.';
    } elseif ($action === 'delete_user') {
        $uid = intval($_POST['user_id']);
        if ($uid !== $me['id']) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            $msg = 'User deleted.';
        }
    } elseif ($action === 'promote_admin') {
        $pdo->prepare("UPDATE users SET role='admin' WHERE id=?")->execute([intval($_POST['user_id'])]);
        $msg = 'User promoted to admin.';
    } elseif ($action === 'demote_admin') {
        $uid = intval($_POST['user_id']);
        if ($uid !== $me['id']) {
            $pdo->prepare("UPDATE users SET role='user' WHERE id=?")->execute([$uid]);
            $msg = 'Admin demoted.';
        }
    } elseif ($action === 'delete_server') {
        $sid = intval($_POST['server_id']);
        $pdo->prepare("DELETE FROM channel_messages WHERE channel_id IN (SELECT id FROM channels WHERE server_id=?)")->execute([$sid]);
        $pdo->prepare("DELETE FROM channels WHERE server_id=?")->execute([$sid]);
        $pdo->prepare("DELETE FROM server_members WHERE server_id=?")->execute([$sid]);
        $pdo->prepare("DELETE FROM servers WHERE id=?")->execute([$sid]);
        $msg = 'Server deleted.';
    } elseif ($action === 'resolve_report') {
        $pdo->prepare("UPDATE reports SET status='resolved' WHERE id=?")->execute([intval($_POST['report_id'])]);
        $msg = 'Report marked resolved.';
    }
    header('Location: admin.php?tab='.$tab.'&msg='.urlencode($msg));
    exit;
}

if (isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// ── Load data for current tab ────────────────────────────────────────────────
$users   = [];
$servers = [];
$reports = [];
$stats   = [];

if ($tab === 'users' || $tab === 'overview') {
    $users = $pdo->query("SELECT id, username, display_name, email, role, status, created_at, last_seen_at FROM users ORDER BY created_at DESC")->fetchAll();
}
if ($tab === 'servers' || $tab === 'overview') {
    $servers = $pdo->query("
        SELECT s.*, u.username as owner_name,
               (SELECT COUNT(*) FROM server_members WHERE server_id=s.id) as members,
               (SELECT COUNT(*) FROM channels WHERE server_id=s.id) as channels
        FROM servers s JOIN users u ON s.owner_id=u.id
        ORDER BY s.created_at DESC
    ")->fetchAll();
}
if ($tab === 'reports') {
    $reports = $pdo->query("
        SELECT r.*, u.username as reporter_name
        FROM reports r JOIN users u ON r.reporter_id=u.id
        ORDER BY r.created_at DESC
        LIMIT 100
    ")->fetchAll();
}
if ($tab === 'overview') {
    $stats = [
        'users'    => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'servers'  => $pdo->query("SELECT COUNT(*) FROM servers")->fetchColumn(),
        'messages' => $pdo->query("SELECT COUNT(*) FROM channel_messages")->fetchColumn(),
        'dms'      => $pdo->query("SELECT COUNT(*) FROM direct_messages")->fetchColumn(),
        'reports'  => $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn(),
    ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin — Connected Center</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', system-ui, sans-serif; background: #1e1f22; color: #dcddde; min-height: 100vh; }
  a { color: #5865f2; text-decoration: none; }
  a:hover { text-decoration: underline; }

  /* Top bar */
  .topbar {
    background: #2b2d31; border-bottom: 1px solid rgba(255,255,255,0.06);
    padding: 0 24px; height: 56px; display: flex; align-items: center; gap: 16px;
  }
  .topbar-logo { font-size: 16px; font-weight: 800; color: #fff; }
  .topbar-logo span { color: #5865f2; }
  .topbar-sep { flex: 1; }
  .topbar-user { font-size: 13px; color: #949ba4; }
  .topbar-btn { padding: 6px 14px; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: #dcddde; font-size: 13px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
  .topbar-btn:hover { background: rgba(255,255,255,0.12); text-decoration: none; }

  /* Layout */
  .layout { display: flex; min-height: calc(100vh - 56px); }
  .nav { width: 200px; background: #2b2d31; padding: 16px 0; flex-shrink: 0; }
  .nav-item { display: flex; align-items: center; gap: 8px; padding: 9px 16px; font-size: 14px; font-weight: 500; color: #949ba4; cursor: pointer; border-radius: 0; transition: background 0.1s, color 0.1s; }
  .nav-item:hover { background: rgba(255,255,255,0.05); color: #f2f3f5; text-decoration: none; }
  .nav-item.active { background: rgba(88,101,242,0.15); color: #7b8cff; }
  .content { flex: 1; padding: 28px 32px; overflow-x: auto; }

  /* Message banner */
  .banner { padding: 12px 16px; border-radius: 8px; background: rgba(35,165,89,0.15); color: #23a559; font-size: 13px; font-weight: 500; margin-bottom: 20px; }

  /* Stats */
  .stats { display: grid; grid-template-columns: repeat(auto-fill,minmax(160px,1fr)); gap: 14px; margin-bottom: 28px; }
  .stat { background: #2b2d31; border-radius: 10px; padding: 18px 20px; }
  .stat-num { font-size: 32px; font-weight: 800; color: #fff; }
  .stat-label { font-size: 13px; color: #949ba4; margin-top: 4px; }

  /* Table */
  h2 { font-size: 18px; font-weight: 700; color: #f2f3f5; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th { text-align: left; padding: 10px 12px; background: #2b2d31; color: #949ba4; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; position: sticky; top: 0; }
  td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; }
  tr:hover td { background: rgba(255,255,255,0.02); }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
  .badge-admin  { background: rgba(88,101,242,0.2); color: #7b8cff; }
  .badge-banned { background: rgba(242,63,67,0.2); color: #f23f43; }
  .badge-active { background: rgba(35,165,89,0.2); color: #23a559; }
  .badge-user   { background: rgba(255,255,255,0.08); color: #949ba4; }
  .badge-pending { background: rgba(250,168,26,0.2); color: #faa81a; }
  .badge-resolved { background: rgba(35,165,89,0.15); color: #23a559; }

  /* Action forms */
  .actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .act-btn { padding: 4px 10px; border-radius: 4px; border: none; font-size: 12px; font-weight: 600; cursor: pointer; font-family: inherit; }
  .act-ban    { background: rgba(242,63,67,0.15); color: #f23f43; }
  .act-ban:hover { background: rgba(242,63,67,0.3); }
  .act-unban  { background: rgba(35,165,89,0.15); color: #23a559; }
  .act-unban:hover { background: rgba(35,165,89,0.3); }
  .act-del    { background: rgba(242,63,67,0.1); color: #f23f43; }
  .act-del:hover { background: rgba(242,63,67,0.25); }
  .act-prom   { background: rgba(88,101,242,0.15); color: #7b8cff; }
  .act-prom:hover { background: rgba(88,101,242,0.3); }
  .act-dem    { background: rgba(255,255,255,0.08); color: #949ba4; }
  .act-dem:hover { background: rgba(255,255,255,0.15); }
  .act-resolve { background: rgba(35,165,89,0.15); color: #23a559; }

  /* Server color swatch */
  .srv-icon { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 11px; font-weight: 800; }

  @media (max-width: 700px) {
    .nav { display: none; }
    .content { padding: 20px 16px; }
  }
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-logo">Connected <span>Center</span> — Admin</div>
  <div class="topbar-sep"></div>
  <span class="topbar-user"><?= ccEsc($me['display_name'] ?: $me['username']) ?></span>
  <a href="dashboard.php" class="topbar-btn">← Dashboard</a>
  <a href="logout.php" class="topbar-btn">Log out</a>
</div>

<div class="layout">
  <nav class="nav">
    <?php
    $navItems = [
      'overview' => ['📊', 'Overview'],
      'users'    => ['👥', 'Users'],
      'servers'  => ['🖥️', 'Servers'],
      'reports'  => ['🚨', 'Reports'],
    ];
    foreach ($navItems as $t => [$icon, $label]):
    ?>
    <a href="?tab=<?= $t ?>" class="nav-item<?= $tab===$t?' active':'' ?>">
      <?= $icon ?> <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="content">
    <?php if ($msg): ?><div class="banner"><?= $msg ?></div><?php endif; ?>

    <?php if ($tab === 'overview'): ?>
    <h2>Overview</h2>
    <div class="stats">
      <div class="stat"><div class="stat-num"><?= $stats['users'] ?></div><div class="stat-label">Users</div></div>
      <div class="stat"><div class="stat-num"><?= $stats['servers'] ?></div><div class="stat-label">Servers</div></div>
      <div class="stat"><div class="stat-num"><?= $stats['messages'] ?></div><div class="stat-label">Channel Messages</div></div>
      <div class="stat"><div class="stat-num"><?= $stats['dms'] ?></div><div class="stat-label">Direct Messages</div></div>
      <div class="stat"><div class="stat-num"><?= $stats['reports'] ?></div><div class="stat-label">Pending Reports</div></div>
    </div>

    <h2>Recent Users</h2>
    <table>
      <tr><th>Username</th><th>Role</th><th>Status</th><th>Joined</th></tr>
      <?php foreach (array_slice($users, 0, 10) as $u): ?>
      <tr>
        <td><?= ccEsc($u['username']) ?><?= $u['display_name'] ? ' <span style="color:#949ba4;font-size:12px">('.ccEsc($u['display_name']).')</span>' : '' ?></td>
        <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
        <td><span class="badge badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>

    <?php elseif ($tab === 'users'): ?>
    <h2>Users (<?= count($users) ?>)</h2>
    <table>
      <tr><th>Username</th><th>Display Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Last Seen</th><th>Actions</th></tr>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= ccEsc($u['username']) ?></td>
        <td><?= ccEsc($u['display_name'] ?: '—') ?></td>
        <td><?= ccEsc($u['email']) ?></td>
        <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
        <td><span class="badge badge-<?= $u['status'] ?>"><?= $u['status'] ?></span></td>
        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
        <td><?= $u['last_seen_at'] ? date('M j H:i', strtotime($u['last_seen_at'])) : '—' ?></td>
        <td>
          <?php if ($u['id'] !== $me['id']): ?>
          <div class="actions">
            <?php if ($u['status'] === 'active'): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="ban_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="act-btn act-ban" type="submit" onclick="return confirm('Ban this user?')">Ban</button></form>
            <?php else: ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="unban_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="act-btn act-unban" type="submit">Unban</button></form>
            <?php endif; ?>
            <?php if ($u['role'] === 'user'): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="promote_admin"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="act-btn act-prom" type="submit" onclick="return confirm('Promote to admin?')">→ Admin</button></form>
            <?php else: ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="demote_admin"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="act-btn act-dem" type="submit" onclick="return confirm('Remove admin?')">Demote</button></form>
            <?php endif; ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button class="act-btn act-del" type="submit" onclick="return confirm('Permanently delete user and all their data?')">Delete</button></form>
          </div>
          <?php else: ?>
          <span style="color:#949ba4;font-size:12px">You</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>

    <?php elseif ($tab === 'servers'): ?>
    <h2>Servers (<?= count($servers) ?>)</h2>
    <table>
      <tr><th>Name</th><th>Owner</th><th>Members</th><th>Channels</th><th>Created</th><th>Actions</th></tr>
      <?php foreach ($servers as $s): ?>
      <tr>
        <td style="display:flex;align-items:center;gap:10px;padding:10px 12px;">
          <span class="srv-icon" style="background:<?= ccEsc($s['icon_color']) ?>"><?= ccEsc(mb_strtoupper(mb_substr($s['name'],0,2))) ?></span>
          <?= ccEsc($s['name']) ?>
        </td>
        <td>@<?= ccEsc($s['owner_name']) ?></td>
        <td><?= $s['members'] ?></td>
        <td><?= $s['channels'] ?></td>
        <td><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
        <td>
          <form method="POST">
            <input type="hidden" name="action" value="delete_server">
            <input type="hidden" name="server_id" value="<?= $s['id'] ?>">
            <button class="act-btn act-del" type="submit" onclick="return confirm('Delete this entire server?')">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>

    <?php elseif ($tab === 'reports'): ?>
    <h2>Reports (<?= count($reports) ?>)</h2>
    <?php if (!$reports): ?>
    <p style="color:#949ba4;font-size:14px;">No reports yet.</p>
    <?php else: ?>
    <table>
      <tr><th>ID</th><th>Reporter</th><th>Type</th><th>Target ID</th><th>Reason</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      <?php foreach ($reports as $r): ?>
      <tr>
        <td>#<?= $r['id'] ?></td>
        <td><?= ccEsc($r['reporter_name']) ?></td>
        <td><?= ccEsc($r['target_type']) ?></td>
        <td><?= $r['target_id'] ?></td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= ccEsc($r['reason']) ?>"><?= ccEsc($r['reason']) ?></td>
        <td><span class="badge badge-<?= $r['status'] ==='pending'?'pending':'resolved' ?>"><?= $r['status'] ?></span></td>
        <td><?= date('M j', strtotime($r['created_at'])) ?></td>
        <td>
          <?php if ($r['status'] !== 'resolved'): ?>
          <form method="POST"><input type="hidden" name="action" value="resolve_report"><input type="hidden" name="report_id" value="<?= $r['id'] ?>"><button class="act-btn act-resolve" type="submit">Resolve</button></form>
          <?php else: ?><span style="color:#949ba4;font-size:12px">Done</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
