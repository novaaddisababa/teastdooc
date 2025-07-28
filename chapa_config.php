<?php
require_once __DIR__ . '/chapa-php/vendor/autoload.php';

use Chapa\Chapa;
use Chapa\Model\PostData;

class ChapaConfig {
    private static $secretKey = null;
    private static $chapa = null;
    private static $baseUrl = null;
    private static $webhookSecret = null;
    
    public static function init() {
        // Load environment variables
        self::loadEnv();
        
        // Validate secret key
        if (!self::$secretKey || 
            self::$secretKey === 'your_chapa_secret_key_here' || 
            self::$secretKey === 'CHASECK_TEST-your_actual_test_key_here' ||
            self::$secretKey === 'your_webhook_secret_here') {
            throw new Exception("Chapa secret key not properly configured. Please set a valid CHAPA_SECRET_KEY in .env file");
        }
        
        // Validate secret key format
        if (!preg_match('/^CHASECK(_TEST)?-[a-zA-Z0-9]+$/', self::$secretKey)) {
            throw new Exception("Invalid Chapa secret key format. Key should start with CHASECK- or CHASECK_TEST-");
        }
        
        try {
            self::$chapa = new Chapa(self::$secretKey);
            return self::$chapa;
        } catch (Exception $e) {
            throw new Exception("Failed to initialize Chapa SDK: " . $e->getMessage());
        }
    }
    
    private static function loadEnv() {
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    
                    switch ($key) {
                        case 'CHAPA_SECRET_KEY':
                            self::$secretKey = $value;
                            break;
                        case 'CHAPA_BASE_URL':
                            self::$baseUrl = $value;
                            break;
                        case 'CHAPA_WEBHOOK_SECRET':
                            self::$webhookSecret = $value;
                            break;
                    }
                }
            }
        }
        
        // Fallback to environment variables
        if (!self::$secretKey) {
            self::$secretKey = getenv('CHAPA_SECRET_KEY');
        }
        if (!self::$baseUrl) {
            self::$baseUrl = getenv('CHAPA_BASE_URL') ?: 'https://api.chapa.co/v1';
        }
        if (!self::$webhookSecret) {
            self::$webhookSecret = getenv('CHAPA_WEBHOOK_SECRET');
        }
    }
    
    public static function getChapa() {
        if (!self::$chapa) {
            self::init();
        }
        return self::$chapa;
    }
    
    public static function generateTransactionRef($prefix = 'TXN') {
        return $prefix . '_' . time() . '_' . bin2hex(random_bytes(8));
    }
    
    public static function validateAmount($amount) {
        return is_numeric($amount) && $amount >= 1 && $amount <= 50000;
    }
    
    public static function formatAmount($amount) {
        return number_format((float)$amount, 2, '.', '');
    }
    
    public static function getCallbackUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/chapa_callback.php';
    }
    
    public static function getReturnUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/index.php?payment=success';
    }
    
    public static function getWebhookUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/chapa_webhook.php';
    }
    
    public static function verifyWebhookSignature($payload, $signature) {
        if (!self::$webhookSecret) {
            error_log('Webhook secret not configured');
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, self::$webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }
    
    public static function isTestMode() {
        return strpos(self::$secretKey ?? '', 'CHASECK_TEST-') === 0;
    }
    
    public static function logTransaction($message, $data = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'data' => $data,
            'test_mode' => self::isTestMode()
        ];
        error_log('Chapa Transaction: ' . json_encode($logData));
    }
}