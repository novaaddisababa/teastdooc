
-- Test Data Setup for Chapa Payment System
-- Run this AFTER creating the main tables (chapa_database.sql and manual_withdrawals_schema.sql)

-- Create test users with different balance levels
INSERT INTO `users` (`username`, `email`, `password`, `balance`, `created_at`) VALUES
('test_user_1', 'test1@example.com', '$2y$10$example_hash_1', 5000.00, NOW()),
('test_user_2', 'test2@example.com', '$2y$10$example_hash_2', 15000.00, NOW()),
('test_user_3', 'test3@example.com', '$2y$10$example_hash_3', 500.00, NOW()),
('test_user_4', 'test4@example.com', '$2y$10$example_hash_4', 25000.00, NOW()),
('test_admin', 'admin@example.com', '$2y$10$example_hash_admin', 0.00, NOW())
ON DUPLICATE KEY UPDATE 
    balance = VALUES(balance),
    updated_at = NOW();

-- Create test admin user
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `role`, `permissions`, `created_at`) VALUES
('admin', 'admin@toady-game.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 
'{"withdrawals": ["view", "approve", "reject"], "users": ["view", "edit"], "reports": ["view"]}', NOW()),
('test_admin', 'testadmin@example.com', '$2y$10$example_hash_test_admin', 'admin', 
'{"withdrawals": ["view", "approve"]}', NOW())
ON DUPLICATE KEY UPDATE 
    role = VALUES(role),
    permissions = VALUES(permissions);

-- Create sample test transactions (completed deposits)
INSERT INTO `chapa_transactions` (`user_id`, `transaction_ref`, `amount`, `type`, `status`, `chapa_response`, `created_at`) VALUES
(1, 'TEST_DEP_001', 1000.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_001", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'TEST_DEP_002', 2500.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_002", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'TEST_DEP_003', 500.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_003", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 'TEST_DEP_004', 5000.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_004", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Create sample pending transactions for testing
INSERT INTO `chapa_transactions` (`user_id`, `transaction_ref`, `amount`, `type`, `status`, `created_at`) VALUES
(1, 'TEST_PEND_001', 750.00, 'deposit', 'pending', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 'TEST_PEND_002', 1200.00, 'deposit', 'processing', DATE_SUB(NOW(), INTERVAL 15 MINUTE));

-- Create sample withdrawal transactions for testing
INSERT INTO `chapa_transactions` (`user_id`, `transaction_ref`, `amount`, `type`, `status`, `withdrawal_method`, `account_number`, `bank_code`, `processing_details`, `created_at`) VALUES
(1, 'TEST_WITH_001', 2000.00, 'withdraw', 'pending', 'bank_transfer', '1234567890', 'CBE', 
'{"method": "bank_transfer", "bank_name": "Commercial Bank of Ethiopia", "account_number": "1234567890", "account_holder": "Test User 1"}', 
DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'TEST_WITH_002', 3500.00, 'withdraw', 'processing', 'mobile_money', '251911234567', 'MBIRR', 
'{"method": "mobile_money", "mobile_provider": "M-Birr", "phone_number": "251911234567", "account_holder": "Test User 2"}', 
DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(4, 'TEST_WITH_003', 15000.00, 'withdraw', 'pending', 'bank_transfer', '9876543210', 'AIB', 
'{"method": "bank_transfer", "bank_name": "Awash International Bank", "account_number": "9876543210", "account_holder": "Test User 4"}', 
DATE_SUB(NOW(), INTERVAL 45 MINUTE));

-- Create sample manual withdrawals for admin testing
INSERT INTO `manual_withdrawals` (`transaction_ref`, `user_id`, `amount`, `priority`, `processing_details`, `admin_notes`, `queued_at`) VALUES
('TEST_WITH_001', 1, 2000.00, 'normal', 
'{"withdrawal_details": {"method": "bank_transfer", "bank_name": "Commercial Bank of Ethiopia", "account_number": "1234567890", "account_holder": "Test User 1"}, "reason": "Small amount - automated processing failed", "attempts": 1}', 
'Automated processing failed - requires manual review', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('TEST_WITH_003', 4, 15000.00, 'high', 
'{"withdrawal_details": {"method": "bank_transfer", "bank_name": "Awash International Bank", "account_number": "9876543210", "account_holder": "Test User 4"}, "reason": "Large amount - requires manual processing", "attempts": 0}', 
'Large amount withdrawal - requires verification', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
('TEST_WITH_004', 2, 8500.00, 'urgent', 
'{"withdrawal_details": {"method": "mobile_money", "mobile_provider": "TeleBirr", "phone_number": "251922334455", "account_holder": "Test User 2"}, "reason": "User requested urgent processing", "attempts": 0}', 
'User marked as VIP - urgent processing requested', DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Create sample processing logs
INSERT INTO `withdrawal_processing_logs` (`transaction_ref`, `action`, `performed_by`, `details`, `created_at`) VALUES
('TEST_WITH_001', 'queued_for_manual', 'system', '{"reason": "Automated processing failed", "error": "Bank API timeout"}', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('TEST_WITH_002', 'processing_started', 'system', '{"method": "mobile_money_api", "provider": "M-Birr"}', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('TEST_WITH_003', 'queued_for_manual', 'system', '{"reason": "Amount exceeds automated limit", "threshold": 10000}', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
('TEST_WITH_004', 'queued_for_manual', 'system', '{"reason": "User requested urgent processing", "priority": "urgent"}', DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Display test data summary
SELECT 'TEST DATA CREATED SUCCESSFULLY' as status;

SELECT 'USER ACCOUNTS' as section, username, email, balance FROM users WHERE email LIKE '%example.com' OR email LIKE '%toady-game.com';

SELECT 'ADMIN ACCOUNTS' as section, username, email, role FROM admin_users;

SELECT 'SAMPLE TRANSACTIONS' as section, 
       transaction_ref, 
       (SELECT username FROM users WHERE id = chapa_transactions.user_id) as user,
       amount, 
       type, 
       status, 
       created_at 
FROM chapa_transactions 
WHERE transaction_ref LIKE 'TEST_%' 
ORDER BY created_at DESC;

SELECT 'MANUAL WITHDRAWALS QUEUE' as section,
       transaction_ref,
       (SELECT username FROM users WHERE id = manual_withdrawals.user_id) as user,
       amount,
       priority,
       status,
       queued_at
FROM manual_withdrawals
ORDER BY queued_at DESC;
-- Test Data Setup for Chapa Payment System
-- Run this AFTER creating the main tables (chapa_database.sql and manual_withdrawals_schema.sql)

-- Create test users with different balance levels
INSERT INTO `users` (`username`, `email`, `password`, `balance`, `created_at`) VALUES
('test_user_1', 'test1@example.com', '$2y$10$example_hash_1', 5000.00, NOW()),
('test_user_2', 'test2@example.com', '$2y$10$example_hash_2', 15000.00, NOW()),
('test_user_3', 'test3@example.com', '$2y$10$example_hash_3', 500.00, NOW()),
('test_user_4', 'test4@example.com', '$2y$10$example_hash_4', 25000.00, NOW()),
('test_admin', 'admin@example.com', '$2y$10$example_hash_admin', 0.00, NOW())
ON DUPLICATE KEY UPDATE 
    balance = VALUES(balance),
    updated_at = NOW();

-- Create test admin user
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `role`, `permissions`, `created_at`) VALUES
('admin', 'admin@toady-game.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 
'{"withdrawals": ["view", "approve", "reject"], "users": ["view", "edit"], "reports": ["view"]}', NOW()),
('test_admin', 'testadmin@example.com', '$2y$10$example_hash_test_admin', 'admin', 
'{"withdrawals": ["view", "approve"]}', NOW())
ON DUPLICATE KEY UPDATE 
    role = VALUES(role),
    permissions = VALUES(permissions);

-- Create sample test transactions (completed deposits)
INSERT INTO `chapa_transactions` (`user_id`, `transaction_ref`, `amount`, `type`, `status`, `chapa_response`, `created_at`) VALUES
(1, 'TEST_DEP_001', 1000.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_001", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'TEST_DEP_002', 2500.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_002", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'TEST_DEP_003', 500.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_003", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 'TEST_DEP_004', 5000.00, 'deposit', 'completed', '{"status": "success", "reference": "TEST_DEP_004", "test_mode": true}', DATE_SUB(NOW(), INTERVAL 1 HOUR));

-- Create sample pending transactions for testing
INSERT INTO `chapa_transactions` (`user_id`, `transaction_ref`, `amount`, `type`, `status`, `created_at`) VALUES
(1, 'TEST_PEND_001', 750.00, 'deposit', 'pending', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(2, 'TEST_PEND_002', 1200.00, 'deposit', 'processing', DATE_SUB(NOW(), INTERVAL 15 MINUTE));

-- Create sample withdrawal transactions for testing
INSERT INTO `chapa_transactions` (`user_id`, `transaction_ref`, `amount`, `type`, `status`, `withdrawal_method`, `account_number`, `bank_code`, `processing_details`, `created_at`) VALUES
(1, 'TEST_WITH_001', 2000.00, 'withdraw', 'pending', 'bank_transfer', '1234567890', 'CBE', 
'{"method": "bank_transfer", "bank_name": "Commercial Bank of Ethiopia", "account_number": "1234567890", "account_holder": "Test User 1"}', 
DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(2, 'TEST_WITH_002', 3500.00, 'withdraw', 'processing', 'mobile_money', '251911234567', 'MBIRR', 
'{"method": "mobile_money", "mobile_provider": "M-Birr", "phone_number": "251911234567", "account_holder": "Test User 2"}', 
DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(4, 'TEST_WITH_003', 15000.00, 'withdraw', 'pending', 'bank_transfer', '9876543210', 'AIB', 
'{"method": "bank_transfer", "bank_name": "Awash International Bank", "account_number": "9876543210", "account_holder": "Test User 4"}', 
DATE_SUB(NOW(), INTERVAL 45 MINUTE));

-- Create sample manual withdrawals for admin testing
INSERT INTO `manual_withdrawals` (`transaction_ref`, `user_id`, `amount`, `priority`, `processing_details`, `admin_notes`, `queued_at`) VALUES
('TEST_WITH_001', 1, 2000.00, 'normal', 
'{"withdrawal_details": {"method": "bank_transfer", "bank_name": "Commercial Bank of Ethiopia", "account_number": "1234567890", "account_holder": "Test User 1"}, "reason": "Small amount - automated processing failed", "attempts": 1}', 
'Automated processing failed - requires manual review', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('TEST_WITH_003', 4, 15000.00, 'high', 
'{"withdrawal_details": {"method": "bank_transfer", "bank_name": "Awash International Bank", "account_number": "9876543210", "account_holder": "Test User 4"}, "reason": "Large amount - requires manual processing", "attempts": 0}', 
'Large amount withdrawal - requires verification', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
('TEST_WITH_004', 2, 8500.00, 'urgent', 
'{"withdrawal_details": {"method": "mobile_money", "mobile_provider": "TeleBirr", "phone_number": "251922334455", "account_holder": "Test User 2"}, "reason": "User requested urgent processing", "attempts": 0}', 
'User marked as VIP - urgent processing requested', DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Create sample processing logs
INSERT INTO `withdrawal_processing_logs` (`transaction_ref`, `action`, `performed_by`, `details`, `created_at`) VALUES
('TEST_WITH_001', 'queued_for_manual', 'system', '{"reason": "Automated processing failed", "error": "Bank API timeout"}', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('TEST_WITH_002', 'processing_started', 'system', '{"method": "mobile_money_api", "provider": "M-Birr"}', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('TEST_WITH_003', 'queued_for_manual', 'system', '{"reason": "Amount exceeds automated limit", "threshold": 10000}', DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
('TEST_WITH_004', 'queued_for_manual', 'system', '{"reason": "User requested urgent processing", "priority": "urgent"}', DATE_SUB(NOW(), INTERVAL 30 MINUTE));

-- Display test data summary
SELECT 'TEST DATA CREATED SUCCESSFULLY' as status;

SELECT 'USER ACCOUNTS' as section, username, email, balance FROM users WHERE email LIKE '%example.com' OR email LIKE '%toady-game.com';

SELECT 'ADMIN ACCOUNTS' as section, username, email, role FROM admin_users;

SELECT 'SAMPLE TRANSACTIONS' as section, 
       transaction_ref, 
       (SELECT username FROM users WHERE id = chapa_transactions.user_id) as user,
       amount, 
       type, 
       status, 
       created_at 
FROM chapa_transactions 
WHERE transaction_ref LIKE 'TEST_%' 
ORDER BY created_at DESC;

SELECT 'MANUAL WITHDRAWALS QUEUE' as section,
       transaction_ref,
       (SELECT username FROM users WHERE id = manual_withdrawals.user_id) as user,
       amount,
       priority,
       status,
       queued_at
FROM manual_withdrawals
ORDER BY queued_at DESC;