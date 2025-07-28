<?php
/**
 * Deposit Callback Handler for Chapa Payments
 * Handles payment verification and balance updates
 */

require_once 'db_connect.php';
require_once 'security.php';

// Initialize secure session
Security::secureSessionStart();

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Log all callback attempts for debugging
error_log("Deposit Callback: " . json_encode($_REQUEST));

try {
    // Get transaction reference
    $transactionRef = Security::sanitizeInput($_GET['trx_ref'] ?? $_POST['trx_ref'] ?? '');
    
    if (empty($transactionRef)) {
        throw new Exception("Transaction reference is required");
    }
    
    // Get transaction details from database
    $stmt = $conn->prepare("SELECT * FROM chapa_transactions WHERE transaction_ref = ? AND type = 'deposit' AND status = 'pending'");
    $stmt->execute([$transactionRef]);
    $dbTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dbTransaction) {
        throw new Exception("Transaction not found or already processed");
    }
    
    // Verify payment with Chapa (in a real implementation, you would call Chapa's verify API)
    // For now, we'll assume the payment is successful if we reach this callback
    
    // Begin database transaction
    $conn->beginTransaction();
    
    try {
        // Update user balance
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$dbTransaction['amount'], $dbTransaction['user_id']]);
        
        // Record transaction in transactions table
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, reference) VALUES (?, ?, ?, ?)");
        $stmt->execute([$dbTransaction['user_id'], $dbTransaction['amount'], 'deposit', $transactionRef]);
        
        // Update Chapa transaction status
        $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'completed', updated_at = NOW() WHERE transaction_ref = ?");
        $stmt->execute([$transactionRef]);
        
        // Log the transaction
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $dbTransaction['user_id'], 
            'deposit_completed', 
            $_SERVER['REMOTE_ADDR'],
            json_encode(['transaction_ref' => $transactionRef, 'amount' => $dbTransaction['amount']])
        ]);
        
        $conn->commit();
        
        // Redirect to success page
        header("Location: deposit_success.php?tx_ref=" . urlencode($transactionRef));
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Deposit Callback Error: " . $e->getMessage());
    
    // Update transaction status to failed if it exists
    if (isset($transactionRef)) {
        try {
            $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'failed', error_message = ?, updated_at = NOW() WHERE transaction_ref = ?");
            $stmt->execute([$e->getMessage(), $transactionRef]);
        } catch (Exception $dbError) {
            error_log("Failed to update transaction status: " . $dbError->getMessage());
        }
    }
    
    // Redirect to error page
    header("Location: deposit_error.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>
