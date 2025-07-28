<?php

/**
 * Test Configuration Class
 * Provides test data for the Chapa Payment System tests
 */
class TestConfig
{
    /**
     * Get test users for testing
     * @return array
     */
    public static function getTestUsers()
    {
        return [
            [
                'id' => 1,
                'username' => 'testuser1',
                'email' => 'test1@example.com',
                'balance' => 1000.00
            ],
            [
                'id' => 2,
                'username' => 'testuser2',
                'email' => 'test2@example.com',
                'balance' => 500.00
            ],
            [
                'id' => 3,
                'username' => 'testuser3',
                'email' => 'test3@example.com',
                'balance' => 0.00
            ]
        ];
    }

    /**
     * Get test bank data for testing
     * @return array
     */
    public static function getTestBankData()
    {
        return [
            [
                'bank_code' => 'CBE',
                'bank_name' => 'Commercial Bank of Ethiopia',
                'account_number' => '1234567890',
                'account_holder' => 'Test User One'
            ],
            [
                'bank_code' => 'DBE',
                'bank_name' => 'Development Bank of Ethiopia',
                'account_number' => '0987654321',
                'account_holder' => 'Test User Two'
            ],
            [
                'bank_code' => 'AIB',
                'bank_name' => 'Awash International Bank',
                'account_number' => '1122334455',
                'account_holder' => 'Test User Three'
            ]
        ];
    }

    /**
     * Get test mobile money data for testing
     * @return array
     */
    public static function getTestMobileMoneyData()
    {
        return [
            [
                'provider' => 'telebirr',
                'provider_name' => 'TeleBirr',
                'phone_number' => '+251911123456',
                'account_holder' => 'Test User One'
            ],
            [
                'provider' => 'mpesa',
                'provider_name' => 'M-Pesa',
                'phone_number' => '+251922654321',
                'account_holder' => 'Test User Two'
            ],
            [
                'provider' => 'cbepay',
                'provider_name' => 'CBE Pay',
                'phone_number' => '+251933987654',
                'account_holder' => 'Test User Three'
            ]
        ];
    }

    /**
     * Get test deposit amounts for testing
     * @return array
     */
    public static function getTestDepositAmounts()
    {
        return [10, 50, 100, 500, 1000, 5000];
    }

    /**
     * Get test withdrawal amounts for testing
     * @return array
     */
    public static function getTestWithdrawalAmounts()
    {
        return [5, 25, 75, 200, 500];
    }

    /**
     * Get Chapa test configuration
     * @return array
     */
    public static function getChapaTestConfig()
    {
        return [
            'test_secret_key' => 'CHASECK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'test_public_key' => 'CHAPUBK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'webhook_secret' => 'test_webhook_secret_key',
            'callback_url' => 'http://localhost/final-toady-game/chapa_callback.php',
            'webhook_url' => 'http://localhost/final-toady-game/chapa_webhook.php'
        ];
    }

    /**
     * Get test transaction data
     * @return array
     */
    public static function getTestTransactions()
    {
        return [
            [
                'id' => 'tx_test_001',
                'user_id' => 1,
                'amount' => 100.00,
                'currency' => 'ETB',
                'type' => 'deposit',
                'status' => 'completed',
                'payment_method' => 'telebirr',
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'tx_test_002',
                'user_id' => 2,
                'amount' => 50.00,
                'currency' => 'ETB',
                'type' => 'withdrawal',
                'status' => 'pending',
                'payment_method' => 'bank_transfer',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Get test API responses
     * @return array
     */
    public static function getTestApiResponses()
    {
        return [
            'successful_deposit' => [
                'status' => 'success',
                'message' => 'Payment initialized successfully',
                'data' => [
                    'checkout_url' => 'https://checkout.chapa.co/checkout/payment/test_123',
                    'tx_ref' => 'tx_test_123'
                ]
            ],
            'failed_deposit' => [
                'status' => 'failed',
                'message' => 'Payment initialization failed',
                'data' => null
            ],
            'webhook_verification' => [
                'status' => 'success',
                'tx_ref' => 'tx_test_123',
                'amount' => 100,
                'currency' => 'ETB',
                'status' => 'success'
            ]
        ];
    }

    /**
     * Get database test configuration
     * @return array
     */
    public static function getDatabaseTestConfig()
    {
        return [
            'host' => 'localhost',
            'username' => 'test_user',
            'password' => 'test_password',
            'database' => 'test_toady_game',
            'charset' => 'utf8mb4'
        ];
    }
}