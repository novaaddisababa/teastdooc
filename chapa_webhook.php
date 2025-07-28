<?php
require_once 'db_connect.php';
require_once 'chapa_config.php';

// Set proper headers for webhook response
header('Content-Type: application/json');

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

// Get raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Log webhook for debugging
ChapaConfig::logTransaction('Webhook received', ['payload' => $input]);

try {
    // Verify webhook signature if configured
    $signature = $_SERVER['HTTP_X_CHAPA_SIGNATURE'] ?? '';
    if ($signature && !ChapaConfig::verifyWebhookSignature($input, $signature)) {
        throw new Exception("Invalid webhook signature");
    }
    
    if (!$data || !isset($data['tx_ref'])) {
        throw new Exception("Invalid webhook data - missing tx_ref");
    }
    
    $txRef = $data['tx_ref'];
    $status = $data['status'] ?? '';
    $amount = $data['amount'] ?? 0;
    $currency = $data['currency'] ?? 'ETB';
    
    ChapaConfig::logTransaction('Processing webhook', [
        'tx_ref' => $txRef,
        'status' => $status,
        'amount' => $amount
    ]);
    
    // Verify transaction exists in our database
    $stmt = $conn->prepare("SELECT * FROM chapa_transactions WHERE transaction_ref = ?");
    $stmt->execute([$txRef]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        throw new Exception("Transaction not found: " . $txRef);
    }
    
    if ($status === 'success') {
        // Verify transaction with Chapa API to ensure authenticity
        $chapa = ChapaConfig::getChapa();
        $response = $chapa->verifyTransaction($txRef);
        
        if ($response->getStatus() === 'success' && $response->getData()['status'] === 'success') {
            $transactionData = $response->getData();
            
            // Update transaction status
            $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'completed', chapa_response = ?, updated_at = NOW() WHERE transaction_ref = ?");
            $stmt->execute([json_encode($transactionData), $txRef]);
            
            if ($transaction['type'] === 'deposit') {
                // Update user balance
                $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                
                // Record successful transaction
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, reference) VALUES (?, ?, ?, ?)");
                $stmt->execute([$transaction['user_id'], $transaction['amount'], 'deposit', $txRef]);
                
                // Log successful deposit
                $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $transaction['user_id'], 
                    'deposit_completed', 
                    $_SERVER['REMOTE_ADDR'] ?? 'webhook',
                    json_encode(['transaction_ref' => $txRef, 'amount' => $transaction['amount']])
                ]);
            }
        } else {
            ChapaConfig::logTransaction('Transaction verification failed', [
                'tx_ref' => $txRef,
                'chapa_response' => $response->getMessage()
            ]);
            throw new Exception("Failed to verify transaction with Chapa API: " . $response->getMessage());
        }
    } elseif ($status === 'failed' || $status === 'cancelled') {
        // Transaction failed or cancelled
        $stmt = $conn->prepare("UPDATE chapa_transactions SET status = ?, chapa_response = ?, updated_at = NOW() WHERE transaction_ref = ?");
        $stmt->execute([$status, json_encode($data), $txRef]);
        
        ChapaConfig::logTransaction('Transaction failed/cancelled', [
            'tx_ref' => $txRef,
            'status' => $status
        ]);
    } else {
        // Unknown status
        ChapaConfig::logTransaction('Unknown transaction status', [
            'tx_ref' => $txRef,
            'status' => $status
        ]);
    }
    
    // Respond with success
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook processed successfully',
        'tx_ref' => $txRef
    ]);
    
} catch (Exception $e) {
    ChapaConfig::logTransaction('Webhook error', [
        'error' => $e->getMessage(),
        'tx_ref' => $txRef ?? 'unknown'
    ]);
    
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Webhook processing failed',
        'error' => $e->getMessage()
    ]);
}
?>
