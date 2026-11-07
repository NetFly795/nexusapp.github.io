<?php
session_start();

// Check if config exists, redirect to install if not
if (!file_exists('config.php')) {
    header('Location: install.php');
    exit;
}

require_once 'config.php';

// Get database connection
$pdo = getDBConnection();

$message = '';
$error = '';

// Handle signup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($username) || empty($password)) {
        $error = "All fields are required.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Username already exists. Please choose a different one.";
        } else {
                // Get security questions
                $security_question = trim($_POST['security_question'] ?? '');
                $security_answer = trim($_POST['security_answer'] ?? '');
                
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, security_question, security_answer) VALUES (?, ?, 'user', ?, ?)");
                
                if ($stmt->execute([$username, $hashed_password, $security_question, $security_answer])) {
                    $user_id = $pdo->lastInsertId();
                    
                    // Generate account numbers
                    function generateAccountNumber() {
                        global $pdo;
                        do {
                            $number = sprintf('%04d-%04d-%04d-%04d', 
                                rand(1000, 9999), rand(1000, 9999), 
                                rand(1000, 9999), rand(1000, 9999));
                            
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE account_number = ?");
                            $stmt->execute([$number]);
                        } while ($stmt->fetchColumn() > 0);
                        
                        return $number;
                    }
                    
                    // Create checking account
                    $checking_number = generateAccountNumber();
                    $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, account_type, balance) VALUES (?, ?, 'checking', 0.00)");
                    $stmt->execute([$user_id, $checking_number]);
                    
                    // Create savings account
                    $savings_number = generateAccountNumber();
                    $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, account_type, balance) VALUES (?, ?, 'savings', 0.00)");
                    $stmt->execute([$user_id, $savings_number]);
                    
                    $message = "Connected Account created successfully! You now have a checking and savings Connected Account. You can now login.";
                } else {
                    $error = "Error creating account. Please try again.";
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo BANK_NAME; ?> - Sign Up</title>
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
        
        .signup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .logo h2 {
            color: #666;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .logo .subtitle {
            color: #666;
            font-size: 14px;
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
        
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="email"]:focus, input[type="password"]:focus, select:focus {
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
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .login-link a {
            color: #1e3c72;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
        
        .password-requirements h4 {
            color: #1e3c72;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .password-requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .password-requirements li {
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }
        
        .password-requirements li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
        
        .strength-meter {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 4px;
            background: #e1e5e9;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .strength-weak { background-color: #dc3545; }
        .strength-medium { background-color: #ffc107; }
        .strength-strong { background-color: #28a745; }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="logo">
            <h1>Connected Center</h1>
            <div class="subtitle">Create a Connected Account</div>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            

            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <div class="strength-meter">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul>
                    <li>At least 6 characters long</li>
                    <li>Should be unique and secure</li>
                    <li>Consider using a mix of letters, numbers, and symbols</li>
                </ul>
            </div>
            
            <div class="form-group">
                <label for="security_question">Security Question</label>
                <select id="security_question" name="security_question" required>
                    <option value="">Select a security question</option>
                    <option value="What was the name of your first pet?">What was the name of your first pet?</option>
                    <option value="In what city were you born?">In what city were you born?</option>
                    <option value="What was your mother's maiden name?">What was your mother's maiden name?</option>
                    <option value="What was the name of your first school?">What was the name of your first school?</option>
                    <option value="What is your favorite color?">What is your favorite color?</option>
                    <option value="What was your childhood nickname?">What was your childhood nickname?</option>
                    <option value="What is the name of the street you grew up on?">What is the name of the street you grew up on?</option>
                    <option value="What is your favorite movie?">What is your favorite movie?</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="security_answer">Answer to Security Question</label>
                <input type="text" id="security_answer" name="security_answer" required>
            </div>
            
            <button type="submit" class="btn">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="index.php">Sign in here</a>
        </div>
    </div>
    
    <script>
        // Password strength meter
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthFill = document.getElementById('strengthFill');
            let strength = 0;
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]/)) strength += 25;
            if (password.match(/[A-Z]/)) strength += 25;
            if (password.match(/[0-9]/)) strength += 25;
            
            strengthFill.style.width = strength + '%';
            strengthFill.className = 'strength-fill';
            
            if (strength <= 25) {
                strengthFill.classList.add('strength-weak');
            } else if (strength <= 75) {
                strengthFill.classList.add('strength-medium');
            } else {
                strengthFill.classList.add('strength-strong');
            }
        });
        
        // Password confirmation check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e1e5e9';
            }
        });
    </script>
</body>
</html> 