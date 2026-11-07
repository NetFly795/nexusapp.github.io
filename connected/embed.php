<?php
session_start();

// Check if config exists, redirect to install if not
if (!file_exists('config.php')) {
    die('Nexus Pay is not properly configured. Please contact the administrator.');
}

require_once 'config.php';

// Get database connection
$pdo = getDBConnection();

$login_error = '';
$signup_error = '';
$signup_success = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        if ($user['role'] === 'banned') {
            $login_error = "Your account has been banned. Please contact support.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect to dashboard or return success
            if (isset($_GET['redirect'])) {
                header('Location: ' . $_GET['redirect']);
            } else {
                header('Location: done.html');
            }
            exit;
        }
    } else {
        $login_error = "Invalid username or password";
    }
}

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $username = trim($_POST['signup_username']);
    $password = $_POST['signup_password'];
    $confirm_password = $_POST['confirm_password'];
    $security_question = trim($_POST['security_question']);
    $security_answer = trim($_POST['security_answer']);
    
    // Validation
    if (empty($username) || empty($password) || empty($confirm_password) || empty($security_question) || empty($security_answer)) {
        $signup_error = "All fields are required";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $signup_error = "Username must be between 3 and 20 characters";
    } elseif (strlen($password) < 6) {
        $signup_error = "Password must be at least 6 characters";
    } elseif ($password !== $confirm_password) {
        $signup_error = "Passwords do not match";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $signup_error = "Username already exists";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password, security_question, security_answer, role, created_at) 
                    VALUES (?, ?, ?, ?, 'user', NOW())
                ");
                $stmt->execute([$username, $hashed_password, $security_question, $security_answer]);
                $user_id = $pdo->lastInsertId();
                
                // Create checking account
                $checking_number = 'CHK' . str_pad($user_id, 8, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("
                    INSERT INTO accounts (user_id, account_type, account_number, balance, created_at) 
                    VALUES (?, 'checking', ?, 1000.00, NOW())
                ");
                $stmt->execute([$user_id, $checking_number]);
                
                // Create savings account
                $savings_number = 'SAV' . str_pad($user_id, 8, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("
                    INSERT INTO accounts (user_id, account_type, account_number, balance, created_at) 
                    VALUES (?, 'savings', ?, 500.00, NOW())
                ");
                $stmt->execute([$user_id, $savings_number]);
                
                $pdo->commit();
                $signup_success = "Connected Account created successfully! You can now sign in.";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $signup_error = "Error creating account. Please try again.";
            }
        }
    }
}

// Get embed parameters
$embed_title = $_GET['title'] ?? 'Connected Center';
$embed_subtitle = $_GET['subtitle'] ?? 'Sign in to your Connected Account';
$embed_theme = $_GET['theme'] ?? 'default';
$embed_width = $_GET['width'] ?? '400';
$embed_height = $_GET['height'] ?? '600';
$show_logo = isset($_GET['logo']) ? $_GET['logo'] === 'true' : true;
$redirect_url = $_GET['redirect'] ?? '';

// Set theme colors
$themes = [
    'default' => ['primary' => '#1e3c72', 'secondary' => '#667eea', 'background' => '#f5f7fa'],
    'dark' => ['primary' => '#2c3e50', 'secondary' => '#34495e', 'background' => '#2c3e50'],
    'blue' => ['primary' => '#3498db', 'secondary' => '#2980b9', 'background' => '#ecf0f1'],
    'green' => ['primary' => '#27ae60', 'secondary' => '#2ecc71', 'background' => '#f0f8f0'],
    'purple' => ['primary' => '#9b59b6', 'secondary' => '#8e44ad', 'background' => '#f8f0f8']
];

$theme = $themes[$embed_theme] ?? $themes['default'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($embed_title); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: <?php echo $theme['background']; ?>;
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .embed-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            width: <?php echo $embed_width; ?>px;
            max-width: 90vw;
            max-height: <?php echo $embed_height; ?>px;
            overflow-y: auto;
        }
        
        .embed-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .embed-logo {
            font-size: 24px;
            font-weight: 600;
            color: <?php echo $theme['primary']; ?>;
            margin-bottom: 10px;
        }
        
        .embed-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .embed-subtitle {
            color: #666;
            font-size: 14px;
        }
        
        .tab-container {
            display: flex;
            margin-bottom: 25px;
            border-bottom: 2px solid #e1e5e9;
        }
        
        .tab {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab.active {
            border-bottom-color: <?php echo $theme['primary']; ?>;
            color: <?php echo $theme['primary']; ?>;
        }
        
        .tab:hover {
            background: #f8f9fa;
        }
        
        .form-container {
            display: none;
        }
        
        .form-container.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: <?php echo $theme['primary']; ?>;
        }
        
        .btn {
            width: 100%;
            background: <?php echo $theme['primary']; ?>;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: <?php echo $theme['secondary']; ?>;
            transform: translateY(-1px);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .forgot-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .forgot-link a {
            color: <?php echo $theme['primary']; ?>;
            text-decoration: none;
            font-size: 14px;
        }
        
        .forgot-link a:hover {
            text-decoration: underline;
        }
        
        .powered-by {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
            font-size: 12px;
            color: #666;
        }
        
        .powered-by a {
            color: <?php echo $theme['primary']; ?>;
            text-decoration: none;
        }
        
        @media (max-width: 480px) {
            .embed-container {
                padding: 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="embed-container">
        <div class="embed-header">
            <?php if ($show_logo): ?>
                <div class="embed-logo"></div>
            <?php endif; ?>
            <div class="embed-title"><?php echo htmlspecialchars($embed_title); ?></div>
            <div class="embed-subtitle"><?php echo htmlspecialchars($embed_subtitle); ?></div>
        </div>
        
        <div class="tab-container">
            <div class="tab active" onclick="switchTab('login')">Sign In</div>
            <div class="tab" onclick="switchTab('signup')">Sign Up</div>
        </div>
        
        <!-- Login Form -->
        <div id="loginForm" class="form-container active">
            <?php if ($login_error): ?>
                <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="btn">Sign In</button>
            </form>
            
            <div class="forgot-link">
                <a href="forgot_password.php<?php echo $redirect_url ? '?redirect=' . urlencode($redirect_url) : ''; ?>">Forgot Password?</a>
            </div>
        </div>
        
        <!-- Signup Form -->
        <div id="signupForm" class="form-container">
            <?php if ($signup_error): ?>
                <div class="error"><?php echo htmlspecialchars($signup_error); ?></div>
            <?php endif; ?>
            
            <?php if ($signup_success): ?>
                <div class="success"><?php echo htmlspecialchars($signup_success); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="signup_username">Username</label>
                    <input type="text" id="signup_username" name="signup_username" required minlength="3" maxlength="20">
                </div>
                
                <div class="form-group">
                    <label for="signup_password">Password</label>
                    <input type="password" id="signup_password" name="signup_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="security_question">Security Question</label>
                    <input type="text" id="security_question" name="security_question" required placeholder="e.g., What was your first pet's name?">
                </div>
                
                <div class="form-group">
                    <label for="security_answer">Security Answer</label>
                    <input type="text" id="security_answer" name="security_answer" required>
                </div>
                
                <button type="submit" name="signup" class="btn">Create Connected Account</button>
            </form>
        </div>
        
        <div class="powered-by">

        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            // Update tab styling
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide forms
            document.querySelectorAll('.form-container').forEach(f => f.classList.remove('active'));
            if (tab === 'login') {
                document.getElementById('loginForm').classList.add('active');
            } else {
                document.getElementById('signupForm').classList.add('active');
            }
        }
        
        // Auto-resize for iframe embedding
        function resizeIframe() {
            if (window.parent !== window) {
                const height = document.body.scrollHeight;
                window.parent.postMessage({
                    type: 'resize',
                    height: height
                }, '*');
            }
        }
        
        // Resize on load and after form submissions
        window.addEventListener('load', resizeIframe);
        window.addEventListener('resize', resizeIframe);
        
        // Resize after form interactions
        document.addEventListener('input', resizeIframe);
        document.addEventListener('change', resizeIframe);
    </script>
</body>
</html> 