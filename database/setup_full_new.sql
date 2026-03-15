-- ============================================================
-- Rani Mobiles Sales & Service - Full Fresh Setup SQL
-- Use this file for a clean new installation.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_notes = 0;

-- -------------------------
-- Reset and create database
-- -------------------------
DROP DATABASE IF EXISTS `rani_erp`;
CREATE DATABASE IF NOT EXISTS `rani_erp` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `rani_erp`;

-- -------------------------
-- Table: branches
-- -------------------------
CREATE TABLE IF NOT EXISTS `branches` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`        VARCHAR(10) NOT NULL UNIQUE,
  `name`        VARCHAR(100) NOT NULL,
  `address`     TEXT,
  `phone`       VARCHAR(20),
  `gstin`       VARCHAR(20),
  `latitude`    DECIMAL(10,8) DEFAULT NULL,
  `longitude`   DECIMAL(11,8) DEFAULT NULL,
  `radius_m`    INT DEFAULT 100,
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `branches` (`code`,`name`,`address`,`phone`,`gstin`) VALUES
('R1','Branch R1','Branch R1 Address','9000000001','29XXXXX0001Z1'),
('R2','Branch R2','Branch R2 Address','9000000002','29XXXXX0002Z2'),
('R3','Branch R3','Branch R3 Address','9000000003','29XXXXX0003Z3'),
('R4','Branch R4','Branch R4 Address','9000000004','29XXXXX0004Z4'),
('R5','Branch R5','Branch R5 Address','9000000005','29XXXXX0005Z5');

-- -------------------------
-- Table: roles
-- -------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO `roles` (`name`) VALUES ('Admin'),('Manager'),('Cashier'),('Technician');

-- -------------------------
-- Table: users
-- -------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`   INT UNSIGNED NOT NULL,
  `role_id`     INT UNSIGNED NOT NULL DEFAULT 3,
  `username`    VARCHAR(50) NOT NULL UNIQUE,
  `password`    VARCHAR(255) NOT NULL,
  `full_name`   VARCHAR(100) NOT NULL,
  `phone`       VARCHAR(20),
  `email`       VARCHAR(100),
  `photo`       VARCHAR(255),
  `is_active`   TINYINT(1) DEFAULT 1,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`role_id`)   REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

-- Default users:
-- 1) admin (Admin role, all-shop access)
-- 2) r1user/r2user/r3user/r4user (branch users, shop-wise access)
-- Password for all below: Admin@1234
INSERT INTO `users` (`branch_id`,`role_id`,`username`,`password`,`full_name`,`phone`,`email`) VALUES
(1, 1, 'admin',  '$2y$12$XykbVfRmHZnIMvTP6XnCCOsozriSIMlGD65Y0iG07rNCnE4XYFEZ6', 'System Admin',   '9999999999', 'admin@ranimobiles.in'),
(1, 3, 'r1user', '$2y$12$XykbVfRmHZnIMvTP6XnCCOsozriSIMlGD65Y0iG07rNCnE4XYFEZ6', 'R1 Shop User',  '9000000101', 'r1@ranimobiles.in'),
(2, 3, 'r2user', '$2y$12$XykbVfRmHZnIMvTP6XnCCOsozriSIMlGD65Y0iG07rNCnE4XYFEZ6', 'R2 Shop User',  '9000000102', 'r2@ranimobiles.in'),
(3, 3, 'r3user', '$2y$12$XykbVfRmHZnIMvTP6XnCCOsozriSIMlGD65Y0iG07rNCnE4XYFEZ6', 'R3 Shop User',  '9000000103', 'r3@ranimobiles.in'),
(4, 3, 'r4user', '$2y$12$XykbVfRmHZnIMvTP6XnCCOsozriSIMlGD65Y0iG07rNCnE4XYFEZ6', 'R4 Shop User',  '9000000104', 'r4@ranimobiles.in');

-- -------------------------
-- Table: activity_log
-- -------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED,
  `branch_id`   INT UNSIGNED,
  `action`      VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address`  VARCHAR(45),
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`created_at`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: staff_attendance
-- -------------------------
CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `branch_id`    INT UNSIGNED NOT NULL,
  `date`         DATE NOT NULL,
  `login_time`   DATETIME,
  `logout_time`  DATETIME,
  `latitude`     DECIMAL(10,8),
  `longitude`    DECIMAL(11,8),
  `is_late`      TINYINT(1) DEFAULT 0,
  `approved_by`  INT UNSIGNED,
  `notes`        VARCHAR(255),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: serial_numbers
-- -------------------------
CREATE TABLE IF NOT EXISTS `serial_numbers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`   INT UNSIGNED NOT NULL,
  `doc_type`    VARCHAR(10) NOT NULL,
  `year`        YEAR NOT NULL,
  `last_serial` INT UNSIGNED DEFAULT 0,
  UNIQUE KEY `uniq_seq` (`branch_id`,`doc_type`,`year`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: brands
-- -------------------------
CREATE TABLE IF NOT EXISTS `brands` (
  `id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO `brands`(`name`) VALUES ('Samsung'),('Apple'),('Realme'),('Xiaomi'),('Vivo'),('Oppo'),('OnePlus'),('Motorola'),('Nokia'),('Others');

-- -------------------------
-- Table: categories
-- -------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO `categories`(`name`) VALUES ('Smartphone'),('Feature Phone'),('Tablet'),('Accessories'),('Spare Parts'),('Second Hand');

-- -------------------------
-- Table: vendors
-- -------------------------
CREATE TABLE IF NOT EXISTS `vendors` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20),
  `email`      VARCHAR(100),
  `address`    TEXT,
  `gstin`      VARCHAR(20),
  `balance`    DECIMAL(12,2) DEFAULT 0.00,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------
-- Table: customers
-- -------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100) NOT NULL,
  `phone`         VARCHAR(20) UNIQUE,
  `email`         VARCHAR(100),
  `address`       TEXT,
  `gstin`         VARCHAR(20),
  `total_purchases` DECIMAL(12,2) DEFAULT 0.00,
  `last_purchase`  DATE,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------
-- Table: products
-- -------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`        INT UNSIGNED,
  `category_id`      INT UNSIGNED,
  `brand_id`         INT UNSIGNED,
  `name`             VARCHAR(150) NOT NULL,
  `model`            VARCHAR(100),
  `barcode`          VARCHAR(50),
  `hsn_code`         VARCHAR(20),
  `gst_percent`      DECIMAL(5,2) DEFAULT 18.00,
  `purchase_cost`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sale_price`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `min_stock`        INT DEFAULT 5,
  `imei_tracking`    TINYINT(1) DEFAULT 0,
  `barcode_tracking` TINYINT(1) DEFAULT 0,
  `preferred_vendor` INT UNSIGNED,
  `is_active`        TINYINT(1) DEFAULT 1,
  `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`barcode`),
  INDEX (`brand_id`),
  FOREIGN KEY (`brand_id`)     REFERENCES `brands`(`id`),
  FOREIGN KEY (`category_id`)  REFERENCES `categories`(`id`),
  FOREIGN KEY (`preferred_vendor`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Table: product_price_history
-- -------------------------
CREATE TABLE IF NOT EXISTS `product_price_history` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`  INT UNSIGNED NOT NULL,
  `old_cost`    DECIMAL(12,2),
  `new_cost`    DECIMAL(12,2),
  `old_price`   DECIMAL(12,2),
  `new_price`   DECIMAL(12,2),
  `changed_by`  INT UNSIGNED,
  `changed_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------
-- Table: stock
-- -------------------------
CREATE TABLE IF NOT EXISTS `stock` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`  INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `qty`        INT DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_stock` (`branch_id`,`product_id`),
  FOREIGN KEY (`branch_id`)  REFERENCES `branches`(`id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: imei_numbers
-- -------------------------
CREATE TABLE IF NOT EXISTS `imei_numbers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id`  INT UNSIGNED NOT NULL,
  `branch_id`   INT UNSIGNED NOT NULL,
  `imei`        VARCHAR(20) NOT NULL UNIQUE,
  `status`      ENUM('in_stock','sold','transferred','servicing') DEFAULT 'in_stock',
  `purchase_id` INT UNSIGNED,
  `sale_id`     INT UNSIGNED,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`imei`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: purchases
-- -------------------------
CREATE TABLE IF NOT EXISTS `purchases` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`    VARCHAR(30) NOT NULL UNIQUE,
  `branch_id`     INT UNSIGNED NOT NULL,
  `vendor_id`     INT UNSIGNED,
  `vendor_name`   VARCHAR(100),
  `vendor_invoice` VARCHAR(50),
  `purchase_date` DATE NOT NULL,
  `subtotal`      DECIMAL(12,2) DEFAULT 0.00,
  `gst_amount`    DECIMAL(12,2) DEFAULT 0.00,
  `total`         DECIMAL(12,2) DEFAULT 0.00,
  `paid`          DECIMAL(12,2) DEFAULT 0.00,
  `balance`       DECIMAL(12,2) DEFAULT 0.00,
  `payment_mode`  VARCHAR(20) DEFAULT 'cash',
  `notes`         TEXT,
  `created_by`    INT UNSIGNED,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Table: purchase_items
-- -------------------------
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `qty`         INT NOT NULL DEFAULT 1,
  `cost`        DECIMAL(12,2) NOT NULL,
  `gst_percent` DECIMAL(5,2) DEFAULT 0.00,
  `gst_amount`  DECIMAL(12,2) DEFAULT 0.00,
  `total`       DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: sales
-- -------------------------
CREATE TABLE IF NOT EXISTS `sales` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_no`    VARCHAR(30) NOT NULL UNIQUE,
  `branch_id`     INT UNSIGNED NOT NULL,
  `customer_id`   INT UNSIGNED,
  `customer_name` VARCHAR(100),
  `customer_phone` VARCHAR(20),
  `sale_date`     DATE NOT NULL,
  `subtotal`      DECIMAL(12,2) DEFAULT 0.00,
  `discount`      DECIMAL(12,2) DEFAULT 0.00,
  `gst_amount`    DECIMAL(12,2) DEFAULT 0.00,
  `total`         DECIMAL(12,2) DEFAULT 0.00,
  `paid_cash`     DECIMAL(12,2) DEFAULT 0.00,
  `paid_upi`      DECIMAL(12,2) DEFAULT 0.00,
  `paid_card`     DECIMAL(12,2) DEFAULT 0.00,
  `paid_credit`   DECIMAL(12,2) DEFAULT 0.00,
  `is_gst_invoice` TINYINT(1) DEFAULT 0,
  `is_internal`   TINYINT(1) DEFAULT 0,
  `to_branch_id`  INT UNSIGNED,
  `notes`         TEXT,
  `created_by`    INT UNSIGNED,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`)    REFERENCES `branches`(`id`),
  FOREIGN KEY (`customer_id`)  REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Table: sale_items
-- -------------------------
CREATE TABLE IF NOT EXISTS `sale_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sale_id`     INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `imei`        VARCHAR(20),
  `qty`         INT NOT NULL DEFAULT 1,
  `unit_price`  DECIMAL(12,2) NOT NULL,
  `discount`    DECIMAL(12,2) DEFAULT 0.00,
  `gst_percent` DECIMAL(5,2) DEFAULT 0.00,
  `gst_amount`  DECIMAL(12,2) DEFAULT 0.00,
  `total`       DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`sale_id`)     REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: branch_transfers
-- -------------------------
CREATE TABLE IF NOT EXISTS `branch_transfers` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_no`    VARCHAR(30) NOT NULL UNIQUE,
  `from_branch_id` INT UNSIGNED NOT NULL,
  `to_branch_id`   INT UNSIGNED NOT NULL,
  `transfer_date`  DATE NOT NULL,
  `total_cost`     DECIMAL(12,2) DEFAULT 0.00,
  `transfer_price` DECIMAL(12,2) DEFAULT 0.00,
  `profit`         DECIMAL(12,2) DEFAULT 0.00,
  `paid`           DECIMAL(12,2) DEFAULT 0.00,
  `balance`        DECIMAL(12,2) DEFAULT 0.00,
  `status`         ENUM('pending','received','rejected') DEFAULT 'pending',
  `notes`          TEXT,
  `created_by`     INT UNSIGNED,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`from_branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`to_branch_id`)   REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: branch_transfer_items
-- -------------------------
CREATE TABLE IF NOT EXISTS `branch_transfer_items` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `transfer_id` INT UNSIGNED NOT NULL,
  `product_id`  INT UNSIGNED NOT NULL,
  `imei`        VARCHAR(20),
  `qty`         INT NOT NULL DEFAULT 1,
  `cost`        DECIMAL(12,2) NOT NULL,
  `transfer_price` DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`transfer_id`) REFERENCES `branch_transfers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`)  REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: second_hand_purchases
-- -------------------------
CREATE TABLE IF NOT EXISTS `second_hand_purchases` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ref_no`        VARCHAR(30) NOT NULL UNIQUE,
  `branch_id`     INT UNSIGNED NOT NULL,
  `seller_name`   VARCHAR(100) NOT NULL,
  `seller_phone`  VARCHAR(20),
  `seller_address` TEXT,
  `seller_photo`  VARCHAR(255),
  `id_proof_photo` VARCHAR(255),
  `imei`          VARCHAR(20),
  `brand_id`      INT UNSIGNED,
  `model`         VARCHAR(100),
  `condition`     ENUM('excellent','good','fair','poor') DEFAULT 'good',
  `buy_price`     DECIMAL(12,2) NOT NULL,
  `notes`         TEXT,
  `sold`          TINYINT(1) DEFAULT 0,
  `created_by`    INT UNSIGNED,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`brand_id`)  REFERENCES `brands`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Table: second_hand_sales
-- -------------------------
CREATE TABLE IF NOT EXISTS `second_hand_sales` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_id`  INT UNSIGNED NOT NULL,
  `sale_ref`     VARCHAR(30) NOT NULL UNIQUE,
  `branch_id`    INT UNSIGNED NOT NULL,
  `buyer_name`   VARCHAR(100),
  `buyer_phone`  VARCHAR(20),
  `sale_price`   DECIMAL(12,2) NOT NULL,
  `profit`       DECIMAL(12,2) DEFAULT 0.00,
  `sale_date`    DATE,
  `created_by`   INT UNSIGNED,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`purchase_id`) REFERENCES `second_hand_purchases`(`id`),
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: service_jobs
-- -------------------------
CREATE TABLE IF NOT EXISTS `service_jobs` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_no`         VARCHAR(30) NOT NULL UNIQUE,
  `branch_id`      INT UNSIGNED NOT NULL,
  `customer_id`    INT UNSIGNED,
  `customer_name`  VARCHAR(100),
  `customer_phone` VARCHAR(20),
  `imei`           VARCHAR(20),
  `brand_id`       INT UNSIGNED,
  `model`          VARCHAR(100),
  `complaint`      TEXT,
  `technician_id`  INT UNSIGNED,
  `parts_used`     TEXT,
  `repair_cost`    DECIMAL(10,2) DEFAULT 0.00,
  `advance_paid`   DECIMAL(10,2) DEFAULT 0.00,
  `status`         ENUM('received','diagnosed','in_repair','ready','delivered') DEFAULT 'received',
  `received_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `delivered_at`   DATETIME,
  `created_by`     INT UNSIGNED,
  FOREIGN KEY (`branch_id`)    REFERENCES `branches`(`id`),
  FOREIGN KEY (`brand_id`)     REFERENCES `brands`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`customer_id`)  REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`technician_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Table: expenses
-- -------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ref_no`       VARCHAR(30),
  `branch_id`    INT UNSIGNED NOT NULL,
  `category`     VARCHAR(50),
  `description`  TEXT,
  `amount`       DECIMAL(12,2) NOT NULL,
  `expense_date` DATE NOT NULL,
  `payment_mode` VARCHAR(20) DEFAULT 'cash',
  `created_by`   INT UNSIGNED,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: cash_register
-- -------------------------
CREATE TABLE IF NOT EXISTS `cash_register` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `branch_id`    INT UNSIGNED NOT NULL,
  `date`         DATE NOT NULL,
  `opening_cash` DECIMAL(12,2) DEFAULT 0.00,
  `cash_in`      DECIMAL(12,2) DEFAULT 0.00,
  `cash_out`     DECIMAL(12,2) DEFAULT 0.00,
  `closing_cash` DECIMAL(12,2) DEFAULT 0.00,
  `notes`        TEXT,
  `closed_by`    INT UNSIGNED,
  `closed_at`    DATETIME,
  UNIQUE KEY (`branch_id`,`date`),
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
) ENGINE=InnoDB;

-- -------------------------
-- Table: payments_received
-- -------------------------
CREATE TABLE IF NOT EXISTS `payments_received` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ref_no`       VARCHAR(30),
  `branch_id`    INT UNSIGNED NOT NULL,
  `customer_id`  INT UNSIGNED,
  `customer_name` VARCHAR(100),
  `amount`       DECIMAL(12,2) NOT NULL,
  `payment_mode` VARCHAR(20) DEFAULT 'cash',
  `reference`    VARCHAR(100),
  `payment_date` DATE NOT NULL,
  `notes`        TEXT,
  `created_by`   INT UNSIGNED,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`)   REFERENCES `branches`(`id`),
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Table: payments_made
-- -------------------------
CREATE TABLE IF NOT EXISTS `payments_made` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ref_no`       VARCHAR(30),
  `branch_id`    INT UNSIGNED NOT NULL,
  `vendor_id`    INT UNSIGNED,
  `vendor_name`  VARCHAR(100),
  `amount`       DECIMAL(12,2) NOT NULL,
  `payment_mode` VARCHAR(20) DEFAULT 'cash',
  `reference`    VARCHAR(100),
  `payment_date` DATE NOT NULL,
  `notes`        TEXT,
  `created_by`   INT UNSIGNED,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`),
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Table: system_settings
-- -------------------------
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key_name`   VARCHAR(100) NOT NULL UNIQUE,
  `key_value`  TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `system_settings` (`key_name`,`key_value`) VALUES
('business_name','Rani Mobiles Sales & Service'),
('address','Your Business Address Here'),
('phone','9999999999'),
('gstin','29XXXXXXXXXXX1Z1'),
('email','info@ranimobiles.in'),
('currency_symbol','₹'),
('backup_schedule','daily'),
('login_start_time','09:00'),
('login_end_time','21:00');

SET foreign_key_checks = 1;
SET sql_notes = 1;
