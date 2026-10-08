ALTER TABLE `payments`
    ADD COLUMN `stripe_checkout_session_id` VARCHAR(255) DEFAULT NULL AFTER `transaction_reference`,
    ADD COLUMN `checkout_url` TEXT DEFAULT NULL AFTER `stripe_checkout_session_id`,
    ADD COLUMN `checkout_expires_at` DATETIME DEFAULT NULL AFTER `checkout_url`,
    ADD UNIQUE INDEX `idx_stripe_checkout_session_id` (`stripe_checkout_session_id`);

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`)
VALUES ('currency_code', 'php', 'general', 'ISO 4217 currency code for Stripe payments')
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
