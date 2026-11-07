<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Include shared configuration
require_once 'config.php';

// Get database connection
$pdo = getDBConnection();

try {
    // Get current user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $displayName = trim($_POST['display_name'] ?? '');
        $profilePicture = null;
        
        // Handle file upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_picture'];
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            // Validate file type
            if (!in_array($file['type'], $allowedTypes)) {
                $error = "Please upload a valid image file (JPEG, PNG, or GIF)";
            }
            // Validate file size
            elseif ($file['size'] > $maxSize) {
                $error = "File size must be less than 5MB";
            }
            else {
                // Read file and convert to base64
                $imageData = file_get_contents($file['tmp_name']);
                $base64 = base64_encode($imageData);
                $profilePicture = 'data:' . $file['type'] . ';base64,' . $base64;
            }
        }
        
        // Validate display name
        if (strlen($displayName) > 100) {
            $error = "Display name must be 100 characters or less";
        } else {
            // Update user profile
            $stmt = $pdo->prepare("UPDATE users SET display_name = ?, profile_picture = ? WHERE id = ?");
            $stmt->execute([$displayName ?: null, $profilePicture ?: null, $_SESSION['user_id']]);
            
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
        }
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Pay - Customize Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #667eea;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="file"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type="file"] {
            opacity: 0;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: block;
            padding: 12px;
            border: 2px dashed #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .file-input-label:hover {
            border-color: #667eea;
            background: #f0f2ff;
        }
        
        .file-input-label.has-file {
            border-color: #28a745;
            background: #d4edda;
            color: #155724;
        }
        
        .profile-preview {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .profile-picture {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            background: #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            background-size: cover;
            background-position: center;
        }
        
        .display-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .username {
            color: #666;
            font-size: 14px;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .file-info {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Customize:</h1>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>🔧 Customize Profile</h2>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="display_name">Display Name:</label>
                    <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" placeholder="Enter your display name">
                </div>
                
                <div class="form-group">
                    <label for="profile_picture">Profile Picture:</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*" onchange="updateFileLabel(this)">
                        <label for="profile_picture" class="file-input-label" id="fileLabel">
                            📷 Click to upload profile picture
                        </label>
                    </div>
                    <div class="file-info">
                        Supported formats: JPEG, PNG, GIF (max 5MB)
                    </div>
                </div>
                
                <div class="profile-preview">
                    <div class="profile-picture" id="previewPicture" style="<?php echo $user['profile_picture'] ? 'background-image: url(' . htmlspecialchars($user['profile_picture']) . ')' : ''; ?>">
                        <?php if (!$user['profile_picture']): ?>
                            <?php echo strtoupper(substr($user['display_name'] ?: $user['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="display-name"><?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></div>
                    <div class="username">@<?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                
                <button type="submit" class="btn">Save Changes</button>
            </form>
        </div>
    </div>
    
    <script>
        function updateFileLabel(input) {
            const label = document.getElementById('fileLabel');
            const preview = document.getElementById('previewPicture');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                label.textContent = `📷 ${file.name}`;
                label.classList.add('has-file');
                
                // Preview the image
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.backgroundImage = `url(${e.target.result})`;
                    preview.innerHTML = '';
                };
                reader.readAsDataURL(file);
            } else {
                label.textContent = '📷 Click to upload profile picture';
                label.classList.remove('has-file');
            }
        }
    </script>
</body>
</html> 