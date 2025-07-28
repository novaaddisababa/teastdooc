<?php
// Debug script to test Chapa SDK integration
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Chapa SDK Debug Test</h2>\n";

// Test 1: Check if autoloader exists
echo "<h3>1. Checking Autoloader</h3>\n";
$autoloadPath = __DIR__ . '/chapa-php/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    echo "✓ Autoloader found at: $autoloadPath<br>\n";
    require_once $autoloadPath;
} else {
    echo "✗ Autoloader NOT found at: $autoloadPath<br>\n";
    exit("Cannot proceed without autoloader");
}

// Test 2: Check if Chapa classes can be loaded
echo "<h3>2. Checking Chapa Classes</h3>\n";
try {
    if (class_exists('Chapa\Chapa')) {
        echo "✓ Chapa\Chapa class found<br>\n";
    } else {
        echo "✗ Chapa\Chapa class NOT found<br>\n";
    }
    
    if (class_exists('Chapa\Model\PostData')) {
        echo "✓ Chapa\Model\PostData class found<br>\n";
    } else {
        echo "✗ Chapa\Model\PostData class NOT found<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Error loading Chapa classes: " . $e->getMessage() . "<br>\n";
}

// Test 3: Check environment configuration
echo "<h3>3. Checking Environment Configuration</h3>\n";
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo "✓ .env file found<br>\n";
    $envContent = file_get_contents($envFile);
    if (strpos($envContent, 'CHAPA_SECRET_KEY') !== false) {
        echo "✓ CHAPA_SECRET_KEY found in .env<br>\n";
        
        // Load the key
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $secretKey = null;
        foreach ($lines as $line) {
            if (strpos($line, 'CHAPA_SECRET_KEY=') === 0 && strpos($line, '#') !== 0) {
                $secretKey = trim(substr($line, strlen('CHAPA_SECRET_KEY=')), " \t\n\r\0\x0B\"'");
                break;
            }
        }
        
        if ($secretKey && $secretKey !== 'your_chapa_secret_key_here') {
            echo "✓ Secret key is configured (not default)<br>\n";
            echo "Key format: " . substr($secretKey, 0, 10) . "...<br>\n";
        } else {
            echo "✗ Secret key is not properly configured<br>\n";
        }
    } else {
        echo "✗ CHAPA_SECRET_KEY not found in .env<br>\n";
    }
} else {
    echo "✗ .env file NOT found<br>\n";
}

// Test 4: Test ChapaConfig class
echo "<h3>4. Testing ChapaConfig Class</h3>\n";
try {
    require_once __DIR__ . '/chapa_config.php';
    echo "✓ ChapaConfig class loaded<br>\n";
    
    $chapa = ChapaConfig::getChapa();
    if ($chapa) {
        echo "✓ Chapa instance created successfully<br>\n";
        echo "Chapa class: " . get_class($chapa) . "<br>\n";
    } else {
        echo "✗ Failed to create Chapa instance<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Error with ChapaConfig: " . $e->getMessage() . "<br>\n";
}

// Test 5: Test basic API connectivity (if we have a valid key)
echo "<h3>5. Testing API Connectivity</h3>\n";
if (isset($chapa) && $chapa) {
    try {
        // Create a test transaction using proper setter methods
        $postData = new \Chapa\Model\PostData();
        $postData->setAmount('10.00');
        $postData->setCurrency('ETB');
        $postData->setEmail('test@example.com');
        $postData->setFirstName('Test');
        $postData->setLastName('User');
        $postData->setPhoneNumber('0911234567');
        $postData->setTxRef('TEST_' . time());
        $postData->setCallbackUrl('https://example.com/callback');
        $postData->setReturnUrl('https://example.com/return');
        
        echo "Attempting to initialize test payment using setter methods...<br>\n";
        $response = $chapa->initializePayment($postData);
        
        if ($response) {
            echo "✓ API responded<br>\n";
            echo "Response status: " . $response->getStatus() . "<br>\n";
            echo "Response message: " . $response->getMessage() . "<br>\n";
            
            if ($response->getStatus() === 'success') {
                echo "✓ Test payment initialization successful<br>\n";
            } else {
                echo "⚠ Test payment failed, but API is responding<br>\n";
            }
        } else {
            echo "✗ No response from API<br>\n";
        }
    } catch (Exception $e) {
        echo "✗ API Error: " . $e->getMessage() . "<br>\n";
        echo "Error details: " . print_r($e, true) . "<br>\n";
    }
} else {
    echo "⚠ Skipping API test - Chapa instance not available<br>\n";
}

// Test 6: Check database connection
echo "<h3>6. Testing Database Connection</h3>\n";
try {
    require_once __DIR__ . '/db_connect.php';
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "✓ Database connection successful<br>\n";
        
        // Check if chapa_transactions table exists
        $stmt = $conn->query("SHOW TABLES LIKE 'chapa_transactions'");
        if ($stmt->rowCount() > 0) {
            echo "✓ chapa_transactions table exists<br>\n";
        } else {
            echo "✗ chapa_transactions table NOT found<br>\n";
        }
    } else {
        echo "✗ Database connection failed<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Database Error: " . $e->getMessage() . "<br>\n";
}

echo "<h3>Debug Complete</h3>\n";
echo "Check the results above to identify integration issues.<br>\n";
?>
