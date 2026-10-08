-- Smart Printing Payment System Database Schema
-- ================================================

CREATE DATABASE IF NOT EXISTS `printweb_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `printweb_db`;
SET NAMES utf8mb4;

-- ================================================
-- USERS TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(20) UNIQUE NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('student','faculty','staff','other','admin','operator') NOT NULL DEFAULT 'student',
    `contact_number` VARCHAR(20) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `profile_picture` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- ESP32 DEVICES TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `esp32_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` VARCHAR(50) UNIQUE NOT NULL,
    `device_name` VARCHAR(100) NOT NULL,
    `api_key` VARCHAR(64) NOT NULL,
    `printer_id` INT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `firmware_version` VARCHAR(20) DEFAULT NULL,
    `connection_status` ENUM('connected','disconnected','error') NOT NULL DEFAULT 'disconnected',
    `last_heartbeat` DATETIME DEFAULT NULL,
    `led_status` ENUM('green','yellow','red','blue','off') NOT NULL DEFAULT 'off',
    `buzzer_command` VARCHAR(50) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- PRINTERS TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `printers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `printer_name` VARCHAR(100) NOT NULL,
    `printer_model` VARCHAR(100) DEFAULT NULL,
    `location` VARCHAR(150) DEFAULT NULL,
    `status` ENUM('available','printing','offline','error','disabled') NOT NULL DEFAULT 'offline',
    `esp32_id` INT DEFAULT NULL,
    `paper_level` INT NOT NULL DEFAULT 100 COMMENT 'Percentage 0-100',
    `ink_level` INT NOT NULL DEFAULT 100 COMMENT 'Percentage 0-100',
    `current_job_id` INT DEFAULT NULL,
    `total_pages_printed` INT NOT NULL DEFAULT 0,
    `supports_color` TINYINT(1) NOT NULL DEFAULT 1,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`esp32_id`) REFERENCES `esp32_devices`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- SYSTEM SETTINGS TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT NOT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- PRINT JOBS TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `print_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_id` VARCHAR(20) UNIQUE NOT NULL,
    `user_id` INT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `original_file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL DEFAULT 0,
    `total_pages` INT NOT NULL DEFAULT 1,
    `copies` INT NOT NULL DEFAULT 1,
    `print_type` ENUM('bw','color') NOT NULL DEFAULT 'bw',
    `paper_size` ENUM('A4','Letter','Legal') NOT NULL DEFAULT 'A4',
    `pages_to_print` VARCHAR(255) NOT NULL DEFAULT 'all' COMMENT 'all or page range like 1-5,7,9',
    `selected_pages_count` INT NOT NULL DEFAULT 0,
    `price_per_page` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('pending','processing','successful','failed','cancelled') NOT NULL DEFAULT 'pending',
    `print_status` ENUM('draft','awaiting_payment','queued','waiting','printing','completed','failed','cancelled') NOT NULL DEFAULT 'draft',
    `printer_id` INT DEFAULT NULL,
    `queue_number` INT DEFAULT NULL,
    `current_page` INT NOT NULL DEFAULT 0,
    `error_message` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT NULL,
    `payment_at` DATETIME DEFAULT NULL,
    `queued_at` DATETIME DEFAULT NULL,
    `printing_started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`printer_id`) REFERENCES `printers`(`id`) ON DELETE SET NULL,
    INDEX `idx_job_id` (`job_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_print_status` (`print_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- PAYMENTS TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `payment_id` VARCHAR(20) UNIQUE NOT NULL,
    `job_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cash','online','card','ewallet','simulation') NOT NULL DEFAULT 'cash',
    `payment_status` ENUM('pending','processing','successful','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
    `transaction_reference` VARCHAR(100) DEFAULT NULL,
    `stripe_checkout_session_id` VARCHAR(255) DEFAULT NULL,
    `checkout_url` TEXT DEFAULT NULL,
    `checkout_expires_at` DATETIME DEFAULT NULL,
    `payment_notes` TEXT DEFAULT NULL,
    `processed_by` INT DEFAULT NULL COMMENT 'Admin/Operator user ID who processed',
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_id`) REFERENCES `print_jobs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_payment_id` (`payment_id`),
    INDEX `idx_job_id` (`job_id`),
    INDEX `idx_payment_status` (`payment_status`),
    UNIQUE INDEX `idx_stripe_checkout_session_id` (`stripe_checkout_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- NOTIFICATIONS TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` ENUM('payment_success','job_queued','printing_started','printing_completed','printer_error','printer_offline','low_paper','job_cancelled','job_failed','system') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `job_id` INT DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_id`) REFERENCES `print_jobs`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- PRINTER HISTORY TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `printer_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `printer_id` INT NOT NULL,
    `job_id` INT DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `status_from` VARCHAR(50) DEFAULT NULL,
    `status_to` VARCHAR(50) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`printer_id`) REFERENCES `printers`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`job_id`) REFERENCES `print_jobs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- ESP32 LOG TABLE
-- ================================================
CREATE TABLE IF NOT EXISTS `esp32_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `device_id` INT NOT NULL,
    `log_type` ENUM('heartbeat','status_change','command','error','info') NOT NULL,
    `message` TEXT NOT NULL,
    `payload` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`device_id`) REFERENCES `esp32_devices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- DEFAULT DATA
-- ================================================

-- Default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('bw_price_a4', '2.00', 'pricing', 'Black & White printing price per page - A4'),
('bw_price_letter', '2.00', 'pricing', 'Black & White printing price per page - Letter'),
('bw_price_legal', '2.50', 'pricing', 'Black & White printing price per page - Legal'),
('color_price_a4', '8.00', 'pricing', 'Color printing price per page - A4'),
('color_price_letter', '8.00', 'pricing', 'Color printing price per page - Letter'),
('color_price_legal', '10.00', 'pricing', 'Color printing price per page - Legal'),
('allowed_file_types', 'pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png', 'upload', 'Allowed file types for upload'),
('max_file_size_mb', '50', 'upload', 'Maximum file size in megabytes'),
('max_copies', '50', 'print', 'Maximum number of copies per job'),
('queue_auto_assign', '1', 'queue', 'Auto-assign print jobs to available printers'),
('notification_email', '1', 'notification', 'Send email notifications'),
('notification_system', '1', 'notification', 'Show system notifications'),
('app_name', 'PrintWeb', 'general', 'Application name'),
('app_tagline', 'Smart Printing Payment System', 'general', 'Application tagline'),
('maintenance_mode', '0', 'general', 'Enable maintenance mode'),
('currency_symbol', '₱', 'general', 'Currency symbol'),
('currency_code', 'php', 'general', 'ISO 4217 currency code for Stripe payments')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
