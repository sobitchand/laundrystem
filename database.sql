-- DD Laundry Database Setup
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS dd_laundry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dd_laundry;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    password_hash VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_expires_at DATETIME DEFAULT NULL,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admin Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Services Table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    icon VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_service_name (name)
);

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','confirmed','picked_up','in_process','ready','delivered','cancelled') DEFAULT 'pending',
    pickup_address TEXT,
    delivery_address TEXT,
    pickup_date DATE,
    delivery_date DATE,
    notes TEXT,
    payment_method ENUM('cash','online') DEFAULT 'cash',
    payment_status ENUM('pending','paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Order Status History
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    note TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Contact Messages
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    phone VARCHAR(20),
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Services
INSERT INTO services (name, description, price, unit, icon) VALUES
('Regular Wash', 'Standard machine wash with detergent. Suitable for everyday wearable clothes including shirts, pants, and casuals.', 50.00, 'per piece', 'tshirt'),
('Premium Wash', 'Advanced wash with premium detergent and fabric conditioner. Ideal for delicate and high-quality garments.', 80.00, 'per piece', 'star'),
('Dry Cleaning', 'Professional dry cleaning for suits, sarees, formal wear, and delicate fabrics that cannot be machine washed.', 120.00, 'per piece', 'brush'),
('Ironing', 'Professional steam ironing for crisp, wrinkle-free clothes. Available for all fabric types.', 30.00, 'per piece', 'lightning')
ON DUPLICATE KEY UPDATE
description = VALUES(description),
price = VALUES(price),
unit = VALUES(unit),
icon = VALUES(icon),
is_active = 1;

UPDATE services
SET is_active = 0
WHERE name NOT IN ('Regular Wash', 'Premium Wash', 'Dry Cleaning', 'Ironing');

-- Insert Default Admin (password: Admin@123)
INSERT INTO admins (username, email, password_hash) VALUES
('admin', 'admin@ddlaundry.com', '$2y$12$Vshzw0kbGV2quzU4ntcjv.ihsTjdY4fS3XcQGE24EL0jQLWdAiwzq')
ON DUPLICATE KEY UPDATE email = VALUES(email);

-- Add location columns to orders (safe for existing databases)
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'dd_laundry' AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'pickup_lat');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE orders ADD COLUMN pickup_lat DECIMAL(10,8) DEFAULT NULL, ADD COLUMN pickup_lng DECIMAL(11,8) DEFAULT NULL, ADD COLUMN delivery_lat DECIMAL(10,8) DEFAULT NULL, ADD COLUMN delivery_lng DECIMAL(11,8) DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Note: Change the admin password after first login!
