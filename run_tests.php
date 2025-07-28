<?php
/**
 * Simple Test Runner for Chapa Payment System
 * Runs tests without requiring PHPUnit installation
 */

// Set up environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🧪 Chapa Payment System Test Runner\n";
echo "=====================================\n\n";

// Check MySQL connection first
function checkMySQLConnection() {
    echo "🔍 Checking MySQL connection...\n";
    
    try {
        $pdo = new PDO("mysql:host=localhost", "root", "");
        echo "✅ MySQL connection successful\n\n";
        return true;
    } catch (PDOException $e) {
        echo "❌ MySQL connection failed: " . $e->getMessage() . "\n";
        echo "💡 Please start MySQL service: sudo systemctl start mysql\n\n";
        return false;
    }
}

// Set up test database
function setupTestDatabase() {
    echo "🗄️ Setting up test database...\n";
    
    try {
        $pdo = new PDO("mysql:host=localhost", "root", "");
        
        // Create test database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS toady_game_test");
        echo "✅ Test database created\n";
        
        // Create test user (if needed)
        $pdo->exec("CREATE USER IF NOT EXISTS 'test_user'@'localhost' IDENTIFIED BY 'test_password'");
        $pdo->exec("GRANT ALL PRIVILEGES ON toady_game_test.* TO 'test_user'@'localhost'");
        $pdo->exec("FLUSH PRIVILEGES");
        echo "✅ Test user created with permissions\n";
        
        return true;
    } catch (PDOException $e) {
        echo "❌ Database setup failed: " . $e->getMessage() . "\n";
        return false;
    }
}

// Test basic functionality without PHPUnit
function runBasicTests() {
    echo "🔬 Running basic functionality tests...\n\n";
    
    // Test 1: Check if test configuration loads
    echo "Test 1: Loading test configuration...\n";
    if (file_exists('tests/TestConfig.php')) {
        require_once 'tests/TestConfig.php';
        $testUsers = TestConfig::getTestUsers();
        echo "✅ Test configuration loaded successfully\n";
        echo "   - Found " . count($testUsers) . " test users\n";
        
        $bankData = TestConfig::getTestBankData();
        echo "   - Found " . count($bankData) . " test banks\n";
        
        $mobileData = TestConfig::getTestMobileMoneyData();
        echo "   - Found " . count($mobileData) . " mobile providers\n\n";
    } else {
        echo "❌ TestConfig.php not found\n\n";
        return false;
    }
    
    // Test 2: Check database connection with test credentials
    echo "Test 2: Testing database connection...\n";
    try {
        $config = TestConfig::getTestDatabaseConfig();
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
            $config['username'],
            $config['password']
        );
        echo "✅ Test database connection successful\n\n";
    } catch (PDOException $e) {
        echo "❌ Test database connection failed: " . $e->getMessage() . "\n\n";
        return false;
    }
    
    // Test 3: Validate test bank accounts
    echo "Test 3: Validating test bank accounts...\n";
    $validAccounts = 0;
    foreach ($bankData as $bankCode => $bank) {
        foreach ($bank['test_accounts'] as $account) {
            if (TestConfig::isValidTestBankAccount($bankCode, $account)) {
                $validAccounts++;
            }
        }
    }
    echo "✅ Validated $validAccounts test bank accounts\n\n";
    
    // Test 4: Validate test mobile numbers
    echo "Test 4: Validating test mobile numbers...\n";
    $validNumbers = 0;
    foreach ($mobileData as $provider => $data) {
        foreach ($data['test_numbers'] as $number) {
            if (TestConfig::isValidTestMobileNumber($provider, $number)) {
                $validNumbers++;
            }
        }
    }
    echo "✅ Validated $validNumbers test mobile numbers\n\n";
    
    return true;
}

// Test Chapa configuration
function testChapaConfig() {
    echo "🔐 Testing Chapa configuration...\n";
    
    if (file_exists('chapa_config.php')) {
        require_once 'chapa_config.php';
        echo "✅ Chapa configuration file found\n";
        
        // Check if .env file exists
        if (file_exists('.env')) {
            echo "✅ Environment file found\n";
        } else {
            echo "⚠️ .env file not found - create from .env template\n";
        }
    } else {
        echo "❌ chapa_config.php not found\n";
        return false;
    }
    
    echo "\n";
    return true;
}

// Test withdrawal processor
function testWithdrawalProcessor() {
    echo "💸 Testing withdrawal processor...\n";
    
    if (file_exists('withdrawal_processor.php')) {
        require_once 'withdrawal_processor.php';
        echo "✅ Withdrawal processor file found\n";
        
        try {
            $processor = new WithdrawalProcessor();
            echo "✅ WithdrawalProcessor class instantiated successfully\n";
        } catch (Exception $e) {
            echo "❌ WithdrawalProcessor instantiation failed: " . $e->getMessage() . "\n";
            return false;
        }
    } else {
        echo "❌ withdrawal_processor.php not found\n";
        return false;
    }
    
    echo "\n";
    return true;
}

// Display test data summary
function displayTestDataSummary() {
    echo "📊 Test Data Summary\n";
    echo "===================\n\n";
    
    require_once 'tests/TestConfig.php';
    
    // Bank data
    echo "🏛️ Ethiopian Banks:\n";
    $bankData = TestConfig::getTestBankData();
    foreach ($bankData as $code => $bank) {
        $apiSupport = $bank['supports_api'] ? '✅ API' : '❌ Manual';
        echo "   $code ({$bank['name']}) - $apiSupport\n";
        echo "      Accounts: " . implode(', ', $bank['test_accounts']) . "\n";
    }
    echo "\n";
    
    // Mobile money
    echo "📱 Mobile Money Providers:\n";
    $mobileData = TestConfig::getTestMobileMoneyData();
    foreach ($mobileData as $code => $provider) {
        $apiSupport = $provider['supports_api'] ? '✅ API' : '❌ Manual';
        echo "   {$provider['name']} ($code) - $apiSupport\n";
        echo "      Numbers: " . implode(', ', $provider['test_numbers']) . "\n";
        echo "      Limits: ETB {$provider['min_amount']} - ETB {$provider['max_amount']}\n";
    }
    echo "\n";
    
    // Test users
    echo "👥 Test Users:\n";
    $testUsers = TestConfig::getTestUsers();
    foreach ($testUsers as $key => $user) {
        echo "   {$user['username']} ({$user['email']}) - ETB {$user['balance']}\n";
        echo "      Use case: {$user['use_case']}\n";
    }
    echo "\n";
}

// Main execution
function main() {
    // Check MySQL first
    if (!checkMySQLConnection()) {
        echo "❌ Cannot proceed without MySQL. Please start MySQL service and try again.\n";
        return;
    }
    
    // Set up test database
    if (!setupTestDatabase()) {
        echo "❌ Cannot proceed without test database setup.\n";
        return;
    }
    
    // Run basic tests
    if (!runBasicTests()) {
        echo "❌ Basic tests failed.\n";
        return;
    }
    
    // Test Chapa configuration
    testChapaConfig();
    
    // Test withdrawal processor
    testWithdrawalProcessor();
    
    // Display test data summary
    displayTestDataSummary();
    
    echo "🎉 All basic tests completed!\n\n";
    echo "📋 Next Steps:\n";
    echo "1. Ensure MySQL is running: sudo systemctl start mysql ✅\n";
    echo "2. Update .env file with your Chapa test keys\n";
    echo "3. Test deposit/withdrawal flows in your browser\n";
    echo "4. Access admin dashboard at: admin_withdrawals.php\n\n";
    
    echo "🔗 Quick Links:\n";
    echo "- Test data reference: tests/CHAPA_TEST_DATA.md\n";
    echo "- Testing guide: TESTING_GUIDE.md\n";
    echo "- Admin dashboard: admin_withdrawals.php\n";
    echo "- Main application: index.php\n\n";
    
    echo "💡 To test payments:\n";
    echo "   php -S localhost:8000\n";
    echo "   Then visit: http://localhost:8000/index.php\n";
}

// Run the tests
main();
?>