"""
Add documentation comments to all remaining PHP and JS files in DD Laundry project.
"""
import re
import os

PROJECT = r"C:\xampp\htdocs\dd_laundry"

def add_header(filepath, doc_block):
    """Add a documentation block at the top of a PHP file."""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # Check if already has the doc block
    if "DD Laundry -" in content and doc_block[:50] in content:
        return False

    # Find the opening <?php tag
    php_tag_match = re.match(r'^<\?php\s*\n', content)
    if not php_tag_match:
        # Try with just <?php
        php_tag_match = re.match(r'^<\?php', content)

    if php_tag_match:
        end_pos = php_tag_match.end()
        new_content = content[:end_pos] + "\n" + doc_block + content[end_pos:]
    else:
        new_content = doc_block + content

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
    return True

def prepend_to_js(filepath, doc_block):
    """Add documentation at the top of a JS file."""
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    if "DD Laundry" in content and "DOCUMENTATION" in doc_block[:50]:
        return False

    new_content = doc_block + "\n\n" + content
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
    return True


# ============================================================
# pricing.php - Full file documentation
# ============================================================
pricing_docs = """
 * ============================================================
 * DD Laundry - Pricing Page
 * pricing.php
 *
 * PURPOSE:
 * Displays all laundry service rates with expandable details
 * and a sticky sidebar summary table. Customers can view
 * pricing per service type (Regular Wash, Premium Wash,
 * Dry Cleaning, Ironing) before placing an order.
 *
 * FEATURES:
 * - Responsive hero banner with gradient background
 * - Service cards loaded dynamically from 'services' database table
 * - Each card shows: icon, name, description, price, unit
 * - Expandable card details showing what's included per service
 * - Sticky sidebar with pricing summary table
 * - Order CTA card (different links for logged-in vs guest users)
 * - FAQ section with 6 common questions (author-controlled content)
 * - Keyboard accessible (Enter/Space to toggle cards and FAQs)
 * - Scroll-triggered fade-in animations via IntersectionObserver
 *
 * DATA FLOW:
 * 1. PHP queries 'services' table for active services
 * 2. Services rendered as expandable HTML cards
 * 3. Prices shown per unit (per piece/per kg)
 * 4. FAQ data is hardcoded (safe - no user input)
 * 5. CTA buttons route to dashboard.php or register.php
 *
 * SECURITY:
 * - CSRF token embedded in meta tag
 * - Security headers sent via sendSecurityHeaders()
 * - All dynamic data escaped with htmlspecialchars()
 * - FAQ content is developer-controlled (no XSS risk)
 * - Logged-in state checked to show appropriate CTA buttons
 *
 * OWASP: A05 (security headers), A07 (login state check), A03 (XSS prevention)
 * ============================================================
"""

# ============================================================
# login.php - Full file documentation
# ============================================================
login_docs = """
 * ============================================================
 * DD Laundry - Customer Login Page
 * login.php
 *
 * PURPOSE:
 * Provides the customer login interface. Redirects already
 * logged-in users to dashboard. Handles email/password
 * authentication via AJAX call to php/auth.php.
 *
 * FEATURES:
 * - Split-screen layout (visual left side, form right side)
 * - Email and password fields with validation
 * - Password show/hide toggle button
 * - "Forgot password?" link to forgot-password.php
 * - AJAX login via apiCall() to php/auth.php (action: 'login')
 * - Loading state on submit button during API call
 * - Alert display for success/error messages
 * - Special handling for unverified accounts (shows verification link)
 * - CSRF token embedded in hidden form field and meta tag
 * - Responsive design (stacks on mobile)
 *
 * DATA FLOW:
 * 1. PHP: Check if already logged in -> redirect to dashboard
 * 2. PHP: Generate CSRF token, embed in form
 * 3. User submits email + password
 * 4. JS: apiCall() sends POST to php/auth.php with action=login
 * 5. php/auth.php: Validates CSRF, checks rate limit, verifies credentials
 * 6. On success: Redirect to dashboard.php
 * 7. On unverified account: Show email verification link
 * 8. On failure: Show error alert
 *
 * SECURITY:
 * - CSRF token on form (hidden input + meta tag)
 * - Security headers via sendSecurityHeaders()
 * - Password field uses autocomplete='current-password'
 * - Rate limiting handled server-side in php/auth.php
 * - No sensitive data stored in client-side JavaScript
 * - XSS prevention via textContent for alerts (not innerHTML)
 *
 * OWASP: A01 (CSRF), A03 (XSS prevention), A05 (security headers),
 *        A07 (rate limiting, session security)
 * ============================================================
"""

# ============================================================
# register.php - Full file documentation
# ============================================================
register_docs = """
 * ============================================================
 * DD Laundry - Customer Registration Page
 * register.php
 *
 * PURPOSE:
 * Two-step registration flow: (1) Account creation with form
 * validation, (2) Email OTP verification. Sends OTP via
 * Gmail SMTP and verifies it before activating the account.
 *
 * FEATURES:
 * - Step 1: Registration form with fields:
 *   - Full Name (required, min 2 chars)
 *   - Phone Number (required, Nepal format: 98XXXXXXXX)
 *   - Email Address (required, validated)
 *   - Password (required, min 8 chars)
 *   - Confirm Password (must match)
 * - Step 2: 6-digit OTP verification with:
 *   - 6 separate input boxes with auto-advance
 *   - Paste support for full OTP
 *   - Resend OTP button with 60-second countdown
 * - Visual left panel showing registration benefits
 * - AJAX calls to php/auth.php for register, verify_otp, resend_otp
 * - Loading states on all buttons
 * - Field-level error highlighting
 * - Pre-fills email from URL parameter (?email=...)
 *
 * DATA FLOW:
 * 1. PHP: Check if logged in -> redirect to dashboard
 * 2. PHP: Generate CSRF token
 * 3. User fills registration form
 * 4. JS: apiCall() POST to php/auth.php (action: 'register')
 * 5. php/auth.php: Validates, creates user, sends OTP email
 * 6. JS: Hides Step 1, shows Step 2 OTP form
 * 7. User enters 6-digit OTP from email
 * 8. JS: apiCall() POST to php/auth.php (action: 'verify_otp')
 * 9. php/auth.php: Verifies OTP with timing-safe comparison
 * 10. On success: Redirect to login.php
 * 11. On OTP failure: Resend available after 60s cooldown
 *
 * SECURITY:
 * - CSRF token on all forms
 * - Rate limiting: 10 registrations per IP per hour
 * - OTP: 6 digits, 15-min expiry, single-use
 * - Password hashed with bcrypt cost-12 server-side
 * - No passwords stored/transmitted in plain text
 * - Email enumeration prevention (same response for existing/unregistered)
 * - All user input sanitized server-side
 *
 * OWASP: A01 (CSRF), A02 (bcrypt), A03 (prepared statements),
 *        A07 (rate limiting, OTP expiry, timing-safe comparison),
 *        A09 (security logging)
 * ============================================================
"""

# ============================================================
# forgot-password.php - Full file documentation
# ============================================================
forgot_docs = """
 * ============================================================
 * DD Laundry - Forgot Password Page
 * forgot-password.php
 *
 * PURPOSE:
 * Three-step password recovery flow: (1) Enter email,
 * (2) Enter OTP received via email, (3) Set new password.
 * Uses the same OTP mechanism as registration.
 *
 * FEATURES:
 * - Step 1 (fp1): Email input form
 *   - Sends reset OTP via php/auth.php (action: 'forgot')
 *   - Returns same response whether email exists (anti-enumeration)
 * - Step 2 (fp2): 6-digit OTP entry
 *   - 6 auto-advancing input boxes
 *   - Paste support
 *   - Verify button triggers Step 3
 * - Step 3 (fp3): New password form
 *   - New Password (min 8 chars)
 *   - Confirm Password (must match)
 *   - Password show/hide toggle
 *   - Submits to php/auth.php (action: 'reset')
 * - Visual left panel with key icon and tagline
 * - All steps use AJAX (no page reloads)
 * - Redirects to login.php on success
 *
 * DATA FLOW:
 * 1. PHP: Check if logged in -> redirect to dashboard
 * 2. PHP: Generate CSRF token
 * 3. User enters email in Step 1
 * 4. JS: apiCall() POST to php/auth.php (action: 'forgot')
 * 5. php/auth.php: Generates OTP, sends email via PHPMailer
 * 6. JS: Hides Step 1, shows Step 2 OTP form
 * 7. User enters OTP from email
 * 8. JS: Hides Step 2, shows Step 3 new password form
 * 9. User enters new password + confirmation
 * 10. JS: apiCall() POST to php/auth.php (action: 'reset')
 * 11. php/auth.php: Verifies OTP, hashes new password (bcrypt)
 * 12. On success: Redirect to login.php after 2s
 *
 * SECURITY:
 * - CSRF token on all forms
 * - Rate limiting on forgot and reset endpoints
 * - OTP: 6-digit, 15-min expiry, timing-safe comparison
 * - Password hashed with bcrypt cost-12
 * - Same response for existing/non-existing emails (anti-enumeration)
 * - All validation done server-side in php/auth.php
 *
 * OWASP: A01 (CSRF), A02 (bcrypt), A03 (prepared statements),
 *        A07 (OTP expiry, timing-safe, rate limiting), A09 (logging)
 * ============================================================
"""

# ============================================================
# dashboard.php - Full file documentation
# ============================================================
dashboard_docs = """
 * ============================================================
 * DD Laundry - Customer Dashboard
 * dashboard.php
 *
 * PURPOSE:
 * Main customer control panel after login. Provides 6 tabs:
 * Overview, New Order, My Orders, Feedback, Profile, and
 * Change Password. All data loaded via AJAX from backend APIs.
 *
 * TABS & FEATURES:
 *
 * 1. OVERVIEW (?tab=overview)
 *    - Welcome message with user's first name
 *    - 4 stat cards: Total Orders, In Progress, Delivered, Total Spent
 *    - Recent Orders table (last 5 orders)
 *    - Data loaded via AJAX from php/orders.php (get_orders)
 *
 * 2. NEW ORDER (?tab=order)
 *    - Dynamic cloth type selector grouped by service type
 *    - Line items with service type -> cloth type -> quantity -> price
 *    - Live total calculation as items are added
 *    - Add/remove line item rows dynamically
 *    - Minus/plus quantity buttons
 *    - Pickup address with Leaflet map picker
 *    - Delivery address (optional, defaults to same as pickup)
 *    - "Use My Location" button for GPS-based address
 *    - Nominatim geocoding for address search on map
 *    - Drag-to-move pin on map for precise location
 *    - Preferred pickup date selector
 *    - Payment method (Cash on Delivery / Online)
 *    - Special instructions textarea
 *    - Order submission via AJAX to php/orders.php (action: 'place')
 *    - Server-side price validation (never trusts client prices)
 *    - Invoice number generated automatically
 *
 * 3. MY ORDERS (?tab=orders)
 *    - Paginated order history table
 *    - Shows: Order #, Date, Items count, Total, Status badge
 *    - Click "View" opens order detail modal
 *    - Modal shows: Progress tracker, items list, status history timeline
 *    - "Download Invoice" button opens printable invoice page
 *
 * 4. FEEDBACK (?tab=feedback)
 *    - Star rating (1-5) with interactive buttons
 *    - Feedback message textarea
 *    - Submission via AJAX to php/orders.php (action: 'submit_feedback')
 *    - Requires admin approval before public display
 *
 * 5. PROFILE (?tab=profile)
 *    - Display/edit: Full Name, Phone, Address
 *    - Email shown as read-only (cannot be changed)
 *    - User avatar with initial letter
 *    - Update via AJAX to php/profile.php (action: 'update')
 *
 * 6. CHANGE PASSWORD (?tab=password)
 *    - Current Password, New Password, Confirm New Password
 *    - Password show/hide toggles
 *    - Submission via AJAX to php/profile.php (action: 'change_password')
 *
 * GENERAL FEATURES:
 * - Responsive sidebar navigation (collapses to hamburger on mobile)
 * - Mobile overlay when sidebar is open
 * - Logout button in sidebar footer
 * - CSRF token in meta tag for all AJAX calls
 * - Loading states on all form submission buttons
 * - Toast notifications for success/error feedback
 * - XSS-safe DOM manipulation (escHtml, textContent)
 *
 * DATA FLOW:
 * 1. PHP: requireLogin() checks session, redirects if not logged in
 * 2. PHP: Determines active tab from URL parameter
 * 3. PHP: Renders appropriate tab HTML
 * 4. JS: On page load, calls relevant API endpoints
 * 5. JS: Renders data into DOM (tables, stats, modals)
 * 6. JS: Handles form submissions via AJAX
 * 7. Backend APIs: Validate CSRF, process data, return JSON
 * 8. JS: Updates UI based on API responses
 *
 * MAP INTEGRATION:
 * - Leaflet.js for interactive OpenStreetMap
 * - Default center: Imadol, Lalitpur (27.6535, 85.3318)
 * - Click-to-drop-pin for pickup/delivery locations
 * - Drag-to-move pin updates coordinates
 * - Nominatim reverse geocoding fills address from coordinates
 * - Nominatim search geocoding fills address from text search
 * - Geolocation API for "Use My Location" button
 *
 * SECURITY:
 * - requireLogin() gate on all tabs
 * - CSRF token on all POST forms
 * - Session regeneration every 15 minutes
 * - XSS prevention: escHtml() for user data, textContent for DOM
 * - Server-side price validation (order placement)
 * - User-scoped queries (can only see own orders)
 * - Input sanitization on all form fields
 *
 * OWASP: A01 (CSRF), A03 (XSS prevention, prepared statements),
 *        A04 (user-scoped queries prevent IDOR),
 *        A05 (security headers), A07 (session regeneration)
 * ============================================================
"""

# ============================================================
# index.php - Full file documentation
# ============================================================
index_docs = """
 * ============================================================
 * DD Laundry - Homepage / Public Website
 * index.php
 *
 * PURPOSE:
 * Main public-facing landing page for DD Laundry. Showcases
 * services, features, customer testimonials, contact info,
 * and Google Maps location. Acts as the entry point for
 * new and returning visitors.
 *
 * SECTIONS (top to bottom):
 *
 * 1. NAVBAR
 *    - Logo, navigation links (Services, Pricing, Contact)
 *    - Dynamic links based on login state:
 *      - Logged in: "Hello, [Name]" -> Dashboard
 *      - Guest: "Login" + "Get Started" buttons
 *    - Hamburger menu for mobile
 *    - Scroll effect: background becomes solid on scroll
 *    - Mobile menu overlay
 *
 * 2. HERO SECTION
 *    - Full-width gradient background with animated visual
 *    - Headline: "Fresh Clothes, Delivered to Your Door"
 *    - Subtitle describing DD Laundry services
 *    - CTA buttons: "Book Now" (register) or "Place Order" (dashboard)
 *    - Stats bar: 500+ Customers, 4 Services, 24hr Turnaround
 *    - Animated order card visual showing active order state
 *
 * 3. SERVICES SECTION
 *    - Grid of service cards loaded from 'services' table
 *    - Each card: icon, name, price/unit, expandable details
 *    - Services: Regular Wash, Premium Wash, Dry Cleaning, Ironing
 *    - Cards expand on click to show description + feature tags
 *    - "View Full Pricing" button links to pricing.php
 *    - Scroll-triggered fade-in animations
 *
 * 4. WHY US SECTION (dark background)
 *    - 4 feature cards: Free Pickup, 24hr Turnaround,
 *      Quality Guaranteed, Real-Time Tracking
 *    - Each card with icon, title, and description
 *
 * 5. CUSTOMER FEEDBACK SECTION
 *    - Loaded from 'feedback' table (approved only)
 *    - Shows up to 6 testimonials in a grid
 *    - Each card: star rating, message, author name, date
 *    - Hidden entirely if no approved feedback exists
 *
 * 6. CONTACT & MAP SECTION
 *    - Contact info card: Address, Phone, Hours, Pickup Service
 *    - Embedded contact form (name, email, phone, message)
 *    - Form submits to php/contact.php via AJAX
 *    - Google Maps iframe showing DD Laundry location in Imadol
 *
 * 7. CTA BANNER (only for guests)
 *    - "Ready for Fresh, Clean Clothes?" with signup/login buttons
 *    - Hidden for logged-in users
 *
 * 8. FOOTER
 *    - Brand info, Services link, Account links, Contact info
 *    - Copyright with current year (dynamic)
 *    - "Made with love for Lalitpur" tagline
 *
 * DATA FLOW:
 * 1. PHP: Check login state, generate CSRF token
 * 2. PHP: Query 'services' table for active services -> render cards
 * 3. PHP: Query 'feedback' table for approved testimonials -> render grid
 * 4. JS: Contact form submits via AJAX to php/contact.php
 * 5. JS: Mobile menu toggle, scroll animations, service card accordion
 * 6. JS: Logout handler for mobile menu (if logged in)
 *
 * SECURITY:
 * - CSRF token in meta tag for contact form
 * - Security headers via sendSecurityHeaders()
 * - All user data escaped with htmlspecialchars()
 * - Login state determines which navigation/CTAs to show
 * - XSS prevention: htmlspecialchars on all dynamic content
 * - Contact form rate-limited server-side (5 per IP per 10 min)
 *
 * RESPONSIVE DESIGN:
 * - Mobile-first CSS with breakpoints at 900px and 600px
 * - Hamburger menu replaces nav links on mobile
 * - Service cards stack vertically on narrow screens
 * - Hero section adapts layout for mobile
 * - Contact grid stacks on mobile
 *
 * OWASP: A01 (CSRF on contact form), A03 (XSS prevention),
 *        A05 (security headers), A07 (rate limiting on contact)
 * ============================================================
"""

# ============================================================
# admin/login.php - Full file documentation
# ============================================================
admin_login_docs = """
 * ============================================================
 * DD Laundry - Admin Login Page
 * admin/login.php
 *
 * PURPOSE:
 * Separate authentication page for administrators. Uses
 * dedicated admin session (separate from customer sessions)
 * with its own CSRF tokens, rate limiting, and security logging.
 *
 * FEATURES:
 * - Dark charcoal background (visually distinct from customer login)
 * - Username or Email field (accepts either)
 * - Password field with show/hide toggle
 * - AJAX login via apiCall() to php/admin_api.php (action: 'admin_login')
 * - Loading state on submit button
 * - Alert display for success/error
 * - "Back to Website" link to main site
 * - CSRF token in hidden form field and meta tag
 *
 * DATA FLOW:
 * 1. PHP: Check if admin already logged in -> redirect to admin dashboard
 * 2. PHP: Generate CSRF token, render login form
 * 3. User enters username/email + password
 * 4. JS: apiCall() POST to php/admin_api.php (action: 'admin_login')
 * 5. php/admin_api.php: Validates CSRF, checks rate limit (per IP)
 * 6. Server: Queries admins table (by username OR email)
 * 7. Server: password_verify() with timing-safe comparison
 * 8. On success: Regenerate session, set admin_id in session
 * 9. JS: Redirect to admin/index.php
 * 10. On failure: Show error, rate limit counter incremented
 *
 * SECURITY:
 * - Separate admin session (admin_id vs user_id)
 * - Rate limiting per IP address (10 attempts per 5 minutes)
 * - CSRF token validation
 * - Session regeneration on successful login
 * - Security logging for all login attempts (success and failure)
 * - Password hashed with bcrypt cost-12
 * - Constant-time password verification (prevents timing attacks)
 * - No sensitive data in JavaScript or URL parameters
 * - Security headers via sendSecurityHeaders()
 *
 * OWASP: A01 (CSRF), A02 (bcrypt), A03 (prepared statements),
 *        A07 (rate limiting, session regeneration, timing-safe),
 *        A09 (security logging)
 * ============================================================
"""

# ============================================================
# admin/index.php - Full file documentation
# ============================================================
admin_index_docs = """
 * ============================================================
 * DD Laundry - Admin Dashboard
 * admin/index.php
 *
 * PURPOSE:
 * Complete administration panel for managing the DD Laundry
 * business. Provides 7 tabs: Dashboard, All Orders, Messages,
 * Services, Customers, Feedback, and Change Password.
 * Protected by requireAdmin() authentication gate.
 *
 * TABS & FEATURES:
 *
 * 1. DASHBOARD (?tab=dashboard)
 *    - 4 stat cards: Today's Revenue, Month Revenue,
 *      Total Revenue, Total Orders
 *    - 4 more cards: Today's Orders, Pending Orders,
 *      Total Customers, Active Services
 *    - 7-day revenue trend mini-chart (sparkline-style bars)
 *    - Top 5 services by revenue
 *    - Status breakdown pie chart (orders by status)
 *    - All data loaded via AJAX from php/admin_api.php (get_dashboard)
 *
 * 2. ALL ORDERS (?tab=orders)
 *    - Filterable order table with:
 *      - Status filter dropdown (all statuses + "All")
 *      - Search box (order number, invoice, customer name, email)
 *      - Pagination (15 orders per page)
 *    - Columns: Order #, Invoice #, Customer, Date, Items,
 *      Total, Status (dropdown), Payment, Actions
 *    - Inline status update dropdown (saves via AJAX)
 *    - Click row to open full order detail modal
 *    - Modal shows: Order info, customer details, itemized list,
 *      status history timeline, map with pickup/delivery markers
 *    - "View Invoice" button for printable invoice
 *    - "Delete Order" button with confirmation
 *
 * 3. MESSAGES (?tab=messages)
 *    - List of contact form submissions from public website
 *    - Shows: Name, Email, Phone, Message, Date, Read status
 *    - Click to expand full message
 *    - Mark as read/unread
 *    - Delete individual messages
 *
 * 4. SERVICES (?tab=services)
 *    - Full CRUD for service categories
 *    - Add Service: name, description, price, unit, icon
 *    - Edit Service: inline editing of all fields
 *    - Toggle Service: activate/deactivate (soft delete)
 *    - Delete Service: hard remove from database
 *    - All operations via AJAX to php/admin_api.php
 *
 * 5. CUSTOMERS (?tab=customers)
 *    - Searchable customer list (by name, email, phone)
 *    - Shows: Name, Email, Phone, Orders count, Total Spent,
 *      Registration date, Verified status
 *    - Click customer row to open profile modal
 *    - Modal shows: Full profile, order history, status breakdown
 *    - New customer badge count on sidebar
 *    - "Delete Customer" button with confirmation
 *
 * 6. FEEDBACK (?tab=feedback)
 *    - List of all customer feedback with user email
 *    - Approve/Unapprove toggle (controls public display)
 *    - Delete feedback permanently
 *    - Shows: Customer name, rating stars, message, date, status
 *
 * 7. CHANGE PASSWORD (?tab=password)
 *    - Current Password, New Password, Confirm Password
 *    - Password show/hide toggles
 *    - Validates current password before allowing change
 *    - New password hashed with bcrypt cost-12
 *
 * GENERAL FEATURES:
 * - Responsive sidebar navigation (collapses to hamburger on mobile)
 * - Mobile overlay when sidebar is open
 * - New customer toast notifications (polls every 30s)
 * - Red badge on Customers menu showing unseen customer count
 * - Logout button in sidebar footer
 * - "View Website" link to open public site in new tab
 * - CSRF token on all POST forms
 *
 * DATA FLOW:
 * 1. PHP: requireAdmin() checks admin session, redirects if not logged in
 * 2. PHP: Determines active tab from URL parameter
 * 3. PHP: Renders tab-specific HTML structure
 * 4. JS: On page load, calls appropriate API endpoints
 * 5. JS: Renders data into tables, charts, modals
 * 6. JS: Handles all CRUD operations via AJAX POST requests
 * 7. Backend APIs: Validate CSRF, check admin auth, process data
 * 8. JS: Updates UI, shows success/error toasts
 *
 * MAP FEATURES (in order detail modal):
 * - Leaflet map showing order location
 * - Green marker for pickup location
 * - Red marker for delivery location
 * - Polyline route between pickup and delivery
 * - Falls back to Imadol center if no GPS data
 *
 * SECURITY:
 * - requireAdmin() gate on all tabs and API calls
 * - Separate admin session with its own CSRF tokens
 * - Session regeneration every 15 minutes
 * - All mutations require CSRF validation
 * - XSS prevention: escHtml() for user data, textContent for DOM
 * - Input sanitization on all form fields
 * - Whitelist validation for order statuses
 * - Cascade deletes via foreign keys for data integrity
 * - Security logging for all admin actions
 *
 * OWASP: A01 (CSRF on all mutations), A03 (prepared statements,
 *        XSS prevention), A04 (admin-only endpoints),
 *        A05 (security headers), A07 (session regeneration),
 *        A09 (comprehensive security logging)
 * ============================================================
"""

# Apply all PHP file documentation
files_to_doc = {
    'pricing.php': pricing_docs,
    'login.php': login_docs,
    'register.php': register_docs,
    'forgot-password.php': forgot_docs,
    'dashboard.php': dashboard_docs,
    'index.php': index_docs,
    'admin/login.php': admin_login_docs,
    'admin/index.php': admin_index_docs,
}

modified = []
for filename, doc_block in files_to_doc.items():
    filepath = os.path.join(PROJECT, filename)
    if os.path.exists(filepath):
        if add_header(filepath, doc_block):
            modified.append(filename)

print(f"Documented {len(modified)} PHP files:")
for f in modified:
    print(f"  {f}")
