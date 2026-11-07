<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$pdo = getDBConnection();

// Handle Auto Pay deletion
if (isset($_POST['delete_auto_pay'])) {
    $autoPayId = $_POST['auto_pay_id'];
    $stmt = $pdo->prepare("DELETE FROM auto_pay WHERE id = ? AND user_id = ?");
    $stmt->execute([$autoPayId, $_SESSION['user_id']]);
    header('Location: auto_pay_management.php?message=deleted');
    exit;
}

// Get user's Auto Pay settings
$stmt = $pdo->prepare("
    SELECT ap.*, a.account_type, a.account_number
    FROM auto_pay ap
    JOIN accounts a ON ap.from_account_id = a.id
    WHERE ap.user_id = ?
    ORDER BY ap.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$autoPaySettings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexus Pay - Auto Pay Management</title>
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
            background: white;
            color: #333;
            padding: 20px 0;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
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
        
        .back-btn {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #e1e5e9;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            background: #e9ecef;
            transform: translateY(-1px);
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .card h2 {
            color: #1e3c72;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .auto-pay-item {
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        
        .auto-pay-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .auto-pay-title {
            font-weight: bold;
            color: #1e3c72;
        }
        
        .auto-pay-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .auto-pay-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 2px;
        }
        
        .detail-value {
            font-weight: 500;
            color: #333;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .empty-state {
            text-align: center;
            color: #666;
            padding: 40px 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #152f5b;
            transform: translateY(-1px);
        }
        
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>🏦 Nexus Pay</h1>
            </div>
            <a href="index.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>🔄 Auto Pay Management</h2>
            
            <?php if (isset($_GET['message']) && $_GET['message'] === 'deleted'): ?>
                <div class="message success">
                    Auto Pay setting deleted successfully.
                </div>
            <?php endif; ?>
            
            <?php if (empty($autoPaySettings)): ?>
                <div class="empty-state">
                    <h3>No Auto Pay Settings</h3>
                    <p>You haven't set up any automatic payments yet.</p>
                    <a href="index.php" class="btn">Set Up Auto Pay</a>
                </div>
            <?php else: ?>
                <?php foreach ($autoPaySettings as $autoPay): ?>
                    <div class="auto-pay-item">
                        <div class="auto-pay-header">
                            <div class="auto-pay-title">
                                Auto Pay to <?php echo htmlspecialchars($autoPay['to_username']); ?>
                            </div>
                            <span class="auto-pay-status status-active">Active</span>
                        </div>
                        
                        <div class="auto-pay-details">
                            <div class="detail-item">
                                <span class="detail-label">From Account</span>
                                <span class="detail-value">
                                    <?php echo ucfirst($autoPay['account_type']); ?> - ****<?php echo substr($autoPay['account_number'], -4); ?>
                                </span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Amount</span>
                                <span class="detail-value">$<?php echo number_format($autoPay['amount'], 2); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Frequency</span>
                                <span class="detail-value"><?php echo ucfirst($autoPay['frequency']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Next Payment</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y g:i A', strtotime($autoPay['next_payment_date'])); ?>
                                </span>
                            </div>
                            
                            <?php if ($autoPay['last_payment_date']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Last Payment</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y g:i A', strtotime($autoPay['last_payment_date'])); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($autoPay['description']) && $autoPay['description']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Description</span>
                                <span class="detail-value"><?php echo htmlspecialchars($autoPay['description']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this Auto Pay setting?')">
                            <input type="hidden" name="auto_pay_id" value="<?php echo $autoPay['id']; ?>">
                            <button type="submit" name="delete_auto_pay" class="delete-btn">Delete Auto Pay</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 