<?php
/**
 * PHPUnit Bootstrap File for Chapa Payment System Tests
 * Sets up test environment and loads necessary dependencies
 */

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define test constants
define('TEST_MODE', true);
define('TEST_ROOT', __DIR__);
define('PROJECT_ROOT', dirname(__DIR__));

// Load test configuration
require_once __DIR__ . '/TestConfig.php';

// Load project dependencies
require_once PROJECT_ROOT . '/chapa_config.php';
require_once PROJECT_ROOT . '/withdrawal_processor.php';

// Set up test database connection
function setupTestDatabase()
{
    $config = TestConfig::getTestDatabaseConfig();
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};charset={$config['charset']}",
            $config['username'],
            $config['password']
        );
        
        // Create test database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['dbname']}`");
        $pdo->exec("USE `{$config['dbname']}`");
        
        echo "✓ Test database setup complete\n";
        return $pdo;
        
    } catch (PDOException $e) {
        echo "✗ Test database setup failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Initialize test environment
function initializeTestEnvironment()
{
    // Set up test database
    $testDb = setupTestDatabase();
    
    // Create test tables if they don't exist
    createTestTables($testDb);
    
    // Set environment variables for testing
    $_ENV['CHAPA_TEST_MODE'] = 'true';
    $_ENV['CHAPA_SECRET_KEY'] = TestConfig::CHAPA_TEST_SECRET_KEY;
    $_ENV['CHAPA_PUBLIC_KEY'] = TestConfig::CHAPA_TEST_PUBLIC_KEY;
    
    echo "✓ Test environment initialized\n";
}

// Create necessary test tables
function createTestTables($pdo)
{
    $tables = [
        // Users table
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL UNIQUE,
            `email` varchar(100) NOT NULL UNIQUE,
            `password` varchar(255) NOT NULL,
            `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Chapa transactions table
        "CREATE TABLE IF NOT EXISTS `chapa_transactions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `transaction_ref` varchar(100) NOT NULL UNIQUE,
            `amount` decimal(10,2) NOT NULL,
            `type` enum('deposit','withdraw') NOT NULL,
            `status` enum('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
            `withdrawal_method` varchar(50) DEFAULT NULL,
            `account_number` varchar(100) DEFAULT NULL,
            `bank_code` varchar(20) DEFAULT NULL,
            `chapa_response` text DEFAULT NULL,
            `processing_details` text DEFAULT NULL,
            `error_message` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `status` (`status`),
            KEY `type` (`type`),
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Manual withdrawals table
        "CREATE TABLE IF NOT EXISTS `manual_withdrawals` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `transaction_ref` varchar(100) NOT NULL UNIQUE,
            `user_id` int(11) NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `status` enum('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
            `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
            `processing_details` text NOT NULL,
            `admin_notes` text DEFAULT NULL,
            `assigned_to` int(11) DEFAULT NULL,
            `queued_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `processed_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            KEY `status` (`status`),
            KEY `priority` (`priority`),
            FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Admin users table
        "CREATE TABLE IF NOT EXISTS `admin_users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL UNIQUE,
            `email` varchar(100) NOT NULL UNIQUE,
            `password_hash` varchar(255) NOT NULL,
            `role` enum('admin','super_admin','viewer') NOT NULL DEFAULT 'admin',
            `permissions` json DEFAULT NULL,
            `last_login` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Withdrawal processing logs table
        "CREATE TABLE IF NOT EXISTS `withdrawal_processing_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `transaction_ref` varchar(100) NOT NULL,
            `action` varchar(50) NOT NULL,
            `performed_by` varchar(100) NOT NULL,
            `details` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `transaction_ref` (`transaction_ref`),
            KEY `action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    
    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            echo "Warning: Could not create table - " . $e->getMessage() . "\n";
        }
    }
    
    echo "✓ Test tables created\n";
}

// Clean up function for tests
function cleanupTestData()
{
    $config = TestConfig::getTestDatabaseConfig();
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
            $config['username'],
            $config['password']
        );
        
        // Clean up test data
        $pdo->exec("DELETE FROM withdrawal_processing_logs WHERE transaction_ref LIKE '%TEST%'");
        $pdo->exec("DELETE FROM manual_withdrawals WHERE transaction_ref LIKE '%TEST%'");
        $pdo->exec("DELETE FROM chapa_transactions WHERE transaction_ref LIKE '%TEST%'");
        $pdo->exec("DELETE FROM users WHERE email LIKE '%example.com'");
        $pdo->exec("DELETE FROM admin_users WHERE username LIKE 'test%'");
        
    } catch (PDOException $e) {
        echo "Warning: Could not clean up test data - " . $e->getMessage() . "\n";
    }
}

// Register shutdown function to clean up
register_shutdown_function('cleanupTestData');

// Initialize test environment
initializeTestEnvironment();

echo "🧪 Chapa Payment System Test Environment Ready!\n";
echo "📊 Test Database: " . TestConfig::getTestDatabaseConfig()['dbname'] . "\n";
echo "🔑 Test Mode: Enabled\n";
echo "🚀 Ready to run tests...\n\n";