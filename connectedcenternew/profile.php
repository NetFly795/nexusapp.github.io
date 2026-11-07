<?php
require_once __DIR__ . '/auth.php';
$me = ccAuthRequired('index.php');
$pdo = ccDB();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display = trim($_POST['display_name'] ?? '');
    $email   = trim($_POST['email']        ?? '');
    $pwNew   = $_POST['new_password']      ?? '';
    $pwConf  = $_POST['confirm_password']  ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif ($pwNew && strlen($pwNew) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($pwNew && $pwNew !== $pwConf) {
        $error = 'Passwords do not match.';
    } else {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $stmt->execute([$email, $me['id']]);
        if ($stmt->fetch()) {
            $error = 'That email is already in use.';
        } else {
            // Handle avatar (browser-compressed base64 JPEG from canvas)
            $picPath = $me['profile_picture'];
            $avatarData = trim($_POST['avatar_data'] ?? '');
            if ($avatarData && substr($avatarData, 0, 23) === 'data:image/jpeg;base64,') {
                $b64  = substr($avatarData, 23);
                $raw  = base64_decode($b64, true);
                if ($raw === false || strlen($raw) > 600 * 1024) {
                    $error = 'Avatar data is invalid or too large.';
                } else {
                    $dir = __DIR__ . '/avatars/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $fname = 'av_' . $me['id'] . '_' . time() . '.jpg';
                    if (file_put_contents($dir . $fname, $raw) !== false) {
                        // Delete old avatar file if it exists and is in our folder
                        if ($me['profile_picture'] && strpos($me['profile_picture'], 'avatars/') === 0) {
                            @unlink(__DIR__ . '/' . $me['profile_picture']);
                        }
                        $picPath = 'avatars/' . $fname;
                    } else {
                        $error = 'Failed to save avatar.';
                    }
                }
            }

            if (!$error) {
                if ($pwNew) {
                    $pdo->prepare("UPDATE users SET display_name=?,email=?,profile_picture=?,password_hash=? WHERE id=?")
                        ->execute([$display ?: null, $email, $picPath, password_hash($pwNew, PASSWORD_DEFAULT), $me['id']]);
                } else {
                    $pdo->prepare("UPDATE users SET display_name=?,email=?,profile_picture=? WHERE id=?")
                        ->execute([$display ?: null, $email, $picPath, $me['id']]);
                }
                $success = 'Profile updated!';
                // Refresh $me
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
                $stmt->execute([$me['id']]);
                $me = $stmt->fetch();
            }
        }
    }
}

$displayName  = $me['display_name'] ?: $me['username'];
$avatarLetter = strtoupper(mb_substr($displayName, 0, 1));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Edit Profile — Connected Center</title>
<script src="../theme-sync.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body, input, button { font-family: 'Avenir Next Regular', 'Avenir Next', 'Avenir', sans-serif; font-weight: 400; }
  h1, h2, strong { font-family: 'Avenir Next Medium', 'Avenir Next', 'Avenir', sans-serif !important; font-weight: 500 !important; }
  body { min-height: 100vh; background: var(--theme-bg, #f0f4f9); color: var(--theme-text-primary, #1a1a2e); display: flex; flex-direction: column; align-items: center; padding: 40px 16px; }
  .card { width: 480px; max-width: 100%; background: var(--theme-glass-tint, rgba(255,255,255,0.72)); border: 1px solid var(--theme-glass-border, rgba(255,255,255,0.7)); border-radius: 22px; padding: 32px; backdrop-filter: blur(24px) saturate(180%); -webkit-backdrop-filter: blur(24px) saturate(180%); box-shadow: 0 8px 40px rgba(0,0,0,0.08), inset 0 1px 0 rgba(255,255,255,0.6); }
  .back { color: var(--theme-accent, #2196f3); font-size: 13px; text-decoration: none; margin-bottom: 22px; display: inline-block; }
  .back:hover { text-decoration: underline; }
  h1 { font-size: 20px; color: var(--theme-text-primary, #1a1a2e); margin-bottom: 22px; }
  .avatar-wrap { display: flex; align-items: center; gap: 16px; margin-bottom: 8px; }
  .av { width: 68px; height: 68px; border-radius: 50%; background: var(--theme-accent, #2196f3); display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 600; color: #fff; overflow: hidden; }
  .av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .av-hint { font-size: 11.5px; color: var(--theme-text-secondary, #888); margin-top: 3px; }
  label { display: block; font-size: 10px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.7px; color: var(--theme-text-secondary, #888); margin-bottom: 6px; margin-top: 16px; }
  input[type=text], input[type=email], input[type=password] { width: 100%; background: rgba(255,255,255,0.6); border: 1px solid var(--theme-glass-border, rgba(0,0,0,0.12)); border-radius: 10px; color: var(--theme-text-primary, #1a1a2e); font-family: inherit; font-size: 14px; padding: 10px 13px; outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
  input:focus { border-color: var(--theme-accent, #2196f3); box-shadow: 0 0 0 3px rgba(33,150,243,0.12); }
  input[type=file] { color: var(--theme-text-secondary, #888); font-size: 13px; margin-top: 8px; }
  .msg { padding: 10px 14px; border-radius: 10px; font-size: 13px; margin-top: 14px; }
  .msg-ok  { background: rgba(76,175,80,0.1); color: #2e7d32; }
  .msg-err { background: rgba(229,57,53,0.1); color: #c62828; }
  .divider { height: 1px; background: var(--theme-glass-border, rgba(0,0,0,0.08)); margin: 22px 0; }
  .btn { background: var(--theme-accent, #2196f3); border: none; color: #fff; border-radius: 12px; padding: 12px 24px; font-family: inherit; font-size: 14px; font-weight: 500; cursor: pointer; transition: filter 0.15s, transform 0.1s; margin-top: 20px; box-shadow: 0 4px 14px rgba(33,150,243,0.28); }
  .btn:hover { filter: brightness(1.1); }
  .btn:active { transform: scale(0.98); }
</style>
</head>
<body>
<div class="card">
  <a href="dashboard.php" class="back">← Back to Dashboard</a>
  <h1>Edit Profile</h1>

  <?php if ($success): ?><div class="msg msg-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="msg msg-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <!-- Avatar canvas compression: file → hidden field with compressed base64 JPEG -->
  <form method="POST" id="profileForm" onsubmit="return prepareForm(event)">
    <!-- Hidden field receives the compressed base64 avatar -->
    <input type="hidden" name="avatar_data" id="avatarData" />

    <label>Avatar</label>
    <div class="avatar-wrap">
      <div class="av" id="avPreview">
        <?php if ($me['profile_picture']): ?>
        <img src="<?= ccEsc($me['profile_picture']) ?>" alt="" id="avImg" />
        <?php else: ?>
        <span id="avLetter"><?= $avatarLetter ?></span>
        <?php endif; ?>
      </div>
      <div>
        <input type="file" id="avatarFile" accept="image/jpeg,image/png,image/gif,image/webp"
               onchange="previewAndCompress(this)" style="display:none" />
        <button type="button" class="btn" style="margin-top:0;width:auto;padding:8px 18px;font-size:13px;"
                onclick="document.getElementById('avatarFile').click()">Choose Photo</button>
        <p class="av-hint" id="avHint">Compressed to 256×256 · JPG</p>
      </div>
    </div>

    <!-- Hidden canvas used for compression -->
    <canvas id="compressCanvas" width="256" height="256" style="display:none"></canvas>

    <label>Display Name</label>
    <input type="text" name="display_name" value="<?= ccEsc($me['display_name'] ?? '') ?>" placeholder="How others see you" maxlength="100" />

    <label>Email</label>
    <input type="email" name="email" value="<?= ccEsc($me['email']) ?>" required />

    <div class="divider"></div>

    <label>New Password <span style="color:var(--theme-text-secondary,#aaa);text-transform:none;font-weight:400">(leave blank to keep current)</span></label>
    <input type="password" name="new_password" placeholder="Min. 8 characters" />

    <label>Confirm New Password</label>
    <input type="password" name="confirm_password" placeholder="Repeat new password" />

    <button class="btn" type="submit">Save Changes</button>
  </form>
</div>

<script>
function previewAndCompress(input) {
  var file = input.files[0];
  if (!file) return;
  var hint = document.getElementById('avHint');
  hint.textContent = 'Compressing…';
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = new Image();
    img.onload = function() {
      var canvas = document.getElementById('compressCanvas');
      var ctx = canvas.getContext('2d');
      // Center-crop to square, then draw at 256×256
      var size = Math.min(img.width, img.height);
      var sx = (img.width  - size) / 2;
      var sy = (img.height - size) / 2;
      ctx.clearRect(0, 0, 256, 256);
      ctx.drawImage(img, sx, sy, size, size, 0, 0, 256, 256);
      var dataUrl = canvas.toDataURL('image/jpeg', 0.88);
      // Live preview
      var preview = document.getElementById('avPreview');
      preview.innerHTML = '<img src="' + dataUrl + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" />';
      // Store in hidden field
      document.getElementById('avatarData').value = dataUrl;
      var kb = Math.round(dataUrl.length * 0.75 / 1024);
      hint.textContent = 'Ready · 256×256 · ~' + kb + ' KB';
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
}

function prepareForm(e) {
  // Nothing to do — avatarData is already set by previewAndCompress if a file was chosen
  return true;
}
</script>
</body>
</html>
