<?php
/**
 * Chapa Payment Deposit Endpoint
 * Handles payment initialization requests
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
    $chapaAPI = new ChapaAPI();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        ResponseHandler::error('Invalid JSON input');
    }
    
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
        'callback_url' => $callbackUrl ?: ($_ENV['CALLBACK_URL'] ?? 'https://yourdomain.com/webhook.php'),
        'return_url' => $returnUrl ?: ($_ENV['RETURN_URL'] ?? 'https://yourdomain.com/success.php'),
        'customization' => [
            'title' => $input['title'] ?? 'Payment',
            'description' => $input['description'] ?? 'Payment for services',
            'logo' => $input['logo'] ?? null
        ]
    ];
    
    // Initialize payment
    $response = $chapaAPI->initializePayment($paymentData);
    
    if ($response['success']) {
        ResponseHandler::success($response['data'], 'Payment initialized successfully');
    } else {
        ResponseHandler::error('Payment initialization failed', 400, $response['data']);
    }
    
} catch (Exception $e) {
    ResponseHandler::error($e->getMessage());
}
?>
