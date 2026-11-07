<?php
session_start();

// Include shared configuration
require_once 'config.php';

// Get database connection
$pdo = getDBConnection();

try {
    $step = $_GET['step'] ?? 'username';
    $error = '';
    $success = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($step === 'username') {
            $username = trim($_POST['username'] ?? '');
            
            if (empty($username)) {
                $error = 'Please enter a username';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role != 'banned'");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user) {
                    if ($user['security_question'] && $user['security_answer']) {
                        $_SESSION['reset_user_id'] = $user['id'];
                        header('Location: forgot_password.php?step=questions');
                        exit;
                    } else {
                        $error = 'This account does not have security questions set up. Please contact support.';
                    }
                } else {
                    $error = 'Username not found or account is banned';
                }
            }
        } elseif ($step === 'questions') {
            if (!isset($_SESSION['reset_user_id'])) {
                header('Location: forgot_password.php');
                exit;
            }
            
            $answer1 = trim($_POST['answer1'] ?? '');
            $answer2 = trim($_POST['answer2'] ?? '');
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['reset_user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $correct = strtolower(trim($user['security_answer'])) === strtolower($answer1);
                
                if ($correct) {
                    $_SESSION['reset_verified'] = true;
                    header('Location: forgot_password.php?step=new_password');
                    exit;
                } else {
                    $error = 'One or more security answers are incorrect';
                }
            } else {
                $error = 'User not found';
            }
        } elseif ($step === 'new_password') {
            if (!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_verified'])) {
                header('Location: forgot_password.php');
                exit;
            }
            
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (strlen($newPassword) < 6) {
                $error = 'Password must be at least 6 characters long';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['reset_user_id']]);
                
                // Clear session
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_verified']);
                
                $success = 'Password has been reset successfully! You can now log in with your new password.';
            }
        }
    }
    
    // Get user data for questions step
    $user = null;
    if ($step === 'questions' && isset($_SESSION['reset_user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['reset_user_id']]);
        $user = $stmt->fetch();
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
    <title>Nexus Pay - Forgot Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e1e5e9;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 10px;
            position: relative;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -20px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 2px;
            background: #e1e5e9;
        }
        
        .step.completed:not(:last-child)::after {
            background: #28a745;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
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
        
        .btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .security-question {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .security-question h4 {
            color: #667eea;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Forgot Password</h1>
            <p>Reset your password using security questions</p>
        </div>
        
        <div class="step-indicator">
            <div class="step <?php echo $step === 'username' ? 'active' : ($step === 'questions' || $step === 'new_password' ? 'completed' : ''); ?>">1</div>
            <div class="step <?php echo $step === 'questions' ? 'active' : ($step === 'new_password' ? 'completed' : ''); ?>">2</div>
            <div class="step <?php echo $step === 'new_password' ? 'active' : ''; ?>">3</div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <a href="index.php" class="btn">Go to Login</a>
        <?php else: ?>
            <?php if ($step === 'username'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn">Continue</button>
                </form>
                
            <?php elseif ($step === 'questions' && $user): ?>
                <form method="POST">
                    <div class="security-question">
                        <h4>Security Question:</h4>
                        <p><?php echo htmlspecialchars($user['security_question']); ?></p>
                        <input type="text" name="answer1" placeholder="Your answer" required>
                    </div>
                    
                    <button type="submit" class="btn">Verify Answer</button>
                </form>
                
            <?php elseif ($step === 'new_password'): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" 
                               placeholder="Enter new password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               placeholder="Confirm new password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <a href="index.php" class="btn btn-secondary">Back to Login</a>
        <?php endif; ?>
    </div>
    
    <script>
        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (newPassword && confirmPassword) {
            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Passwords do not match');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
        }
    </script>
</body>
</html> 