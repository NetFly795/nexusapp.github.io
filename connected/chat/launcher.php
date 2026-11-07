<?php
session_start();

// Check if config exists, redirect to install if not
if (!file_exists('../config.php')) {
    header('Location: ../install.php');
    exit;
}

require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Check if user still exists and is not banned
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'banned'");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    
    if (!$current_user) {
        session_destroy();
        header('Location: ../index.php?message=account_inactive');
        exit;
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get chat statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role != 'banned'");
$stmt->execute();
$total_users = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages");
$stmt->execute();
$total_messages = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_messages = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Chat - Connected Accounts</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .launcher-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .logo {
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 32px;
            color: #007AFF;
            margin-bottom: 10px;
        }
        
        .logo .subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007AFF;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .features {
            margin: 30px 0;
            text-align: left;
        }
        
        .features h3 {
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .feature-icon {
            font-size: 20px;
        }
        
        .feature-text {
            color: #333;
            font-size: 14px;
        }
        
        .btn {
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
            margin: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #e1e5e9;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #007AFF 0%, #5856D6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        
        .user-details h4 {
            color: #333;
            margin-bottom: 2px;
        }
        
        .user-details p {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="launcher-container">
        <div class="logo">
            <h1>💬 Nexus Chat</h1>
            <div class="subtitle">Connected Accounts Messaging</div>
        </div>
        
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                <p>Connected Account</p>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Connected Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_messages; ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $user_messages; ?></div>
                <div class="stat-label">Your Messages</div>
            </div>
        </div>
        
        <div class="features">
            <h3>✨ Features</h3>
            <div class="feature-item">
                <span class="feature-icon">🔒</span>
                <span class="feature-text">Secure messaging with Connected Accounts</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">⚡</span>
                <span class="feature-text">Real-time message delivery</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">📱</span>
                <span class="feature-text">Mobile-responsive design</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">🎨</span>
                <span class="feature-text">iMessage-style interface</span>
            </div>
            <div class="feature-item">
                <span class="feature-icon">🔄</span>
                <span class="feature-text">Auto-refresh conversations</span>
            </div>
        </div>
        
        <a href="index.php" class="btn">🚀 Launch Nexus Chat</a>
        <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>
</body>
</html> 