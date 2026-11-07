<?php
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['cc_user_id'])) {
    if (ccIsEmbed()) ccEmitEmbedAuthSuccess();
    header('Location: dashboard.php');
    exit;
}

$error = '';
$values = ['username' => '', 'email' => '', 'display_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username']     ?? '');
    $email        = trim($_POST['email']        ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $password     = $_POST['password']          ?? '';
    $confirm      = $_POST['confirm']           ?? '';

    $values = compact('username', 'email', 'display_name');

    if (!$username || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username: 3–30 chars, letters/numbers/underscores only.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = ccDB();

            // Check uniqueness
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email is already taken.';
            } else {
                // First user ever → admin
                $isFirst = ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0);
                $role    = $isFirst ? 'admin' : 'user';

                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, display_name, password_hash, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $name = $display_name !== '' ? $display_name : $username;

                $stmt->execute([$username, $email, $name, $hash, $role]);
                $newId = (int)$pdo->lastInsertId();
                if ($newId <= 0) {
                    throw new PDOException('Account insert failed');
                }

                session_regenerate_id(true);
                $_SESSION['cc_user_id'] = $newId;
                $_SESSION['cc_role']    = $role;
                if (ccIsEmbed() || (!empty($_POST['embed']) && $_POST['embed'] === '1')) {
                    ccEmitEmbedAuthSuccess();
                }
                header('Location: dashboard.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Could not create account. Please try again.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Connected Center — Sign Up</title>
<script src="../theme-sync.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body, input, button { font-family: 'Avenir Next Regular', 'Avenir Next', 'Avenir', sans-serif; font-weight: 400; }
  h1, h2, strong { font-family: 'Avenir Next Medium', 'Avenir Next', 'Avenir', sans-serif !important; font-weight: 500 !important; }
  body { min-height: 100vh; background: var(--theme-bg, #f0f4f9); display: flex; align-items: center; justify-content: center; color: var(--theme-text-primary, #1a1a2e); padding: 24px 0; }
  .blur-bg { position: fixed; z-index: 0; inset: 0; pointer-events: none; overflow: hidden; }
  .blur-ball { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0; mix-blend-mode: lighten; animation: ballFloat 18s ease-in-out infinite alternate, ballIn 1.2s cubic-bezier(.4,0,.2,1) forwards; }
  .bb1 { width: 420px; height: 420px; background: var(--theme-ball-1, rgba(130,180,255,0.5)); left: -80px; top: -60px; animation-delay: 0s, 0.1s; }
  .bb2 { width: 340px; height: 340px; background: var(--theme-ball-2, rgba(200,160,255,0.45)); right: -60px; top: 10vh; animation-delay: 3s, 0.3s; }
  @keyframes ballFloat { 0% { transform: translateY(0); } 100% { transform: translateY(-28px); } }
  @keyframes ballIn { 0% { opacity: 0; } 100% { opacity: 0.55; } }
  .card { position: relative; z-index: 1; width: 440px; max-width: calc(100vw - 32px); background: var(--theme-glass-tint, rgba(255,255,255,0.72)); border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.7)); border-radius: 24px; padding: 36px; backdrop-filter: blur(28px) saturate(180%); -webkit-backdrop-filter: blur(28px) saturate(180%); box-shadow: 0 12px 48px rgba(0,0,0,0.1), inset 0 1px 0 rgba(255,255,255,0.6); animation: cardIn 0.4s cubic-bezier(0.22,1,0.36,1); }
  @keyframes cardIn { from { opacity:0; transform: translateY(20px) scale(0.97); } to { opacity:1; transform:none; } }
  .logo { text-align: center; margin-bottom: 20px; }
  .logo-icon { width: 56px; height: 56px; border-radius: 16px; background: var(--theme-accent, #2196f3); display: inline-flex; align-items: center; justify-content: center; font-size: 27px; margin-bottom: 10px; box-shadow: 0 4px 16px rgba(33,150,243,0.28); }
  h1 { font-size: 21px; color: var(--theme-text-primary, #1a1a2e); text-align: center; }
  .subtitle { text-align: center; color: var(--theme-text-secondary, #888); font-size: 13px; margin-top: 3px; margin-bottom: 20px; }
  .msg-err { padding: 10px 14px; border-radius: 10px; background: rgba(229,57,53,0.1); color: #c62828; font-size: 13px; margin-bottom: 14px; }
  label { display: block; font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.7px; color: var(--theme-text-secondary, #888); margin-bottom: 5px; margin-top: 14px; }
  .note { font-size: 11px; color: var(--theme-text-secondary, #aaa); margin-top: 3px; text-transform: none; letter-spacing: 0; }
  input { width: 100%; background: rgba(255,255,255,0.6); border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.12)); border-radius: 10px; color: var(--theme-text-primary, #1a1a2e); font-family: inherit; font-size: 14px; padding: 10px 13px; outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
  input:focus { border-color: var(--theme-accent, #2196f3); box-shadow: 0 0 0 3px rgba(33,150,243,0.12); }
  .btn { width: 100%; background: var(--theme-accent, #2196f3); color: #fff; border: none; border-radius: 12px; padding: 12px; font-family: inherit; font-size: 14px; font-weight: 500; cursor: pointer; margin-top: 20px; transition: filter 0.15s, transform 0.1s; box-shadow: 0 4px 16px rgba(33,150,243,0.28); }
  .btn:hover { filter: brightness(1.1); }
  .btn:active { transform: scale(0.98); }
  .divider { height: 1px; background: var(--theme-glass-border, rgba(0,0,0,0.08)); margin: 20px 0; }
  .footer-links { text-align: center; font-size: 13px; color: var(--theme-text-secondary, #888); }
  .footer-links a { color: var(--theme-accent, #2196f3); text-decoration: none; font-weight: 500; }
</style>
</head>
<body>
<div class="blur-bg">
  <div class="blur-ball bb1"></div>
  <div class="blur-ball bb2"></div>
</div>
<div class="card">
  <div class="logo">
    <div class="logo-icon">💬</div>
    <h1>Create an Account</h1>
    <p class="subtitle">Join Connected Center today</p>
  </div>

  <?php if ($error): echo '<div class="msg-err">' . htmlspecialchars($error) . '</div>'; endif; ?>

  <form method="POST" autocomplete="on">
    <?php if (ccIsEmbed()): ?><input type="hidden" name="embed" value="1" /><?php endif; ?>
    <label>Username <span style="color:#f23f43">*</span></label>
    <input type="text" name="username" value="<?= htmlspecialchars($values['username']) ?>" placeholder="cool_username" pattern="[a-zA-Z0-9_]{3,30}" required />
    <p class="note">3–30 characters. Letters, numbers, underscores.</p>

    <label>Display Name</label>
    <input type="text" name="display_name" value="<?= htmlspecialchars($values['display_name']) ?>" placeholder="How others will see you" maxlength="100" />

    <label>Email <span style="color:#f23f43">*</span></label>
    <input type="email" name="email" value="<?= htmlspecialchars($values['email']) ?>" placeholder="you@example.com" required />

    <label>Password <span style="color:#f23f43">*</span></label>
    <input type="password" name="password" placeholder="Min. 8 characters" required />

    <label>Confirm Password <span style="color:#f23f43">*</span></label>
    <input type="password" name="confirm" placeholder="Repeat your password" required />

    <button class="btn" type="submit">Create Account</button>
  </form>

  <div class="divider"></div>
  <div class="footer-links">Already have an account? <a href="index.php<?= ccIsEmbed() ? '?embed=1' : '' ?>">Log in</a></div>
</div>
</body>
</html>
