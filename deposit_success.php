<?php
require_once 'db_connect.php';
require_once 'security.php';

// Initialize secure session
Security::secureSessionStart();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$transactionRef = Security::sanitizeInput($_GET['tx_ref'] ?? '');
$transactionDetails = null;

if (!empty($transactionRef)) {
    // Get transaction details
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT ct.*, u.username FROM chapa_transactions ct JOIN users u ON ct.user_id = u.id WHERE ct.transaction_ref = ? AND ct.user_id = ?");
    $stmt->execute([$transactionRef, $_SESSION['user_id']]);
    $transactionDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session balance if transaction is completed
    if ($transactionDetails && $transactionDetails['status'] === 'completed') {
        $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['balance'] = $user['balance'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Success - LuckXXXXXXXXXXX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-container {
            max-width: 600px;
            margin: 3rem auto;
            padding: 3rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 2rem;
            animation: bounce 1s ease-in-out;
        }
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            80% { transform: translateY(-10px); }
        }
        .amount-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #28a745;
            margin: 1rem 0;
        }
        .transaction-details {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-container">
            <?php if ($transactionDetails && $transactionDetails['status'] === 'completed'): ?>
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <h1 class="text-success mb-3">Payment Successful!</h1>
                <p class="lead">Your deposit has been processed successfully.</p>
                
                <div class="amount-display">
                    ETB <?= number_format($transactionDetails['amount'], 2) ?>
                </div>
                
                <div class="transaction-details">
                    <div class="row">
                        <div class="col-md-6">
                            <strong><i class="fas fa-user me-2"></i>Account:</strong><br>
                            <?= htmlspecialchars($transactionDetails['username']) ?>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-wallet me-2"></i>New Balance:</strong><br>
                            ETB <?= number_format($_SESSION['balance'], 2) ?>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <strong><i class="fas fa-tag me-2"></i>Transaction ID:</strong><br>
                            <small><?= htmlspecialchars($transactionDetails['transaction_ref']) ?></small>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-clock me-2"></i>Date:</strong><br>
                            <?= date('M d, Y H:i', strtotime($transactionDetails['created_at'])) ?>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-success">
                    <i class="fas fa-info-circle me-2"></i>
                    Your account balance has been updated. You can now start playing!
                </div>
                
            <?php else: ?>
                <div class="text-warning" style="font-size: 4rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                
                <h1 class="text-warning mb-3">Payment Processing</h1>
                <p class="lead">Your payment is being processed. Please wait...</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-clock me-2"></i>
                    This may take a few minutes. Your balance will be updated once the payment is confirmed.
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="index.php" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-gamepad me-2"></i>Start Playing
                </a>
                <a href="index.php#transactions" class="btn btn-outline-secondary">
                    <i class="fas fa-history me-2"></i>View Transactions
                </a>
            </div>
            
            <div class="mt-4">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    Secure payment processed by Chapa
                </small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!$transactionDetails || $transactionDetails['status'] !== 'completed'): ?>
    <script>
        // Auto-refresh page every 5 seconds to check payment status
        setTimeout(function() {
            location.reload();
        }, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
