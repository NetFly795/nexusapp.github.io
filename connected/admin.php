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

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_money':
                $account_id = $_POST['account_id'];
                $amount = floatval($_POST['amount']);
                $description = $_POST['description'] ?: 'Admin deposit';
                
                if ($amount > 0) {
                    // Add transaction
                    $stmt = $pdo->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, 'deposit', ?, ?)");
                    $stmt->execute([$account_id, $amount, $description]);
                    
                    // Update account balance
                    $stmt = $pdo->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
                    $stmt->execute([$amount, $account_id]);
                    
                    $message = "Successfully added $" . number_format($amount, 2) . " to account.";
                }
                break;
                
            case 'ban_user':
                $user_id = $_POST['user_id'];
                // First, let's update the database schema to include 'banned' role
                try {
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'banned') DEFAULT 'user'");
                } catch (PDOException $e) {
                    // Column might already be updated, continue
                }
                $stmt = $pdo->prepare("UPDATE users SET role = 'banned' WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                
                // Get username for notification
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $username = $stmt->fetchColumn();
                
                $message = "User '$username' banned successfully. They will be automatically signed out.";
                break;
                
            case 'unban_user':
                $user_id = $_POST['user_id'];
                // First, let's update the database schema to include 'banned' role
                try {
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'banned') DEFAULT 'user'");
                } catch (PDOException $e) {
                    // Column might already be updated, continue
                }
                $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ? AND role = 'banned'");
                $stmt->execute([$user_id]);
                $message = "User unbanned successfully.";
                break;
                
            case 'delete_user':
                $user_id = $_POST['user_id'];
                
                // Get username for notification
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $username = $stmt->fetchColumn();
                
                // Delete transactions first
                $stmt = $pdo->prepare("DELETE t FROM transactions t JOIN accounts a ON t.account_id = a.id WHERE a.user_id = ?");
                $stmt->execute([$user_id]);
                // Delete accounts
                $stmt = $pdo->prepare("DELETE FROM accounts WHERE user_id = ?");
                $stmt->execute([$user_id]);
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                $message = "User '$username' and all associated data deleted successfully. They will be automatically signed out.";
                break;
                
            case 'create_account':
                $user_id = $_POST['user_id'];
                $account_type = $_POST['account_type'];
                $account_number = generateAccountNumber();
                $initial_balance = floatval($_POST['initial_balance']);
                
                $stmt = $pdo->prepare("INSERT INTO accounts (user_id, account_number, account_type, balance) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $account_number, $account_type, $initial_balance]);
                
                if ($initial_balance > 0) {
                    $account_id = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("INSERT INTO transactions (account_id, transaction_type, amount, description) VALUES (?, 'deposit', ?, 'Initial deposit')");
                    $stmt->execute([$account_id, $initial_balance]);
                }
                
                $message = "Connected Account created successfully with number: " . $account_number;
                break;
        }
    }
}

// Generate unique account number
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

// Get all users
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();

// Get all accounts with user info
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.role as user_role 
    FROM accounts a 
    JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC
");
$stmt->execute();
$accounts = $stmt->fetchAll();

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, 
           a1.account_type as from_account_type,
           a2.account_type as to_account_type,
           u1.username as from_username,
           u2.username as to_username
    FROM transactions t 
    LEFT JOIN accounts a1 ON t.from_account_id = a1.id 
    LEFT JOIN accounts a2 ON t.to_account_id = a2.id
    LEFT JOIN users u1 ON a1.user_id = u1.id
    LEFT JOIN users u2 ON a2.user_id = u2.id
    ORDER BY t.created_at DESC 
    LIMIT 20
");
$stmt->execute();
$recent_transactions = $stmt->fetchAll();

// Get reported chats
try {
    $stmt = $pdo->prepare("
        SELECT rc.*, c.name as conversation_name, c.conversation_type,
               u.username as reported_by_username,
               (SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = c.id) as participant_count
        FROM reported_chats rc
        JOIN conversations c ON rc.conversation_id = c.id
        JOIN users u ON rc.reported_by = u.id
        ORDER BY rc.created_at DESC
    ");
    $stmt->execute();
    $reported_chats = $stmt->fetchAll();
} catch (PDOException $e) {
    $reported_chats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo BANK_NAME; ?> - Admin Dashboard</title>
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
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
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
        
        .admin-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card h3 {
            color: #1e3c72;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #1e3c72;
        }
        
        .btn {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        input[type="text"], input[type="number"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="number"]:focus, input[type="password"]:focus, select:focus {
            outline: none;
            border-color: #1e3c72;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .modal-content {
            background-color: white;
            margin: 20px auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
        }
        
        .close:hover {
            color: #000;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-admin {
            background: #1e3c72;
            color: white;
        }
        
        .status-user {
            background: #28a745;
            color: white;
        }
        
        .status-banned {
            background: #dc3545;
            color: white;
        }
        
        @media (max-width: 768px) {
            .admin-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>🏦 <?php echo BANK_NAME; ?> - Admin Panel</h1>
            </div>
            <div class="admin-nav">
                <a href="index.php" class="nav-link">← Back to Dashboard</a>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="index.php?logout=1" class="nav-link">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($accounts); ?></div>
                <div class="stat-label">Total Accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format(array_sum(array_column($accounts, 'balance')), 2); ?></div>
                <div class="stat-label">Total Assets</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($recent_transactions); ?></div>
                <div class="stat-label">Recent Transactions</div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="card" style="margin-bottom: 30px;">
            <h3>🔄 System Status</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <h4 style="color: #1e3c72; margin-bottom: 10px;">Auto Sign-out</h4>
                    <p style="color: #28a745; font-weight: 500;">✓ Active</p>
                    <small style="color: #666;">Banned/deleted users are automatically signed out</small>
                </div>
                <div>
                    <h4 style="color: #1e3c72; margin-bottom: 10px;">Account Creation</h4>
                    <p style="color: #28a745; font-weight: 500;">✓ Active</p>
                    <small style="color: #666;">New users get checking & savings accounts</small>
                </div>
                <div>
                    <h4 style="color: #1e3c72; margin-bottom: 10px;">Status Monitoring</h4>
                    <p style="color: #28a745; font-weight: 500;">✓ Active</p>
                    <small style="color: #666;">Real-time account status checks</small>
                </div>
            </div>
        </div>

        <div class="admin-grid">
            <!-- User Management -->
            <div class="card">
                <h3>👥 User Management</h3>
                <button class="btn" onclick="openModal('createAccountModal')">Create Account</button>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php if ($user['role'] !== 'admin'): ?>
                                            <?php if ($user['role'] === 'banned'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="unban_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-small">Unban</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="ban_user">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-small">Ban</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure? This will delete all user data.')">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Account Management -->
            <div class="card">
                <h3>💳 Connected Account Management</h3>
                <button class="btn" onclick="openModal('addMoneyModal')">Add Money</button>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Type</th>
                                <th>Balance</th>
                                <th>Owner</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td>****<?php echo substr($account['account_number'], -4); ?></td>
                                    <td><?php echo ucfirst($account['account_type']); ?></td>
                                    <td>$<?php echo number_format($account['balance'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($account['username']); ?></td>
                                    <td>
                                        <button class="btn btn-small" onclick="openModal('addMoneyModal')">
                                            Add Money
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <h3>📊 Recent Transactions</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Account</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $transaction['transaction_type']; ?>">
                                        <?php echo ucfirst($transaction['transaction_type']); ?>
                                    </span>
                                </td>
                                <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td>****<?php echo substr($transaction['account_number'], -4); ?></td>
                                <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Reported Chats -->
        <div class="card">
            <h3>🚨 Reported Chats</h3>
            <?php if (empty($reported_chats)): ?>
                <p style="text-align: center; color: #666; padding: 20px;">No reported chats</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Conversation</th>
                                <th>Type</th>
                                <th>Reported By</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reported_chats as $report): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></td>
                                    <td>
                                        <?php if ($report['conversation_type'] === 'group'): ?>
                                            👥 <?php echo htmlspecialchars($report['conversation_name']); ?>
                                        <?php else: ?>
                                            💬 Direct Message
                                        <?php endif; ?>
                                        <br>
                                        <small><?php echo $report['participant_count']; ?> participants</small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $report['conversation_type']; ?>">
                                            <?php echo ucfirst($report['conversation_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($report['reported_by_username']); ?></td>
                                    <td><?php echo htmlspecialchars($report['reason']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $report['status']; ?>">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-small" onclick="viewConversation(<?php echo $report['conversation_id']; ?>)">
                                            View
                                        </button>
                                        <button class="btn btn-warning btn-small" onclick="updateReportStatus(<?php echo $report['id']; ?>, 'reviewed')">
                                            Review
                                        </button>
                                        <button class="btn btn-success btn-small" onclick="updateReportStatus(<?php echo $report['id']; ?>, 'resolved')">
                                            Resolve
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Money Modal -->
    <div id="addMoneyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addMoneyModal')">&times;</span>
            <h3>💰 Add Money to Account</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_money">
                
                <div class="form-group">
                    <label for="account_id">Select Account</label>
                    <select name="account_id" id="accountSelect" required>
                        <option value="">Choose an account...</option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>">
                                <?php echo htmlspecialchars($account['username']); ?> - 
                                <?php echo ucfirst($account['account_type']); ?> 
                                (****<?php echo substr($account['account_number'], -4); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount ($)</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" placeholder="Optional description">
                </div>
                
                <button type="submit" class="btn">Add Money</button>
            </form>
        </div>
    </div>

    <!-- Create Account Modal -->
    <div id="createAccountModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('createAccountModal')">&times;</span>
            <h3>🏦 Create New Connected Account</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_account">
                
                <div class="form-group">
                    <label for="user_id">User</label>
                    <select name="user_id" required>
                        <option value="">Select User</option>
                        <?php foreach ($users as $user): ?>
                            <?php if ($user['role'] !== 'banned'): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?> (<?php echo ucfirst($user['role']); ?>)
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="account_type">Connected Account Type</label>
                    <select name="account_type" required>
                        <option value="checking">Checking</option>
                        <option value="savings">Savings</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="initial_balance">Initial Balance ($)</label>
                    <input type="number" name="initial_balance" step="0.01" min="0" value="0" required>
                </div>
                
                <button type="submit" class="btn">Create Account</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function addMoneyToAccount(accountId, username) {
            // This function is no longer needed since we use a dropdown
            // But keeping it for backward compatibility
            openModal('addMoneyModal');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        function viewConversation(conversationId) {
            window.open(`chat/?conversation=${conversationId}`, '_blank');
        }
        
        function updateReportStatus(reportId, status) {
            if (confirm(`Are you sure you want to mark this report as ${status}?`)) {
                fetch('update_report_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        report_id: reportId,
                        status: status
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
    </script>
</body>
</html> 