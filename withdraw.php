<?php
/**
 * Chapa Payment Withdrawal Endpoint
 * Handles withdrawal/payout requests
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
    $response = $chapaAPI->processWithdrawal($withdrawalData);
    
    if ($response['success']) {
        ResponseHandler::success($response['data'], 'Withdrawal processed successfully');
    } else {
        ResponseHandler::error('Withdrawal processing failed', 400, $response['data']);
    }
    
} catch (Exception $e) {
    ResponseHandler::error($e->getMessage());
}
?>
