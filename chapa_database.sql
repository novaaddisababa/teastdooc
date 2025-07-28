-- Database table for Chapa transactions
CREATE TABLE IF NOT EXISTS `chapa_transactions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance (transaction_ref already has unique index)
CREATE INDEX `idx_chapa_user_status` ON `chapa_transactions` (`user_id`, `status`);
CREATE INDEX `idx_chapa_created_at` ON `chapa_transactions` (`created_at`);
