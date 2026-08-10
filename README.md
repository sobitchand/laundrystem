# DD Laundry — Online Laundry Service Management System

A secure, full-stack PHP/MySQL web application built for **DD Laundry**, Imadol, Lalitpur, Nepal. Digitizes the complete laundry order lifecycle — from customer registration with email OTP verification to order placement, real-time tracking, invoice generation, and administrative management.

---

## System Overview

| Item | Detail |
|------|--------|
| **Client** | DD Laundry, Imadol, Lalitpur, Nepal |
| **Purpose** | Replace manual paper-based order management with a secure web platform |
| **Architecture** | Three-tier (Presentation → Application → Data) |
| **Deployment** | Local XAMPP development; production-ready for HTTPS deployment |

---

## Technology Stack

| Layer | Technology | Reason |
|-------|-----------|--------|
| **Frontend** | HTML5, CSS3, JavaScript (AJAX) | Responsive UI, live order total calculation, Leaflet maps |
| **Backend** | PHP 8+ | Rapid development, widely hosted, mature ecosystem |
| **Database** | MySQL (via XAMPP) | Relational integrity, foreign keys, transactions |
| **Email** | PHPMailer + Gmail SMTP | Live OTP and notification delivery |
| **Maps** | Leaflet + OpenStreetMap + Nominatim | Pickup/delivery location picker with geocoding |
| **Dependency Mgmt** | Composer | PHPMailer installation and autoloading |
| **Server** | Apache 2.4 (XAMPP) | Local development bundle |

---

## Setup Instructions

### Prerequisites
- XAMPP (Apache + MySQL + PHP 8.0+)
- Composer
- A Gmail account with an **App Password** (for SMTP)

### Installation

1. **Copy the project** to your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\dd_laundry
   ```

2. **Start Apache and MySQL** in the XAMPP Control Panel.

3. **Import the database** in phpMyAdmin (`http://localhost/phpmyadmin`):
   - First import: `database.sql` (creates all 7 core tables + seed data)
   - Then import: `database_phase1.sql` (adds 5 additional tables for Phase 2 features)

4. **Install dependencies**:
   ```bash
   cd C:\xampp\htdocs\dd_laundry
   composer install
   ```

5. **Configure SMTP** in `php/config.php`:
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_USER', 'your_gmail@gmail.com');
   define('SMTP_PASS', 'your_16_char_app_password');
   define('SMTP_FROM', 'your_gmail@gmail.com');
   define('SMTP_FROM_NAME', 'DD Laundry');
   ```

6. **Access the application**:
   - Website: `http://localhost/dd_laundry/`
   - User Dashboard: `http://localhost/dd_laundry/dashboard.php`
   - Admin Panel: `http://localhost/dd_laundry/admin/login.php`

### Default Admin Credentials
- **Username:** `admin`
- **Password:** `Admin@123`
- **Change immediately after first login.**

---

## Database Schema (12 Tables)

```
users                   — Customer accounts (email, phone, OTP, verification status)
admins                  — Admin accounts (username, email, bcrypt password hash)
services                — 4 service categories (Regular Wash, Premium Wash, Dry Cleaning, Ironing)
cloth_types             — 26 garment types with per-piece prices, linked to services
orders                  — Customer orders (order_number, total, status, payment, invoice_number, GPS coords)
order_items_v2          — Line items linked to cloth_types (quantity, unit_price_snapshot, line_total)
order_status_history    — Audit log of every status transition per order
payments                — Payment tracking (method: cash/esewa/khalti, status, transaction_ref)
feedback                — Customer reviews (rating 1-5, message, approval status)
contact_messages        — Public contact form submissions
invoice_sequence        — Yearly invoice number counters (DD-YYYY-NNNNNN)
```

**Key relationships:**
- `orders.user_id` → `users.id` (CASCADE DELETE)
- `order_items_v2.order_id` → `orders.id` (CASCADE DELETE)
- `order_items_v2.cloth_type_id` → `cloth_types.id`
- `order_status_history.order_id` → `orders.id` (CASCADE DELETE)
- `payments.order_id` → `orders.id` (CASCADE DELETE)
- `feedback.user_id` → `users.id` (CASCADE DELETE)
- `cloth_types.service_id` → `services.id`

---

## Pricing Model (Per Cloth Type)

Prices are managed per **individual garment type**, not per service category. Admin can add/edit cloth types from the admin panel.

| Service | Cloth Type | Rate (NPR) |
|---------|-----------|------------|
| **Regular Wash** | Shirt, T-Shirt, Trouser, Kurta, Skirt | 50/piece |
| | Jeans | 60/piece |
| | Undergarment | 30/piece |
| | Socks | 20/piece |
| | Towel | 40/piece |
| **Premium Wash** | Shirt, Trouser, Kurta, Formal Shirt | 80/piece |
| | Silk Scarf | 90/piece |
| **Dry Cleaning** | Saree, Jacket | 150/piece |
| | Coat | 200/piece |
| | Blazer | 180/piece |
| | Shawl | 100/piece |
| | Suit | 250/piece |
| **Ironing** | Shirt, Trouser, Kurta | 30/piece |
| | Bedsheet | 50/piece |
| | Curtain | 60/piece |
| | Blanket | 80/piece |

**26 cloth types** total, each with a specific per-piece price.

---

## Order Lifecycle

```
pending → confirmed → picked_up → in_process → ready → delivered
                                                          ↓
                                                      cancelled (from pending only)
```

Every status transition is logged in `order_status_history` with timestamp. Customer receives an email notification on each status change.

**Invoice format:** `DD-YYYY-NNNNNN` (e.g., `DD-2026-000001`)

---

## Features

### Public Website
- Responsive homepage with hero section, dynamic service listing, and pricing
- Customer feedback testimonials carousel (admin-approved only)
- Google Map with DD Laundry location (Imadol, Lalitpur)
- Contact form with validation (messages stored in database)
- About page with business information

### Customer Features
- **Registration** with email OTP verification (6-digit, 15-min expiry, single-use)
- **Login** with rate limiting (blocks after repeated failed attempts)
- **Forgot Password** with email OTP reset via Gmail SMTP
- **Profile Management** — edit name, email, phone, address, password
- **Order Placement** — select cloth types + quantities, live total calculation, GPS location picker for pickup/delivery
- **Order Tracking** — real-time status updates with visual timeline
- **Order History** — view all past orders with details
- **Invoice Download** — printable invoice page with unique invoice number
- **Feedback Submission** — star rating (1-5) + review message

### Admin Features
- **Dashboard** — revenue overview (total/pending), new customer alerts with badge count, quick stats
- **Order Management** — view all orders, update status, view order details modal with map (green pickup marker, red delivery marker, route polyline)
- **Customer Management** — view customer list, profile modal with full details and order history, delete customers
- **Revenue Tracking** — total revenue, pending revenue, order count analytics
- **Contact Messages** — read and manage customer enquiries
- **Feedback Moderation** — approve/unapprove/delete customer reviews
- **Service Management** — CRUD for service categories
- **Cloth Type Management** — CRUD for cloth types with per-piece pricing
- **Admin Password Change**
- **Direct Order Links** — open specific order via URL parameter (`?tab=orders&open_order=DD-XXXXXX-XXXXXX`)
- **Delete Order** — cascade delete with foreign key integrity

### Email System
- **Gmail SMTP** via PHPMailer for live email delivery
- **OTP emails** for registration verification and password reset
- **Order status change notifications** sent to customer automatically
- **SSL certificate bypass** for local development (SMTPOptions configured)
- **Mail log fallback** — if SMTP fails, email content is logged to `logs/mail.log`

### Map Integration
- **Leaflet** with OpenStreetMap tiles for interactive maps
- **Nominatim** geocoding for address search
- **"Use My Location"** button for GPS-based pickup/delivery address
- **Admin map** shows pickup (green marker) and delivery (red marker) with route polyline

---

## Security Measures

### OWASP Top 10 Mitigation

| Risk | Implementation |
|------|---------------|
| **A01: Broken Access Control** | Admin-only endpoints with `requireAdmin()`, user-scoped queries, order ownership validation |
| **A02: Cryptographic Failures** | Bcrypt password hashing (cost factor 12), HTTP-only session cookies |
| **A03: Injection** | PDO prepared statements for ALL database queries, input sanitization via `sanitize()` |
| **A04: Insecure Design** | Rate limiting on login/OTP, session regeneration on login, CSRF tokens on all POST forms |
| **A05: Security Misconfiguration** | Security headers on all responses, CSP headers, disabled directory listing |
| **A06: Vulnerable Components** | Composer for dependency management, PHPMailer latest version |
| **A07: Auth Failures** | OTP verification (6-digit, 15-min expiry), account activation required, rate limiting |
| **A08: Data Integrity Failures** | Server-side validation of all status values against whitelist, cascade deletes via foreign keys |
| **A09: Logging Failures** | Security event logging (login attempts, status changes, deletions), mail log fallback |
| **A10: SSRF** | No external URL fetching, maps use CDN with CSP allowlist |

### Additional Security
- **Session fixation prevention** — session ID regenerated on login
- **IDOR prevention** — users can only access their own orders/data
- **XSS prevention** — `textContent` for dynamic content, HTML entity encoding
- **CSRF protection** — tokens generated per session, validated on all mutation requests
- **Content-Security-Policy** headers to prevent unauthorized script execution
- **Permissions-Policy** headers for browser feature restrictions

---

## File Structure

```
dd_laundry/
├── php/
│   ├── config.php          # Database + SMTP configuration
│   ├── db.php              # PDO database connection
│   ├── auth.php            # Authentication handlers (login, register, OTP, forgot password)
│   ├── order_handler.php   # Order creation and management
│   ├── admin_handler.php   # Admin-specific handlers
│   ├── mailer.php          # PHPMailer email sending with SMTP + fallback
│   └── functions.php       # Helper functions (sanitize, rate limiting, etc.)
├── admin/
│   ├── login.php           # Admin authentication
│   ├── index.php           # Admin dashboard
│   ├── admin_api.php       # Admin AJAX endpoints
│   └── logout.php          # Admin logout
├── css/
│   ├── style.css           # Main stylesheet
│   └── admin.css           # Admin panel styles
├── js/
│   ├── main.js             # Frontend JavaScript (AJAX, maps, order builder)
│   └── admin.js            # Admin panel JavaScript
├── vendor/                 # Composer dependencies (PHPMailer)
── logs/                   # Mail fallback logs, PHP error logs
├── database.sql            # Core schema (7 tables + seed data)
├── database_phase1.sql     # Phase 2 migration (5 additional tables)
├── composer.json           # Dependency definition
└── README.md               # This file
```

---

## Recent Enhancements

- **Per-piece cloth-type pricing** — replaced per-kg service pricing with 26 individual garment types
- **Invoice generation** — sequential invoice numbers (`DD-YYYY-NNNNNN`) with printable invoice page
- **Feedback system** — customer reviews with star ratings, admin approval workflow
- **Payment tracking** — payment method and status tracking per order
- **GPS location picker** — Leaflet maps for pickup/delivery addresses with geocoding
- **Admin map view** — order modal shows pickup (green) and delivery (red) markers with route
- **New customer alerts** — admin dashboard polls for new verified users with toast notifications + badge
- **Direct order links** — admins can open specific orders via URL parameter
- **SSL fix** — added SMTPOptions to bypass certificate verification for local development
- **Security hardening** — added rate limiting, CSRF tokens, bcrypt hashing, session regeneration
- **Forgot password OTP** — email-based password reset via Gmail SMTP
- **Table 4.2 test cases expanded** — added T8 (forgot password), T9 (CSRF), T10 (email delivery)

---

## Development Notes

- **OTP Testing:** If OTP email doesn't arrive, check `logs/mail.log` for the fallback log
- **Database Migration:** Run `database.sql` first, then `database_phase1.sql` for the complete schema
- **Composer:** Run `composer install` from the project root to install PHPMailer
- **Admin Password:** Change from default `Admin@123` immediately after first login
- **Live Deployment:** Update `config.php` with production database credentials and HTTPS URL before deploying
- **TOC Update:** When opening the final report document in Word, right-click the Table of Contents → Update Field → Update entire table

---

## Project Team

| Member | Roll Number |
|--------|-------------|
| Basanta Paudel | 22120114 |
| Binaya Bastakoti | 22120118 |
| Purushottam Chand Bohora | 22120132 |
| Santosh Gurung | 22120136 |

**Supervisor:** Pradip Paudel
**Institution:** Everest Engineering College, Sanepa-2, Lalitpur
**Program:** Bachelor of Information Technology Engineering

---

## License

This project was developed as an academic project for Everest Engineering College. All rights reserved.
