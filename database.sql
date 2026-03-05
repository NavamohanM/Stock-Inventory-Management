-- StockIMS Production Database Schema
-- Compatible with MySQL 8.0+ / MariaDB 10.4+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ──────────────────────────────────────────────
-- Create database (skip if already exists)
-- ──────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `ims480` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ims480`;

-- ──────────────────────────────────────────────
-- users
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)    NOT NULL,
  `username`   VARCHAR(60)     NOT NULL UNIQUE,
  `password`   VARCHAR(255)    NOT NULL,
  `role`       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `is_active`  TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin (password: admin123 — CHANGE THIS after first login)
INSERT INTO `users` (`name`, `username`, `password`, `role`) VALUES
('Administrator', 'admin', '$2y$12$9fPooEeLcmFlnIOKRFjCbeNIBCZm1jPSzSqV2OT.TzBH2SCv8CBWO', 'admin');
-- Password above is bcrypt of 'admin123' — CHANGE after first login

-- ──────────────────────────────────────────────
-- categories
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)    NOT NULL UNIQUE,
  `description` VARCHAR(255)    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`, `description`) VALUES
('General', 'Default category'),
('Electronics', 'Electronic items'),
('Clothing', 'Apparel and clothing'),
('Stationery', 'Office and school supplies'),
('Food & Beverage', 'Food and drink items');

-- ──────────────────────────────────────────────
-- suppliers
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)    NOT NULL,
  `contact`     VARCHAR(100)    DEFAULT NULL,
  `phone`       VARCHAR(30)     DEFAULT NULL,
  `email`       VARCHAR(150)    DEFAULT NULL,
  `address`     TEXT            DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`  TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `suppliers` (`name`, `contact`, `phone`) VALUES
('Default Supplier', 'General Contact', '0000000000');

-- ──────────────────────────────────────────────
-- products
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(150)    NOT NULL,
  `description`     TEXT            DEFAULT NULL,
  `sku`             VARCHAR(80)     DEFAULT NULL UNIQUE,
  `category_id`     INT UNSIGNED    DEFAULT 1,
  `supplier_id`     INT UNSIGNED    DEFAULT 1,
  `unit`            INT             NOT NULL DEFAULT 0,
  `unit_price`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `selling_price`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `low_stock_alert` INT             NOT NULL DEFAULT 10,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      TIMESTAMP       DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_supplier` (`supplier_id`),
  CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_product_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`name`, `description`, `sku`, `category_id`, `unit`, `unit_price`, `selling_price`, `low_stock_alert`) VALUES
('Shirt (Demo)',  'Cotton shirt',  'SKU-001', 3, 22, 250.00, 350.00, 5),
('Notebook',     'A4 ruled',      'SKU-002', 4, 14, 50.00,  78.00,  5),
('Pen Set',      'Ball-point 10x','SKU-003', 4, 12, 80.00, 123.00,  5);

-- ──────────────────────────────────────────────
-- purchases
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `purchases` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED    NOT NULL,
  `supplier_id` INT UNSIGNED    DEFAULT NULL,
  `quantity`    INT             NOT NULL,
  `unit_price`  DECIMAL(12,2)   NOT NULL,
  `total_cost`  DECIMAL(14,2)   GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `note`        VARCHAR(255)    DEFAULT NULL,
  `created_by`  INT UNSIGNED    DEFAULT NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_product` (`product_id`),
  CONSTRAINT `fk_purchase_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────
-- sales
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sales` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `product_id`    INT UNSIGNED    NOT NULL,
  `product_name`  VARCHAR(150)    NOT NULL,
  `quantity`      INT             NOT NULL,
  `unit_price`    DECIMAL(12,2)   NOT NULL,
  `total_price`   DECIMAL(14,2)   GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `customer_name` VARCHAR(150)    DEFAULT NULL,
  `note`          VARCHAR(255)    DEFAULT NULL,
  `created_by`    INT UNSIGNED    DEFAULT NULL,
  `created_at`    TIMESTAMP(6)    NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_sale_product` (`product_id`),
  KEY `idx_sale_date`    (`created_at`),
  CONSTRAINT `fk_sale_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
