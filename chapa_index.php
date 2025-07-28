<?php
/**
 * Chapa Payment Gateway Integration
 * Complete implementation for deposits, withdrawals, and webhook handling
 * 
 * Required PHP Extensions: curl, json, openssl
 * 
 * Author: Cascade AI Assistant
 * Date: 2025-07-23
 */

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
class ChapaConfig {
    // Environment settings
    const ENVIRONMENT = 'sandbox'; // Change to 'live' for production
    
    // API Configuration
    const SANDBOX_URL = 'https://api.chapa.co/v1/';
    const LIVE_URL = 'https://api.chapa.co/v1/';
    
    // Get API key from environment or config
    public static function getApiKey() {
        // Try to get from environment variable first
        $apiKey = $_ENV['CHAPA_API_KEY'] ?? getenv('CHAPA_API_KEY');
        
        // Fallback to config (not recommended for production)
        if (!$apiKey) {
            $apiKey = self::ENVIRONMENT === 'sandbox' 
                ? 'CHASECK_TEST-your-sandbox-secret-key-here'
                : 'CHASECK-your-live-secret-key-here';
        }
        
        return $apiKey;
    }
    
    public static function getBaseUrl() {
        return self::ENVIRONMENT === 'sandbox' ? self::SANDBOX_URL : self::LIVE_URL;
    }
}

// Utility class for API requests
class ChapaAPI {
    private $apiKey;
    private $baseUrl;
    
    public function __construct() {
        $this->apiKey = ChapaConfig::getApiKey();
        $this->baseUrl = ChapaConfig::getBaseUrl();
    }
    
    /**
     * Make HTTP request to Chapa API
     */
    private function makeRequest($endpoint, $data = null, $method = 'POST') {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ]
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        $decodedResponse = json_decode($response, true);
        
        return [
            'status_code' => $httpCode,
            'data' => $decodedResponse,
            'success' => $httpCode >= 200 && $httpCode < 300
        ];
    }
    
    /**
     * Initialize payment
     */
    public function initializePayment($data) {
        return $this->makeRequest('transaction/initialize', $data);
    }
    
    /**
     * Verify payment
     */
    public function verifyPayment($txRef) {
        return $this->makeRequest('transaction/verify/' . $txRef, null, 'GET');
    }
    
    /**
     * Process withdrawal/payout
     */
    public function processWithdrawal($data) {
        return $this->makeRequest('transfers', $data);
    }
    
    /**
     * Get supported banks
     */
    public function getBanks() {
        return $this->makeRequest('banks', null, 'GET');
    }
}

// Input validation and sanitization
class InputValidator {
    /**
     * Validate and sanitize email
     */
    public static function validateEmail($email) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
        return $email;
    }
    
    /**
     * Validate and sanitize amount
     */
    public static function validateAmount($amount) {
        if (!is_numeric($amount) || $amount <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number');
        }
        return (float) $amount;
    }
    
    /**
     * Validate currency
     */
    public static function validateCurrency($currency) {
        $allowedCurrencies = ['ETB', 'USD'];
        $currency = strtoupper(trim($currency));
        if (!in_array($currency, $allowedCurrencies)) {
            throw new InvalidArgumentException('Invalid currency. Allowed: ' . implode(', ', $allowedCurrencies));
        }
        return $currency;
    }
    
    /**
     * Generate unique transaction reference
     */
    public static function generateTxRef($prefix = 'tx') {
        return $prefix . '_' . time() . '_' . bin2hex(random_bytes(8));
    }
    
    /**
     * Sanitize string input
     */
    public static function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// Response handler
class ResponseHandler {
    public static function success($data, $message = 'Success') {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
    
    public static function error($message, $code = 400, $data = null) {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ]);
        exit();
    }
}

// Webhook signature verification
class WebhookVerifier {
    public static function verifySignature($payload, $signature, $secret) {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}

// Main router
class Router {
    private $chapaAPI;
    
    public function __construct() {
        $this->chapaAPI = new ChapaAPI();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        try {
            switch ($path) {
                case '/deposit':
                case '/deposit.php':
                    if ($method === 'POST') {
                        $this->handleDeposit();
                    } else {
                        ResponseHandler::error('Method not allowed', 405);
                    }
                    break;
                    
                case '/withdraw':
                case '/withdraw.php':
                    if ($method === 'POST') {
                        $this->handleWithdrawal();
                    } else {
                        ResponseHandler::error('Method not allowed', 405);
                    }
                    break;
                    
                case '/webhook':
                case '/webhook.php':
                    if ($method === 'POST') {
                        $this->handleWebhook();
                    } else {
                        ResponseHandler::error('Method not allowed', 405);
                    }
                    break;
                    
                case '/verify':
                case '/verify.php':
                    if ($method === 'GET') {
                        $this->handleVerification();
                    } else {
                        ResponseHandler::error('Method not allowed', 405);
                    }
                    break;
                    
                case '/banks':
                case '/banks.php':
                    if ($method === 'GET') {
                        $this->handleGetBanks();
                    } else {
                        ResponseHandler::error('Method not allowed', 405);
                    }
                    break;
                    
                default:
                    $this->showDocumentation();
                    break;
            }
        } catch (Exception $e) {
            ResponseHandler::error($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle deposit initialization
     */
    private function handleDeposit() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            ResponseHandler::error('Invalid JSON input');
        }
        
        try {
            // Validate required fields
            $amount = InputValidator::validateAmount($input['amount'] ?? null);
            $email = InputValidator::validateEmail($input['email'] ?? null);
            $currency = InputValidator::validateCurrency($input['currency'] ?? 'ETB');
            $txRef = $input['tx_ref'] ?? InputValidator::generateTxRef('deposit');
            
            // Optional fields
            $firstName = InputValidator::sanitizeString($input['first_name'] ?? 'Customer');
            $lastName = InputValidator::sanitizeString($input['last_name'] ?? 'User');
            $phone = InputValidator::sanitizeString($input['phone'] ?? '');
            $returnUrl = filter_var($input['return_url'] ?? '', FILTER_VALIDATE_URL);
            $callbackUrl = filter_var($input['callback_url'] ?? '', FILTER_VALIDATE_URL);
            
            // Prepare payment data
            $paymentData = [
                'amount' => $amount,
                'currency' => $currency,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone_number' => $phone,
                'tx_ref' => $txRef,
                'callback_url' => $callbackUrl ?: 'https://yourdomain.com/webhook.php',
                'return_url' => $returnUrl ?: 'https://yourdomain.com/success.php',
                'customization' => [
                    'title' => 'Payment',
                    'description' => 'Payment for services'
                ]
            ];
            
            // Initialize payment
            $response = $this->chapaAPI->initializePayment($paymentData);
            
            if ($response['success']) {
                ResponseHandler::success($response['data'], 'Payment initialized successfully');
            } else {
                ResponseHandler::error('Payment initialization failed', 400, $response['data']);
            }
            
        } catch (Exception $e) {
            ResponseHandler::error($e->getMessage());
        }
    }
    
    /**
     * Handle withdrawal processing
     */
    private function handleWithdrawal() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            ResponseHandler::error('Invalid JSON input');
        }
        
        try {
            // Validate required fields
            $amount = InputValidator::validateAmount($input['amount'] ?? null);
            $currency = InputValidator::validateCurrency($input['currency'] ?? 'ETB');
            $accountNumber = InputValidator::sanitizeString($input['account_number'] ?? null);
            $bankCode = InputValidator::sanitizeString($input['bank_code'] ?? null);
            $reference = $input['reference'] ?? InputValidator::generateTxRef('withdrawal');
            
            if (!$accountNumber || !$bankCode) {
                ResponseHandler::error('Account number and bank code are required');
            }
            
            // Optional fields
            $accountName = InputValidator::sanitizeString($input['account_name'] ?? 'Beneficiary');
            $beneficiaryName = InputValidator::sanitizeString($input['beneficiary_name'] ?? $accountName);
            
            // Prepare withdrawal data
            $withdrawalData = [
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'amount' => $amount,
                'beneficiary_name' => $beneficiaryName,
                'currency' => $currency,
                'reference' => $reference,
                'bank_code' => $bankCode
            ];
            
            // Process withdrawal
            $response = $this->chapaAPI->processWithdrawal($withdrawalData);
            
            if ($response['success']) {
                ResponseHandler::success($response['data'], 'Withdrawal processed successfully');
            } else {
                ResponseHandler::error('Withdrawal processing failed', 400, $response['data']);
            }
            
        } catch (Exception $e) {
            ResponseHandler::error($e->getMessage());
        }
    }
    
    /**
     * Handle webhook notifications
     */
    private function handleWebhook() {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_CHAPA_SIGNATURE'] ?? '';
        
        // Verify webhook signature (implement your secret)
        $webhookSecret = $_ENV['CHAPA_WEBHOOK_SECRET'] ?? 'your-webhook-secret';
        
        if (!WebhookVerifier::verifySignature($payload, $signature, $webhookSecret)) {
            ResponseHandler::error('Invalid webhook signature', 401);
        }
        
        $data = json_decode($payload, true);
        
        if (!$data) {
            ResponseHandler::error('Invalid webhook payload');
        }
        
        try {
            // Process webhook based on event type
            $event = $data['event'] ?? 'charge.success';
            $transactionData = $data['data'] ?? [];
            
            switch ($event) {
                case 'charge.success':
                    $this->handleSuccessfulPayment($transactionData);
                    break;
                    
                case 'charge.failed':
                    $this->handleFailedPayment($transactionData);
                    break;
                    
                case 'transfer.success':
                    $this->handleSuccessfulTransfer($transactionData);
                    break;
                    
                case 'transfer.failed':
                    $this->handleFailedTransfer($transactionData);
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
    }
    
    /**
     * Handle payment verification
     */
    private function handleVerification() {
        $txRef = $_GET['tx_ref'] ?? null;
        
        if (!$txRef) {
            ResponseHandler::error('Transaction reference is required');
        }
        
        try {
            $response = $this->chapaAPI->verifyPayment($txRef);
            
            if ($response['success']) {
                ResponseHandler::success($response['data'], 'Payment verified successfully');
            } else {
                ResponseHandler::error('Payment verification failed', 400, $response['data']);
            }
            
        } catch (Exception $e) {
            ResponseHandler::error($e->getMessage());
        }
    }
    
    /**
     * Get supported banks
     */
    private function handleGetBanks() {
        try {
            $response = $this->chapaAPI->getBanks();
            
            if ($response['success']) {
                ResponseHandler::success($response['data'], 'Banks retrieved successfully');
            } else {
                ResponseHandler::error('Failed to retrieve banks', 400, $response['data']);
            }
            
        } catch (Exception $e) {
            ResponseHandler::error($e->getMessage());
        }
    }
    
    /**
     * Handle successful payment webhook
     */
    private function handleSuccessfulPayment($data) {
        // Implement your database update logic here
        $txRef = $data['tx_ref'] ?? '';
        $amount = $data['amount'] ?? 0;
        $email = $data['email'] ?? '';
        
        // Example: Update database
        // $this->updatePaymentStatus($txRef, 'success', $amount, $email);
        
        error_log("Payment successful: {$txRef} - Amount: {$amount} - Email: {$email}");
    }
    
    /**
     * Handle failed payment webhook
     */
    private function handleFailedPayment($data) {
        // Implement your database update logic here
        $txRef = $data['tx_ref'] ?? '';
        $reason = $data['reason'] ?? 'Unknown error';
        
        // Example: Update database
        // $this->updatePaymentStatus($txRef, 'failed', 0, '', $reason);
        
        error_log("Payment failed: {$txRef} - Reason: {$reason}");
    }
    
    /**
     * Handle successful transfer webhook
     */
    private function handleSuccessfulTransfer($data) {
        // Implement your database update logic here
        $reference = $data['reference'] ?? '';
        $amount = $data['amount'] ?? 0;
        
        error_log("Transfer successful: {$reference} - Amount: {$amount}");
    }
    
    /**
     * Handle failed transfer webhook
     */
    private function handleFailedTransfer($data) {
        // Implement your database update logic here
        $reference = $data['reference'] ?? '';
        $reason = $data['reason'] ?? 'Unknown error';
        
        error_log("Transfer failed: {$reference} - Reason: {$reason}");
    }
    
    /**
     * Show API documentation
     */
    private function showDocumentation() {
        $docs = [
            'title' => 'Chapa Payment Gateway API',
            'version' => '1.0.0',
            'endpoints' => [
                'POST /deposit' => [
                    'description' => 'Initialize a payment',
                    'required_fields' => ['amount', 'email'],
                    'optional_fields' => ['currency', 'tx_ref', 'first_name', 'last_name', 'phone', 'return_url', 'callback_url'],
                    'example' => [
                        'amount' => 100,
                        'email' => 'customer@example.com',
                        'currency' => 'ETB',
                        'first_name' => 'John',
                        'last_name' => 'Doe'
                    ]
                ],
                'POST /withdraw' => [
                    'description' => 'Process a withdrawal',
                    'required_fields' => ['amount', 'account_number', 'bank_code'],
                    'optional_fields' => ['currency', 'reference', 'account_name', 'beneficiary_name'],
                    'example' => [
                        'amount' => 50,
                        'account_number' => '1234567890',
                        'bank_code' => 'CBE',
                        'currency' => 'ETB'
                    ]
                ],
                'POST /webhook' => [
                    'description' => 'Handle Chapa webhook notifications',
                    'note' => 'This endpoint is called by Chapa automatically'
                ],
                'GET /verify?tx_ref=xxx' => [
                    'description' => 'Verify a payment status',
                    'required_params' => ['tx_ref']
                ],
                'GET /banks' => [
                    'description' => 'Get list of supported banks'
                ]
            ],
            'sample_curl_commands' => [
                'deposit' => 'curl -X POST http://yourdomain.com/deposit -H "Content-Type: application/json" -d \'{"amount": 100, "email": "test@example.com", "currency": "ETB"}\'',
                'withdraw' => 'curl -X POST http://yourdomain.com/withdraw -H "Content-Type: application/json" -d \'{"amount": 50, "account_number": "1234567890", "bank_code": "CBE"}\'',
                'verify' => 'curl -X GET "http://yourdomain.com/verify?tx_ref=your_transaction_reference"',
                'banks' => 'curl -X GET http://yourdomain.com/banks'
            ],
            'required_php_extensions' => ['curl', 'json', 'openssl'],
            'environment_variables' => [
                'CHAPA_API_KEY' => 'Your Chapa API secret key',
                'CHAPA_WEBHOOK_SECRET' => 'Your webhook secret for signature verification'
            ]
        ];
        
        ResponseHandler::success($docs, 'API Documentation');
    }
}

// Initialize and handle request
try {
    $router = new Router();
    $router->handleRequest();
} catch (Exception $e) {
    ResponseHandler::error('Internal server error: ' . $e->getMessage(), 500);
}
?>