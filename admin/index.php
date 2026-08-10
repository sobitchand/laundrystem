<?php


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
require_once __DIR__ . '/../php/config.php';
sendSecurityHeaders();
requireAdmin();
$activeTab = in_array($_GET['tab'] ?? '', ['dashboard','orders','messages','services','customers','feedback','password'])
             ? $_GET['tab'] : 'dashboard';
$adminName  = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
$adminInit  = strtoupper(substr((string)($_SESSION['admin_name'] ?? 'A'), 0, 1));
$csrf       = generateCSRFToken();
$titles     = ['dashboard'=>'Dashboard','orders'=>'All Orders','messages'=>'Messages','services'=>'Services','customers'=>'Customers','feedback'=>'Feedback','password'=>'Change Password'];
$pageTitle  = $titles[$activeTab];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= $pageTitle ?> &mdash; DD Laundry Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <style>
    .mob-menu-btn { display:none; background:none; border:none; font-size:1.4rem; cursor:pointer; padding:4px 6px; }
    .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:599; }
    .sidebar-overlay.show { display:block; }
    @media(max-width:768px){
      .dashboard-layout { grid-template-columns:1fr; }
      .sidebar { position:fixed; left:-270px; top:0; bottom:0; z-index:600; transition:left .3s; width:260px; }
      .sidebar.open { left:0; box-shadow:4px 0 32px rgba(0,0,0,.3); }
      .mob-menu-btn { display:flex !important; }
      .dashboard-content { padding:16px; }
    }
    select.status-sel {
      padding:7px 12px; border-radius:8px; border:1.5px solid var(--gray-300);
      font-size:.82rem; font-family:'DM Sans',sans-serif; cursor:pointer; outline:none;
      background:#fff; transition:border-color .2s;
    }
    select.status-sel:focus { border-color:var(--red); }
  </style>
</head>
<body>
<div class="dashboard-layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
    <div class="sidebar-logo">&#x1F510; DD<span>Admin</span></div>
    <nav class="sidebar-nav">
      <div class="sidebar-section-label">Management</div>
      <a href="?tab=dashboard" class="sidebar-link <?= $activeTab==='dashboard'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F4CA;</span> Dashboard
      </a>
      <a href="?tab=orders"    class="sidebar-link <?= $activeTab==='orders'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F4CB;</span> All Orders
      </a>
      <a href="?tab=messages"  class="sidebar-link <?= $activeTab==='messages'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F4AC;</span> Messages
      </a>
      <a href="?tab=services"  class="sidebar-link <?= $activeTab==='services'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x2699;</span> Services
      </a>
      <a href="?tab=customers"  class="sidebar-link <?= $activeTab==='customers'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F465;</span> Customers
        <span id="newUserBadge" class="badge" style="display:none;margin-left:auto;background:var(--red);color:#fff;font-size:.65rem;padding:2px 6px;border-radius:10px;">0</span>
      </a>
      <a href="?tab=feedback"  class="sidebar-link <?= $activeTab==='feedback'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x2B50;</span> Feedback
      </a>
      <div class="sidebar-section-label">Settings</div>
      <a href="?tab=password"  class="sidebar-link <?= $activeTab==='password'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F512;</span> Change Password
      </a>
      <a href="../index.php" target="_blank" rel="noopener" class="sidebar-link">
        <span class="sidebar-icon" aria-hidden="true">&#x1F310;</span> View Website &#x2197;
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar" aria-hidden="true"><?= $adminInit ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= $adminName ?></div>
          <div class="sidebar-user-role">Administrator</div>
        </div>
      </div>
      <a href="#" id="adminLogoutBtn" class="sidebar-link" style="color:rgba(255,100,80,.75);margin-top:8px;">
        <span class="sidebar-icon" aria-hidden="true">&#x1F6AA;</span> Logout
      </a>
    </div>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Main -->
  <div class="dashboard-main">
    <header class="dashboard-topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="mob-menu-btn" id="mobMenuBtn" aria-label="Open navigation"
                aria-expanded="false" aria-controls="sidebar">&#x2630;</button>
        <h1 class="dashboard-title"><?= $pageTitle ?></h1>
      </div>
      <span style="font-size:.82rem;color:var(--gray-500);">DD Laundry Admin Panel</span>
    </header>

    <div class="dashboard-content">

      <!-- ════════ DASHBOARD ════════ -->
      <?php if ($activeTab === 'dashboard'): ?>
      <div class="stats-grid" id="statsGrid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr));">
        <?php
        $statDefs = [
          ['&#x1F4B0;','Today Revenue'],['&#x1F4C5;','Month Revenue'],
          ['&#x1F48E;','Total Revenue'],['&#x1F4CB;','Total Orders'],
          ['&#x1F4E6;','Today Orders'],['&#x23F3;','Pending'],['&#x1F465;','Customers'],
        ];
        foreach ($statDefs as [$ico,$lbl]): ?>
        <div class="stat-card">
          <div class="stat-card-icon" aria-hidden="true"><?= $ico ?></div>
          <div class="stat-card-value">&#x2014;</div>
          <div class="stat-card-label"><?= $lbl ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;" class="dash-charts">
        <div class="data-table-wrapper">
          <div class="data-table-header"><h2 style="font-size:1rem;font-weight:600;">Last 7 Days Revenue</h2></div>
          <div id="revChart" style="padding:24px;">Loading&hellip;</div>
        </div>
        <div class="data-table-wrapper">
          <div class="data-table-header"><h2 style="font-size:1rem;font-weight:600;">Top Services</h2></div>
          <div id="topSvcChart" style="padding:24px;">Loading&hellip;</div>
        </div>
      </div>

      <div class="data-table-wrapper mt-3">
        <div class="data-table-header">
          <h2 style="font-size:1rem;font-weight:600;">Orders by Status</h2>
          <a href="?tab=orders" class="btn btn-outline btn-sm">View All &rarr;</a>
        </div>
        <div id="statusGrid" style="padding:20px;display:flex;flex-wrap:wrap;gap:12px;">Loading&hellip;</div>
      </div>

      <div class="data-table-wrapper mt-3">
        <div class="data-table-header">
          <h2 style="font-size:1rem;font-weight:600;">Recent Orders</h2>
          <a href="?tab=orders" class="btn btn-outline btn-sm">View All &rarr;</a>
        </div>
        <div id="recentOrdersAdmin" style="overflow-x:auto;">Loading&hellip;</div>
      </div>

      <!-- ════════ ORDERS ════════ -->
      <?php elseif ($activeTab === 'orders'): ?>
      <div class="data-table-wrapper">
        <div class="data-table-header">
          <h2 style="font-size:1rem;font-weight:600;">All Orders</h2>
          <div class="filter-bar">
            <input type="search" id="searchInput" placeholder="Search order, customer&hellip;"
                   style="min-width:180px;" aria-label="Search orders">
            <select id="statusFilter" aria-label="Filter by status">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="confirmed">Confirmed</option>
              <option value="picked_up">Picked Up</option>
              <option value="in_process">In Process</option>
              <option value="ready">Ready</option>
              <option value="delivered">Delivered</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div id="ordersTableWrap" style="overflow-x:auto;padding:32px;text-align:center;color:var(--gray-400);">
          Loading&hellip;
        </div>
      </div>

      <!-- ════════ MESSAGES ════════ -->
      <?php elseif ($activeTab === 'messages'): ?>
      <div class="data-table-wrapper">
        <div class="data-table-header"><h2 style="font-size:1rem;font-weight:600;">Contact Messages</h2></div>
        <div id="messagesWrap" style="padding:16px;">Loading&hellip;</div>
      </div>

      <!-- ════════ SERVICES ════════ -->
      <?php elseif ($activeTab === 'services'): ?>
      <div class="data-table-wrapper">
        <div class="data-table-header">
          <h2 style="font-size:1rem;font-weight:600;">Manage Services</h2>
          <button class="btn btn-primary btn-sm" id="addServiceBtn" onclick="openServiceModal()">&#x2795; Add Service</button>
        </div>
        <div id="servicesTableWrap" style="overflow-x:auto;padding:32px;text-align:center;color:var(--gray-400);">
          Loading&hellip;
        </div>
      </div>

      <!-- ════════ CUSTOMERS ════════ -->
      <?php elseif ($activeTab === 'customers'): ?>
      <div class="data-table-wrapper">
        <div class="data-table-header">
          <h2 style="font-size:1rem;font-weight:600;">All Customers</h2>
          <div class="filter-bar">
            <input type="search" id="customerSearchInput" placeholder="Search name, email, phone&hellip;"
                   style="min-width:200px;" aria-label="Search customers">
          </div>
        </div>
        <div id="customersTableWrap" style="overflow-x:auto;padding:32px;text-align:center;color:var(--gray-400);">
          Loading&hellip;
        </div>
      </div>

      <!-- ════════ FEEDBACK ════════ -->
      <?php elseif ($activeTab === 'feedback'): ?>
      <div class="data-table-wrapper">
        <div class="data-table-header"><h2 style="font-size:1rem;font-weight:600;">Customer Feedback</h2></div>
        <div id="feedbackWrap" style="padding:16px;">Loading&hellip;</div>
      </div>

      <!-- ════════ CHANGE PASSWORD ════════ -->
      <?php elseif ($activeTab === 'password'): ?>
      <div style="max-width:460px;">
        <div class="card">
          <div class="card-body">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.3rem;margin-bottom:20px;">
              Change Admin Password
            </h2>
            <div id="adminPassAlert" role="alert" aria-live="polite"></div>
            <form id="adminPassForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action"     value="change_password">
              <div class="form-group">
                <label class="form-label" for="currPwd">Current Password</label>
                <div class="input-group">
                  <span class="input-icon" aria-hidden="true">&#x1F512;</span>
                  <input type="password" class="form-control" id="currPwd" name="current_password"
                         maxlength="128" required>
                  <button type="button" class="input-toggle" data-target="currPwd"
                          aria-label="Show password">&#x1F441;&#xFE0F;</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="newPwd">New Password</label>
                <div class="input-group">
                  <span class="input-icon" aria-hidden="true">&#x1F511;</span>
                  <input type="password" class="form-control" id="newPwd" name="new_password"
                         placeholder="Min. 8 characters" minlength="8" maxlength="128" required>
                  <button type="button" class="input-toggle" data-target="newPwd"
                          aria-label="Show password">&#x1F441;&#xFE0F;</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="confPwd">Confirm New Password</label>
                <input type="password" class="form-control" id="confPwd" name="confirm_password"
                       maxlength="128" required>
              </div>
              <button type="submit" class="btn btn-primary btn-full" id="chAdminPassBtn">
                Update Password
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /dashboard-content -->
  </div><!-- /dashboard-main -->
</div>

<!-- Update Status Modal -->
<div class="modal-overlay" id="updateModal" role="dialog" aria-modal="true" aria-labelledby="updateModalTitle">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <h2 class="modal-title" id="updateModalTitle"
          style="font-family:'Playfair Display',serif;font-size:1.2rem;">
        Update Order Status
      </h2>
      <button class="modal-close" aria-label="Close">&#x00D7;</button>
    </div>
    <div id="updateModalBody"></div>
  </div>
</div>

<!-- Customer Profile Modal -->
<div class="modal-overlay" id="customerModal" role="dialog" aria-modal="true" aria-labelledby="customerModalTitle">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header">
      <h2 class="modal-title" id="customerModalTitle"
          style="font-family:'Playfair Display',serif;font-size:1.2rem;">
        Customer Profile
      </h2>
      <button class="modal-close" aria-label="Close">&#x00D7;</button>
    </div>
    <div id="customerModalBody" style="max-height:75vh;overflow-y:auto;">
      <div style="padding:32px;text-align:center;color:var(--gray-400);">Loading&hellip;</div>
    </div>
  </div>
</div>

<!-- Service Add/Edit Modal -->
<div class="modal-overlay" id="serviceModal" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h2 class="modal-title" id="serviceModalTitle"
          style="font-family:'Playfair Display',serif;font-size:1.2rem;">
        Add Service
      </h2>
      <button class="modal-close" aria-label="Close">&#x00D7;</button>
    </div>
    <div id="serviceModalBody" style="padding:24px;">
      <div id="serviceModalAlert" role="alert" aria-live="polite"></div>
      <form id="serviceForm" novalidate>
        <input type="hidden" id="svcEditId" value="">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="form-group">
          <label class="form-label" for="svcName">Service Name <span class="req">*</span></label>
          <input type="text" class="form-control" id="svcName" maxlength="100" placeholder="e.g. Regular Wash" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="svcDesc">Description</label>
          <textarea class="form-control" id="svcDesc" rows="3" maxlength="500" placeholder="Brief description of the service"></textarea>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="svcPrice">Price (NPR) <span class="req">*</span></label>
            <input type="number" class="form-control" id="svcPrice" min="0" step="0.01" placeholder="50" required>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label" for="svcUnit">Unit</label>
            <select class="form-control" id="svcUnit">
              <option value="per piece">per piece</option>
              <option value="per kg">per kg</option>
              <option value="flat rate">flat rate</option>
            </select>
          </div>
        </div>
        <div class="form-group" style="margin-top:16px;">
          <label class="form-label" for="svcIcon">Icon (emoji or text)</label>
          <input type="text" class="form-control" id="svcIcon" maxlength="10" placeholder="e.g. &#x1F455;">
        </div>
        <div style="display:flex;gap:12px;margin-top:20px;">
          <button type="submit" class="btn btn-primary" id="svcSaveBtn">Save Service</button>
          <button type="button" class="btn btn-ghost" onclick="closeModal('serviceModal')">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="../js/main.js"></script>
<script>
// ── Mobile sidebar ────────────────────────────────────────
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const menuBtn = document.getElementById('mobMenuBtn');
function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('show'); menuBtn.setAttribute('aria-expanded','true'); }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('show'); menuBtn.setAttribute('aria-expanded','false'); }
menuBtn?.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
overlay?.addEventListener('click', closeSidebar);

// ── Logout ────────────────────────────────────────────────
document.getElementById('adminLogoutBtn').addEventListener('click', async e => {
  e.preventDefault();
  const res = await apiCall('../php/admin_api.php', { action: 'admin_logout' });
  window.location.href = res.redirect || 'login.php';
});

// ── Dashboard Stats ───────────────────────────────────────
async function loadDashboard() {
  const res = await apiGet('../php/admin_api.php', { action: 'get_dashboard' });
  if (!res.success) return;
  const s = res.stats;

  const icons  = ['&#x1F4B0;','&#x1F4C5;','&#x1F48E;','&#x1F4CB;','&#x1F4E6;','&#x23F3;','&#x1F465;'];
  const values = [
    'NPR ' + s.today_revenue.toLocaleString(),
    'NPR ' + s.month_revenue.toLocaleString(),
    'NPR ' + s.total_revenue.toLocaleString(),
    s.total_orders, s.today_orders, s.pending_orders, s.total_users,
  ];
  document.querySelectorAll('#statsGrid .stat-card').forEach((card, i) => {
    card.querySelector('.stat-card-icon').innerHTML = icons[i];
    const v = card.querySelector('.stat-card-value');
    v.textContent = values[i];
    v.style.fontSize = i < 3 ? '1.2rem' : '2rem';
  });

  // Revenue chart — all DOM, no innerHTML with data
  const revWrap = document.getElementById('revChart');
  revWrap.innerHTML = '';
  const rev = res.recent_revenue || [];
  if (rev.length) {
    const max = Math.max(...rev.map(r => parseFloat(r.revenue)));
    rev.forEach(r => {
      const pct = max > 0 ? (parseFloat(r.revenue) / max * 100) : 0;
      const d   = new Date(r.date);
      const lbl = d.toLocaleDateString('en-IN', { month:'short', day:'numeric' });
      const row = document.createElement('div'); row.className = 'rev-row';
      const labelEl = document.createElement('span'); labelEl.className = 'rev-label'; labelEl.textContent = lbl;
      const barEl   = document.createElement('div');  barEl.className = 'rev-bar'; barEl.style.width = pct + '%';
      const valEl   = document.createElement('span'); valEl.className = 'rev-value'; valEl.textContent = 'NPR ' + parseFloat(r.revenue).toLocaleString();
      row.append(labelEl, barEl, valEl);
      revWrap.appendChild(row);
    });
  } else {
    revWrap.textContent = 'No data yet';
    revWrap.style.color = 'var(--gray-400)';
  }

  // Top services chart
  const svcWrap = document.getElementById('topSvcChart');
  svcWrap.innerHTML = '';
  const svcs = res.top_services || [];
  if (svcs.length) {
    const maxRev = Math.max(...svcs.map(s => parseFloat(s.total_rev)));
    svcs.forEach(s => {
      const pct = maxRev > 0 ? (parseFloat(s.total_rev) / maxRev * 100) : 0;
      const row = document.createElement('div'); row.className = 'rev-row';
      const lbl = document.createElement('span'); lbl.className = 'rev-label';
      lbl.style.width = '110px'; lbl.style.overflow = 'hidden'; lbl.style.textOverflow = 'ellipsis';
      lbl.textContent = s.name;
      const bar = document.createElement('div'); bar.className = 'rev-bar';
      bar.style.width = pct + '%'; bar.style.background = 'var(--gold)';
      const val = document.createElement('span'); val.className = 'rev-value';
      val.textContent = 'NPR ' + parseFloat(s.total_rev).toLocaleString();
      row.append(lbl, bar, val);
      svcWrap.appendChild(row);
    });
  } else {
    svcWrap.textContent = 'No data yet';
    svcWrap.style.color = 'var(--gray-400)';
  }

  // Status grid
  const sgWrap = document.getElementById('statusGrid');
  sgWrap.innerHTML = '';
  (res.status_counts || []).forEach(sc => {
    const cfg  = STATUS_CONFIG[sc.status] || { label: sc.status, icon: '?' };
    const card = document.createElement('div');
    card.className = 'stat-card';
    card.style.cssText = 'flex:0 0 auto;min-width:120px;text-align:center;padding:16px;';
    const badge = document.createElement('span');
    badge.className = `status-badge status-${escHtml(sc.status)}`;
    badge.textContent = `${cfg.icon} ${cfg.label}`;
    const val = document.createElement('div');
    val.style.cssText = 'font-family:Playfair Display,serif;font-size:1.8rem;font-weight:700;margin-top:8px;';
    val.textContent = sc.count;
    card.append(badge, val);
    sgWrap.appendChild(card);
  });

  // Recent orders (limited)
  await loadAdminOrders('', '', 1, 'recentOrdersAdmin', 8);
  checkNewUsers();
}

// ── New-user notifications ─────────────────────────────────
async function checkNewUsers() {
  const res = await apiGet('../php/admin_api.php', { action: 'get_new_users' });
  if (!res.success || !res.new_users || !res.new_users.length) return;
  const badge = document.getElementById('newUserBadge');
  if (badge) {
    badge.textContent = res.new_users.length;
    badge.style.display = 'inline-block';
  }
  res.new_users.forEach((u, i) => {
    setTimeout(() => {
      ToastManager.show(`New customer registered: ${u.full_name || u.email}`, 'info', 5000);
    }, i * 800);
  });
}

// ── Orders Table ──────────────────────────────────────────
async function loadAdminOrders(search = '', status = '', page = 1, containerId = 'ordersTableWrap', limit = 15) {
  const wrap = document.getElementById(containerId);
  if (!wrap) return;
  const res = await apiGet('../php/admin_api.php', { action: 'get_orders', search, status, page });
  if (!res.success) { wrap.textContent = 'Failed to load orders.'; return; }

  const orders = (res.orders || []).slice(0, limit);
  wrap.innerHTML = '';

  if (!orders.length) {
    wrap.style.padding = '40px'; wrap.style.textAlign = 'center'; wrap.style.color = 'var(--gray-400)';
    wrap.textContent = 'No orders found.';
    return;
  }

  const table = document.createElement('table');
  table.className = 'data-table';
  table.innerHTML = `<thead><tr>
    <th>Order #</th><th>Invoice</th><th>Customer</th><th>Phone</th><th>Items</th>
    <th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th>
  </tr></thead>`;
  const tbody = document.createElement('tbody');

  orders.forEach(o => {
    const tr = document.createElement('tr');

    const tdNum = document.createElement('td');
    const numSpan = document.createElement('strong');
    numSpan.style.cssText = 'color:var(--red);font-size:.8rem;';
    numSpan.textContent = o.order_number;
    tdNum.appendChild(numSpan);

    const tdInv = document.createElement('td');
    tdInv.style.cssText = 'font-size:.78rem;color:var(--gray-500);';
    tdInv.textContent = o.invoice_number || '—';

    const tdCust = document.createElement('td');
    const custName = document.createElement('div'); custName.style.fontWeight = '600'; custName.style.fontSize = '.88rem';
    custName.textContent = o.customer_name;
    const custEmail = document.createElement('div'); custEmail.style.cssText = 'color:var(--gray-500);font-size:.75rem;';
    custEmail.textContent = o.customer_email;
    tdCust.append(custName, custEmail);

    const tdPhone = document.createElement('td'); tdPhone.style.fontSize = '.82rem';
    tdPhone.textContent = o.customer_phone || '—';

    const tdItems = document.createElement('td'); tdItems.textContent = o.item_count + ' piece(s)';

    const tdTotal = document.createElement('td');
    const totStrong = document.createElement('strong'); totStrong.textContent = formatNPR(o.total_amount);
    tdTotal.appendChild(totStrong);

    const tdStatus = document.createElement('td');
    const badge = document.createElement('span');
    badge.className = `status-badge status-${escHtml(o.status)}`;
    const cfg = STATUS_CONFIG[o.status] || { label: o.status, icon: '' };
    badge.textContent = `${cfg.icon} ${cfg.label}`;
    tdStatus.appendChild(badge);

    const tdPay = document.createElement('td');
    const payBadge = document.createElement('span');
    payBadge.className = 'status-badge ' + (o.pay_status === 'paid' ? 'status-delivered' : o.pay_status === 'refunded' ? 'status-cancelled' : 'status-pending');
    payBadge.textContent = (o.pay_status || 'pending').toUpperCase();
    tdPay.appendChild(payBadge);

    const tdDate = document.createElement('td');
    tdDate.style.cssText = 'font-size:.8rem;color:var(--gray-500);';
    tdDate.textContent = formatDate(o.created_at);

    const tdAct = document.createElement('td');
    tdAct.style.cssText = 'display:flex;gap:6px;flex-wrap:wrap;';
    const btn = document.createElement('button');
    btn.className = 'btn btn-primary btn-sm';
    btn.textContent = 'View / Update';
    btn.addEventListener('click', () => openUpdateModal(o.id, o.order_number, o.status, o.customer_email));
    tdAct.appendChild(btn);
    if (o.customer_id) {
      const custBtn = document.createElement('button');
      custBtn.className = 'btn btn-outline btn-sm';
      custBtn.textContent = 'Profile';
      custBtn.addEventListener('click', () => viewCustomer(o.customer_id));
      tdAct.appendChild(custBtn);
    }
    const delBtn = document.createElement('button');
    delBtn.className = 'btn btn-ghost btn-sm';
    delBtn.textContent = '🗑 Delete';
    delBtn.addEventListener('click', () => {
      if (confirm(`Permanently delete order ${o.order_number}?\nThis cannot be undone.`)) deleteOrder(o.id);
    });
    tdAct.appendChild(delBtn);

    tr.append(tdNum, tdInv, tdCust, tdPhone, tdItems, tdTotal, tdStatus, tdPay, tdDate, tdAct);
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  wrap.appendChild(table);
}

async function deleteOrder(orderId) {
  const res = await apiCall('../php/admin_api.php', { action: 'delete_order', order_id: orderId });
  if (res.success) {
    ToastManager.show('Order deleted!', 'success');
    loadAdminOrders(
      document.getElementById('searchInput')?.value || '',
      document.getElementById('statusFilter')?.value || ''
    );
  } else {
    ToastManager.show(res.error || 'Failed to delete order', 'error');
  }
}

// ── Status Pipeline ─────────────────────────────────────────
const STATUS_PIPELINE = ['pending','confirmed','picked_up','in_process','ready','delivered'];

function getVisibleStatuses(currentStatus) {
  if (currentStatus === 'cancelled') return ['cancelled'];
  const idx = STATUS_PIPELINE.indexOf(currentStatus);
  if (idx === -1) return [...STATUS_PIPELINE, 'cancelled'];
  const remaining = STATUS_PIPELINE.slice(idx);
  if (currentStatus === 'pending') return ['pending', 'cancelled', ...remaining.filter(s => s !== 'pending')];
  return remaining;
}

function renderPipelineStepper(currentStatus) {
  const wrap = document.createElement('div');
  wrap.style.cssText = 'margin-bottom:18px;padding:14px 16px;background:var(--gray-50);border-radius:10px;border:1px solid var(--gray-200);';
  const title = document.createElement('div');
  title.style.cssText = 'font-size:.78rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;';
  title.textContent = 'Order Pipeline';
  wrap.appendChild(title);

  const row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:0;flex-wrap:wrap;';

  if (currentStatus === 'cancelled') {
    const chip = document.createElement('span');
    chip.style.cssText = 'background:#fee2e2;color:#b91c1c;padding:5px 14px;border-radius:20px;font-size:.78rem;font-weight:600;';
    chip.textContent = '❌ Cancelled';
    row.appendChild(chip);
    wrap.appendChild(row);
    return wrap;
  }

  const visible = getVisibleStatuses(currentStatus).filter(s => s !== 'cancelled');
  const currentIdx = STATUS_PIPELINE.indexOf(currentStatus);
  const futureSteps = visible.filter(s => s !== currentStatus);

  visible.forEach((s, i) => {
    const cfg = STATUS_CONFIG[s] || { label: s, icon: '?' };
    const step = document.createElement('div');
    step.style.cssText = 'display:flex;align-items:center;';

    const isCurrent = s === currentStatus;

    const dot = document.createElement('div');
    if (isCurrent) {
      dot.style.cssText = 'width:26px;height:26px;border-radius:50%;background:var(--red);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;box-shadow:0 0 0 3px rgba(200,42,42,.18);';
    } else {
      dot.style.cssText = 'width:26px;height:26px;border-radius:50%;background:var(--gray-200);color:var(--gray-400);display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;';
    }
    dot.textContent = cfg.icon;

    const label = document.createElement('span');
    label.style.cssText = `font-size:.7rem;margin-left:4px;margin-right:8px;white-space:nowrap;font-weight:${isCurrent?'700':'400'};color:${isCurrent?'var(--red)':'var(--gray-400)'};`;
    label.textContent = cfg.label;

    step.append(dot, label);

    if (i < visible.length - 1) {
      const line = document.createElement('div');
      line.style.cssText = 'width:20px;height:2px;flex-shrink:0;background:var(--gray-200);margin-right:4px;';
      step.append(line);
    }

    row.appendChild(step);

    if (isCurrent) {
      if (currentStatus === 'pending') {
        const cancelChip = document.createElement('div');
        cancelChip.style.cssText = 'display:flex;align-items:center;margin-left:8px;margin-right:8px;';
        const cancelDot = document.createElement('div');
        cancelDot.style.cssText = 'width:26px;height:26px;border-radius:50%;background:#fee2e2;color:#b91c1c;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;';
        cancelDot.textContent = '❌';
        const cancelLabel = document.createElement('span');
        cancelLabel.style.cssText = 'font-size:.7rem;margin-left:4px;color:#b91c1c;font-weight:600;';
        cancelLabel.textContent = 'Cancel';
        cancelChip.append(cancelDot, cancelLabel);
        row.appendChild(cancelChip);

        if (futureSteps.length > 0) {
          const sep = document.createElement('div');
          sep.style.cssText = 'width:20px;height:2px;flex-shrink:0;background:var(--gray-200);margin-right:4px;';
          row.appendChild(sep);
        }
      }
    }
  });

  wrap.appendChild(row);
  return wrap;
}

// ── Update Modal ──────────────────────────────────────────
function openUpdateModal(orderId, orderNum, currentStatus, customerEmail) {
  document.getElementById('updateModalTitle').textContent = 'Order #' + orderNum;

  const body = document.getElementById('updateModalBody');
  body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--gray-400);">Loading order details&hellip;</div>';
  openModal('updateModal');

  apiGet('../php/admin_api.php', { action: 'get_order_detail', id: orderId }).then(res => {
    body.innerHTML = '';
    if (!res.success || !res.order) {
      body.innerHTML = '<div class="alert alert-error">Failed to load order details.</div>';
      return;
    }
    const o = res.order;

    // Customer info section
    const info = document.createElement('div');
    info.style.cssText = 'margin-bottom:18px;font-size:.86rem;color:var(--gray-600);';
    info.innerHTML = `<strong style="color:var(--gray-800);">${o.customer_name || 'Customer'}</strong><br>
      <span style="font-size:.8rem;color:var(--gray-500);">${o.customer_email || ''} &middot; ${o.customer_phone || ''}</span><br>
      <span style="font-size:.8rem;color:var(--gray-400);margin-top:4px;display:block;">An email notification will be sent automatically when you update the status.</span>`;
    body.appendChild(info);

    // Pickup & Delivery addresses
    const locWrap = document.createElement('div');
    locWrap.style.cssText = 'margin-bottom:16px;padding:12px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-200);';
    const locTitle = document.createElement('strong');
    locTitle.style.cssText = 'font-size:.85rem;display:block;margin-bottom:8px;color:var(--gray-700);';
    locTitle.textContent = 'Pickup & Delivery Locations';
    locWrap.appendChild(locTitle);

    const addrGrid = document.createElement('div');
    addrGrid.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;font-size:.8rem;color:var(--gray-600);';
    addrGrid.innerHTML = `
      <div style="background:#fff;padding:8px;border-radius:6px;border-left:3px solid #22c55e;">
        <strong style="color:#15803d;font-size:.75rem;">PICKUP</strong><br>
        ${o.pickup_address ? o.pickup_address.replace(/</g,'&lt;') : 'Not provided'}
      </div>
      <div style="background:#fff;padding:8px;border-radius:6px;border-left:3px solid #ef4444;">
        <strong style="color:#b91c1c;font-size:.75rem;">DELIVERY</strong><br>
        ${o.delivery_address ? o.delivery_address.replace(/</g,'&lt;') : 'Not provided'}
      </div>`;
    locWrap.appendChild(addrGrid);

    const mapWrap = document.createElement('div');
    mapWrap.id = 'updateMapWrap';
    mapWrap.style.cssText = 'height:220px;border-radius:8px;overflow:hidden;border:1px solid var(--gray-300);display:none;';
    locWrap.appendChild(mapWrap);
    body.appendChild(locWrap);

    if (o.pickup_lat && o.pickup_lng) {
      mapWrap.style.display = 'block';
      setTimeout(() => {
        const m = L.map(mapWrap, { zoomControl: true, dragging: false, scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM' }).addTo(m);

        const iconHtml = (color, label) => L.divIcon({
          className: 'custom-map-marker',
          html: `<div style="background:${color};color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.25);">${label}</div>`,
          iconSize: [24, 24], iconAnchor: [12, 12]
        });

        const pickup = L.marker([o.pickup_lat, o.pickup_lng], { icon: iconHtml('#22c55e', 'P') }).addTo(m)
          .bindPopup('<strong>Pickup</strong><br>' + (o.pickup_address || ''));
        const bounds = L.latLngBounds([o.pickup_lat, o.pickup_lng], [o.pickup_lat, o.pickup_lng]);

        if (o.delivery_lat && o.delivery_lng) {
          const delivery = L.marker([o.delivery_lat, o.delivery_lng], { icon: iconHtml('#ef4444', 'D') }).addTo(m)
            .bindPopup('<strong>Delivery</strong><br>' + (o.delivery_address || ''));
          bounds.extend([o.delivery_lat, o.delivery_lng]);
          L.polyline([[o.pickup_lat, o.pickup_lng], [o.delivery_lat, o.delivery_lng]], { color: '#6366f1', dashArray: '6,8', weight: 3 }).addTo(m);
        }
        m.fitBounds(bounds, { padding: [20, 20], maxZoom: 16 });
        setTimeout(() => m.invalidateSize(), 100);
      }, 350);
    }

    const alertDiv = document.createElement('div'); alertDiv.id = 'updateModalAlert';
    alertDiv.setAttribute('role','alert'); alertDiv.setAttribute('aria-live','polite');

    const stepperWrap = document.createElement('div');
    stepperWrap.id = 'pipelineStepper';
    stepperWrap.appendChild(renderPipelineStepper(currentStatus));

    const statusGrp = document.createElement('div'); statusGrp.className = 'form-group';
    const statusLbl = document.createElement('label'); statusLbl.className = 'form-label'; statusLbl.textContent = 'New Status';
    const statusSel = document.createElement('select'); statusSel.className = 'form-control'; statusSel.id = 'newStatusSel';
    getVisibleStatuses(currentStatus).forEach(s => {
      const opt = document.createElement('option');
      opt.value = s; opt.selected = s === currentStatus;
      const cfg = STATUS_CONFIG[s] || { label: s, icon: '' };
      opt.textContent = `${cfg.icon} ${cfg.label}`;
      statusSel.appendChild(opt);
    });
    statusSel.addEventListener('change', () => {
      const sw = document.getElementById('pipelineStepper');
      if (sw) { sw.innerHTML = ''; sw.appendChild(renderPipelineStepper(statusSel.value)); }
    });
    statusGrp.append(statusLbl, statusSel);

    const noteGrp = document.createElement('div'); noteGrp.className = 'form-group';
    const noteLbl = document.createElement('label'); noteLbl.className = 'form-label'; noteLbl.textContent = 'Note for Customer (optional)';
    const noteTA  = document.createElement('textarea'); noteTA.className = 'form-control'; noteTA.id = 'statusNoteTA';
    noteTA.rows = 3; noteTA.placeholder = 'e.g. Your clothes are ready for pickup.'; noteTA.maxLength = 500;
    noteGrp.append(noteLbl, noteTA);

    const btnWrap = document.createElement('div');
    btnWrap.style.cssText = 'display:flex;gap:12px;margin-top:8px;';
    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'btn btn-primary'; confirmBtn.id = 'confirmUpdateBtn';
    confirmBtn.textContent = 'Update & Notify Customer';
    confirmBtn.addEventListener('click', () => confirmStatusUpdate(orderId));
    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button'; cancelBtn.className = 'btn btn-ghost';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.addEventListener('click', () => closeModal('updateModal'));
    btnWrap.append(confirmBtn, cancelBtn);

    body.append(stepperWrap, alertDiv, statusGrp, noteGrp, btnWrap);
  });
}

async function confirmStatusUpdate(orderId) {
  const btn = document.getElementById('confirmUpdateBtn');
  setLoading(btn, true);
  const res = await apiCall('../php/admin_api.php', {
    action:    'update_status',
    order_id:  orderId,
    status:    document.getElementById('newStatusSel').value,
    note:      document.getElementById('statusNoteTA').value,
  });
  setLoading(btn, false);
  if (res.success) {
    ToastManager.show('Status updated & customer notified!', 'success');
    closeModal('updateModal');
    const tab = '<?= $activeTab ?>';
    if (tab === 'dashboard') loadDashboard();
    else loadAdminOrders(
      document.getElementById('searchInput')?.value || '',
      document.getElementById('statusFilter')?.value || ''
    );
  } else {
    showAlert('updateModalAlert', res.error || 'Update failed', 'error');
  }
}

// ── Customer Profile Modal ──────────────────────────────
async function viewCustomer(customerId) {
  document.getElementById('customerModalTitle').textContent = 'Customer Profile';
  const body = document.getElementById('customerModalBody');
  body.innerHTML = '<div style="padding:32px;text-align:center;color:var(--gray-400);">Loading&hellip;</div>';
  openModal('customerModal');

  const res = await apiGet('../php/admin_api.php', { action: 'get_customer', id: customerId });
  if (!res.success) {
    body.innerHTML = '';
    showAlert('customerModalBody', res.error || 'Failed to load customer', 'error');
    return;
  }

  const u = res.user;
  const s = res.stats;
  body.innerHTML = '';

  // Profile header
  const header = document.createElement('div');
  header.className = 'cust-profile-header';
  const avatar = document.createElement('div');
  avatar.className = 'cust-avatar';
  avatar.textContent = (u.full_name || 'U').charAt(0).toUpperCase();
  const nameBlock = document.createElement('div');
  const nameEl = document.createElement('div');
  nameEl.style.cssText = 'font-family:Playfair Display,serif;font-size:1.15rem;font-weight:600;';
  nameEl.textContent = u.full_name;
  const metaEl = document.createElement('div');
  metaEl.className = 'cust-meta';
  metaEl.textContent = u.email;
  nameBlock.append(nameEl, metaEl);
  header.append(avatar, nameBlock);
  body.appendChild(header);

  // Details grid
  const details = document.createElement('div');
  details.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;font-size:.85rem;';
  [
    ['Phone', u.phone || '—'],
    ['Address', u.address || '—'],
    ['Member Since', formatDate(u.created_at)],
  ].forEach(([lbl, val]) => {
    const d = document.createElement('div');
    const l = document.createElement('strong'); l.textContent = lbl + ': ';
    const v = document.createElement('span'); v.style.color = 'var(--gray-600)'; v.textContent = val;
    d.append(l, v);
    details.appendChild(d);
  });
  body.appendChild(details);

  // Stats
  const statsRow = document.createElement('div');
  statsRow.className = 'cust-stats-row';
  [
    [s.total_orders, 'Total Orders'],
    [formatNPR(s.total_spent), 'Total Spent'],
  ].forEach(([val, lbl]) => {
    const card = document.createElement('div');
    card.className = 'cust-stat';
    const v = document.createElement('div');
    v.className = 'cust-stat-val';
    v.textContent = val;
    const l = document.createElement('div');
    l.className = 'cust-stat-lbl';
    l.textContent = lbl;
    card.append(v, l);
    statsRow.appendChild(card);
  });
  // Status breakdown
  (s.status_counts || []).forEach(sc => {
    const cfg = STATUS_CONFIG[sc.status] || { label: sc.status, icon: '' };
    const card = document.createElement('div');
    card.className = 'cust-stat';
    const v = document.createElement('div');
    v.className = 'cust-stat-val';
    v.textContent = sc.count;
    const l = document.createElement('div');
    l.className = 'cust-stat-lbl';
    l.textContent = cfg.label;
    card.append(v, l);
    statsRow.appendChild(card);
  });
  body.appendChild(statsRow);

  // Delete customer button
  const delWrap = document.createElement('div');
  delWrap.style.cssText = 'margin:16px 0 8px;padding-top:16px;border-top:1px solid var(--gray-100);';
  const delBtn = document.createElement('button');
  delBtn.className = 'btn btn-ghost btn-sm';
  delBtn.textContent = '🗑 Delete Customer Profile';
  delBtn.addEventListener('click', () => {
    if (confirm(`Permanently delete ${u.full_name}?\nAll their orders and feedback will also be removed.`)) deleteUser(u.id);
  });
  delWrap.appendChild(delBtn);
  body.appendChild(delWrap);

  // Orders list
  const ordersTitle = document.createElement('strong');
  ordersTitle.style.cssText = 'font-size:.88rem;display:block;margin-bottom:10px;';
  ordersTitle.textContent = 'Order History';
  body.appendChild(ordersTitle);

  const ordersList = document.createElement('div');
  ordersList.className = 'cust-orders-list';
  const orders = res.orders || [];
  if (!orders.length) {
    const empty = document.createElement('div');
    empty.style.cssText = 'padding:20px;text-align:center;color:var(--gray-400);';
    empty.textContent = 'No orders yet.';
    ordersList.appendChild(empty);
  } else {
    orders.forEach(o => {
      const row = document.createElement('div');
      row.className = 'cust-order-row';
      const left = document.createElement('div');
      const oNum = document.createElement('strong');
      oNum.style.cssText = 'color:var(--red);font-size:.8rem;';
      oNum.textContent = o.order_number;
      const oDate = document.createElement('div');
      oDate.style.cssText = 'font-size:.75rem;color:var(--gray-400);';
      oDate.textContent = formatDate(o.created_at);
      left.append(oNum, oDate);
      const right = document.createElement('div');
      right.style.cssText = 'display:flex;align-items:center;gap:8px;';
      const tot = document.createElement('span');
      tot.style.fontWeight = '600';
      tot.textContent = formatNPR(o.total_amount);
      const badge = document.createElement('span');
      badge.className = `status-badge status-${escHtml(o.status)}`;
      const cfg = STATUS_CONFIG[o.status] || { label: o.status, icon: '' };
      badge.textContent = `${cfg.icon} ${cfg.label}`;
      right.append(tot, badge);
      row.append(left, right);
      ordersList.appendChild(row);
    });
  }
  body.appendChild(ordersList);
}

// ── Messages ──────────────────────────────────────────────
async function loadMessages() {
  const res = await apiGet('../php/admin_api.php', { action: 'get_messages' });
  const wrap = document.getElementById('messagesWrap');
  wrap.innerHTML = '';
  if (!res.success || !(res.messages||[]).length) {
    wrap.style.cssText = 'padding:48px;text-align:center;color:var(--gray-400);';
    wrap.textContent = 'No messages yet.';
    return;
  }
  res.messages.forEach(m => {
    const card = document.createElement('div');
    card.style.cssText = `border:1px solid var(--gray-300);border-radius:12px;
      padding:18px 20px;margin-bottom:12px;
      background:${m.is_read ? '#fff' : 'var(--red-pale)'};`;

    const head = document.createElement('div');
    head.style.cssText = 'display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap;gap:6px;';
    const nameEl = document.createElement('div');
    const strong = document.createElement('strong'); strong.textContent = m.name;
    nameEl.appendChild(strong);
    if (m.email) { const em = document.createTextNode(' · ' + m.email); nameEl.appendChild(em); }
    if (m.phone) { const ph = document.createTextNode(' · ' + m.phone); nameEl.appendChild(ph); }
    const dateEl = document.createElement('span');
    dateEl.style.cssText = 'font-size:.75rem;color:var(--gray-400);';
    dateEl.textContent = formatDate(m.created_at);
    head.append(nameEl, dateEl);

    const msg = document.createElement('p');
    msg.style.cssText = 'font-size:.88rem;color:var(--gray-700);line-height:1.6;';
    msg.textContent = m.message;

    card.append(head, msg);
    wrap.appendChild(card);
  });
}

// ── Admin Password ────────────────────────────────────────
document.getElementById('adminPassForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('chAdminPassBtn');
  setLoading(btn, true);
  const res = await apiCall('../php/admin_api.php', {
    action:           'change_password',
    current_password: document.getElementById('currPwd').value,
    new_password:     document.getElementById('newPwd').value,
    confirm_password: document.getElementById('confPwd').value,
  });
  setLoading(btn, false);
  if (res.success) {
    showAlert('adminPassAlert', res.message || 'Password updated!', 'success');
    document.getElementById('adminPassForm').reset();
  } else {
    showAlert('adminPassAlert', res.error || 'Failed', 'error');
  }
});

// ── Orders search/filter ──────────────────────────────────
let searchTimer;
document.getElementById('searchInput')?.addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    loadAdminOrders(
      document.getElementById('searchInput').value,
      document.getElementById('statusFilter').value
    );
  }, 380);
});
document.getElementById('statusFilter')?.addEventListener('change', () => {
  loadAdminOrders(
    document.getElementById('searchInput').value,
    document.getElementById('statusFilter').value
  );
});

// ── Responsive charts ─────────────────────────────────────
function makeChartsResponsive() {
  const charts = document.querySelector('.dash-charts');
  if (!charts) return;
  if (window.innerWidth < 700) charts.style.gridTemplateColumns = '1fr';
  else charts.style.gridTemplateColumns = '1fr 1fr';
}
window.addEventListener('resize', makeChartsResponsive);
makeChartsResponsive();

// ── Services CRUD ───────────────────────────────────────
async function loadServices() {
  const wrap = document.getElementById('servicesTableWrap');
  if (!wrap) return;
  const res = await apiGet('../php/admin_api.php', { action: 'get_all_services' });
  if (!res.success) { wrap.textContent = 'Failed to load services.'; return; }

  const svcs = res.services || [];
  wrap.innerHTML = '';
  if (!svcs.length) {
    wrap.style.padding = '40px'; wrap.style.textAlign = 'center'; wrap.style.color = 'var(--gray-400)';
    wrap.textContent = 'No services yet. Click "Add Service" to create one.';
    return;
  }

  const table = document.createElement('table');
  table.className = 'data-table';
  table.innerHTML = `<thead><tr>
    <th>Icon</th><th>Name</th><th>Description</th><th>Price</th><th>Unit</th><th>Status</th><th></th>
  </tr></thead>`;
  const tbody = document.createElement('tbody');

  svcs.forEach(s => {
    const tr = document.createElement('tr');
    if (!s.is_active) tr.style.opacity = '0.55';

    const tdIcon = document.createElement('td');
    tdIcon.style.fontSize = '1.3rem';
    tdIcon.textContent = s.icon || '—';

    const tdName = document.createElement('td');
    const nameStrong = document.createElement('strong');
    nameStrong.textContent = s.name;
    tdName.appendChild(nameStrong);

    const tdDesc = document.createElement('td');
    tdDesc.style.cssText = 'font-size:.82rem;color:var(--gray-500);max-width:200px;';
    tdDesc.textContent = s.description || '—';

    const tdPrice = document.createElement('td');
    const priceStrong = document.createElement('strong');
    priceStrong.style.color = 'var(--red)';
    priceStrong.textContent = 'NPR ' + parseFloat(s.price).toLocaleString();
    tdPrice.appendChild(priceStrong);

    const tdUnit = document.createElement('td');
    tdUnit.style.fontSize = '.82rem';
    tdUnit.textContent = s.unit;

    const tdStatus = document.createElement('td');
    const statusBadge = document.createElement('span');
    statusBadge.className = 'status-badge ' + (s.is_active ? 'status-delivered' : 'status-cancelled');
    statusBadge.textContent = s.is_active ? '✓ Active' : '✗ Inactive';
    tdStatus.appendChild(statusBadge);

    const tdAct = document.createElement('td');
    tdAct.style.cssText = 'display:flex;gap:6px;flex-wrap:wrap;';

    const editBtn = document.createElement('button');
    editBtn.className = 'btn btn-primary btn-sm';
    editBtn.textContent = 'Edit';
    editBtn.addEventListener('click', () => openServiceModal(s));
    tdAct.appendChild(editBtn);

    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'btn btn-outline btn-sm';
    toggleBtn.textContent = s.is_active ? 'Deactivate' : 'Activate';
    toggleBtn.addEventListener('click', () => toggleService(s.id));
    tdAct.appendChild(toggleBtn);

    tr.append(tdIcon, tdName, tdDesc, tdPrice, tdUnit, tdStatus, tdAct);
    tbody.appendChild(tr);
  });

  table.appendChild(tbody);
  wrap.appendChild(table);
}

function openServiceModal(svc) {
  const isEdit = svc && svc.id;
  document.getElementById('serviceModalTitle').textContent = isEdit ? 'Edit Service' : 'Add Service';
  document.getElementById('svcEditId').value = isEdit ? svc.id : '';
  document.getElementById('svcName').value = isEdit ? svc.name : '';
  document.getElementById('svcDesc').value = isEdit ? (svc.description || '') : '';
  document.getElementById('svcPrice').value = isEdit ? svc.price : '';
  document.getElementById('svcUnit').value = isEdit ? (svc.unit || 'per piece') : 'per piece';
  document.getElementById('svcIcon').value = isEdit ? (svc.icon || '') : '';
  document.getElementById('svcSaveBtn').textContent = isEdit ? 'Update Service' : 'Save Service';
  document.getElementById('serviceModalAlert').innerHTML = '';
  openModal('serviceModal');
}

document.getElementById('serviceForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const editId = document.getElementById('svcEditId').value;
  const btn = document.getElementById('svcSaveBtn');
  setLoading(btn, true);
  const payload = {
    action:      editId ? 'update_service' : 'add_service',
    service_id:  editId || '',
    name:        document.getElementById('svcName').value.trim(),
    description: document.getElementById('svcDesc').value.trim(),
    price:       document.getElementById('svcPrice').value,
    unit:        document.getElementById('svcUnit').value,
    icon:        document.getElementById('svcIcon').value.trim(),
  };
  const res = await apiCall('../php/admin_api.php', payload);
  setLoading(btn, false);
  if (res.success) {
    ToastManager.show(res.message || 'Service saved!', 'success');
    closeModal('serviceModal');
    loadServices();
  } else {
    showAlert('serviceModalAlert', res.error || 'Failed', 'error');
  }
});

async function toggleService(id) {
  const res = await apiCall('../php/admin_api.php', { action: 'toggle_service', service_id: id });
  if (res.success) {
    ToastManager.show(res.message || 'Toggled!', 'success');
    loadServices();
  } else {
    ToastManager.show(res.error || 'Failed', 'error');
  }
}

// ── Customers List ──────────────────────────────────────────
async function loadCustomers(search = '', page = 1) {
  const wrap = document.getElementById('customersTableWrap');
  if (!wrap) return;
  const res = await apiGet('../php/admin_api.php', { action: 'get_customers', search, page });
  if (!res.success) { wrap.textContent = 'Failed to load customers.'; return; }

  const customers = res.customers || [];
  wrap.innerHTML = '';

  if (!customers.length) {
    wrap.style.padding = '40px'; wrap.style.textAlign = 'center'; wrap.style.color = 'var(--gray-400)';
    wrap.textContent = 'No customers found.';
    return;
  }

  const table = document.createElement('table');
  table.className = 'data-table';
  table.innerHTML = `<thead><tr>
    <th>Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Orders</th><th>Total Spent</th><th></th>
  </tr></thead>`;
  const tbody = document.createElement('tbody');

  customers.forEach(c => {
    const tr = document.createElement('tr');

    const tdName = document.createElement('td');
    const nameStrong = document.createElement('strong'); nameStrong.textContent = c.full_name;
    tdName.appendChild(nameStrong);

    const tdEmail = document.createElement('td'); tdEmail.textContent = c.email;
    const tdPhone = document.createElement('td'); tdPhone.textContent = c.phone || '—';
    const tdJoined = document.createElement('td');
    tdJoined.style.cssText = 'font-size:.82rem;color:var(--gray-500);';
    tdJoined.textContent = formatDate(c.created_at);

    const tdOrders = document.createElement('td'); tdOrders.textContent = c.order_count;
    const tdSpent = document.createElement('td');
    const spentStrong = document.createElement('strong'); spentStrong.textContent = formatNPR(c.total_spent);
    tdSpent.appendChild(spentStrong);

    const tdAct = document.createElement('td');
    tdAct.style.cssText = 'display:flex;gap:6px;flex-wrap:wrap;';
    const btn = document.createElement('button');
    btn.className = 'btn btn-primary btn-sm';
    btn.textContent = 'View';
    btn.addEventListener('click', () => viewCustomer(c.id));
    tdAct.appendChild(btn);
    const delBtn = document.createElement('button');
    delBtn.className = 'btn btn-ghost btn-sm';
    delBtn.textContent = '🗑 Delete';
    delBtn.addEventListener('click', () => {
      if (confirm(`Permanently delete customer ${c.full_name}?\nAll their orders and feedback will also be removed.`)) deleteUser(c.id);
    });
    tdAct.appendChild(delBtn);

    tr.append(tdName, tdEmail, tdPhone, tdJoined, tdOrders, tdSpent, tdAct);
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  wrap.appendChild(table);
}

async function deleteUser(userId) {
  const res = await apiCall('../php/admin_api.php', { action: 'delete_user', user_id: userId });
  if (res.success) {
    ToastManager.show('Customer deleted!', 'success');
    closeModal('customerModal');
    loadCustomers(document.getElementById('customerSearchInput')?.value || '');
  } else {
    ToastManager.show(res.error || 'Failed to delete customer', 'error');
  }
}

// Customer search
let customerSearchTimer;
document.getElementById('customerSearchInput')?.addEventListener('input', () => {
  clearTimeout(customerSearchTimer);
  customerSearchTimer = setTimeout(() => {
    loadCustomers(document.getElementById('customerSearchInput').value);
  }, 380);
});

// ── Feedback Moderation ──────────────────────────────────────
async function loadFeedback() {
  const wrap = document.getElementById('feedbackWrap');
  if (!wrap) return;
  const res = await apiGet('../php/admin_api.php', { action: 'get_feedback' });
  wrap.innerHTML = '';
  if (!res.success || !(res.feedback||[]).length) {
    wrap.style.cssText = 'padding:48px;text-align:center;color:var(--gray-400);';
    wrap.textContent = 'No feedback yet.';
    return;
  }
  res.feedback.forEach(f => {
    const card = document.createElement('div');
    card.style.cssText = `border:1px solid var(--gray-300);border-radius:12px;
      padding:18px 20px;margin-bottom:12px;
      background:${f.is_approved ? '#fff' : 'var(--red-pale)'};`;

    const head = document.createElement('div');
    head.style.cssText = 'display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap;gap:6px;';
    const left = document.createElement('div');
    const strong = document.createElement('strong'); strong.textContent = f.name;
    left.appendChild(strong);
    const stars = document.createElement('span');
    stars.style.cssText = 'color:var(--gold);margin-left:8px;';
    stars.textContent = '★'.repeat(f.rating) + '☆'.repeat(5 - f.rating);
    left.appendChild(stars);
    const emailEl = document.createElement('div');
    emailEl.style.cssText = 'font-size:.78rem;color:var(--gray-400);';
    emailEl.textContent = f.email;
    left.appendChild(emailEl);
    const dateEl = document.createElement('span');
    dateEl.style.cssText = 'font-size:.75rem;color:var(--gray-400);';
    dateEl.textContent = formatDate(f.created_at);
    head.append(left, dateEl);

    const msg = document.createElement('p');
    msg.style.cssText = 'font-size:.88rem;color:var(--gray-700);line-height:1.6;';
    msg.textContent = f.message;

    const actions = document.createElement('div');
    actions.style.cssText = 'display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;';
    if (!f.is_approved) {
      const approveBtn = document.createElement('button');
      approveBtn.className = 'btn btn-primary btn-sm';
      approveBtn.textContent = '✓ Approve';
      approveBtn.addEventListener('click', () => approveFeedback(f.id));
      actions.appendChild(approveBtn);
    } else {
      const rejectBtn = document.createElement('button');
      rejectBtn.className = 'btn btn-outline btn-sm';
      rejectBtn.textContent = '✗ Unapprove';
      rejectBtn.addEventListener('click', () => rejectFeedback(f.id));
      actions.appendChild(rejectBtn);
    }
    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'btn btn-ghost btn-sm';
    deleteBtn.textContent = '🗑 Delete';
    deleteBtn.addEventListener('click', () => {
      if (confirm('Delete this feedback permanently?')) deleteFeedback(f.id);
    });
    actions.appendChild(deleteBtn);

    card.append(head, msg, actions);
    wrap.appendChild(card);
  });
}

async function approveFeedback(id) {
  const res = await apiCall('../php/admin_api.php', { action: 'approve_feedback', id });
  if (res.success) { ToastManager.show('Approved!', 'success'); loadFeedback(); }
  else ToastManager.show(res.error || 'Failed', 'error');
}

async function rejectFeedback(id) {
  const res = await apiCall('../php/admin_api.php', { action: 'reject_feedback', id });
  if (res.success) { ToastManager.show('Unapproved!', 'success'); loadFeedback(); }
  else ToastManager.show(res.error || 'Failed', 'error');
}

async function deleteFeedback(id) {
  const res = await apiCall('../php/admin_api.php', { action: 'delete_feedback', id });
  if (res.success) { ToastManager.show('Deleted!', 'success'); loadFeedback(); }
  else ToastManager.show(res.error || 'Failed', 'error');
}

// ── Init ─────────────────────────────────────────────────
const tab = '<?= $activeTab ?>';
if (tab === 'dashboard') loadDashboard();
if (tab === 'orders')    loadAdminOrders();
if (tab === 'messages')  loadMessages();
if (tab === 'services')  loadServices();
if (tab === 'customers') loadCustomers();
if (tab === 'feedback')  loadFeedback();

// Auto-open order modal from URL ?open_order=ORDER_NUMBER
const urlParams = new URLSearchParams(window.location.search);
const openOrderNum = urlParams.get('open_order');
if (openOrderNum && tab === 'orders') {
  (async () => {
    const res = await apiGet('../php/admin_api.php', { action: 'get_orders', search: openOrderNum });
    if (res.success && res.orders && res.orders.length) {
      const o = res.orders.find(x => x.order_number === openOrderNum);
      if (o) openUpdateModal(o.id, o.order_number, o.status, o.customer_email);
    }
  })();
}
</script>
</body>
</html>
