<?php
session_start();

// Check if config exists
if (!file_exists('../connected/config.php')) {
    die('Connected Center is not properly configured. Please contact the administrator.');
}

require_once '../connected/config.php';

// Get database connection
$pdo = getDBConnection();

// Check if user is logged in
$user_info = null;
$recent_games = [];

if (isset($_SESSION['user_id'])) {
    // Check if user still exists and is not banned
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'banned'");
    $stmt->execute([$_SESSION['user_id']]);
    $user_info = $stmt->fetch();
    
    if ($user_info) {
        // Get recent online multiplayer games from browser storage (will be set by JavaScript)
        $recent_games = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connected Center - Game Profile</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: white;
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        
        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .display-name {
            font-size: 28px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .username {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }
        
        .recent-games {
            margin-top: 30px;
        }
        
        .recent-games h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .game-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .game-item {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            transition: transform 0.2s ease;
        }
        
        .game-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .game-thumbnail {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
        }
        
        .game-info {
            flex: 1;
            text-align: left;
        }
        
        .game-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .game-category {
            font-size: 12px;
            color: #666;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 4px;
        }
        
        .play-time {
            font-size: 12px;
            color: #999;
        }
        
        .not-signed-in {
            text-align: center;
            color: #666;
        }
        
        .not-signed-in h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .sign-in-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .sign-in-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
    <script>
        // Load recent games from browser storage
        function loadRecentGames() {
            const recentGames = JSON.parse(localStorage.getItem('recentOnlineMultiplayerGames') || '[]');
            const gameList = document.getElementById('recentGamesList');
            
            if (recentGames.length === 0) {
                gameList.innerHTML = '<p style="color: #666; text-align: center;">No recent online multiplayer games</p>';
                return;
            }
            
            gameList.innerHTML = recentGames.map(game => `
                <div class="game-item">
                    <img src="${game.image}" alt="${game.name}" class="game-thumbnail" />
                    <div class="game-info">
                        <div class="game-name">${game.name}</div>
                        <div class="game-category">${game.category}</div>
                        <div class="play-time">${game.playTime}</div>
                    </div>
                </div>
            `).join('');
        }
        
        // Load games when page loads
        document.addEventListener('DOMContentLoaded', loadRecentGames);
    </script>
</head>
<body>
    <div class="profile-container">
        <?php if ($user_info): ?>
            <div class="profile-picture">
                <?php echo strtoupper(substr($user_info['username'], 0, 1)); ?>
            </div>
            
            <div class="display-name">Connected Center</div>
            <div class="username">@<?php echo htmlspecialchars($user_info['username']); ?></div>
            
            <div class="recent-games">
                <h3>Last 3 Online Multiplayer Games</h3>
                <div class="game-list" id="recentGamesList">
                    <!-- Games will be loaded from browser storage -->
                </div>
            </div>
        <?php else: ?>
            <div class="not-signed-in">
                <h2>Not Signed In</h2>
                <p>Please sign in to view your Connected Center profile.</p>
                <a href="../connected/embed.php" class="sign-in-btn">Sign In</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
