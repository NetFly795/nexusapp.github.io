<?php
require_once __DIR__ . '/auth.php';

// Already logged in → go to dashboard (or notify embed parent)
if (!empty($_SESSION['cc_user_id'])) {
    if (ccIsEmbed()) ccEmitEmbedAuthSuccess();
    header('Location: dashboard.php');
    exit;
}

$error = '';
$info  = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!$identifier || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $pdo  = ccDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status != 'banned'");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['cc_user_id'] = $user['id'];
                $_SESSION['cc_role']    = $user['role'];
                $pdo->prepare("UPDATE users SET last_seen_at = NOW() WHERE id = ?")
                    ->execute([$user['id']]);
                if (ccIsEmbed() || (!empty($_POST['embed']) && $_POST['embed'] === '1')) {
                    ccEmitEmbedAuthSuccess();
                }
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username/email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}

$infoMessages = [
    'account_inactive' => 'Your account has been deactivated.',
    'logged_out'       => 'You have been logged out.',
    'registered'       => 'Account created! Please log in.',
];
$infoMsg = $infoMessages[$info] ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Connected Center — Login</title>
<script src="../theme-sync.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body, input, button, label {
    font-family: 'Avenir Next Regular', 'Avenir Next', 'Avenir', sans-serif;
    font-weight: 400;
  }
  h1, h2, strong { font-family: 'Avenir Next Medium', 'Avenir Next', 'Avenir', sans-serif !important; font-weight: 500 !important; }
  body {
    min-height: 100vh;
    background: var(--theme-bg, #f0f4f9);
    display: flex; align-items: center; justify-content: center;
    color: var(--theme-text-primary, #1a1a2e);
  }
  .blur-bg { position: fixed; z-index: 0; inset: 0; pointer-events: none; overflow: hidden; }
  .blur-ball { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0; mix-blend-mode: lighten; animation: ballFloat 18s ease-in-out infinite alternate, ballIn 1.2s cubic-bezier(.4,0,.2,1) forwards; }
  .bb1 { width: 420px; height: 420px; background: var(--theme-ball-1, rgba(130,180,255,0.5)); left: -80px; top: -60px; animation-delay: 0s, 0.1s; }
  .bb2 { width: 340px; height: 340px; background: var(--theme-ball-2, rgba(200,160,255,0.45)); right: -60px; top: 10vh; animation-delay: 3s, 0.3s; }
  .bb3 { width: 280px; height: 280px; background: var(--theme-ball-3, rgba(130,230,200,0.4)); left: 20vw; bottom: 5vh; animation-delay: 6s, 0.5s; }
  @keyframes ballFloat { 0% { transform: translateY(0); } 100% { transform: translateY(-28px); } }
  @keyframes ballIn { 0% { opacity: 0; } 100% { opacity: 0.55; } }

  .card {
    position: relative; z-index: 1;
    width: 420px; max-width: calc(100vw - 32px);
    background: var(--theme-glass-tint, rgba(255,255,255,0.72));
    border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.7));
    border-radius: 24px;
    padding: 40px 36px;
    backdrop-filter: blur(28px) saturate(180%);
    -webkit-backdrop-filter: blur(28px) saturate(180%);
    box-shadow: 0 12px 48px rgba(0,0,0,0.1), inset 0 1px 0 rgba(255,255,255,0.6);
    animation: cardIn 0.4s cubic-bezier(0.22,1,0.36,1);
  }
  @keyframes cardIn { from { opacity:0; transform: translateY(20px) scale(0.97); } to { opacity:1; transform: none; } }

  .logo { text-align: center; margin-bottom: 24px; }
  .logo-icon {
    width: 60px; height: 60px; border-radius: 18px;
    background: var(--theme-accent, #2196f3);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 30px; margin-bottom: 10px;
    box-shadow: 0 4px 16px rgba(33,150,243,0.3);
  }
  h1 { font-size: 22px; color: var(--theme-text-primary, #1a1a2e); text-align: center; margin-bottom: 3px; }
  .subtitle { text-align: center; color: var(--theme-text-secondary, #888); font-size: 13px; margin-bottom: 24px; }

  .msg { padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 14px; }
  .msg-err  { background: rgba(229,57,53,0.1); color: #c62828; }
  .msg-info { background: rgba(33,150,243,0.1); color: #1565c0; }

  label { display: block; font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.7px; color: var(--theme-text-secondary, #888); margin-bottom: 6px; margin-top: 16px; }
  input {
    width: 100%;
    background: rgba(255,255,255,0.6);
    border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.12));
    border-radius: 10px; color: var(--theme-text-primary, #1a1a2e);
    font-family: inherit; font-size: 14px;
    padding: 11px 14px; outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  input:focus { border-color: var(--theme-accent, #2196f3); box-shadow: 0 0 0 3px rgba(33,150,243,0.12); }

  .btn {
    width: 100%; background: var(--theme-accent, #2196f3); color: #fff;
    border: none; border-radius: 12px;
    padding: 13px; font-family: inherit; font-size: 14px; font-weight: 500;
    cursor: pointer; margin-top: 22px;
    transition: filter 0.15s, transform 0.1s;
    box-shadow: 0 4px 16px rgba(33,150,243,0.3);
  }
  .btn:hover { filter: brightness(1.1); }
  .btn:active { transform: scale(0.98); }

  .divider { height: 1px; background: var(--theme-glass-border, rgba(0,0,0,0.08)); margin: 22px 0; }
  .footer-links { text-align: center; font-size: 13px; color: var(--theme-text-secondary, #888); }
  .footer-links a { color: var(--theme-accent, #2196f3); text-decoration: none; font-weight: 500; }
  .footer-links a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="blur-bg">
  <div class="blur-ball bb1"></div>
  <div class="blur-ball bb2"></div>
  <div class="blur-ball bb3"></div>
</div>
<div class="card">
  <div class="logo">
    <div class="logo-icon">💬</div>
    <h1>Connected Center</h1>
    <p class="subtitle">Sign in to continue</p>
  </div>

  <?php if ($error):  echo '<div class="msg msg-err">'  . htmlspecialchars($error)  . '</div>'; endif; ?>
  <?php if ($infoMsg): echo '<div class="msg msg-info">' . htmlspecialchars($infoMsg) . '</div>'; endif; ?>

  <form method="POST" autocomplete="on">
    <?php if (ccIsEmbed()): ?><input type="hidden" name="embed" value="1" /><?php endif; ?>
    <label>Username or Email</label>
    <input type="text" name="identifier" placeholder="your_username" autocomplete="username" required />
    <label>Password</label>
    <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required />
    <button class="btn" type="submit">Log In</button>
  </form>

  <div class="divider"></div>
  <div class="footer-links">
    Don't have an account? <a href="signup.php<?= ccIsEmbed() ? '?embed=1' : '' ?>">Create one</a>
  </div>
</div>
</body>
</html>
