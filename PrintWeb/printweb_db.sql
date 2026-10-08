-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Oct 08, 2026 at 04:25 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `printweb_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `esp32_devices`
--

CREATE TABLE `esp32_devices` (
  `id` int(11) NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `device_name` varchar(100) NOT NULL,
  `api_key` varchar(64) NOT NULL,
  `printer_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `firmware_version` varchar(20) DEFAULT NULL,
  `connection_status` enum('connected','disconnected','error') NOT NULL DEFAULT 'disconnected',
  `last_heartbeat` datetime DEFAULT NULL,
  `led_status` enum('green','yellow','red','blue','off') NOT NULL DEFAULT 'off',
  `buzzer_command` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `esp32_logs`
--

CREATE TABLE `esp32_logs` (
  `id` int(11) NOT NULL,
  `device_id` int(11) NOT NULL,
  `log_type` enum('heartbeat','status_change','command','error','info') NOT NULL,
  `message` text NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('payment_success','job_queued','printing_started','printing_completed','printer_error','printer_offline','low_paper','job_cancelled','job_failed','system') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `job_id`, `is_read`, `created_at`) VALUES
(1, 3, 'job_queued', 'Print Order Created', 'Your print order JOB-EAB49F-20261008 has been created. Please proceed to payment.', 1, 1, '2026-10-08 03:50:38'),
(5, 3, 'system', 'Payment Pending', 'Job JOB-EAB49F-20261008: Payment is pending confirmation. Method: cash payment.', 1, 1, '2026-10-08 04:19:22'),
(6, 4, 'system', 'Payment Status Changed: Pending', 'Job JOB-EAB49F-20261008: Payment is pending confirmation. Method: cash payment.', 1, 0, '2026-10-08 04:19:22'),
(7, 3, 'payment_success', 'Payment Successful', 'Job JOB-EAB49F-20261008: Payment was confirmed. Method: cash payment.', 1, 1, '2026-10-08 04:21:25'),
(8, 4, 'system', 'Payment Status Changed: Successful', 'Job JOB-EAB49F-20261008: Payment was confirmed. Method: cash payment.', 1, 0, '2026-10-08 04:21:25'),
(9, 3, 'job_queued', 'Print Job Queued', 'Job JOB-EAB49F-20261008: Your print job has entered the print queue. Cash receipt was confirmed by an administrator.', 1, 1, '2026-10-08 04:21:25'),
(10, 4, 'system', 'Print Job Status Changed: Queued', 'Job JOB-EAB49F-20261008 status changed to queued. Cash receipt was confirmed by an administrator.', 1, 0, '2026-10-08 04:21:25'),
(11, 3, 'printing_started', 'Printing Started', 'Job JOB-EAB49F-20261008: Your print job is now printing. Started manually; no available printer or ESP32 is required for status management.', 1, 1, '2026-10-08 04:21:34'),
(12, 4, 'system', 'Print Job Status Changed: Printing', 'Job JOB-EAB49F-20261008 status changed to printing. Started manually; no available printer or ESP32 is required for status management.', 1, 0, '2026-10-08 04:21:34'),
(13, 3, 'printing_completed', 'Print Job Completed', 'Job JOB-EAB49F-20261008: Your print job has completed.', 1, 1, '2026-10-08 04:21:40'),
(14, 4, 'system', 'Print Job Status Changed: Completed', 'Job JOB-EAB49F-20261008 status changed to completed.', 1, 0, '2026-10-08 04:21:40'),
(15, 3, 'system', 'Payment Required', 'Job JOB-C61AF7-20261008: Your print job is waiting for payment. It will receive a queue number after payment is confirmed.', 2, 1, '2026-10-08 04:33:16'),
(16, 4, 'system', 'Print Job Status Changed: Awaiting payment', 'Job JOB-C61AF7-20261008 status changed to awaiting_payment. It will receive a queue number after payment is confirmed.', 2, 0, '2026-10-08 04:33:16'),
(17, 3, 'payment_success', 'Payment Successful', 'Job JOB-C61AF7-20261008: Payment was confirmed. Method: online payment simulation.', 2, 1, '2026-10-08 04:38:57'),
(18, 4, 'system', 'Payment Status Changed: Successful', 'Job JOB-C61AF7-20261008: Payment was confirmed. Method: online payment simulation.', 2, 0, '2026-10-08 04:38:57'),
(19, 3, 'job_queued', 'Print Job Queued', 'Job JOB-C61AF7-20261008: Your print job has entered the print queue. No real money was charged.', 2, 1, '2026-10-08 04:38:57'),
(20, 4, 'system', 'Print Job Status Changed: Queued', 'Job JOB-C61AF7-20261008 status changed to queued. No real money was charged.', 2, 0, '2026-10-08 04:38:57'),
(21, 3, 'job_failed', 'Print Job Failed', 'Job JOB-C61AF7-20261008: Your print job could not be completed.', 2, 1, '2026-10-08 04:42:26'),
(22, 4, 'system', 'Print Job Status Changed: Failed', 'Job JOB-C61AF7-20261008 status changed to failed.', 2, 0, '2026-10-08 04:42:26'),
(23, 3, 'system', 'Payment Required', 'Job JOB-41A1FF-20261008: Your print job is waiting for payment. It will receive a queue number after payment is confirmed.', 3, 1, '2026-10-08 04:45:24'),
(24, 4, 'system', 'Print Job Status Changed: Awaiting payment', 'Job JOB-41A1FF-20261008 status changed to awaiting_payment. It will receive a queue number after payment is confirmed.', 3, 0, '2026-10-08 04:45:24'),
(25, 3, 'system', 'Payment Pending', 'Job JOB-41A1FF-20261008: Payment is pending confirmation. Method: cash payment.', 3, 1, '2026-10-08 04:45:35'),
(26, 4, 'system', 'Payment Status Changed: Pending', 'Job JOB-41A1FF-20261008: Payment is pending confirmation. Method: cash payment.', 3, 0, '2026-10-08 04:45:35'),
(27, 3, 'payment_success', 'Payment Successful', 'Job JOB-41A1FF-20261008: Payment was confirmed. Method: cash payment.', 3, 1, '2026-10-08 04:46:20'),
(28, 4, 'system', 'Payment Status Changed: Successful', 'Job JOB-41A1FF-20261008: Payment was confirmed. Method: cash payment.', 3, 0, '2026-10-08 04:46:20'),
(29, 3, 'job_queued', 'Print Job Queued', 'Job JOB-41A1FF-20261008: Your print job has entered the print queue. Cash receipt was confirmed by an administrator.', 3, 1, '2026-10-08 04:46:20'),
(30, 4, 'system', 'Print Job Status Changed: Queued', 'Job JOB-41A1FF-20261008 status changed to queued. Cash receipt was confirmed by an administrator.', 3, 0, '2026-10-08 04:46:20'),
(31, 3, 'printing_started', 'Printing Started', 'Job JOB-41A1FF-20261008: Your print job is now printing. Started manually; no available printer or ESP32 is required for status management.', 3, 1, '2026-10-08 04:46:27'),
(32, 4, 'system', 'Print Job Status Changed: Printing', 'Job JOB-41A1FF-20261008 status changed to printing. Started manually; no available printer or ESP32 is required for status management.', 3, 0, '2026-10-08 04:46:27'),
(33, 3, 'printing_completed', 'Print Job Completed', 'Job JOB-41A1FF-20261008: Your print job has completed.', 3, 1, '2026-10-08 04:46:33'),
(34, 4, 'system', 'Print Job Status Changed: Completed', 'Job JOB-41A1FF-20261008 status changed to completed.', 3, 0, '2026-10-08 04:46:33'),
(35, 3, 'system', 'Payment Required', 'Job JOB-A249A8-20261008: Your print job is waiting for payment. It will receive a queue number after payment is confirmed.', 4, 1, '2026-10-08 04:51:38'),
(36, 4, 'system', 'Print Job Status Changed: Awaiting payment', 'Job JOB-A249A8-20261008 status changed to awaiting_payment. It will receive a queue number after payment is confirmed.', 4, 0, '2026-10-08 04:51:38'),
(37, 3, 'system', 'Payment Pending', 'Job JOB-A249A8-20261008: Payment is pending confirmation. Method: cash payment.', 4, 1, '2026-10-08 04:51:45'),
(38, 4, 'system', 'Payment Status Changed: Pending', 'Job JOB-A249A8-20261008: Payment is pending confirmation. Method: cash payment.', 4, 0, '2026-10-08 04:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `payment_id` varchar(20) NOT NULL,
  `job_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','online','card','ewallet','simulation') NOT NULL DEFAULT 'cash',
  `payment_status` enum('pending','processing','successful','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `transaction_reference` varchar(100) DEFAULT NULL,
  `stripe_checkout_session_id` varchar(255) DEFAULT NULL,
  `checkout_url` text DEFAULT NULL,
  `checkout_expires_at` datetime DEFAULT NULL,
  `payment_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL COMMENT 'Admin/Operator user ID who processed',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `payment_id`, `job_id`, `user_id`, `amount`, `payment_method`, `payment_status`, `transaction_reference`, `stripe_checkout_session_id`, `checkout_url`, `checkout_expires_at`, `payment_notes`, `processed_by`, `paid_at`, `created_at`, `updated_at`) VALUES
(5, 'PAY-85ADC993', 1, 3, 20.00, 'cash', 'successful', NULL, NULL, NULL, NULL, 'Cash received and confirmed by administrator.', 4, '2026-10-08 04:21:25', '2026-10-08 04:19:22', '2026-10-08 04:21:25'),
(8, 'PAY-CF1D2957', 2, 3, 40.00, 'simulation', 'successful', 'SIM-AC72B20CDEA23358', NULL, NULL, NULL, 'Simulated payment; no real charge was made.', NULL, '2026-10-08 04:38:57', '2026-10-08 04:38:57', '2026-10-08 04:38:57'),
(9, 'PAY-E7FE2C8E', 3, 3, 8.00, 'cash', 'successful', NULL, NULL, NULL, NULL, 'Cash received and confirmed by administrator.', 4, '2026-10-08 04:46:20', '2026-10-08 04:45:35', '2026-10-08 04:46:20'),
(10, 'PAY-FF15100D', 4, 3, 2.00, 'cash', 'pending', NULL, NULL, NULL, NULL, 'Cash payment awaiting administrator confirmation.', NULL, NULL, '2026-10-08 04:51:45', '2026-10-08 04:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `printers`
--

CREATE TABLE `printers` (
  `id` int(11) NOT NULL,
  `printer_name` varchar(100) NOT NULL,
  `printer_model` varchar(100) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `status` enum('available','printing','offline','error','disabled') NOT NULL DEFAULT 'offline',
  `esp32_id` int(11) DEFAULT NULL,
  `paper_level` int(11) NOT NULL DEFAULT 100 COMMENT 'Percentage 0-100',
  `ink_level` int(11) NOT NULL DEFAULT 100 COMMENT 'Percentage 0-100',
  `current_job_id` int(11) DEFAULT NULL,
  `total_pages_printed` int(11) NOT NULL DEFAULT 0,
  `supports_color` tinyint(1) NOT NULL DEFAULT 1,
  `ip_address` varchar(45) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `printer_history`
--

CREATE TABLE `printer_history` (
  `id` int(11) NOT NULL,
  `printer_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `status_from` varchar(50) DEFAULT NULL,
  `status_to` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `print_jobs`
--

CREATE TABLE `print_jobs` (
  `id` int(11) NOT NULL,
  `job_id` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL DEFAULT 0,
  `total_pages` int(11) NOT NULL DEFAULT 1,
  `copies` int(11) NOT NULL DEFAULT 1,
  `print_type` enum('bw','color') NOT NULL DEFAULT 'bw',
  `paper_size` enum('A4','Letter','Legal') NOT NULL DEFAULT 'A4',
  `pages_to_print` varchar(255) NOT NULL DEFAULT 'all' COMMENT 'all or page range like 1-5,7,9',
  `selected_pages_count` int(11) NOT NULL DEFAULT 0,
  `price_per_page` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('pending','processing','successful','failed','cancelled') NOT NULL DEFAULT 'pending',
  `print_status` enum('draft','awaiting_payment','queued','waiting','printing','completed','failed','cancelled') NOT NULL DEFAULT 'draft',
  `printer_id` int(11) DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL,
  `current_page` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `payment_at` datetime DEFAULT NULL,
  `queued_at` datetime DEFAULT NULL,
  `printing_started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `print_jobs`
--

INSERT INTO `print_jobs` (`id`, `job_id`, `user_id`, `file_name`, `original_file_name`, `file_path`, `file_size`, `total_pages`, `copies`, `print_type`, `paper_size`, `pages_to_print`, `selected_pages_count`, `price_per_page`, `total_cost`, `payment_status`, `print_status`, `printer_id`, `queue_number`, `current_page`, `error_message`, `notes`, `submitted_at`, `payment_at`, `queued_at`, `printing_started_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(1, 'JOB-EAB49F-20261008', 3, 'doc_6ac790b8ce443.pdf', 'Introduction_to_Computing_Reviewer.pdf', 'C:\\xampp\\htdocs\\PrintWeb\\config/../uploads/doc_6ac790b8ce443.pdf', 35819, 5, 2, 'bw', 'A4', 'all', 5, 2.00, 20.00, 'successful', 'completed', NULL, 1, 10, NULL, NULL, '2026-10-08 03:50:38', '2026-10-08 04:21:25', '2026-10-08 04:21:25', '2026-10-08 04:21:34', '2026-10-08 04:21:40', '2026-10-08 03:50:38', '2026-10-08 04:21:40'),
(2, 'JOB-C61AF7-20261008', 3, 'doc_6ac79b91a8c65.pdf', 'MCO GUIDELINES.pdf', 'C:\\xampp\\htdocs\\PrintWeb\\config/../uploads/doc_6ac79b91a8c65.pdf', 281945, 1, 5, 'color', 'A4', 'all', 1, 8.00, 40.00, 'successful', 'failed', NULL, 2, 0, NULL, NULL, '2026-10-08 04:33:16', '2026-10-08 04:38:57', '2026-10-08 04:38:57', NULL, NULL, '2026-10-08 04:33:16', '2026-10-08 04:42:26'),
(3, 'JOB-41A1FF-20261008', 3, 'doc_6ac79e6aee6a0.docx', 'ReflectionPaper.docx', 'C:\\xampp\\htdocs\\PrintWeb\\config/../uploads/doc_6ac79e6aee6a0.docx', 16057, 1, 4, 'bw', 'A4', 'all', 1, 2.00, 8.00, 'successful', 'completed', NULL, 3, 4, NULL, NULL, '2026-10-08 04:45:24', '2026-10-08 04:46:20', '2026-10-08 04:46:20', '2026-10-08 04:46:27', '2026-10-08 04:46:33', '2026-10-08 04:45:24', '2026-10-08 04:46:33'),
(4, 'JOB-A249A8-20261008', 3, 'doc_6ac79fe4919a1.docx', 'IT101_MidtermMCO_Group2_GOmove.docx', 'C:\\xampp\\htdocs\\PrintWeb\\config/../uploads/doc_6ac79fe4919a1.docx', 4464822, 1, 1, 'bw', 'A4', 'all', 1, 2.00, 2.00, 'pending', 'awaiting_payment', NULL, NULL, 0, NULL, NULL, '2026-10-08 04:51:38', NULL, NULL, NULL, NULL, '2026-10-08 04:51:38', '2026-10-08 04:51:38');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_group` varchar(50) NOT NULL DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_group`, `description`, `updated_at`) VALUES
(1, 'bw_price_a4', '2.00', 'pricing', 'Black & White printing price per page - A4', '2026-10-08 04:23:28'),
(2, 'bw_price_letter', '2.00', 'pricing', 'Black & White printing price per page - Letter', '2026-10-08 04:23:28'),
(3, 'bw_price_legal', '2.50', 'pricing', 'Black & White printing price per page - Legal', '2026-10-08 04:23:28'),
(4, 'color_price_a4', '8.00', 'pricing', 'Color printing price per page - A4', '2026-10-08 04:23:28'),
(5, 'color_price_letter', '8.00', 'pricing', 'Color printing price per page - Letter', '2026-10-08 04:23:28'),
(6, 'color_price_legal', '10.00', 'pricing', 'Color printing price per page - Legal', '2026-10-08 04:23:28'),
(7, 'allowed_file_types', 'pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png', 'upload', 'Allowed file types for upload', '2026-10-08 04:23:28'),
(8, 'max_file_size_mb', '50', 'upload', 'Maximum file size in megabytes', '2026-10-08 04:23:28'),
(9, 'max_copies', '50', 'print', 'Maximum number of copies per job', '2026-10-07 23:21:02'),
(10, 'queue_auto_assign', '1', 'queue', 'Auto-assign print jobs to available printers', '2026-10-07 23:21:02'),
(11, 'notification_email', '1', 'notification', 'Send email notifications', '2026-10-07 23:21:02'),
(12, 'notification_system', '1', 'notification', 'Show system notifications', '2026-10-07 23:21:02'),
(13, 'app_name', 'PrintWeb', 'general', 'Application name', '2026-10-07 23:21:02'),
(14, 'app_tagline', 'Smart Printing Payment System', 'general', 'Application tagline', '2026-10-07 23:21:02'),
(15, 'maintenance_mode', '0', 'general', 'Enable maintenance mode', '2026-10-07 23:21:02'),
(16, 'currency_symbol', '₱', 'general', 'Currency symbol', '2026-10-08 04:23:28'),
(17, 'currency_code', 'php', 'general', 'ISO 4217 currency code for Stripe payments', '2026-10-08 04:23:28'),
(24, 'site_name', 'PrintWeb Smart Printing System', 'general', NULL, '2026-10-08 04:23:28'),
(27, 'auto_assign_printer', '1', 'general', NULL, '2026-10-08 04:23:28'),
(30, 'esp32_poll_interval', '3', 'general', NULL, '2026-10-08 04:23:28'),
(31, 'esp32_offline_timeout', '30', 'general', NULL, '2026-10-08 04:23:28'),
(32, 'esp32_buzzer_enabled', '1', 'general', NULL, '2026-10-08 04:23:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','faculty','staff','other','admin','operator') NOT NULL DEFAULT 'student',
  `contact_number` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `full_name`, `email`, `password_hash`, `role`, `contact_number`, `department`, `status`, `email_verified`, `profile_picture`, `created_at`, `updated_at`) VALUES
(3, 'STU-A3D688', 'student', 'student@gmail.com', '$2y$12$9xF7j1RyxxWWcnvx5s788OXFIzJwc8FvR7FGA9emhcwvI6G8tWqg6', 'student', '09397287323', 'c', 'active', 0, NULL, '2026-10-08 02:57:30', '2026-10-08 02:57:30'),
(4, 'ADM-0D37E402CB', 'PrintWeb Administrator', 'admin@printweb.com', '$2y$12$BoXBilVuDJ8wUDVeJl5.buLcyCaz65yGdwN25QkqTCbcc7ilwYy4S', 'admin', NULL, NULL, 'active', 1, NULL, '2026-10-08 03:00:57', '2026-10-08 03:01:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `esp32_devices`
--
ALTER TABLE `esp32_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`);

--
-- Indexes for table `esp32_logs`
--
ALTER TABLE `esp32_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_id` (`payment_id`),
  ADD UNIQUE KEY `idx_stripe_checkout_session_id` (`stripe_checkout_session_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_payment_status` (`payment_status`);

--
-- Indexes for table `printers`
--
ALTER TABLE `printers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `esp32_id` (`esp32_id`);

--
-- Indexes for table `printer_history`
--
ALTER TABLE `printer_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `printer_id` (`printer_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `print_jobs`
--
ALTER TABLE `print_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_id` (`job_id`),
  ADD KEY `printer_id` (`printer_id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_print_status` (`print_status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `esp32_devices`
--
ALTER TABLE `esp32_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `esp32_logs`
--
ALTER TABLE `esp32_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `printers`
--
ALTER TABLE `printers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `printer_history`
--
ALTER TABLE `printer_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `print_jobs`
--
ALTER TABLE `print_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `esp32_logs`
--
ALTER TABLE `esp32_logs`
  ADD CONSTRAINT `esp32_logs_ibfk_1` FOREIGN KEY (`device_id`) REFERENCES `esp32_devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `print_jobs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `print_jobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `printers`
--
ALTER TABLE `printers`
  ADD CONSTRAINT `printers_ibfk_1` FOREIGN KEY (`esp32_id`) REFERENCES `esp32_devices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `printer_history`
--
ALTER TABLE `printer_history`
  ADD CONSTRAINT `printer_history_ibfk_1` FOREIGN KEY (`printer_id`) REFERENCES `printers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `printer_history_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `print_jobs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `print_jobs`
--
ALTER TABLE `print_jobs`
  ADD CONSTRAINT `print_jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `print_jobs_ibfk_2` FOREIGN KEY (`printer_id`) REFERENCES `printers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
