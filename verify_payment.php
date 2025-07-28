<?php
require_once 'db_connect.php';
require_once 'security.php';
require_once 'chapa_config.php';

header('Content-Type: application/json');
Security::secureSessionStart();

$db = new Database();
$conn = $db->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("You must be logged in");
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !Security::validateCSRFToken($input['csrf_token'])) {
        throw new Exception("Invalid request");
    }
    
    $transactionRef = Security::sanitizeInput($input['transaction_ref'] ?? '');
    if (empty($transactionRef)) {
        throw new Exception("Transaction reference required");
    }
    
    // Get transaction from database
    $stmt = $conn->prepare("SELECT * FROM chapa_transactions WHERE transaction_ref = ? AND user_id = ? AND status = 'pending'");
    $stmt->execute([$transactionRef, $_SESSION['user_id']]);
    $dbTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dbTransaction) {
        throw new Exception("Transaction not found or already processed");
    }
    
    // Verify with Chapa
    $chapa = ChapaConfig::getInstance();
    $response = $chapa->verify($transactionRef);
    $responseData = json_decode($response->getBody(), true);
    
    if ($response->getStatusCode() === 200 && $responseData['status'] === 'success' && $responseData['data']['status'] === 'success') {
        $conn->beginTransaction();
        
        // Update user balance
        $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$dbTransaction['amount'], $_SESSION['user_id']]);
        
        // Update session balance
        $_SESSION['balance'] += $dbTransaction['amount'];
        
        // Record transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, reference) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $dbTransaction['amount'], 'deposit', $transactionRef]);
        
        // Update Chapa transaction status
        $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'completed', chapa_reference = ?, updated_at = NOW() WHERE transaction_ref = ?");
        $stmt->execute([$responseData['data']['reference'] ?? '', $transactionRef]);
        
        // Log completion
        $stmt = $conn->prepare("INSERT INTO user_logs (user_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'deposit_completed', $_SERVER['REMOTE_ADDR'], json_encode(['transaction_ref' => $transactionRef, 'amount' => $dbTransaction['amount']])]);
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Payment verified successfully']);
    } else {
        // Update as failed
        $stmt = $conn->prepare("UPDATE chapa_transactions SET status = 'failed', error_message = ?, updated_at = NOW() WHERE transaction_ref = ?");
        $stmt->execute([json_encode($responseData), $transactionRef]);
        
        throw new Exception("Payment verification failed");
    }
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
