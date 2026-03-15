-- Rani Mobiles ERP — Schema v2 (incremental additions)
-- Run after schema.sql. All tables use CREATE TABLE IF NOT EXISTS.

USE `rani_erp`;

-- day_close: tracks daily closing per branch
CREATE TABLE IF NOT EXISTS `day_close` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `business_date` DATE NOT NULL,
  `counted_cash` DECIMAL(12,2) DEFAULT 0.00,
  `upi_total` DECIMAL(12,2) DEFAULT 0.00,
  `card_total` DECIMAL(12,2) DEFAULT 0.00,
  `credit_total` DECIMAL(12,2) DEFAULT 0.00,
  `sales_total` DECIMAL(12,2) DEFAULT 0.00,
  `variance` DECIMAL(12,2) DEFAULT 0.00,
  `notes` TEXT,
  `closed_by` INT UNSIGNED,
  `reopened_by` INT UNSIGNED,
  `status` ENUM('CLOSED','REOPENED') DEFAULT 'CLOSED',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_dayclose` (`branch_id`,`business_date`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- cash_handovers: multiple handovers per day, owner confirms
CREATE TABLE IF NOT EXISTS `cash_handovers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `business_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `handover_by` INT UNSIGNED NOT NULL,
  `confirmed_by` INT UNSIGNED,
  `status` ENUM('PENDING_CONFIRM','CONFIRMED') DEFAULT 'PENDING_CONFIRM',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` DATETIME,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`handover_by`) REFERENCES `users`(`id`),
  FOREIGN KEY (`confirmed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- branch_collection_targets
CREATE TABLE IF NOT EXISTS `branch_collection_targets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL UNIQUE,
  `daily_target` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `updated_by` INT UNSIGNED,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

INSERT INTO `branch_collection_targets` (`branch_id`,`daily_target`) VALUES (1,6000),(2,2000),(3,6000),(4,2000),(5,2000) ON DUPLICATE KEY UPDATE `daily_target`=VALUES(`daily_target`);

-- collection_reminder_logs
CREATE TABLE IF NOT EXISTS `collection_reminder_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `business_date` DATE NOT NULL,
  `reminded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `channel` VARCHAR(20) DEFAULT 'dashboard',
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- branch_item_settings (reorder config)
CREATE TABLE IF NOT EXISTS `branch_item_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `reorder_min_qty` INT DEFAULT 2,
  `reorder_target_qty` INT DEFAULT 5,
  `lead_time_days` INT DEFAULT 1,
  `preferred_vendor_id` INT UNSIGNED,
  UNIQUE KEY `uniq_bis` (`branch_id`,`item_id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`item_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`preferred_vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- reorder_alerts
CREATE TABLE IF NOT EXISTS `reorder_alerts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `current_qty` INT DEFAULT 0,
  `reorder_min_qty` INT DEFAULT 0,
  `status` ENUM('OPEN','PO_CREATED','CLOSED') DEFAULT 'OPEN',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`item_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- purchase_orders
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_no` VARCHAR(30) NOT NULL UNIQUE,
  `branch_id` INT UNSIGNED NOT NULL,
  `vendor_id` INT UNSIGNED,
  `po_date` DATE NOT NULL,
  `expected_date` DATE,
  `status` ENUM('DRAFT','SENT','RECEIVED','CANCELLED') DEFAULT 'DRAFT',
  `notes` TEXT,
  `created_by` INT UNSIGNED,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- purchase_order_items
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `qty` INT NOT NULL DEFAULT 1,
  `est_cost` DECIMAL(12,2) DEFAULT 0.00,
  FOREIGN KEY (`po_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`item_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- events (event vendor events like Arunachala Aattu Kaatchi)
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- event_branches
CREATE TABLE IF NOT EXISTS `event_branches` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `branch_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uniq_eb` (`event_id`,`branch_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- event_vendors (policy: SAME_DAY_CASH_ONLY = soft enforcement, due alert)
CREATE TABLE IF NOT EXISTS `event_vendors` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT UNSIGNED NOT NULL,
  `vendor_id` INT UNSIGNED NOT NULL,
  `policy` ENUM('SAME_DAY_CASH_ONLY') DEFAULT 'SAME_DAY_CASH_ONLY',
  UNIQUE KEY `uniq_ev` (`event_id`,`vendor_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- branch_expected_stock (baseline min/max per branch per item)
CREATE TABLE IF NOT EXISTS `branch_expected_stock` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `expected_min_qty` INT DEFAULT 0,
  `expected_max_qty` INT DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `updated_by` INT UNSIGNED,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_bes` (`branch_id`,`item_id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`item_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- item_change_requests (when purchase includes non-baseline item)
CREATE TABLE IF NOT EXISTS `item_change_requests` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `purchase_id` INT UNSIGNED,
  `item_id` INT UNSIGNED NOT NULL,
  `substitute_for_item_id` INT UNSIGNED,
  `requested_by` INT UNSIGNED,
  `status` ENUM('PENDING_OWNER','APPROVED','REJECTED') DEFAULT 'PENDING_OWNER',
  `owner_notes` TEXT,
  `reviewed_by` INT UNSIGNED,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`item_id`) REFERENCES `products`(`id`),
  FOREIGN KEY (`substitute_for_item_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- alerts (unified alert system)
CREATE TABLE IF NOT EXISTS `alerts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED,
  `business_date` DATE,
  `alert_type` ENUM('COLLECTION_TARGET_REMAINING','PAYMENT_NOT_GIVEN','VENDOR_DUE','UNSOLD_NEW_STOCK','SHORT','EXCESS','NEW_ITEM','ITEM_CHANGED') NOT NULL,
  `severity` ENUM('INFO','WARN','CRITICAL') DEFAULT 'WARN',
  `ref_type` VARCHAR(50),
  `ref_id` INT UNSIGNED,
  `message` TEXT,
  `status` ENUM('OPEN','CLOSED') DEFAULT 'OPEN',
  `created_by` VARCHAR(50) DEFAULT 'system',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME,
  INDEX (`branch_id`),
  INDEX (`alert_type`),
  INDEX (`status`),
  INDEX (`business_date`)
) ENGINE=InnoDB;

-- stock_ledger (audit trail of stock movements)
CREATE TABLE IF NOT EXISTS `stock_ledger` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `movement_type` ENUM('PURCHASE','SALE','TRANSFER_IN','TRANSFER_OUT','ADJUSTMENT','RETURN') NOT NULL,
  `qty_change` INT NOT NULL,
  `qty_after` INT NOT NULL,
  `ref_type` VARCHAR(50),
  `ref_id` INT UNSIGNED,
  `done_by` INT UNSIGNED,
  `business_date` DATE NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`branch_id`),
  INDEX (`item_id`),
  INDEX (`business_date`)
) ENGINE=InnoDB;

-- user_presence (last_seen heartbeat for "Active now" list)
CREATE TABLE IF NOT EXISTS `user_presence` (
  `user_id` INT UNSIGNED PRIMARY KEY,
  `branch_id` INT UNSIGNED NOT NULL,
  `last_seen` DATETIME NOT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- permissions (role-based)
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT UNSIGNED NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `can_view` TINYINT(1) DEFAULT 0,
  `can_create` TINYINT(1) DEFAULT 0,
  `can_edit` TINYINT(1) DEFAULT 0,
  `can_delete` TINYINT(1) DEFAULT 0,
  UNIQUE KEY `uniq_perm` (`role_id`,`module`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- user_overrides (per-user permission overrides)
CREATE TABLE IF NOT EXISTS `user_overrides` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `can_view` TINYINT(1),
  `can_create` TINYINT(1),
  `can_edit` TINYINT(1),
  `can_delete` TINYINT(1),
  UNIQUE KEY `uniq_uo` (`user_id`,`module`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Update roles to match ERP roles
-- SUP=Supervisor/Owner, BM=Branch Manager, CASH=Cashier/Sales, SK=Stock Keeper, ACC=Accountant
INSERT IGNORE INTO `roles` (`name`) VALUES ('SUP'),('BM'),('CASH'),('SK'),('ACC');
