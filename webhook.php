<?php
/**
 * Chapa Payment Webhook Endpoint
 * Handles payment confirmation webhooks from Chapa
 */

// Include the main index file for shared classes
require_once __DIR__ . '/chapa_index.php';

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ResponseHandler::error('Method not allowed. Use POST.', 405);
}

try {
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_CHAPA_SIGNATURE'] ?? '';
    
    // Verify webhook signature
    $webhookSecret = $_ENV['CHAPA_WEBHOOK_SECRET'] ?? getenv('CHAPA_WEBHOOK_SECRET') ?? 'your-webhook-secret';
    
    if (!WebhookVerifier::verifySignature($payload, $signature, $webhookSecret)) {
        error_log('Invalid webhook signature received');
        ResponseHandler::error('Invalid webhook signature', 401);
    }
    
    $data = json_decode($payload, true);
    
    if (!$data) {
        error_log('Invalid webhook payload received: ' . $payload);
        ResponseHandler::error('Invalid webhook payload');
    }
    
    // Process webhook based on event type
    $event = $data['event'] ?? 'charge.success';
    $transactionData = $data['data'] ?? [];
    
    // Log webhook event
    error_log("Webhook received - Event: {$event}, Data: " . json_encode($transactionData));
    
    switch ($event) {
        case 'charge.success':
            handleSuccessfulPayment($transactionData);
            break;
            
        case 'charge.failed':
            handleFailedPayment($transactionData);
            break;
            
        case 'transfer.success':
            handleSuccessfulTransfer($transactionData);
            break;
            
        case 'transfer.failed':
            handleFailedTransfer($transactionData);
            break;
            
        default:
            error_log('Unknown webhook event: ' . $event);
            break;
    }
    
    ResponseHandler::success(['received' => true], 'Webhook processed successfully');
    
} catch (Exception $e) {
    error_log('Webhook processing error: ' . $e->getMessage());
    ResponseHandler::error('Webhook processing failed', 500);
}

/**
 * Handle successful payment webhook
 */
function handleSuccessfulPayment($data) {
    $txRef = $data['tx_ref'] ?? '';
    $amount = $data['amount'] ?? 0;
    $email = $data['email'] ?? '';
    $status = $data['status'] ?? 'success';
    
    // Log successful payment
    error_log("Payment successful: {$txRef} - Amount: {$amount} - Email: {$email}");
    
    // TODO: Implement your database update logic here
    // Example:
    // updatePaymentStatus($txRef, 'success', $amount, $email);
    // creditUserAccount($email, $amount);
    
    // You can also send notifications, emails, etc.
    // sendPaymentConfirmationEmail($email, $amount, $txRef);
}

/**
 * Handle failed payment webhook
 */
function handleFailedPayment($data) {
    $txRef = $data['tx_ref'] ?? '';
    $reason = $data['reason'] ?? 'Unknown error';
    $email = $data['email'] ?? '';
    
    // Log failed payment
    error_log("Payment failed: {$txRef} - Reason: {$reason} - Email: {$email}");
    
    // TODO: Implement your database update logic here
    // Example:
    // updatePaymentStatus($txRef, 'failed', 0, $email, $reason);
    
    // You can also send failure notifications
    // sendPaymentFailureEmail($email, $reason, $txRef);
}

/**
 * Handle successful transfer webhook
 */
function handleSuccessfulTransfer($data) {
    $reference = $data['reference'] ?? '';
    $amount = $data['amount'] ?? 0;
    $accountNumber = $data['account_number'] ?? '';
    
    // Log successful transfer
    error_log("Transfer successful: {$reference} - Amount: {$amount} - Account: {$accountNumber}");
    
    // TODO: Implement your database update logic here
    // Example:
    // updateTransferStatus($reference, 'success', $amount, $accountNumber);
    // debitUserAccount($accountNumber, $amount);
    
    // You can also send transfer confirmation
    // sendTransferConfirmationEmail($accountNumber, $amount, $reference);
}

/**
 * Handle failed transfer webhook
 */
function handleFailedTransfer($data) {
    $reference = $data['reference'] ?? '';
    $reason = $data['reason'] ?? 'Unknown error';
    $accountNumber = $data['account_number'] ?? '';
    
    // Log failed transfer
    error_log("Transfer failed: {$reference} - Reason: {$reason} - Account: {$accountNumber}");
    
    // TODO: Implement your database update logic here
    // Example:
    // updateTransferStatus($reference, 'failed', 0, $accountNumber, $reason);
    // refundUserAccount($accountNumber, $data['amount'] ?? 0);
    
    // You can also send failure notifications
    // sendTransferFailureEmail($accountNumber, $reason, $reference);
}

/**
 * Example database update function (implement according to your database schema)
 */
function updatePaymentStatus($txRef, $status, $amount = 0, $email = '', $reason = '') {
    // Example implementation - replace with your actual database logic
    /*
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=your_db", $username, $password);
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET status = ?, amount = ?, updated_at = NOW(), failure_reason = ?
            WHERE tx_ref = ?
        ");
        $stmt->execute([$status, $amount, $reason, $txRef]);
        
        if ($status === 'success') {
            // Credit user account
            $stmt = $pdo->prepare("
                UPDATE users 
                SET balance = balance + ? 
                WHERE email = ?
            ");
            $stmt->execute([$amount, $email]);
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
    */
}
?>
