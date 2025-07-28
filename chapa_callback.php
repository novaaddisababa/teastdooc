<?php
require_once 'db_connect.php';
require_once 'security.php';
require_once 'chapa_config.php';

// Initialize secure session
Security::secureSessionStart();

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

try {
    // Get transaction reference from URL
    $txRef = Security::sanitizeInput($_GET['tx_ref'] ?? '');
    $status = Security::sanitizeInput($_GET['status'] ?? '');
    
    if (empty($txRef)) {
        throw new Exception("Invalid transaction reference");
    }
    
    // Verify transaction with Chapa API
    $chapa = ChapaConfig::getChapa();
    $response = $chapa->verifyTransaction($txRef);
    
    if ($response->getStatus() === 'success' && $response->getData()['status'] === 'success') {
        $transactionData = $response->getData();
        
        // Update transaction in database
        $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'completed', chapa_response = ?, updated_at = NOW() WHERE transaction_ref = ?");
        $stmt->execute([json_encode($transactionData), $txRef]);
        
        // Get transaction details
        $stmt = $conn->prepare("SELECT * FROM chapa_transactions WHERE transaction_ref = ? AND status = 'completed'");
        $stmt->execute([$txRef]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction && $transaction['type'] === 'deposit') {
            // Update user balance
            $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$transaction['amount'], $transaction['user_id']]);
            
            // Record successful transaction
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, reference) VALUES (?, ?, ?, ?)");
            $stmt->execute([$transaction['user_id'], $transaction['amount'], 'deposit', $txRef]);
            
            // Update session balance if it's the current user
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $transaction['user_id']) {
                $_SESSION['balance'] = ($_SESSION['balance'] ?? 0) + $transaction['amount'];
            }
            
            $_SESSION['success'] = "Deposit of " . number_format($transaction['amount'], 2) . " ETB completed successfully!";
        }
        
    } else {
        // Transaction failed
        $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'failed', chapa_response = ?, updated_at = NOW() WHERE transaction_ref = ?");
        $stmt->execute([json_encode($response->getData()), $txRef]);
        
        $_SESSION['error'] = "Payment verification failed. Please contact support if money was deducted.";
    }
    
} catch (Exception $e) {
    error_log("Chapa callback error: " . $e->getMessage());
    $_SESSION['error'] = "Error processing payment callback: " . $e->getMessage();
}

// Redirect back to main page
header("Location: index.php");
exit();
?>
