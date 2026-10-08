ALTER TABLE `payments`
    MODIFY COLUMN `payment_method` ENUM('cash','online','card','ewallet','simulation') NOT NULL DEFAULT 'cash';
