-- ============================================================
-- DD Laundry Database Setup - Phase 2 Migration
-- database_phase1.sql
--
-- PURPOSE:
-- Extends the core schema (database.sql) with 5 additional
-- tables and enhanced order tracking features. This migration
-- adds per-cloth-type pricing, payment tracking, customer
-- feedback, invoice generation, and order analytics support.
--
-- HOW TO USE:
-- 1. First run database.sql to create core tables
-- 2. Then run this file (database_phase1.sql) to add Phase 2
-- 3. Safe to re-run (uses IF NOT EXISTS and conditional ALTER)
--
-- NEW TABLES ADDED:
-- 1. cloth_types - Individual garment types with per-piece prices
--    - 26 cloth types across 4 service categories
--    - Enables granular pricing (e.g., Suit=250, Shirt=50)
--    - Replaces generic service-based pricing
--
-- 2. order_items_v2 - Enhanced order line items
--    - Links to cloth_types instead of services
--    - Stores unit_price_snapshot (price at time of order)
--    - Prevents price changes from affecting old orders
--    - Migrates existing data from order_items table
--
-- 3. payments - Payment transaction tracking
--    - Supports cash, eSewa, Khalti, bank_transfer
--    - Tracks payment status (pending/paid/failed/refunded)
--    - Records transaction reference and paid timestamp
--    - Auto-marks COD as paid when order delivered
--
-- 4. feedback - Customer reviews and ratings
--    - 1-5 star rating with CHECK constraint
--    - Requires admin approval before public display
--    - Links to users table (CASCADE DELETE)
--    - Displayed on homepage testimonials section
--
-- 5. invoice_sequence - Invoice number generation
--    - Tracks last invoice number per year
--    - Enables sequential invoice numbers (DD-2026-000001)
--    - Prevents duplicate invoice numbers
--    - Atomic increment via INSERT ... ON DUPLICATE KEY UPDATE
--
-- ENHANCED COLUMNS (orders table):
-- - subtotal: Order subtotal before discount
-- - discount: Discount amount (reserved for future use)
-- - invoice_number: Unique invoice identifier (DD-YYYY-NNNNNN)
--
-- SEED DATA:
-- - 26 cloth types with realistic pricing
-- - Example: Shirt (Regular Wash) = NPR 50, Suit (Dry Cleaning) = NPR 250
--
-- MIGRATION SAFETY:
-- - Uses CREATE TABLE IF NOT EXISTS (no errors if table exists)
-- - Uses ON DUPLICATE KEY UPDATE for seed data (idempotent)
-- - Conditional ALTER TABLE (checks if column exists first)
-- - Migrates existing order_items data to order_items_v2
-- - Backfills invoice numbers for existing orders
--
-- PRICING MODEL CHANGE:
-- Before: Service-based pricing (e.g., Regular Wash = 50/kg)
-- After:  Cloth-type pricing (e.g., Shirt = 50/piece, Jeans = 60/piece)
-- - More accurate pricing for different garment types
-- - Customers select specific items, not just service category
-- - Admin can add/edit cloth types dynamically
--
-- RELATIONSHIPS:
-- - cloth_types.service_id → services.id
-- - order_items_v2.order_id → orders.id (CASCADE DELETE)
-- - order_items_v2.cloth_type_id → cloth_types.id
-- - payments.order_id → orders.id (CASCADE DELETE)
-- - feedback.user_id → users.id (CASCADE DELETE)
--
-- SECURITY NOTES:
-- - Rating CHECK constraint ensures 1-5 range
-- - Foreign keys maintain referential integrity
-- - CASCADE DELETE prevents orphaned records
-- ============================================================

USE dd_laundry;

-- ── Cloth Types ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cloth_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    service_type VARCHAR(100) NOT NULL COMMENT 'Category: Regular Wash, Premium Wash, Dry Cleaning, Ironing',
    service_id INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cloth_service (name, service_type),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- ── Seed cloth types ────────────────────────────────────────
INSERT INTO cloth_types (name, service_type, service_id, unit_price) VALUES
-- Regular Wash (service_id = 1)
('Shirt',     'Regular Wash', 1, 50.00),
('T-Shirt',   'Regular Wash', 1, 50.00),
('Trouser',   'Regular Wash', 1, 50.00),
('Kurta',     'Regular Wash', 1, 50.00),
('Jeans',     'Regular Wash', 1, 60.00),
('Skirt',     'Regular Wash', 1, 50.00),
('Undergarment','Regular Wash',1, 30.00),
('Socks',     'Regular Wash', 1, 20.00),
('Towel',     'Regular Wash', 1, 40.00),
-- Premium Wash (service_id = 2)
('Shirt',     'Premium Wash', 2, 80.00),
('Trouser',   'Premium Wash', 2, 80.00),
('Kurta',     'Premium Wash', 2, 80.00),
('Formal Shirt','Premium Wash',2, 80.00),
('Silk Scarf','Premium Wash', 2, 90.00),
-- Dry Cleaning (service_id = 3)
('Suit',      'Dry Cleaning', 3, 250.00),
('Saree',     'Dry Cleaning', 3, 150.00),
('Jacket',    'Dry Cleaning', 3, 150.00),
('Coat',      'Dry Cleaning', 3, 200.00),
('Shawl',     'Dry Cleaning', 3, 100.00),
('Blazer',    'Dry Cleaning', 3, 180.00),
-- Ironing (service_id = 4)
('Shirt',     'Ironing',      4, 30.00),
('Trouser',   'Ironing',      4, 30.00),
('Kurta',     'Ironing',      4, 30.00),
('Bedsheet',  'Ironing',      4, 50.00),
('Blanket',   'Ironing',      4, 80.00),
('Curtain',   'Ironing',      4, 60.00)
ON DUPLICATE KEY UPDATE unit_price = VALUES(unit_price);

-- ── New order_items (replaces old service-based one) ────────
CREATE TABLE IF NOT EXISTS order_items_v2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    cloth_type_id INT NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price_snapshot DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (cloth_type_id) REFERENCES cloth_types(id)
);

-- Migrate existing data from old order_items → order_items_v2
-- Map each old service_id to a matching cloth_type (first active one for that service)
INSERT INTO order_items_v2 (order_id, cloth_type_id, quantity, unit_price_snapshot, line_total)
SELECT oi.order_id,
       (SELECT ct.id FROM cloth_types ct
        WHERE ct.service_id = oi.service_id AND ct.is_active = 1
        ORDER BY ct.id LIMIT 1),
       CAST(oi.quantity AS UNSIGNED),
       oi.unit_price,
       oi.subtotal
FROM order_items oi
WHERE NOT EXISTS (SELECT 1 FROM order_items_v2 v2 WHERE v2.order_id = oi.order_id)
  AND oi.service_id IN (SELECT id FROM services);

-- ── Payments table ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    method ENUM('cash','esewa','khalti','bank_transfer') NOT NULL DEFAULT 'cash',
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    transaction_ref VARCHAR(100) DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- ── Feedback table ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    message TEXT NOT NULL,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── Orders: add subtotal, discount, invoice_number ──────────
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA='dd_laundry' AND TABLE_NAME='orders' AND COLUMN_NAME='subtotal');
SET @sql := IF(@col=0, 'ALTER TABLE orders ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0 AFTER total_amount', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA='dd_laundry' AND TABLE_NAME='orders' AND COLUMN_NAME='discount');
SET @sql := IF(@col=0, 'ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) DEFAULT 0 AFTER subtotal', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA='dd_laundry' AND TABLE_NAME='orders' AND COLUMN_NAME='invoice_number');
SET @sql := IF(@col=0, 'ALTER TABLE orders ADD COLUMN invoice_number VARCHAR(30) UNIQUE DEFAULT NULL AFTER id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill subtotal from existing total_amount
UPDATE orders SET subtotal = total_amount WHERE subtotal = 0 AND total_amount > 0;

-- Backfill invoice numbers for existing orders (oldest first)
SET @counter := (SELECT COUNT(*) FROM orders WHERE invoice_number IS NOT NULL);
UPDATE orders SET
  invoice_number = CONCAT('DD-', DATE_FORMAT(created_at, '%Y'), '-', LPAD((@counter := @counter + 1), 6, '0'))
WHERE invoice_number IS NULL
ORDER BY created_at ASC;

-- ── Invoice number sequence tracking table ─────────────────
CREATE TABLE IF NOT EXISTS invoice_sequence (
    year_val VARCHAR(4) NOT NULL PRIMARY KEY,
    last_num INT NOT NULL DEFAULT 0
);

-- Helper: generate next invoice number via PHP (not stored proc)
-- PHP code: call generateInvoiceNumber() which uses this table
