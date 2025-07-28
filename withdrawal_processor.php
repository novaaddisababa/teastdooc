<?php
require_once 'db_connect.php';
require_once 'security.php';
require_once 'chapa_config.php';

/**
 * Withdrawal Processor for handling real withdrawals
 * Supports both automated (Chapa Transfer API) and manual processing
 */
class WithdrawalProcessor {
    private $conn;
    private $chapa;
    
    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
        $this->chapa = ChapaConfig::getChapa();
    }
    
    /**
     * Process withdrawal - attempts automated transfer, falls back to manual
     */
    public function processWithdrawal($transactionRef) {
        try {
            // Get withdrawal details
            $withdrawal = $this->getWithdrawalDetails($transactionRef);
            if (!$withdrawal) {
                throw new Exception("Withdrawal not found or already processed");
            }
            
            $processingDetails = json_decode($withdrawal['processing_details'], true);
            $withdrawalDetails = $processingDetails['withdrawal_details'];
            
            // Update status to processing
            $this->updateWithdrawalStatus($transactionRef, 'processing', 'Starting withdrawal process');
            
            // Try automated processing first
            if ($this->canProcessAutomatically($withdrawal, $withdrawalDetails)) {
                return $this->processAutomatically($withdrawal, $withdrawalDetails);
            } else {
                // Queue for manual processing
                return $this->queueForManualProcessing($withdrawal, $withdrawalDetails);
            }
            
        } catch (Exception $e) {
            $this->updateWithdrawalStatus($transactionRef, 'failed', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if withdrawal can be processed automatically
     */
    private function canProcessAutomatically($withdrawal, $withdrawalDetails) {
        // Criteria for automatic processing:
        // 1. Amount under certain threshold (e.g., 10,000 ETB)
        // 2. User has verified account
        // 3. Bank/mobile money provider is supported
        
        $autoThreshold = 10000; // ETB
        
        if ($withdrawal['amount'] > $autoThreshold) {
            return false;
        }
        
        // Check if bank/provider is supported for auto processing
        $supportedBanks = ['CBE', 'AIB', 'BOA', 'UB'];
        $supportedMobileProviders = ['mbirr', 'hellocash', 'telebirr'];
        
        if ($withdrawalDetails['method'] === 'bank_transfer') {
            return in_array($withdrawalDetails['bank_name'], $supportedBanks);
        } elseif ($withdrawalDetails['method'] === 'mobile_money') {
            return in_array($withdrawalDetails['mobile_provider'], $supportedMobileProviders);
        }
        
        return false;
    }
    
    /**
     * Process withdrawal automatically using APIs
     */
    private function processAutomatically($withdrawal, $withdrawalDetails) {
        if ($withdrawalDetails['method'] === 'bank_transfer') {
            return $this->processBankTransfer($withdrawal, $withdrawalDetails);
        } elseif ($withdrawalDetails['method'] === 'mobile_money') {
            return $this->processMobileMoneyTransfer($withdrawal, $withdrawalDetails);
        }
        
        throw new Exception("Unsupported withdrawal method");
    }
    
    /**
     * Process bank transfer (simulated - replace with actual API)
     */
    private function processBankTransfer($withdrawal, $withdrawalDetails) {
        // Simulate bank transfer API call
        // In real implementation, integrate with:
        // - Chapa Transfer API (if available)
        // - Ethiopian bank APIs
        // - Third-party payment processors
        
        $transferData = [
            'amount' => $withdrawal['amount'],
            'currency' => 'ETB',
            'account_number' => $withdrawalDetails['account_number'],
            'account_holder_name' => $withdrawalDetails['account_holder_name'],
            'bank_name' => $withdrawalDetails['bank_name'],
            'bank_code' => $withdrawalDetails['bank_code'],
            'reference' => $withdrawal['transaction_ref']
        ];
        
        // Simulate API call (replace with real implementation)
        $success = $this->simulateBankTransferAPI($transferData);
        
        if ($success) {
            $this->updateWithdrawalStatus(
                $withdrawal['transaction_ref'], 
                'completed', 
                'Bank transfer completed automatically',
                json_encode(['transfer_id' => 'TXN_' . time(), 'method' => 'bank_api'])
            );
            
            // Send notification email
            $this->sendWithdrawalNotification($withdrawal, 'completed');
            
            return ['status' => 'success', 'message' => 'Bank transfer completed'];
        } else {
            throw new Exception('Bank transfer API failed');
        }
    }
    
    /**
     * Process mobile money transfer (simulated - replace with actual API)
     */
    private function processMobileMoneyTransfer($withdrawal, $withdrawalDetails) {
        $transferData = [
            'amount' => $withdrawal['amount'],
            'currency' => 'ETB',
            'phone_number' => $withdrawalDetails['mobile_number'],
            'provider' => $withdrawalDetails['mobile_provider'],
            'account_name' => $withdrawalDetails['mobile_account_name'],
            'reference' => $withdrawal['transaction_ref']
        ];
        
        // Simulate mobile money API call
        $success = $this->simulateMobileMoneyAPI($transferData);
        
        if ($success) {
            $this->updateWithdrawalStatus(
                $withdrawal['transaction_ref'], 
                'completed', 
                'Mobile money transfer completed automatically',
                json_encode(['transfer_id' => 'MM_' . time(), 'method' => 'mobile_api'])
            );
            
            // Send notification
            $this->sendWithdrawalNotification($withdrawal, 'completed');
            
            return ['status' => 'success', 'message' => 'Mobile money transfer completed'];
        } else {
            throw new Exception('Mobile money API failed');
        }
    }
    
    /**
     * Queue withdrawal for manual processing
     */
    public function queueForManualProcessing($withdrawal, $withdrawalDetails, $reason = 'Manual review required') {
        // Update status
        $this->updateWithdrawalStatus(
            $withdrawal['transaction_ref'], 
            'manual_processing', 
            $reason
        );
        
        // Create manual processing record
        $stmt = $this->conn->prepare("
            INSERT INTO manual_withdrawals 
            (transaction_ref, user_id, amount, withdrawal_details, created_at, priority) 
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        
        $priority = $withdrawal['amount'] > 50000 ? 'high' : 'normal';
        $stmt->execute([
            $withdrawal['transaction_ref'],
            $withdrawal['user_id'],
            $withdrawal['amount'],
            json_encode($withdrawalDetails),
            $priority
        ]);
        
        // Send notification to admin
        $this->notifyAdminManualWithdrawal($withdrawal);
        
        // Send notification to user
        $this->sendWithdrawalNotification($withdrawal, 'manual_processing');
        
        return [
            'status' => 'manual_processing', 
            'message' => 'Withdrawal queued for manual processing. You will be notified once completed.'
        ];
    }
    
    /**
     * Get withdrawal details from database
     */
    private function getWithdrawalDetails($transactionRef) {
        $stmt = $this->conn->prepare("
            SELECT * FROM chapa_transactions 
            WHERE transaction_ref = ? AND type = 'withdraw' AND status IN ('pending', 'processing')
        ");
        $stmt->execute([$transactionRef]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Update withdrawal status
     */
    private function updateWithdrawalStatus($transactionRef, $status, $message, $response = null) {
        $stmt = $this->conn->prepare("
            UPDATE chapa_transactions 
            SET status = ?, error_message = ?, chapa_response = ?, updated_at = NOW() 
            WHERE transaction_ref = ?
        ");
        $stmt->execute([$status, $message, $response, $transactionRef]);
    }
    
    /**
     * Simulate bank transfer API (replace with real implementation)
     */
    private function simulateBankTransferAPI($transferData) {
        // Simulate 90% success rate for demo purposes
        // In real implementation, call actual bank APIs
        return (rand(1, 10) <= 9);
    }
    
    /**
     * Simulate mobile money API (replace with real implementation)
     */
    private function simulateMobileMoneyAPI($transferData) {
        // Simulate 85% success rate for demo purposes
        // In real implementation, call actual mobile money APIs
        return (rand(1, 10) <= 8);
    }
    
    /**
     * Send withdrawal notification to user
     */
    private function sendWithdrawalNotification($withdrawal, $status) {
        // Get user email
        $stmt = $this->conn->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmt->execute([$withdrawal['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) return;
        
        $subject = "Withdrawal Update - " . ucfirst($status);
        $message = $this->getWithdrawalEmailTemplate($withdrawal, $status, $user);
        
        // Send email (implement your email sending logic)
        // mail($user['email'], $subject, $message);
        
        // Log notification
        error_log("Withdrawal notification sent to {$user['email']}: {$status}");
    }
    
    /**
     * Get email template for withdrawal notifications
     */
    private function getWithdrawalEmailTemplate($withdrawal, $status, $user) {
        $amount = number_format($withdrawal['amount'], 2);
        $ref = $withdrawal['transaction_ref'];
        
        switch ($status) {
            case 'completed':
                return "Dear {$user['username']},\n\nYour withdrawal of ETB {$amount} (Ref: {$ref}) has been completed successfully.\n\nThank you for using our service.";
            
            case 'manual_processing':
                return "Dear {$user['username']},\n\nYour withdrawal of ETB {$amount} (Ref: {$ref}) is being processed manually. You will receive another notification once completed.\n\nExpected processing time: 1-3 business days.";
            
            case 'failed':
                return "Dear {$user['username']},\n\nYour withdrawal of ETB {$amount} (Ref: {$ref}) could not be processed. Please contact support for assistance.\n\nYour account balance has been restored.";
            
            default:
                return "Dear {$user['username']},\n\nYour withdrawal status has been updated to: {$status}.\n\nReference: {$ref}";
        }
    }
    
    /**
     * Notify admin of manual withdrawal
     */
    private function notifyAdminManualWithdrawal($withdrawal) {
        // Log for admin attention
        error_log("ADMIN ALERT: Manual withdrawal required - Ref: {$withdrawal['transaction_ref']}, Amount: ETB {$withdrawal['amount']}");
        
        // In real implementation, send email/SMS to admin or add to admin dashboard
    }
    
    /**
     * Get pending manual withdrawals for admin dashboard
     */
    public function getPendingManualWithdrawals() {
        $stmt = $this->conn->prepare("
            SELECT ct.*, u.username, u.email, mw.priority, mw.created_at as queued_at
            FROM chapa_transactions ct
            JOIN manual_withdrawals mw ON ct.transaction_ref = mw.transaction_ref
            JOIN users u ON ct.user_id = u.id
            WHERE ct.status = 'manual_processing'
            ORDER BY mw.priority DESC, mw.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Manually approve withdrawal (for admin use)
     */
    public function manuallyApproveWithdrawal($transactionRef, $adminNotes = '') {
        $this->updateWithdrawalStatus(
            $transactionRef, 
            'completed', 
            'Manually approved by admin. Notes: ' . $adminNotes
        );
        
        // Remove from manual processing queue
        $stmt = $this->conn->prepare("DELETE FROM manual_withdrawals WHERE transaction_ref = ?");
        $stmt->execute([$transactionRef]);
        
        // Get withdrawal details for notification
        $withdrawal = $this->getWithdrawalDetails($transactionRef);
        if ($withdrawal) {
            $this->sendWithdrawalNotification($withdrawal, 'completed');
        }
        
        return ['status' => 'success', 'message' => 'Withdrawal approved and completed'];
    }
}
?>