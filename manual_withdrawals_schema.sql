-- Manual withdrawals table for admin processing
CREATE TABLE IF NOT EXISTS `manual_withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_ref` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `withdrawal_details` text NOT NULL,
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `assigned_to` int(11) DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_ref` (`transaction_ref`),
  KEY `user_id` (`user_id`),
  KEY `priority` (`priority`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`transaction_ref`) REFERENCES `chapa_transactions` (`transaction_ref`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table (if not exists)
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','super_admin','processor') NOT NULL DEFAULT 'processor',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Withdrawal processing logs
CREATE TABLE IF NOT EXISTS `withdrawal_processing_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_ref` varchar(100) NOT NULL,
  `action` varchar(100) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transaction_ref` (`transaction_ref`),
  KEY `admin_id` (`admin_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`transaction_ref`) REFERENCES `chapa_transactions` (`transaction_ref`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (change password in production)
INSERT INTO `admin_users` (`username`, `email`, `password`, `role`) VALUES 
('admin', 'admin@toadygame.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin')
ON DUPLICATE KEY UPDATE username=username;

-- Add indexes for better performance
CREATE INDEX `idx_manual_withdrawals_status` ON `manual_withdrawals` (`priority`, `created_at`);
CREATE INDEX `idx_withdrawal_logs_ref_date` ON `withdrawal_processing_logs` (`transaction_ref`, `created_at`);
