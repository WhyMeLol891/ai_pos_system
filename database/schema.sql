-- AI Camera POS System Database Schema
-- Compatible with MySQL 8.0+ / MariaDB 10.4+

CREATE DATABASE IF NOT EXISTS `ai_pos_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ai_pos_system`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Users Table
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Categories Table
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Products Table
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NULL,
  `name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NOT NULL UNIQUE,
  `price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `cost_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `image_path` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Orders Table
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(50) NOT NULL UNIQUE,
  `cashier_id` INT NULL,
  `customer_name` VARCHAR(100) DEFAULT 'Walk-in Customer',
  `subtotal` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash', 'card', 'qr') NOT NULL DEFAULT 'cash',
  `amount_paid` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `change_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('completed', 'cancelled') NOT NULL DEFAULT 'completed',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_orders_cashier` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Order Items Table
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `unit_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Settings Table
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Audit Logs Table
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =======================================================
-- SEED DATA
-- =======================================================

-- Users (admin / admin123, cashier / cashier123)
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `role`, `status`) VALUES
(1, 'admin', '$2y$12$iIYKjULXhigYvvFASkC7IeZDmjQWBXoNGpwPQ.g6ieR1ELGi.77xG', 'System Administrator', 'admin', 'active'),
(2, 'cashier', '$2y$12$a9yZtJhP2qWZgxVyqZgk0e7SP5FILmESkc/fR35tQkguOXnBKst4e', 'Store Cashier', 'cashier', 'active');

-- Categories
INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Beverages', 'Soft drinks, canned drinks, bottled water, juices'),
(2, 'Snacks', 'Chips, crisps, biscuits, chocolates, candies'),
(3, 'Bakery', 'Fresh bread, buns, pastries'),
(4, 'Groceries', 'Instant noodles, cooking essentials, pantry goods'),
(5, 'Dairy', 'Fresh milk, yogurt, cheese');

-- Products
INSERT INTO `products` (`id`, `category_id`, `name`, `sku`, `price`, `cost_price`, `stock_quantity`, `image_path`, `status`) VALUES
(1, 1, 'Coca-Cola Can 320ml', 'BEV-001', 2.80, 1.80, 50, 'assets/uploads/products/coca_cola.svg', 'active'),
(2, 1, 'Pepsi Can 320ml', 'BEV-002', 2.70, 1.70, 45, 'assets/uploads/products/pepsi.svg', 'active'),
(3, 1, 'Spritzer Natural Mineral Water 600ml', 'BEV-003', 1.50, 0.80, 80, 'assets/uploads/products/mineral_water.svg', 'active'),
(4, 1, '100 Plus Isotonic Drink 325ml', 'BEV-004', 2.90, 1.90, 40, 'assets/uploads/products/100plus.svg', 'active'),
(5, 1, 'Milo Active-Go UHT 200ml', 'BEV-005', 2.20, 1.40, 60, 'assets/uploads/products/milo.svg', 'active'),
(6, 2, 'Lay\'s Classic Potato Chips 50g', 'SNK-001', 4.50, 3.00, 30, 'assets/uploads/products/lays_chips.svg', 'active'),
(7, 2, 'Pringles Original 107g', 'SNK-002', 6.80, 4.50, 25, 'assets/uploads/products/pringles.svg', 'active'),
(8, 2, 'Oreo Vanilla Sandwich Cookies 133g', 'SNK-003', 3.90, 2.50, 35, 'assets/uploads/products/oreo.svg', 'active'),
(9, 2, 'KitKat 4-Finger Milk Chocolate 35g', 'SNK-004', 2.50, 1.60, 4, 'assets/uploads/products/kitkat.svg', 'active'),
(10, 3, 'Gardenia Classic White Bread 400g', 'BAK-001', 3.20, 2.20, 20, 'assets/uploads/products/gardenia_bread.svg', 'active'),
(11, 3, 'Massimo Sandwich Wheat Bread 400g', 'BAK-002', 3.40, 2.40, 15, 'assets/uploads/products/wheat_bread.svg', 'active'),
(12, 4, 'Maggi 2-Minute Instant Noodles Curry 5x79g', 'GRO-001', 5.60, 4.00, 30, 'assets/uploads/products/maggi_curry.svg', 'active'),
(13, 4, 'Samyang Hot Chicken Ramen 140g', 'GRO-002', 6.50, 4.80, 3, 'assets/uploads/products/samyang_ramen.svg', 'active'),
(14, 5, 'Dutch Lady Full Cream Milk 1L', 'DAI-001', 7.50, 5.50, 18, 'assets/uploads/products/fresh_milk.svg', 'active');

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('shop_name', 'AI SMART MART'),
('shop_address', '123 Tech Avenue, Digital Mall, 50000 Kuala Lumpur'),
('shop_phone', '+60 12-345 6789'),
('currency_symbol', 'RM'),
('gemini_api_key', ''),
('gemini_model', 'gemini-3.6-flash'),
('low_stock_threshold', '5'),
('receipt_footer', 'Thank you for shopping with AI Smart Mart! Please come again.');
