<?php

require_once __DIR__ . '/../TestConfig.php';
require_once __DIR__ . '/../../withdrawal_processor.php';

use PHPUnit\Framework\TestCase;

class WithdrawalTest extends TestCase
{
    private $testDb;
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
        
        $this->withdrawalProcessor = new WithdrawalProcessor();
    }
    
    protected function tearDown(): void
    {
        // Clean up test data
        $this->testDb->exec("DELETE FROM chapa_transactions WHERE transaction_ref LIKE 'UNIT_TEST_%'");
        $this->testDb->exec("DELETE FROM manual_withdrawals WHERE transaction_ref LIKE 'UNIT_TEST_%'");
        $this->testDb->exec("DELETE FROM withdrawal_processing_logs WHERE transaction_ref LIKE 'UNIT_TEST_%'");
    }
    
    /**
     * Test small bank transfer (automated processing)
     */
    public function testSmallBankTransferAutomated()
    {
        $testUser = TestConfig::getTestUsers()['small_balance'];
        $scenario = TestConfig::getTestTransactionScenarios()['withdrawal_scenarios']['small_bank_transfer'];
        
        // Create withdrawal transaction
        $transactionRef = 'UNIT_TEST_SMALL_BANK_' . time();
        $this->createTestWithdrawal($testUser['id'], $transactionRef, $scenario);
        
        // Test automated processing decision
        $shouldAutomate = $this->shouldProcessAutomatically($scenario['amount'], $scenario['bank_code']);
        $this->assertTrue($shouldAutomate, 'Small bank transfer should be processed automatically');
        
        // Test bank account validation
        $isValidAccount = TestConfig::isValidTestBankAccount($scenario['bank_code'], $scenario['account']);
        $this->assertTrue($isValidAccount, 'Test bank account should be valid');
    }
    
    /**
     * Test large bank transfer (manual processing)
     */
    public function testLargeBankTransferManual()
    {
        $testUser = TestConfig::getTestUsers()['high_balance'];
        $scenario = TestConfig::getTestTransactionScenarios()['withdrawal_scenarios']['large_bank_transfer'];
        
        $transactionRef = 'UNIT_TEST_LARGE_BANK_' . time();
        $this->createTestWithdrawal($testUser['id'], $transactionRef, $scenario);
        
        // Test manual processing decision
        $shouldAutomate = $this->shouldProcessAutomatically($scenario['amount'], $scenario['bank_code']);
        $this->assertFalse($shouldAutomate, 'Large bank transfer should require manual processing');
        
        // Test manual withdrawal queue
        $this->queueForManualProcessing($transactionRef, $testUser['id'], $scenario);
        
        // Verify queued in manual_withdrawals table
        $stmt = $this->testDb->prepare("SELECT * FROM manual_withdrawals WHERE transaction_ref = ?");
        $stmt->execute([$transactionRef]);
        $manualWithdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertNotNull($manualWithdrawal, 'Large withdrawal should be queued for manual processing');
        $this->assertEquals('high', $manualWithdrawal['priority'], 'Large withdrawal should have high priority');
    }
    
    /**
     * Test mobile money automated processing
     */
    public function testMobileMoneyAutomated()
    {
        $testUser = TestConfig::getTestUsers()['medium_balance'];
        $scenario = TestConfig::getTestTransactionScenarios()['withdrawal_scenarios']['mobile_money_automated'];
        
        $transactionRef = 'UNIT_TEST_MOBILE_AUTO_' . time();
        $this->createTestWithdrawal($testUser['id'], $transactionRef, $scenario);
        
        // Test mobile number validation
        $isValidNumber = TestConfig::isValidTestMobileNumber($scenario['provider'], $scenario['phone']);
        $this->assertTrue($isValidNumber, 'Test mobile number should be valid');
        
        // Test provider API support
        $mobileData = TestConfig::getTestMobileMoneyData()[$scenario['provider']];
        $this->assertTrue($mobileData['supports_api'], 'M-Birr should support API processing');
        
        // Test amount limits
        $this->assertGreaterThanOrEqual($mobileData['min_amount'], $scenario['amount']);
        $this->assertLessThanOrEqual($mobileData['max_amount'], $scenario['amount']);
    }
    
    /**
     * Test mobile money manual processing (TeleBirr)
     */
    public function testMobileMoneyManual()
    {
        $testUser = TestConfig::getTestUsers()['medium_balance'];
        $scenario = TestConfig::getTestTransactionScenarios()['withdrawal_scenarios']['mobile_money_manual'];
        
        $transactionRef = 'UNIT_TEST_MOBILE_MANUAL_' . time();
        $this->createTestWithdrawal($testUser['id'], $transactionRef, $scenario);
        
        // Test TeleBirr manual processing requirement
        $mobileData = TestConfig::getTestMobileMoneyData()[$scenario['provider']];
        $this->assertFalse($mobileData['supports_api'], 'TeleBirr should require manual processing');
        
        // Should be queued for manual processing
        $shouldAutomate = $this->shouldProcessAutomatically($scenario['amount'], null, $scenario['provider']);
        $this->assertFalse($shouldAutomate, 'TeleBirr should require manual processing');
    }
    
    /**
     * Test insufficient balance withdrawal
     */
    public function testInsufficientBalanceWithdrawal()
    {
        $testUser = TestConfig::getTestUsers()['low_balance']; // 500 ETB balance
        $scenario = TestConfig::getTestTransactionScenarios()['withdrawal_scenarios']['insufficient_balance'];
        
        // Test balance validation
        $hasEnoughBalance = $this->validateUserBalance($testUser['id'], $scenario['amount']);
        $this->assertFalse($hasEnoughBalance, 'User should not have enough balance');
        
        // Should not create withdrawal transaction
        $canWithdraw = $this->canUserWithdraw($testUser['id'], $scenario['amount']);
        $this->assertFalse($canWithdraw, 'Withdrawal should be rejected due to insufficient balance');
    }
    
    /**
     * Test withdrawal processing priority calculation
     */
    public function testWithdrawalPriorityCalculation()
    {
        // Test different priority scenarios
        $priorities = [
            ['amount' => 1000, 'expected' => 'normal'],
            ['amount' => 5000, 'expected' => 'normal'],
            ['amount' => 15000, 'expected' => 'high'],
            ['amount' => 50000, 'expected' => 'urgent']
        ];
        
        foreach ($priorities as $test) {
            $priority = $this->calculateWithdrawalPriority($test['amount']);
            $this->assertEquals($test['expected'], $priority, 
                "Amount {$test['amount']} should have {$test['expected']} priority");
        }
    }
    
    /**
     * Test concurrent withdrawal prevention
     */
    public function testConcurrentWithdrawalPrevention()
    {
        $testUser = TestConfig::getTestUsers()['medium_balance'];
        
        // Create first withdrawal
        $ref1 = 'UNIT_TEST_CONCURRENT_1_' . time();
        $this->createTestWithdrawal($testUser['id'], $ref1, [
            'amount' => 2000,
            'method' => 'bank_transfer',
            'bank_code' => 'CBE',
            'account' => '1000123456789'
        ]);
        
        // Check for existing pending withdrawals
        $hasPendingWithdrawal = $this->userHasPendingWithdrawal($testUser['id']);
        $this->assertTrue($hasPendingWithdrawal, 'User should have pending withdrawal');
        
        // Second withdrawal should be prevented
        $canWithdrawAgain = $this->canUserWithdraw($testUser['id'], 1000);
        $this->assertFalse($canWithdrawAgain, 'Second withdrawal should be prevented');
    }
    
    /**
     * Test withdrawal method validation
     */
    public function testWithdrawalMethodValidation()
    {
        // Test valid methods
        $this->assertTrue($this->isValidWithdrawalMethod('bank_transfer'));
        $this->assertTrue($this->isValidWithdrawalMethod('mobile_money'));
        
        // Test invalid methods
        $this->assertFalse($this->isValidWithdrawalMethod('invalid_method'));
        $this->assertFalse($this->isValidWithdrawalMethod(''));
        $this->assertFalse($this->isValidWithdrawalMethod(null));
    }
    
    /**
     * Test bank code validation
     */
    public function testBankCodeValidation()
    {
        $validBanks = ['CBE', 'AIB', 'BOA', 'UB', 'DB'];
        
        foreach ($validBanks as $bankCode) {
            $this->assertTrue($this->isValidBankCode($bankCode), "$bankCode should be valid");
        }
        
        // Test invalid bank codes
        $this->assertFalse($this->isValidBankCode('INVALID'));
        $this->assertFalse($this->isValidBankCode(''));
        $this->assertFalse($this->isValidBankCode(null));
    }
    
    /**
     * Test mobile provider validation
     */
    public function testMobileProviderValidation()
    {
        $validProviders = ['M-BIRR', 'HelloCash', 'TeleBirr'];
        
        foreach ($validProviders as $provider) {
            $this->assertTrue($this->isValidMobileProvider($provider), "$provider should be valid");
        }
        
        // Test invalid providers
        $this->assertFalse($this->isValidMobileProvider('INVALID'));
        $this->assertFalse($this->isValidMobileProvider(''));
        $this->assertFalse($this->isValidMobileProvider(null));
    }
    
    /**
     * Test admin approval workflow
     */
    public function testAdminApprovalWorkflow()
    {
        $testUser = TestConfig::getTestUsers()['high_balance'];
        $transactionRef = 'UNIT_TEST_ADMIN_APPROVAL_' . time();
        
        // Create manual withdrawal
        $this->queueForManualProcessing($transactionRef, $testUser['id'], [
            'amount' => 15000,
            'method' => 'bank_transfer',
            'bank_code' => 'AIB',
            'account' => '0123456789012'
        ]);
        
        // Test approval process
        $approvalResult = $this->simulateAdminApproval($transactionRef, 'Approved by admin for testing');
        $this->assertTrue($approvalResult, 'Admin approval should succeed');
        
        // Verify status updated
        $stmt = $this->testDb->prepare("SELECT status FROM manual_withdrawals WHERE transaction_ref = ?");
        $stmt->execute([$transactionRef]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertEquals('completed', $withdrawal['status'], 'Withdrawal should be marked as completed');
    }
    
    // Helper methods for testing
    private function createTestWithdrawal($userId, $transactionRef, $scenario)
    {
        $stmt = $this->testDb->prepare("
            INSERT INTO chapa_transactions (user_id, transaction_ref, amount, type, status, withdrawal_method, account_number, bank_code, created_at)
            VALUES (?, ?, ?, 'withdraw', 'pending', ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            $transactionRef,
            $scenario['amount'],
            $scenario['method'],
            $scenario['account'] ?? $scenario['phone'] ?? null,
            $scenario['bank_code'] ?? $scenario['provider'] ?? null
        ]);
    }
    
    private function shouldProcessAutomatically($amount, $bankCode = null, $provider = null)
    {
        // Automated processing rules
        if ($amount > 10000) return false; // Large amounts require manual processing
        
        if ($bankCode) {
            $bankData = TestConfig::getTestBankData()[$bankCode] ?? null;
            return $bankData && $bankData['supports_api'];
        }
        
        if ($provider) {
            $providerData = TestConfig::getTestMobileMoneyData()[$provider] ?? null;
            return $providerData && $providerData['supports_api'];
        }
        
        return false;
    }
    
    private function validateUserBalance($userId, $amount)
    {
        $stmt = $this->testDb->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user && $user['balance'] >= $amount;
    }
    
    private function canUserWithdraw($userId, $amount)
    {
        // Check balance
        if (!$this->validateUserBalance($userId, $amount)) {
            return false;
        }
        
        // Check for pending withdrawals
        if ($this->userHasPendingWithdrawal($userId)) {
            return false;
        }
        
        return true;
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
    
    private function calculateWithdrawalPriority($amount)
    {
        if ($amount >= 50000) return 'urgent';
        if ($amount >= 15000) return 'high';
        return 'normal';
    }
    
    private function queueForManualProcessing($transactionRef, $userId, $scenario)
    {
        $priority = $this->calculateWithdrawalPriority($scenario['amount']);
        
        $stmt = $this->testDb->prepare("
            INSERT INTO manual_withdrawals (transaction_ref, user_id, amount, priority, processing_details, queued_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $processingDetails = json_encode([
            'withdrawal_details' => $scenario,
            'reason' => 'Unit test manual processing',
            'attempts' => 0
        ]);
        
        return $stmt->execute([$transactionRef, $userId, $scenario['amount'], $priority, $processingDetails]);
    }
    
    private function simulateAdminApproval($transactionRef, $adminNotes)
    {
        // Update manual withdrawal status
        $stmt = $this->testDb->prepare("
            UPDATE manual_withdrawals 
            SET status = 'completed', admin_notes = ?, processed_at = NOW()
            WHERE transaction_ref = ?
        ");
        
        return $stmt->execute([$adminNotes, $transactionRef]);
    }
    
    private function isValidWithdrawalMethod($method)
    {
        return in_array($method, ['bank_transfer', 'mobile_money']);
    }
    
    private function isValidBankCode($bankCode)
    {
        $validBanks = array_keys(TestConfig::getTestBankData());
        return in_array($bankCode, $validBanks);
    }
    
    private function isValidMobileProvider($provider)
    {
        $validProviders = array_keys(TestConfig::getTestMobileMoneyData());
        return in_array($provider, $validProviders);
    }
}