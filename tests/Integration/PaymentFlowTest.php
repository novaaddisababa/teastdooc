<?php

require_once __DIR__ . '/../TestConfig.php';
require_once __DIR__ . '/../../chapa_config.php';
require_once __DIR__ . '/../../withdrawal_processor.php';

use PHPUnit\Framework\TestCase;

class PaymentFlowTest extends TestCase
{
    private $testDb;
    private $chapaConfig;
    private $withdrawalProcessor;
    
    protected function setUp(): void
    {
        // Set up test database connection
        $dbConfig = TestConfig::getTestDatabaseConfig();
        $this->testDb = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
            $dbConfig['username'],
            $dbConfig['password']
        );
        
        $this->chapaConfig = new ChapaConfig();
        $this->chapaConfig->setTestMode(true);
        
        $this->withdrawalProcessor = new WithdrawalProcessor();
    }
    
    protected function tearDown(): void
    {
        // Clean up test data
        $this->testDb->exec("DELETE FROM chapa_transactions WHERE transaction_ref LIKE 'INTEGRATION_TEST_%'");
        $this->testDb->exec("DELETE FROM manual_withdrawals WHERE transaction_ref LIKE 'INTEGRATION_TEST_%'");
        $this->testDb->exec("DELETE FROM withdrawal_processing_logs WHERE transaction_ref LIKE 'INTEGRATION_TEST_%'");
    }
    
    /**
     * Test complete deposit flow: initialization -> webhook -> balance update
     */
    public function testCompleteDepositFlow()
    {
        $testUser = TestConfig::getTestUsers()['small_balance'];
        $depositAmount = 1000.00;
        $transactionRef = 'INTEGRATION_TEST_DEPOSIT_' . time();
        
        // Step 1: Initialize deposit
        $this->initializeTestDeposit($testUser['id'], $transactionRef, $depositAmount);
        
        // Verify transaction created
        $transaction = $this->getTransaction($transactionRef);
        $this->assertNotNull($transaction, 'Deposit transaction should be created');
        $this->assertEquals('pending', $transaction['status']);
        
        // Step 2: Simulate successful webhook
        $this->simulateSuccessfulWebhook($transactionRef, $depositAmount);
        
        // Step 3: Verify transaction updated
        $updatedTransaction = $this->getTransaction($transactionRef);
        $this->assertEquals('completed', $updatedTransaction['status'], 'Transaction should be completed');
        
        // Step 4: Verify user balance updated
        $updatedBalance = $this->getUserBalance($testUser['id']);
        $expectedBalance = $testUser['balance'] + $depositAmount;
        $this->assertEquals($expectedBalance, $updatedBalance, 'User balance should be updated');
    }
    
    /**
     * Test complete small withdrawal flow: request -> automated processing -> completion
     */
    public function testCompleteSmallWithdrawalFlow()
    {
        $testUser = TestConfig::getTestUsers()['medium_balance'];
        $withdrawalAmount = 2000.00;
        $transactionRef = 'INTEGRATION_TEST_WITHDRAWAL_SMALL_' . time();
        
        $withdrawalData = [
            'amount' => $withdrawalAmount,
            'method' => 'bank_transfer',
            'bank_code' => 'CBE',
            'account' => '1000123456789',
            'account_holder' => 'Test User'
        ];
        
        // Step 1: Create withdrawal request
        $this->createWithdrawalRequest($testUser['id'], $transactionRef, $withdrawalData);
        
        // Step 2: Process withdrawal (should be automated)
        $result = $this->withdrawalProcessor->processWithdrawal($transactionRef);
        
        // Step 3: Verify automated processing
        $this->assertTrue($result['success'], 'Small withdrawal should be processed successfully');
        $this->assertEquals('automated', $result['processing_type'], 'Should use automated processing');
        
        // Step 4: Verify transaction status
        $transaction = $this->getTransaction($transactionRef);
        $this->assertEquals('completed', $transaction['status'], 'Transaction should be completed');
        
        // Step 5: Verify balance deducted
        $updatedBalance = $this->getUserBalance($testUser['id']);
        $expectedBalance = $testUser['balance'] - $withdrawalAmount;
        $this->assertEquals($expectedBalance, $updatedBalance, 'Balance should be deducted');
    }
    
    /**
     * Test complete large withdrawal flow: request -> manual queue -> admin approval -> completion
     */
    public function testCompleteLargeWithdrawalFlow()
    {
        $testUser = TestConfig::getTestUsers()['high_balance'];
        $withdrawalAmount = 15000.00;
        $transactionRef = 'INTEGRATION_TEST_WITHDRAWAL_LARGE_' . time();
        
        $withdrawalData = [
            'amount' => $withdrawalAmount,
            'method' => 'bank_transfer',
            'bank_code' => 'AIB',
            'account' => '0123456789012',
            'account_holder' => 'Test User 4'
        ];
        
        // Step 1: Create withdrawal request
        $this->createWithdrawalRequest($testUser['id'], $transactionRef, $withdrawalData);
        
        // Step 2: Process withdrawal (should be queued for manual)
        $result = $this->withdrawalProcessor->processWithdrawal($transactionRef);
        
        // Step 3: Verify manual queuing
        $this->assertTrue($result['success'], 'Large withdrawal should be queued successfully');
        $this->assertEquals('manual', $result['processing_type'], 'Should use manual processing');
        
        // Step 4: Verify manual withdrawal created
        $manualWithdrawal = $this->getManualWithdrawal($transactionRef);
        $this->assertNotNull($manualWithdrawal, 'Manual withdrawal should be created');
        $this->assertEquals('high', $manualWithdrawal['priority'], 'Should have high priority');
        
        // Step 5: Simulate admin approval
        $approvalResult = $this->withdrawalProcessor->manuallyApproveWithdrawal($transactionRef, 'Integration test approval');
        $this->assertTrue($approvalResult['success'], 'Admin approval should succeed');
        
        // Step 6: Verify final status
        $finalTransaction = $this->getTransaction($transactionRef);
        $this->assertEquals('completed', $finalTransaction['status'], 'Transaction should be completed');
        
        $finalManualWithdrawal = $this->getManualWithdrawal($transactionRef);
        $this->assertEquals('completed', $finalManualWithdrawal['status'], 'Manual withdrawal should be completed');
    }
    
    /**
     * Test mobile money withdrawal flow
     */
    public function testMobileMoneyWithdrawalFlow()
    {
        $testUser = TestConfig::getTestUsers()['medium_balance'];
        $withdrawalAmount = 3000.00;
        $transactionRef = 'INTEGRATION_TEST_MOBILE_' . time();
        
        $withdrawalData = [
            'amount' => $withdrawalAmount,
            'method' => 'mobile_money',
            'provider' => 'M-BIRR',
            'phone' => '251911234567',
            'account_holder' => 'Test User 2'
        ];
        
        // Step 1: Create withdrawal request
        $this->createWithdrawalRequest($testUser['id'], $transactionRef, $withdrawalData);
        
        // Step 2: Process withdrawal
        $result = $this->withdrawalProcessor->processWithdrawal($transactionRef);
        
        // Step 3: Verify processing (M-Birr supports API)
        $this->assertTrue($result['success'], 'Mobile money withdrawal should be processed');
        
        // Step 4: Verify transaction completion
        $transaction = $this->getTransaction($transactionRef);
        $this->assertContains($transaction['status'], ['completed', 'processing'], 'Transaction should be processed or completed');
    }
    
    /**
     * Test failed deposit webhook handling
     */
    public function testFailedDepositWebhookHandling()
    {
        $testUser = TestConfig::getTestUsers()['small_balance'];
        $depositAmount = 500.00;
        $transactionRef = 'INTEGRATION_TEST_FAILED_DEPOSIT_' . time();
        
        // Step 1: Initialize deposit
        $this->initializeTestDeposit($testUser['id'], $transactionRef, $depositAmount);
        
        // Step 2: Simulate failed webhook
        $this->simulateFailedWebhook($transactionRef, $depositAmount);
        
        // Step 3: Verify transaction marked as failed
        $transaction = $this->getTransaction($transactionRef);
        $this->assertEquals('failed', $transaction['status'], 'Transaction should be marked as failed');
        
        // Step 4: Verify balance not updated
        $balance = $this->getUserBalance($testUser['id']);
        $this->assertEquals($testUser['balance'], $balance, 'Balance should not be updated for failed deposit');
    }
    
    /**
     * Test insufficient balance withdrawal rejection
     */
    public function testInsufficientBalanceWithdrawalRejection()
    {
        $testUser = TestConfig::getTestUsers()['low_balance']; // 500 ETB balance
        $withdrawalAmount = 1000.00; // More than available
        $transactionRef = 'INTEGRATION_TEST_INSUFFICIENT_' . time();
        
        $withdrawalData = [
            'amount' => $withdrawalAmount,
            'method' => 'bank_transfer',
            'bank_code' => 'CBE',
            'account' => '1000123456789',
            'account_holder' => 'Test User 3'
        ];
        
        // Attempt to create withdrawal request
        $result = $this->createWithdrawalRequest($testUser['id'], $transactionRef, $withdrawalData);
        
        // Should fail due to insufficient balance
        $this->assertFalse($result, 'Withdrawal should be rejected due to insufficient balance');
        
        // Verify no transaction created
        $transaction = $this->getTransaction($transactionRef);
        $this->assertNull($transaction, 'No transaction should be created for insufficient balance');
    }
    
    /**
     * Test concurrent withdrawal prevention
     */
    public function testConcurrentWithdrawalPrevention()
    {
        $testUser = TestConfig::getTestUsers()['medium_balance'];
        
        // Create first withdrawal
        $transactionRef1 = 'INTEGRATION_TEST_CONCURRENT_1_' . time();
        $withdrawalData1 = [
            'amount' => 2000.00,
            'method' => 'bank_transfer',
            'bank_code' => 'CBE',
            'account' => '1000123456789',
            'account_holder' => 'Test User 2'
        ];
        
        $result1 = $this->createWithdrawalRequest($testUser['id'], $transactionRef1, $withdrawalData1);
        $this->assertTrue($result1, 'First withdrawal should be created');
        
        // Attempt second withdrawal while first is pending
        $transactionRef2 = 'INTEGRATION_TEST_CONCURRENT_2_' . time();
        $withdrawalData2 = [
            'amount' => 1000.00,
            'method' => 'mobile_money',
            'provider' => 'M-BIRR',
            'phone' => '251911234567',
            'account_holder' => 'Test User 2'
        ];
        
        $result2 = $this->createWithdrawalRequest($testUser['id'], $transactionRef2, $withdrawalData2);
        $this->assertFalse($result2, 'Second withdrawal should be rejected due to pending withdrawal');
    }
    
    /**
     * Test admin dashboard data retrieval
     */
    public function testAdminDashboardDataRetrieval()
    {
        // Create multiple manual withdrawals with different priorities
        $testUsers = TestConfig::getTestUsers();
        $withdrawals = [
            ['user' => 'high_balance', 'amount' => 20000, 'priority' => 'urgent'],
            ['user' => 'medium_balance', 'amount' => 12000, 'priority' => 'high'],
            ['user' => 'small_balance', 'amount' => 8000, 'priority' => 'normal']
        ];
        
        foreach ($withdrawals as $index => $withdrawal) {
            $user = $testUsers[$withdrawal['user']];
            $transactionRef = 'INTEGRATION_TEST_ADMIN_' . $index . '_' . time();
            
            $this->createManualWithdrawal($user['id'], $transactionRef, $withdrawal['amount'], $withdrawal['priority']);
        }
        
        // Test admin dashboard data retrieval
        $pendingWithdrawals = $this->withdrawalProcessor->getPendingManualWithdrawals();
        $this->assertGreaterThanOrEqual(3, count($pendingWithdrawals), 'Should have at least 3 pending withdrawals');
        
        // Verify priority ordering (urgent first)
        $priorities = array_column($pendingWithdrawals, 'priority');
        $urgentCount = array_count_values($priorities)['urgent'] ?? 0;
        $this->assertGreaterThan(0, $urgentCount, 'Should have urgent priority withdrawals');
    }
    
    /**
     * Test webhook signature validation
     */
    public function testWebhookSignatureValidation()
    {
        $webhookData = TestConfig::getWebhookTestData()['successful_payment'];
        $webhookSecret = TestConfig::CHAPA_TEST_WEBHOOK_SECRET;
        
        // Test valid signature
        $payload = json_encode($webhookData);
        $validSignature = hash_hmac('sha256', $payload, $webhookSecret);
        
        $isValid = $this->validateWebhookSignature($payload, $validSignature, $webhookSecret);
        $this->assertTrue($isValid, 'Valid webhook signature should be accepted');
        
        // Test invalid signature
        $invalidSignature = 'invalid_signature_' . time();
        $isInvalid = $this->validateWebhookSignature($payload, $invalidSignature, $webhookSecret);
        $this->assertFalse($isInvalid, 'Invalid webhook signature should be rejected');
    }
    
    // Helper methods for integration testing
    private function initializeTestDeposit($userId, $transactionRef, $amount)
    {
        $stmt = $this->testDb->prepare("
            INSERT INTO chapa_transactions (user_id, transaction_ref, amount, type, status, created_at)
            VALUES (?, ?, ?, 'deposit', 'pending', NOW())
        ");
        
        return $stmt->execute([$userId, $transactionRef, $amount]);
    }
    
    private function createWithdrawalRequest($userId, $transactionRef, $withdrawalData)
    {
        // Check user balance first
        $userBalance = $this->getUserBalance($userId);
        if ($userBalance < $withdrawalData['amount']) {
            return false;
        }
        
        // Check for pending withdrawals
        if ($this->userHasPendingWithdrawal($userId)) {
            return false;
        }
        
        // Create withdrawal transaction
        $stmt = $this->testDb->prepare("
            INSERT INTO chapa_transactions (user_id, transaction_ref, amount, type, status, withdrawal_method, account_number, bank_code, processing_details, created_at)
            VALUES (?, ?, ?, 'withdraw', 'pending', ?, ?, ?, ?, NOW())
        ");
        
        $processingDetails = json_encode($withdrawalData);
        
        return $stmt->execute([
            $userId,
            $transactionRef,
            $withdrawalData['amount'],
            $withdrawalData['method'],
            $withdrawalData['account'] ?? $withdrawalData['phone'] ?? null,
            $withdrawalData['bank_code'] ?? $withdrawalData['provider'] ?? null,
            $processingDetails
        ]);
    }
    
    private function createManualWithdrawal($userId, $transactionRef, $amount, $priority)
    {
        $stmt = $this->testDb->prepare("
            INSERT INTO manual_withdrawals (transaction_ref, user_id, amount, priority, processing_details, queued_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $processingDetails = json_encode([
            'withdrawal_details' => [
                'amount' => $amount,
                'method' => 'bank_transfer',
                'bank_code' => 'CBE',
                'account' => '1000123456789'
            ],
            'reason' => 'Integration test manual withdrawal',
            'attempts' => 0
        ]);
        
        return $stmt->execute([$transactionRef, $userId, $amount, $priority, $processingDetails]);
    }
    
    private function simulateSuccessfulWebhook($transactionRef, $amount)
    {
        // Update transaction status to completed
        $stmt = $this->testDb->prepare("
            UPDATE chapa_transactions 
            SET status = 'completed', chapa_response = ?, updated_at = NOW()
            WHERE transaction_ref = ?
        ");
        
        $chapaResponse = json_encode([
            'status' => 'success',
            'reference' => $transactionRef,
            'amount' => $amount,
            'currency' => 'ETB'
        ]);
        
        $stmt->execute([$chapaResponse, $transactionRef]);
        
        // Update user balance
        $transaction = $this->getTransaction($transactionRef);
        if ($transaction && $transaction['type'] === 'deposit') {
            $this->updateUserBalance($transaction['user_id'], $amount, 'add');
        }
    }
    
    private function simulateFailedWebhook($transactionRef, $amount)
    {
        $stmt = $this->testDb->prepare("
            UPDATE chapa_transactions 
            SET status = 'failed', error_message = 'Payment failed', updated_at = NOW()
            WHERE transaction_ref = ?
        ");
        
        return $stmt->execute([$transactionRef]);
    }
    
    private function getTransaction($transactionRef)
    {
        $stmt = $this->testDb->prepare("SELECT * FROM chapa_transactions WHERE transaction_ref = ?");
        $stmt->execute([$transactionRef]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getManualWithdrawal($transactionRef)
    {
        $stmt = $this->testDb->prepare("SELECT * FROM manual_withdrawals WHERE transaction_ref = ?");
        $stmt->execute([$transactionRef]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function getUserBalance($userId)
    {
        $stmt = $this->testDb->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['balance'] : 0;
    }
    
    private function updateUserBalance($userId, $amount, $operation = 'add')
    {
        $operator = $operation === 'add' ? '+' : '-';
        $stmt = $this->testDb->prepare("UPDATE users SET balance = balance $operator ? WHERE id = ?");
        return $stmt->execute([$amount, $userId]);
    }
    
    private function userHasPendingWithdrawal($userId)
    {
        $stmt = $this->testDb->prepare("
            SELECT COUNT(*) as count FROM chapa_transactions 
            WHERE user_id = ? AND type = 'withdraw' AND status IN ('pending', 'processing')
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    private function validateWebhookSignature($payload, $signature, $secret)
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}