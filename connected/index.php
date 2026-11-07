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
        }
    } else {
        $login_error = "Invalid username or password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Get user data if logged in
$user_accounts = [];
$recent_transactions = [];
$total_balance = 0;

if (isset($_SESSION['user_id'])) {
    // Check if user still exists and is not banned
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'banned'");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
    
    if (!$current_user) {
        // User has been deleted or banned, sign them out
        session_destroy();
        header('Location: index.php?message=account_inactive');
        exit;
    }
    
    // Get user accounts
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_accounts = $stmt->fetchAll();
    
    // Calculate total balance
    foreach ($user_accounts as $account) {
        $total_balance += $account['balance'];
    }
    
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
            WHERE a1.user_id = ? OR a2.user_id = ?
            ORDER BY t.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $recent_transactions = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo BANK_NAME; ?> - Dashboard</title>
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
            color: #333;
            padding: 20px 0;
            border-radius: 15px;
            margin: 20px;
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .logout-btn {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #e1e5e9;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 40px;
        }
        
        .login-container h2 {
            text-align: center;
            color: #1e3c72;
            margin-bottom: 30px;
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
            border-color: #1e3c72;
        }
        
        .btn {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
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
        }
        
        .balance-card {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            text-align: center;
        }
        
        .balance-card h3 {
            color: white;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .balance-amount {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .account-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .account-item:last-child {
            border-bottom: none;
        }
        
        .account-info h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .account-number {
            color: #666;
            font-size: 14px;
        }
        
        .account-balance {
            font-weight: bold;
            color: #1e3c72;
        }
        
        .auto-pay-btn {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }
        
        .auto-pay-btn:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-info h4 {
            color: #333;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .transaction-date {
            color: #666;
            font-size: 12px;
        }
        
        .transaction-amount {
            font-weight: bold;
        }
        
        .transaction-amount.deposit {
            color: #28a745;
        }
        
        .transaction-amount.withdrawal {
            color: #dc3545;
        }
        
        .transaction-amount.transfer {
            color: #ffc107;
        }
        
        .transaction-amount.outgoing-transfer {
            color: #ffc107;
            font-weight: bold;
        }
        
        .transaction-detail {
            font-size: 12px;
            color: #666;
            margin-top: 2px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .action-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
        }
        
        .action-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .action-card h4 {
            color: #1e3c72;
            margin-bottom: 10px;
        }
        
        .action-card p {
            color: #666;
            font-size: 14px;
        }
        
        .modal {
            display: none !important;
            position: fixed !important;
            z-index: 9999 !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: 100% !important;
            background-color: rgba(0,0,0,0.5) !important;
            overflow-y: auto !important;
            padding: 20px !important;
            box-sizing: border-box !important;
        }
        
        .modal.show {
            display: block !important;
        }
        
        .modal-content {
            background-color: white;
            margin: 20px auto !important;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
            max-height: calc(100vh - 40px) !important;
            overflow-y: auto !important;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-content h2 {
            color: #1e3c72;
            margin-bottom: 20px;
        }
        
        .modal-content select,
        .modal-content input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .modal-content select:focus,
        .modal-content input:focus {
            outline: none;
            border-color: #1e3c72;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
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
                <h1></h1>
            </div>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> | <b>Nexus Pay</b></span>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin.php" class="logout-btn">Admin Panel</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <!-- Login Form -->
            <div class="login-container">
                <h2>Connected Center</h2>
                <p style="text-align: center; color: #666; margin-bottom: 30px;">Login to your Connected Account</p>
                
                <?php if (isset($_GET['message']) && $_GET['message'] === 'account_inactive'): ?>
                    <div class="error">
                        Your account has been deactivated. Please contact support if you believe this is an error.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($login_error)): ?>
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
                
                <div style="text-align: center; margin-top: 20px; color: #666;">
                    Don't have an account? <a href="signup.php" style="color: #1e3c72; text-decoration: none; font-weight: 500;">Sign up here</a>
                    <br><br>
                    <a href="forgot_password.php" style="color: #1e3c72; text-decoration: none; font-weight: 500;">I forgot my password</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Dashboard -->
            <div class="dashboard-grid">
                <div class="card balance-card">
                    <h3>Total Balance</h3>
                    <div class="balance-amount">$<?php echo number_format($total_balance, 2); ?></div>
                    <p>Across all Connected Accounts</p>
                </div>
                
                <div class="card">
                    <h3>📊 Connected Account Overview</h3>
                    <?php if (empty($user_accounts)): ?>
                        <p style="color: #666; text-align: center;">No Connected Accounts found</p>
                    <?php else: ?>
                        <?php foreach ($user_accounts as $account): ?>
                            <div class="account-item">
                                <div class="account-info">
                                    <h4><?php echo ucfirst($account['account_type']); ?> Connected Account</h4>
                                    <div class="account-number">****<?php echo substr($account['account_number'], -4); ?></div>
                                </div>
                                <div class="account-balance">$<?php echo number_format($account['balance'], 2); ?></div>
                                <button class="auto-pay-btn" onclick="openAutoPayModal(<?php echo $account['id']; ?>, '<?php echo ucfirst($account['account_type']); ?>')">
                                    Auto Pay
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-grid">
                <div class="card">
                    <h3>📈 Recent Transactions</h3>
                    <?php if (empty($recent_transactions)): ?>
                        <p style="color: #666; text-align: center;">No recent transactions</p>
                    <?php else: ?>
                        <?php foreach ($recent_transactions as $transaction): ?>
                            <?php
                            // Determine if this is an outgoing transfer (user is the sender)
                            $is_outgoing = ($transaction['from_username'] === $_SESSION['username']);
                            $is_incoming = ($transaction['to_username'] === $_SESSION['username']);
                            $is_external_transfer = ($transaction['from_username'] && $transaction['to_username'] && $transaction['from_username'] !== $transaction['to_username']);
                            ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <h4><?php echo htmlspecialchars($transaction['description'] ?: ucfirst($transaction['transaction_type'])); ?></h4>
                                    <div class="transaction-date"><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></div>
                                    <?php if ($is_external_transfer): ?>
                                        <div class="transaction-detail">
                                            <?php if ($is_outgoing): ?>
                                                To: <?php echo htmlspecialchars($transaction['to_username']); ?>
                                            <?php else: ?>
                                                From: <?php echo htmlspecialchars($transaction['from_username']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="transaction-amount <?php echo $is_outgoing && $is_external_transfer ? 'outgoing-transfer' : $transaction['transaction_type']; ?>">
                                    <?php if ($is_outgoing && $is_external_transfer): ?>
                                        -$<?php echo number_format($transaction['amount'], 2); ?>
                                    <?php else: ?>
                                        <?php echo $transaction['transaction_type'] === 'withdrawal' ? '-' : '+'; ?>$<?php echo number_format($transaction['amount'], 2); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="card">
                    <h3>🔒 Security Status</h3>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Account Security</span>
                            <span style="color: #28a745;">✓ Secure</span>
                        </div>
                        <div style="background: #e1e5e9; height: 6px; border-radius: 3px;">
                            <div style="background: #28a745; height: 100%; width: 95%; border-radius: 3px;"></div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        
                        <div style="background: white; height: 6px; border-radius: 3px;">
                            <div style="background: white; height: 100%; width: 60%; border-radius: 3px;"></div>
                        </div>
                    </div>
                    
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <span>Last Login</span>
                            <span style="color: #666;"><?php echo date('M j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="quick-actions">
                <div class="action-card" onclick="console.log('Button clicked'); openTransferModal();">
                    <div class="action-icon">💳</div>
                    <h4>Transfer Money</h4>
                    <p>Send money between Connected Accounts or to other users</p>
                </div>
                
                
                
                <div class="action-card" onclick="window.location.href='auto_pay_management.php'">
                    <div class="action-icon">🔄</div>
                    <h4>Auto Pay Management</h4>
                    <p>View and manage your automatic payments</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Auto Pay Modal -->
    <div id="autoPayModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAutoPayModal()">&times;</span>
            <h2>🔄 Auto Pay Setup</h2>
            
            <form id="autoPayForm">
                <input type="hidden" id="autoPayAccountId" name="accountId">
                
                <div class="form-group">
                    <label for="autoPayAccountType">Account:</label>
                    <input type="text" id="autoPayAccountType" readonly style="background: #f8f9fa;">
                </div>
                
                <div class="form-group">
                    <label for="toUsername">To Username:</label>
                    <input type="text" id="toUsername" name="toUsername" placeholder="Enter username" required>
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount ($):</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
                
                <div class="form-group">
                    <label for="frequency">Frequency:</label>
                    <select id="frequency" name="frequency" required>
                        <option value="">Select frequency</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="description">Description (optional):</label>
                    <input type="text" id="description" name="description" placeholder="What's this payment for?">
                </div>
                
                <button type="submit" class="btn">Set Up Auto Pay</button>
            </form>
        </div>
    </div>
    
    <!-- Transfer Modal -->
    <div id="transferModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTransferModal()">&times;</span>
            <h2>💳 Transfer Money</h2>
            
            <form id="transferForm">
                <div class="form-group">
                    <label for="transferType">Transfer Type:</label>
                    <select id="transferType" name="transferType" onchange="updateTransferOptions()" required>
                        <option value="">Select transfer type</option>
                        <option value="internal">Between my Connected Accounts</option>
                        <option value="external">To another user</option>
                    </select>
                </div>
                
                <div id="internalTransfer" style="display: none;">
                    <div class="form-group">
                        <label for="fromAccount">From Account:</label>
                        <select id="fromAccount" name="fromAccount">
                            <option value="">Select account</option>
                            <?php foreach ($user_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo ucfirst($account['account_type']); ?> - $<?php echo number_format($account['balance'], 2); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="toAccount">To Account:</label>
                        <select id="toAccount" name="toAccount">
                            <option value="">Select account</option>
                            <?php foreach ($user_accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"><?php echo ucfirst($account['account_type']); ?> - $<?php echo number_format($account['balance'], 2); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div id="externalTransfer" style="display: none;">
                    <div class="form-group">
                        <label for="fromAccountExternal">From Account:</label>
                        <select id="fromAccountExternal" name="fromAccountExternal">
                            <option value="">Select account</option>
                            <?php foreach ($user_accounts as $account): ?>
                                <?php if ($account['account_type'] === 'checking'): ?>
                                    <option value="<?php echo $account['id']; ?>"><?php echo ucfirst($account['account_type']); ?> - $<?php echo number_format($account['balance'], 2); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="toUsername">To Username:</label>
                        <input type="text" id="toUsername" name="toUsername" placeholder="Enter username">
                    </div>
                    
                    <div class="form-group">
                        <label for="toAccountType">To Account Type:</label>
                        <select id="toAccountType" name="toAccountType">
                            <option value="">Select account type</option>
                            <option value="checking">Checking</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount ($):</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="description">Description (optional):</label>
                    <input type="text" id="description" name="description" placeholder="What's this transfer for?">
                </div>
                
                <button type="submit" class="btn">Transfer Money</button>
            </form>
        </div>
    </div>



    <script>
        // Periodic check for account status (every 30 seconds)
        if (document.querySelector('.dashboard-grid')) {
            setInterval(function() {
                fetch('check_status.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'inactive') {
                            window.location.href = 'index.php?message=account_inactive';
                        }
                    })
                    .catch(error => {
                        console.log('Status check failed:', error);
                    });
            }, 30000); // Check every 30 seconds
        }
        
        function openAutoPayModal(accountId, accountType) {
            const modal = document.getElementById('autoPayModal');
            document.getElementById('autoPayAccountId').value = accountId;
            document.getElementById('autoPayAccountType').value = accountType;
            modal.classList.add('show');
        }
        
        function closeAutoPayModal() {
            const modal = document.getElementById('autoPayModal');
            modal.classList.remove('show');
            document.getElementById('autoPayForm').reset();
        }
        
        function openTransferModal() {
            console.log('openTransferModal called');
            const modal = document.getElementById('transferModal');
            if (modal) {
                modal.classList.add('show');
                console.log('Modal should be visible now');
            } else {
                console.error('Modal element not found');
            }
        }
        
        function closeTransferModal() {
            const modal = document.getElementById('transferModal');
            if (modal) {
                modal.classList.remove('show');
            }
            document.getElementById('transferForm').reset();
            document.getElementById('internalTransfer').style.display = 'none';
            document.getElementById('externalTransfer').style.display = 'none';
        }
        
        function updateTransferOptions() {
            const transferType = document.getElementById('transferType').value;
            const internalDiv = document.getElementById('internalTransfer');
            const externalDiv = document.getElementById('externalTransfer');
            
            // Hide both sections
            internalDiv.style.display = 'none';
            externalDiv.style.display = 'none';
            
            // Remove required attributes from all fields
            document.getElementById('fromAccount').removeAttribute('required');
            document.getElementById('toAccount').removeAttribute('required');
            document.getElementById('fromAccountExternal').removeAttribute('required');
            document.getElementById('toUsername').removeAttribute('required');
            document.getElementById('toAccountType').removeAttribute('required');
            document.getElementById('amount').removeAttribute('required');
            
            if (transferType === 'internal') {
                internalDiv.style.display = 'block';
                // Add required attributes for internal transfer
                document.getElementById('fromAccount').setAttribute('required', 'required');
                document.getElementById('toAccount').setAttribute('required', 'required');
                document.getElementById('amount').setAttribute('required', 'required');
            } else if (transferType === 'external') {
                externalDiv.style.display = 'block';
                // Add required attributes for external transfer
                document.getElementById('fromAccountExternal').setAttribute('required', 'required');
                document.getElementById('toUsername').setAttribute('required', 'required');
                document.getElementById('toAccountType').setAttribute('required', 'required');
                document.getElementById('amount').setAttribute('required', 'required');
            }
        }
        
        document.getElementById('autoPayForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('setup_auto_pay.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Auto Pay setup successfully!');
                    closeAutoPayModal();
                    location.reload();
                } else {
                    alert('Auto Pay setup failed: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                console.error('Error:', error);
            });
        });
        
        document.getElementById('transferForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('transfer_money.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Transfer completed successfully!');
                    closeTransferModal();
                    location.reload();
                } else {
                    alert('Transfer failed: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred. Please try again.');
                console.error('Error:', error);
            });
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const transferModal = document.getElementById('transferModal');
            const autoPayModal = document.getElementById('autoPayModal');
            if (event.target === transferModal) {
                closeTransferModal();
            }
            if (event.target === autoPayModal) {
                closeAutoPayModal();
            }
        }
    </script>
</body>
</html> 