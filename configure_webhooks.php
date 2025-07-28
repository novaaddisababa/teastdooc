<?php
/**
 * Chapa Webhook Configuration Script
 * This script configures all webhook endpoints with ngrok URL
 */

require_once 'env_loader.php';
require_once 'chapa_config.php';

// Load environment variables
function loadEnvironmentVariables() {
    $env = [];
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
    }
    return $env;
}

$env = loadEnvironmentVariables();

// Configuration constants
define('NGROK_URL', $env['NGROK_URL'] ?? 'https://041589671ab5.ngrok-free.app');
define('WEBHOOK_URL', NGROK_URL . '/chapa_webhook.php');
define('CALLBACK_URL', NGROK_URL . '/chapa_callback.php');
define('RETURN_URL', NGROK_URL . '/deposit_success.php');

class WebhookConfigurator {
    private $chapaConfig;
    private $ngrokUrl;
    
    public function __construct() {
        $this->chapaConfig = new ChapaConfig();
        $this->ngrokUrl = NGROK_URL;
    }
    
    /**
     * Display current webhook configuration
     */
    public function displayConfiguration() {
        echo "\n=== CHAPA WEBHOOK CONFIGURATION ===\n";
        echo "ngrok Public URL: " . $this->ngrokUrl . "\n";
        echo "Webhook URL: " . WEBHOOK_URL . "\n";
        echo "Callback URL: " . CALLBACK_URL . "\n";
        echo "Return URL: " . RETURN_URL . "\n";
        echo "\n=== CHAPA DASHBOARD SETUP INSTRUCTIONS ===\n";
        echo "1. Go to https://dashboard.chapa.co/\n";
        echo "2. Navigate to Settings → Webhooks\n";
        echo "3. Add webhook URL: " . WEBHOOK_URL . "\n";
        echo "4. Subscribe to events: payment.success, payment.failed, payment.cancelled\n";
        echo "5. Copy the webhook secret and update your .env file\n";
        echo "\n=== TESTING URLS ===\n";
        echo "Test Deposit: " . $this->ngrokUrl . "/index.php\n";
        echo "Test Webhook: " . WEBHOOK_URL . "\n";
        echo "Test Callback: " . CALLBACK_URL . "\n";
    }
    
    /**
     * Test webhook endpoint accessibility
     */
    public function testWebhookEndpoint() {
        echo "\n=== TESTING WEBHOOK ENDPOINT ===\n";
        
        $webhookUrl = WEBHOOK_URL;
        $testData = json_encode([
            'event' => 'test',
            'data' => [
                'id' => 'test_' . time(),
                'status' => 'success',
                'amount' => 100,
                'currency' => 'ETB'
            ]
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Chapa-Signature: test_signature'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ Webhook test failed: $error\n";
            return false;
        }
        
        echo "✅ Webhook endpoint accessible\n";
        echo "HTTP Status: $httpCode\n";
        echo "Response: $response\n";
        
        return $httpCode === 200;
    }
    
    /**
     * Test callback endpoint accessibility
     */
    public function testCallbackEndpoint() {
        echo "\n=== TESTING CALLBACK ENDPOINT ===\n";
        
        $callbackUrl = CALLBACK_URL . '?trx_ref=test_' . time() . '&status=success';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $callbackUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ Callback test failed: $error\n";
            return false;
        }
        
        echo "✅ Callback endpoint accessible\n";
        echo "HTTP Status: $httpCode\n";
        
        return $httpCode === 200 || $httpCode === 302;
    }
    
    /**
     * Generate sample cURL commands for testing
     */
    public function generateTestCommands() {
        echo "\n=== SAMPLE CURL COMMANDS FOR TESTING ===\n";
        
        // Test deposit
        echo "\n1. Test Deposit:\n";
        echo "curl -X POST " . $this->ngrokUrl . "/deposit.php \\\n";
        echo "  -H 'Content-Type: application/json' \\\n";
        echo "  -d '{\n";
        echo "    \"amount\": 100,\n";
        echo "    \"email\": \"test@example.com\",\n";
        echo "    \"first_name\": \"Test\",\n";
        echo "    \"last_name\": \"User\",\n";
        echo "    \"phone_number\": \"+251911234567\"\n";
        echo "  }'\n";
        
        // Test webhook
        echo "\n2. Test Webhook (simulate Chapa callback):\n";
        echo "curl -X POST " . WEBHOOK_URL . " \\\n";
        echo "  -H 'Content-Type: application/json' \\\n";
        echo "  -H 'X-Chapa-Signature: test_signature' \\\n";
        echo "  -d '{\n";
        echo "    \"event\": \"payment.success\",\n";
        echo "    \"data\": {\n";
        echo "      \"id\": \"tx_test_123\",\n";
        echo "      \"status\": \"success\",\n";
        echo "      \"amount\": 100,\n";
        echo "      \"currency\": \"ETB\",\n";
        echo "      \"reference\": \"ref_test_123\"\n";
        echo "    }\n";
        echo "  }'\n";
    }
    
    /**
     * Check ngrok tunnel status
     */
    public function checkNgrokStatus() {
        echo "\n=== NGROK TUNNEL STATUS ===\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:4040/api/tunnels');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "❌ Cannot connect to ngrok API: $error\n";
            echo "Make sure ngrok is running with: ngrok http 80\n";
            return false;
        }
        
        $data = json_decode($response, true);
        if (empty($data['tunnels'])) {
            echo "❌ No active ngrok tunnels found\n";
            return false;
        }
        
        foreach ($data['tunnels'] as $tunnel) {
            if ($tunnel['proto'] === 'https') {
                echo "✅ Active HTTPS tunnel: " . $tunnel['public_url'] . "\n";
                echo "   Local address: " . $tunnel['config']['addr'] . "\n";
                echo "   Connections: " . $tunnel['metrics']['conns']['count'] . "\n";
                return true;
            }
        }
        
        echo "❌ No HTTPS tunnel found\n";
        return false;
    }
    
    /**
     * Run complete configuration check
     */
    public function runCompleteCheck() {
        echo "\n🚀 STARTING COMPLETE WEBHOOK CONFIGURATION CHECK\n";
        echo str_repeat('=', 60) . "\n";
        
        $this->displayConfiguration();
        
        $ngrokOk = $this->checkNgrokStatus();
        $webhookOk = $this->testWebhookEndpoint();
        $callbackOk = $this->testCallbackEndpoint();
        
        $this->generateTestCommands();
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📊 CONFIGURATION SUMMARY:\n";
        echo "ngrok Status: " . ($ngrokOk ? '✅ OK' : '❌ FAILED') . "\n";
        echo "Webhook Endpoint: " . ($webhookOk ? '✅ OK' : '❌ FAILED') . "\n";
        echo "Callback Endpoint: " . ($callbackOk ? '✅ OK' : '❌ FAILED') . "\n";
        
        if ($ngrokOk && $webhookOk && $callbackOk) {
            echo "\n🎉 ALL SYSTEMS READY! Your Chapa webhook integration is configured and working.\n";
            echo "\n📋 NEXT STEPS:\n";
            echo "1. Update your Chapa dashboard with the webhook URL above\n";
            echo "2. Add your actual Chapa secret key to .env file\n";
            echo "3. Test a real payment using the deposit form\n";
        } else {
            echo "\n⚠️  SOME ISSUES DETECTED. Please check the errors above.\n";
        }
        
        echo "\n" . str_repeat('=', 60) . "\n";
    }
}

// Run the configuration if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $configurator = new WebhookConfigurator();
    $configurator->runCompleteCheck();
}

?>