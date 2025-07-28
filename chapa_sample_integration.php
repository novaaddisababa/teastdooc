<?php
/**
 * Comprehensive Chapa SDK Integration Sample
 * This file demonstrates proper Chapa payment gateway integration
 * 
 * Author: Cascade AI Assistant
 * Date: 2025-07-26
 */

require_once __DIR__ . '/chapa-php/vendor/autoload.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/chapa_config.php';

use Chapa\Chapa;
use Chapa\Model\PostData;

class ChapaIntegrationSample {
    
    private $db;
    private $conn;
    private $chapa;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
        $this->chapa = ChapaConfig::getChapa();
    }
    
    /**
     * Initialize a payment with Chapa
     * 
     * @param float $amount Payment amount
     * @param string $email Customer email
     * @param string $firstName Customer first name
     * @param string $lastName Customer last name
     * @param string $phone Customer phone number
     * @param int $userId User ID from your system
     * @return array Payment initialization result
     */
    public function initializePayment($amount, $email, $firstName, $lastName, $phone, $userId) {
        try {
            // Validate input parameters
            if (!ChapaConfig::validateAmount($amount)) {
                throw new Exception("Invalid amount. Must be between 1 and 50,000 ETB");
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address");
            }
            
            // Generate unique transaction reference
            $txRef = ChapaConfig::generateTransactionRef('DEP');
            
            // Store transaction in database
            $stmt = $this->conn->prepare("
                INSERT INTO chapa_transactions (user_id, transaction_ref, amount, type, status, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $txRef, $amount, 'deposit', 'pending']);
            
            // Prepare payment data using setter methods (IMPORTANT!)
            $postData = new PostData();
            $postData->setAmount(ChapaConfig::formatAmount($amount));
            $postData->setCurrency('ETB');
            $postData->setEmail($email);
            $postData->setFirstName($firstName);
            $postData->setLastName($lastName);
            $postData->setPhoneNumber($phone);
            $postData->setTxRef($txRef);
            $postData->setCallbackUrl(ChapaConfig::getCallbackUrl());
            $postData->setReturnUrl(ChapaConfig::getReturnUrl());
            $postData->setCustomization([
                'title' => 'Payment Gateway',
                'description' => 'Payment for services'
            ]);
            
            // Initialize payment with Chapa
            $response = $this->chapa->initializePayment($postData);
            
            if ($response->getStatus() === 'success') {
                $responseData = $response->getData();
                $checkoutUrl = $responseData['checkout_url'];
                
                // Update transaction with Chapa response
                $stmt = $this->conn->prepare("
                    UPDATE chapa_transactions 
                    SET chapa_response = ?, updated_at = NOW() 
                    WHERE transaction_ref = ?
                ");
                $stmt->execute([json_encode($responseData), $txRef]);
                
                ChapaConfig::logTransaction('Payment initialized', [
                    'tx_ref' => $txRef,
                    'amount' => $amount,
                    'user_id' => $userId
                ]);
                
                return [
                    'success' => true,
                    'checkout_url' => $checkoutUrl,
                    'tx_ref' => $txRef,
                    'message' => 'Payment initialized successfully'
                ];
                
            } else {
                throw new Exception("Failed to initialize payment: " . $response->getMessage());
            }
            
        } catch (Exception $e) {
            // Update transaction status if it exists
            if (isset($txRef)) {
                $stmt = $this->conn->prepare("
                    UPDATE chapa_transactions 
                    SET status = 'failed', error_message = ?, updated_at = NOW() 
                    WHERE transaction_ref = ?
                ");
                $stmt->execute([$e->getMessage(), $txRef]);
            }
            
            ChapaConfig::logTransaction('Payment initialization failed', [
                'error' => $e->getMessage(),
                'amount' => $amount,
                'user_id' => $userId
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify a transaction with Chapa API
     * 
     * @param string $txRef Transaction reference
     * @return array Verification result
     */
    public function verifyTransaction($txRef) {
        try {
            $response = $this->chapa->verifyTransaction($txRef);
            
            if ($response->getStatus() === 'success') {
                $data = $response->getData();
                
                ChapaConfig::logTransaction('Transaction verified', [
                    'tx_ref' => $txRef,
                    'status' => $data['status'],
                    'amount' => $data['amount']
                ]);
                
                return [
                    'success' => true,
                    'data' => $data,
                    'message' => 'Transaction verified successfully'
                ];
            } else {
                throw new Exception("Verification failed: " . $response->getMessage());
            }
            
        } catch (Exception $e) {
            ChapaConfig::logTransaction('Transaction verification failed', [
                'tx_ref' => $txRef,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process webhook notification
     * 
     * @param array $webhookData Webhook payload
     * @return array Processing result
     */
    public function processWebhook($webhookData) {
        try {
            if (!isset($webhookData['tx_ref'])) {
                throw new Exception("Missing transaction reference in webhook");
            }
            
            $txRef = $webhookData['tx_ref'];
            $status = $webhookData['status'] ?? '';
            
            // Get transaction from database
            $stmt = $this->conn->prepare("SELECT * FROM chapa_transactions WHERE transaction_ref = ?");
            $stmt->execute([$txRef]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transaction not found: " . $txRef);
            }
            
            if ($status === 'success') {
                // Verify with Chapa API for security
                $verification = $this->verifyTransaction($txRef);
                
                if ($verification['success'] && $verification['data']['status'] === 'success') {
                    // Update transaction status
                    $stmt = $this->conn->prepare("
                        UPDATE chapa_transactions 
                        SET status = 'completed', chapa_response = ?, updated_at = NOW() 
                        WHERE transaction_ref = ?
                    ");
                    $stmt->execute([json_encode($verification['data']), $txRef]);
                    
                    // Update user balance for deposits
                    if ($transaction['type'] === 'deposit') {
                        $stmt = $this->conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                    }
                    
                    ChapaConfig::logTransaction('Webhook processed successfully', [
                        'tx_ref' => $txRef,
                        'amount' => $transaction['amount']
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => 'Webhook processed successfully'
                    ];
                }
            } else {
                // Update failed transaction
                $stmt = $this->conn->prepare("
                    UPDATE chapa_transactions 
                    SET status = ?, chapa_response = ?, updated_at = NOW() 
                    WHERE transaction_ref = ?
                ");
                $stmt->execute([$status, json_encode($webhookData), $txRef]);
            }
            
            return [
                'success' => true,
                'message' => 'Webhook processed'
            ];
            
        } catch (Exception $e) {
            ChapaConfig::logTransaction('Webhook processing failed', [
                'error' => $e->getMessage(),
                'webhook_data' => $webhookData
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get transaction status
     * 
     * @param string $txRef Transaction reference
     * @return array Transaction status
     */
    public function getTransactionStatus($txRef) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM chapa_transactions WHERE transaction_ref = ?");
            $stmt->execute([$txRef]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            return [
                'success' => true,
                'transaction' => $transaction
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

// Usage Examples:
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    echo "<h1>Chapa SDK Integration Sample</h1>\n";
    echo "<h2>Usage Examples:</h2>\n";
    
    try {
        $chapa = new ChapaIntegrationSample();
        
        echo "<h3>1. Initialize Payment</h3>\n";
        echo "<pre>\n";
        echo '$result = $chapa->initializePayment(
    100.00,                    // Amount in ETB
    "customer@example.com",    // Customer email
    "John",                    // First name
    "Doe",                     // Last name
    "0911234567",             // Phone number
    1                         // User ID
);

if ($result["success"]) {
    // Redirect user to checkout URL
    header("Location: " . $result["checkout_url"]);
} else {
    echo "Error: " . $result["message"];
}';
        echo "</pre>\n";
        
        echo "<h3>2. Verify Transaction</h3>\n";
        echo "<pre>\n";
        echo '$result = $chapa->verifyTransaction("DEP_1234567890_abcdef12");

if ($result["success"]) {
    $transactionData = $result["data"];
    echo "Status: " . $transactionData["status"];
    echo "Amount: " . $transactionData["amount"];
} else {
    echo "Verification failed: " . $result["message"];
}';
        echo "</pre>\n";
        
        echo "<h3>3. Process Webhook</h3>\n";
        echo "<pre>\n";
        echo '// In your webhook endpoint (chapa_webhook.php)
$input = file_get_contents("php://input");
$webhookData = json_decode($input, true);

$result = $chapa->processWebhook($webhookData);

if ($result["success"]) {
    http_response_code(200);
    echo json_encode(["status" => "success"]);
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $result["message"]]);
}';
        echo "</pre>\n";
        
        echo "<h3>Configuration Requirements:</h3>\n";
        echo "<ul>\n";
        echo "<li>Set CHAPA_SECRET_KEY in .env file</li>\n";
        echo "<li>Set CHAPA_WEBHOOK_SECRET in .env file (optional but recommended)</li>\n";
        echo "<li>Ensure chapa_transactions table exists in database</li>\n";
        echo "<li>Configure webhook URL in Chapa dashboard</li>\n";
        echo "</ul>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
    }
}
?>
