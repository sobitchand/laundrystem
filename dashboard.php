<?php
require_once __DIR__ . '/php/config.php';
sendSecurityHeaders();
requireLogin();
$activeTab   = in_array($_GET['tab'] ?? '', ['overview','order','orders','feedback','profile','password'])
               ? $_GET['tab'] : 'overview';
$userName    = htmlspecialchars($_SESSION['user_name']  ?? 'User', ENT_QUOTES, 'UTF-8');
$userEmail   = htmlspecialchars($_SESSION['user_email'] ?? '',      ENT_QUOTES, 'UTF-8');
$firstName   = htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0], ENT_QUOTES, 'UTF-8');
$userInitial = strtoupper(substr((string)($_SESSION['user_name'] ?? 'U'), 0, 1));
$csrf        = generateCSRFToken();
$pageTitles  = ['overview'=>'Overview','order'=>'New Order','orders'=>'My Orders','feedback'=>'Feedback','profile'=>'My Profile','password'=>'Change Password'];
$pageTitle   = $pageTitles[$activeTab];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= $pageTitle ?> &mdash; DD Laundry Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <style>
    /* ── Star rating ─────────────────────────────────────── */
    .star-rating { display:flex;gap:4px; }
    .star-btn {
      background:none;border:none;font-size:2rem;cursor:pointer;
      color:var(--gray-300);transition:color .15s,transform .1s;
      padding:0;line-height:1;
    }
    .star-btn:hover,.star-btn.active { color:var(--gold);transform:scale(1.15); }
    .star-btn.active { color:var(--gold); }
    /* ── Order progress ─────────────────────────────────── */
    .prog-wrap { display: flex; align-items: flex-start; overflow-x: auto; padding: 8px 0 4px; gap: 0; }
    .prog-step { display: flex; flex-direction: column; align-items: center; gap: 6px; flex-shrink: 0; }
    .prog-dot {
      width: 32px; height: 32px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .78rem; font-weight: 700; color: #fff;
      background: var(--gray-300); transition: var(--transition);
    }
    .prog-dot.done   { background: #27AE60; }
    .prog-dot.active { background: var(--red); box-shadow: 0 0 0 4px rgba(192,57,43,.18); }
    .prog-line  { width: 44px; height: 2px; background: var(--gray-300); flex-shrink: 0; margin-top: 15px; transition: background .3s; }
    .prog-line.done { background: #27AE60; }
    .prog-label { font-size: .66rem; font-weight: 600; color: var(--gray-400); text-align: center; max-width: 60px; white-space: nowrap; }
    .prog-label.active { color: var(--red); }
    .prog-label.done   { color: #27AE60; }

    /* ── Sidebar mobile ──────────────────────────────────── */
    @media (max-width: 768px) {
      .dashboard-layout  { grid-template-columns: 1fr; }
      .sidebar           { position: fixed; left: -270px; top: 0; bottom: 0; z-index: 600; transition: left .3s ease; width: 260px; }
      .sidebar.open      { left: 0; box-shadow: 4px 0 32px rgba(0,0,0,.3); }
      .sidebar-overlay   { display: none; }
      .mob-menu-btn      { display: flex !important; }
      .dashboard-content { padding: 16px; }
    }
    .mob-menu-btn { display: none; background: none; border: none; font-size: 1.4rem; cursor: pointer; padding: 4px 6px; }
    .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 599; }
    .sidebar-overlay.show { display: block; }
  </style>
</head>
<body>
<div class="dashboard-layout">

  <!-- ── Sidebar ──────────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar" aria-label="Dashboard navigation">
    <div class="sidebar-logo">&#x1F9BA; DD<span>Laundry</span></div>
    <nav class="sidebar-nav" aria-label="Dashboard menu">
      <div class="sidebar-section-label">Main</div>
      <a href="?tab=overview" class="sidebar-link <?= $activeTab==='overview'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F3E0;</span> Overview
      </a>
      <a href="?tab=order"    class="sidebar-link <?= $activeTab==='order'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x2795;</span> New Order
      </a>
      <a href="?tab=orders"   class="sidebar-link <?= $activeTab==='orders'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F4CB;</span> My Orders
      </a>
      <a href="?tab=feedback" class="sidebar-link <?= $activeTab==='feedback'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x2B50;</span> Feedback
      </a>
      <div class="sidebar-section-label">Account</div>
      <a href="?tab=profile"  class="sidebar-link <?= $activeTab==='profile'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F464;</span> Profile
      </a>
      <a href="?tab=password" class="sidebar-link <?= $activeTab==='password'?'active':'' ?>">
        <span class="sidebar-icon" aria-hidden="true">&#x1F512;</span> Change Password
      </a>
      <a href="index.php"     class="sidebar-link">
        <span class="sidebar-icon" aria-hidden="true">&#x1F3E1;</span> Go to Website
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar" aria-hidden="true"><?= $userInitial ?></div>
        <div class="sidebar-user-info">
          <div class="sidebar-user-name"><?= $userName ?></div>
          <div class="sidebar-user-role">Customer</div>
        </div>
      </div>
      <a href="#" id="logoutBtn" class="sidebar-link" style="color:rgba(255,100,80,.75);margin-top:8px;">
        <span class="sidebar-icon" aria-hidden="true">&#x1F6AA;</span> Logout
      </a>
    </div>
  </aside>
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- ── Main ─────────────────────────────────────────────── -->
  <div class="dashboard-main">

    <!-- Topbar -->
    <header class="dashboard-topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <button class="mob-menu-btn" id="mobMenuBtn" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar">
          &#x2630;
        </button>
        <h1 class="dashboard-title"><?= $pageTitle ?></h1>
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <a href="?tab=order" class="btn btn-primary btn-sm">&#x2795; New Order</a>
        <div class="sidebar-avatar" style="cursor:pointer;" title="<?= $userName ?>"
             onclick="window.location='?tab=profile'" aria-label="Go to profile">
          <?= $userInitial ?>
        </div>
      </div>
    </header>

    <!-- Content -->
    <div class="dashboard-content">

      <!-- ════════ OVERVIEW ════════ -->
      <?php if ($activeTab === 'overview'): ?>
      <p style="color:var(--gray-500);margin-bottom:24px;">
        Welcome back, <strong><?= $firstName ?></strong>! Here&rsquo;s your laundry summary.
      </p>
      <div class="stats-grid" id="statsGrid">
        <?php foreach (['Total Orders','In Progress','Delivered','Total Spent'] as $label): ?>
        <div class="stat-card">
          <div class="stat-card-icon" aria-hidden="true">&#x23F3;</div>
          <div class="stat-card-value">&#x2014;</div>
          <div class="stat-card-label"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="data-table-wrapper mt-3">
        <div class="data-table-header">
          <h2 style="font-size:1rem;font-weight:600;">Recent Orders</h2>
          <a href="?tab=orders" class="btn btn-outline btn-sm">View All &rarr;</a>
        </div>
        <div id="recentOrdersWrap" style="padding:32px;text-align:center;color:var(--gray-400);">
          Loading&hellip;
        </div>
      </div>

      <!-- ════════ NEW ORDER ════════ -->
      <?php elseif ($activeTab === 'order'): ?>
      <div style="max-width:760px;">
        <div id="orderPageAlert" role="alert" aria-live="polite"></div>

        <!-- Cloth items selector -->
        <div class="card" style="margin-bottom:20px;">
          <div class="card-body">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.2rem;margin-bottom:6px;">
              What are we washing?
            </h2>
            <p style="color:var(--gray-500);font-size:.86rem;margin-bottom:20px;">
              Add each clothing item with its quantity. You can mix different service types.
            </p>
            <div id="lineItemsWrap">
              <div class="line-item-row" id="lineItem_0">
                <div class="line-item-fields">
                  <select class="form-control svc-type-sel" data-idx="0" aria-label="Service type">
                    <option value="">Select service type...</option>
                  </select>
                  <select class="form-control cloth-sel" data-idx="0" aria-label="Cloth type" disabled>
                    <option value="">Select item...</option>
                  </select>
                  <div class="qty-ctrl">
                    <button type="button" class="qty-btn qty-minus" data-idx="0" aria-label="Decrease quantity">−</button>
                    <span class="qty-val" id="qty_0" aria-live="polite">0</span>
                    <button type="button" class="qty-btn qty-plus" data-idx="0" aria-label="Increase quantity">+</button>
                  </div>
                  <span class="line-item-price" id="price_0">—</span>
                  <button type="button" class="btn btn-ghost btn-sm line-item-remove" data-idx="0" aria-label="Remove item" style="visibility:hidden;">&times;</button>
                </div>
              </div>
            </div>
            <button type="button" class="btn btn-outline btn-sm mt-2" id="addLineItemBtn">&#x2795; Add Another Item</button>
            <div style="border-top:2px solid var(--gray-100);margin-top:16px;padding-top:16px;
                        display:flex;justify-content:space-between;align-items:center;">
              <span style="font-weight:600;color:var(--gray-700);">Order Total</span>
              <span id="orderTotal" style="font-family:'Playfair Display',serif;font-size:1.7rem;font-weight:700;color:var(--red);">
                NPR&nbsp;0
              </span>
            </div>
          </div>
        </div>

        <!-- Pickup details -->
        <div class="card">
          <div class="card-body">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.2rem;margin-bottom:20px;">
              Pickup &amp; Delivery Details
            </h2>
            <form id="orderForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action"     value="place">
              <input type="hidden" id="pickupLat" name="pickup_lat">
              <input type="hidden" id="pickupLng" name="pickup_lng">
              <input type="hidden" id="deliveryLat" name="delivery_lat">
              <input type="hidden" id="deliveryLng" name="delivery_lng">

              <!-- Pickup Location -->
              <div class="form-group">
                <label class="form-label" for="pickupAddr">Pickup Address <span class="req">*</span></label>
                <textarea class="form-control" id="pickupAddr" name="pickup_address"
                          rows="2" placeholder="Enter your pickup address or click on the map"
                          maxlength="500" required></textarea>
                <button type="button" class="map-toggle-btn" id="togglePickupMap">
                  &#x1F4CD; Pick Location on Map
                </button>
                <div id="pickupMapContainer" style="display:none;">
                  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                    <div class="map-picker-search" style="flex:1;min-width:200px;">
                      <span class="map-picker-search-icon">&#x1F50D;</span>
                      <input type="text" id="pickupSearch" placeholder="Search address..." autocomplete="off">
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" id="pickupCurrentLoc">
                      &#x1F9ED; Use My Location
                    </button>
                  </div>
                  <div class="map-picker-wrap">
                    <div id="pickupMap" class="map-picker"></div>
                    <div class="map-picker-hint">&#x1F4CD; Click on the map to drop a pin, or search above</div>
                  </div>
                </div>
              </div>

              <!-- Delivery Location -->
              <div class="form-group">
                <label class="form-label" for="deliveryAddr">Delivery Address</label>
                <div style="margin-bottom:8px;">
                  <label style="display:inline-flex;align-items:center;gap:6px;font-size:.85rem;cursor:pointer;color:var(--gray-700);">
                    <input type="checkbox" id="sameAsPickup" checked style="accent-color:var(--red);">
                    Same as pickup address
                  </label>
                </div>
                <div id="deliveryFields" style="display:none;">
                  <textarea class="form-control" id="deliveryAddr" name="delivery_address"
                            rows="2" placeholder="Enter delivery address or click on the map"
                            maxlength="500"></textarea>
                  <button type="button" class="map-toggle-btn" id="toggleDeliveryMap">
                    &#x1F4CD; Pick Delivery Location on Map
                  </button>
                  <div id="deliveryMapContainer" style="display:none;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                      <div class="map-picker-search" style="flex:1;min-width:200px;">
                        <span class="map-picker-search-icon">&#x1F50D;</span>
                        <input type="text" id="deliverySearch" placeholder="Search address..." autocomplete="off">
                      </div>
                      <button type="button" class="btn btn-outline btn-sm" id="deliveryCurrentLoc">
                        &#x1F9ED; Use My Location
                      </button>
                    </div>
                    <div class="map-picker-wrap">
                      <div id="deliveryMap" class="map-picker"></div>
                      <div class="map-picker-hint">&#x1F4CD; Click on the map to drop a pin, or search above</div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-row">
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label" for="pickupDate">Preferred Pickup Date <span class="req">*</span></label>
                  <input type="date" class="form-control" id="pickupDate" name="pickup_date"
                         min="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                  <label class="form-label" for="payMethod">Payment Method <span class="req">*</span></label>
                  <select class="form-control" id="payMethod" name="payment_method" required>
                    <option value="cash">Cash on Delivery</option>
                    <option value="online">Online Payment</option>
                  </select>
                </div>
              </div>
              <div class="form-group mt-2">
                <label class="form-label" for="orderNotes">Special Instructions</label>
                <textarea class="form-control" id="orderNotes" name="notes"
                          rows="2" placeholder="Any special care instructions?"
                          maxlength="1000"></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-full btn-lg mt-2" id="placeOrderBtn">
                &#x1F9BA; Place Order
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- ════════ MY ORDERS ════════ -->
      <?php elseif ($activeTab === 'orders'): ?>
      <div class="data-table-wrapper">
        <div class="data-table-header">
          <h2 style="font-size:1rem;font-weight:600;">Order History</h2>
          <a href="?tab=order" class="btn btn-primary btn-sm">&#x2795; New Order</a>
        </div>
        <div id="ordersWrap" style="padding:32px;text-align:center;color:var(--gray-400);">
          Loading&hellip;
        </div>
      </div>

      <!-- ════════ FEEDBACK ════════ -->
      <?php elseif ($activeTab === 'feedback'): ?>
      <div style="max-width:560px;">
        <div class="card">
          <div class="card-body">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.3rem;margin-bottom:6px;">
              Share Your Experience
            </h2>
            <p style="color:var(--gray-500);font-size:.86rem;margin-bottom:20px;">
              Your feedback helps us improve. Approved feedback may appear on our website.
            </p>
            <div id="feedbackAlert" role="alert" aria-live="polite"></div>
            <form id="feedbackForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action"     value="submit_feedback">
              <div class="form-group">
                <label class="form-label" for="fbRating">Rating <span class="req">*</span></label>
                <div class="star-rating" id="starRating" role="radiogroup" aria-label="Select rating">
                  <?php for ($i = 5; $i >= 1; $i--): ?>
                  <button type="button" class="star-btn" data-rating="<?= $i ?>"
                          aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"
                          aria-pressed="false">&#x2605;</button>
                  <?php endfor; ?>
                </div>
                <input type="hidden" id="fbRatingVal" name="rating" value="">
              </div>
              <div class="form-group">
                <label class="form-label" for="fbMessage">Your Feedback <span class="req">*</span></label>
                <textarea class="form-control" id="fbMessage" name="message"
                          rows="4" placeholder="Tell us about your experience with DD Laundry..."
                          maxlength="1000" required></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-full" id="submitFeedbackBtn">
                Submit Feedback
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- ════════ PROFILE ════════ -->
      <?php elseif ($activeTab === 'profile'): ?>
      <div style="max-width:520px;">
        <div class="card">
          <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;
                        margin-bottom:24px;padding-bottom:20px;border-bottom:1px solid var(--gray-100);">
              <div class="sidebar-avatar" style="width:60px;height:60px;font-size:1.5rem;" aria-hidden="true">
                <?= $userInitial ?>
              </div>
              <div>
                <div style="font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:600;">
                  <?= $userName ?>
                </div>
                <div style="color:var(--gray-500);font-size:.85rem;"><?= $userEmail ?></div>
              </div>
            </div>
            <div id="profileAlert" role="alert" aria-live="polite"></div>
            <form id="profileForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action"     value="update">
              <div class="form-group">
                <label class="form-label" for="pName">Full Name <span class="req">*</span></label>
                <input type="text" class="form-control" id="pName" name="full_name"
                       placeholder="Your full name" maxlength="100" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="pPhone">Phone Number <span class="req">*</span></label>
                <input type="tel" class="form-control" id="pPhone" name="phone"
                       placeholder="98XXXXXXXX" maxlength="20" required>
              </div>
              <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" value="<?= $userEmail ?>"
                       disabled style="opacity:.6;cursor:not-allowed;" aria-readonly="true">
                <div class="form-hint">Email address cannot be changed.</div>
              </div>
              <div class="form-group">
                <label class="form-label" for="pAddr">Address</label>
                <textarea class="form-control" id="pAddr" name="address"
                          rows="2" placeholder="Your address" maxlength="500"></textarea>
              </div>
              <button type="submit" class="btn btn-primary btn-full" id="saveProfileBtn">
                Save Changes
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- ════════ CHANGE PASSWORD ════════ -->
      <?php elseif ($activeTab === 'password'): ?>
      <div style="max-width:460px;">
        <div class="card">
          <div class="card-body">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.3rem;margin-bottom:20px;">
              Change Password
            </h2>
            <div id="passAlert" role="alert" aria-live="polite"></div>
            <form id="passForm" novalidate>
              <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
              <input type="hidden" name="action"     value="change_password">
              <div class="form-group">
                <label class="form-label" for="currPass">Current Password</label>
                <div class="input-group">
                  <span class="input-icon" aria-hidden="true">&#x1F512;</span>
                  <input type="password" class="form-control" id="currPass" name="current_password"
                         placeholder="Your current password" maxlength="128" required>
                  <button type="button" class="input-toggle" data-target="currPass"
                          aria-label="Show password">&#x1F441;&#xFE0F;</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="newPass">New Password</label>
                <div class="input-group">
                  <span class="input-icon" aria-hidden="true">&#x1F511;</span>
                  <input type="password" class="form-control" id="newPass" name="new_password"
                         placeholder="Min. 8 characters" minlength="8" maxlength="128" required>
                  <button type="button" class="input-toggle" data-target="newPass"
                          aria-label="Show password">&#x1F441;&#xFE0F;</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label" for="confPass">Confirm New Password</label>
                <div class="input-group">
                  <span class="input-icon" aria-hidden="true">&#x1F511;</span>
                  <input type="password" class="form-control" id="confPass" name="confirm_password"
                         placeholder="Repeat new password" maxlength="128" required>
                </div>
              </div>
              <button type="submit" class="btn btn-primary btn-full" id="chPassBtn">
                Update Password
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /dashboard-content -->
  </div><!-- /dashboard-main -->
</div><!-- /dashboard-layout -->

<!-- ── Order Detail Modal ──────────────────────────────────── -->
<div class="modal-overlay" id="orderModal" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
  <div class="modal" style="max-width:640px;">
    <div class="modal-header">
      <h2 class="modal-title" id="orderModalTitle" style="font-family:'Playfair Display',serif;font-size:1.2rem;">
        Order Details
      </h2>
      <button class="modal-close" aria-label="Close modal">&#x00D7;</button>
    </div>
    <div id="orderModalBody" style="max-height:72vh;overflow-y:auto;padding-right:4px;">
      <div style="padding:32px;text-align:center;color:var(--gray-400);">Loading&hellip;</div>
    </div>
  </div>
</div>

<script src="js/main.js"></script>
<script>
// ── Mobile sidebar ────────────────────────────────────────
const sidebar  = document.getElementById('sidebar');
const overlay  = document.getElementById('sidebarOverlay');
const menuBtn  = document.getElementById('mobMenuBtn');

function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('show'); menuBtn.setAttribute('aria-expanded','true'); }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('show'); menuBtn.setAttribute('aria-expanded','false'); }

menuBtn?.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
overlay?.addEventListener('click', closeSidebar);

// ── Logout ────────────────────────────────────────────────
document.getElementById('logoutBtn').addEventListener('click', async e => {
  e.preventDefault();
  const res = await apiCall('./php/auth.php', { action: 'logout' });
  window.location.href = res.redirect || 'login.php';
});

// ── Helpers ───────────────────────────────────────────────
function makeStatusBadge(status) {
  const cfg = STATUS_CONFIG[status] || { label: status, icon: '?' };
  const span = document.createElement('span');
  span.className = `status-badge status-${escHtml(status)}`;
  span.textContent = `${cfg.icon} ${cfg.label}`;
  return span;
}

function buildOrderTable(orders, containerId) {
  const wrap = document.getElementById(containerId);
  if (!wrap) return;
  if (!orders.length) {
    wrap.innerHTML = '';
    const empty = document.createElement('div');
    empty.style.cssText = 'padding:48px;text-align:center;color:var(--gray-400);';
    empty.innerHTML = '<div style="font-size:3rem;margin-bottom:12px;">&#x1F4ED;</div>';
    const h = document.createElement('p');
    h.style.cssText = 'font-weight:600;margin-bottom:16px;';
    h.textContent = 'No orders yet';
    const a = document.createElement('a');
    a.href = '?tab=order'; a.className = 'btn btn-primary'; a.textContent = 'Place Your First Order →';
    empty.appendChild(h); empty.appendChild(a);
    wrap.appendChild(empty);
    return;
  }
  const table = document.createElement('table');
  table.className = 'data-table';
  table.innerHTML = `<thead><tr>
    <th>Order #</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th></th>
  </tr></thead>`;
  const tbody = document.createElement('tbody');
  orders.forEach(o => {
    const tr = document.createElement('tr');
    const num = document.createElement('td');
    num.innerHTML = `<strong style="color:var(--red);font-size:.82rem;">${escHtml(o.order_number)}</strong>`;
    const date = document.createElement('td'); date.textContent = formatDate(o.created_at);
    const items= document.createElement('td'); items.textContent = o.item_count + ' piece(s)';
    const tot  = document.createElement('td');
    tot.innerHTML = `<strong>${escHtml(formatNPR(o.total_amount))}</strong>`;
    const st   = document.createElement('td'); st.appendChild(makeStatusBadge(o.status));
    const act  = document.createElement('td');
    const btn  = document.createElement('button');
    btn.className = 'btn btn-outline btn-sm';
    btn.textContent = 'View →';
    btn.addEventListener('click', () => viewOrderDetail(o.id));
    act.appendChild(btn);
    tr.append(num, date, items, tot, st, act);
    tbody.appendChild(tr);
  });
  table.appendChild(tbody);
  wrap.innerHTML = '';
  wrap.appendChild(table);
}

// ── LEAFLET MAP PICKER ──────────────────────────────────
const DEFAULT_LAT = 27.6535;
const DEFAULT_LNG = 85.3318;
let pickupMap, pickupMarker, deliveryMap, deliveryMarker;

function initMapPicker(mapId, searchId, latInputId, lngInputId, addrInputId) {
  const map = L.map(mapId, { zoomControl: true }).setView([DEFAULT_LAT, DEFAULT_LNG], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map);

  let marker = null;

  map.on('click', function(e) {
    const { lat, lng } = e.latlng;
    if (marker) marker.setLatLng([lat, lng]);
    else marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    document.getElementById(latInputId).value = lat.toFixed(8);
    document.getElementById(lngInputId).value = lng.toFixed(8);

    marker.on('dragend', function() {
      const pos = marker.getLatLng();
      document.getElementById(latInputId).value = pos.lat.toFixed(8);
      document.getElementById(lngInputId).value = pos.lng.toFixed(8);
      reverseGeocode(pos.lat, pos.lng, addrInputId);
    });

    reverseGeocode(lat, lng, addrInputId);
  });

  // Search with Nominatim
  const searchInput = document.getElementById(searchId);
  if (searchInput) {
    let searchTimer;
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimer);
      const query = this.value.trim();
      if (query.length < 3) return;
      searchTimer = setTimeout(() => {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=np`)
          .then(r => r.json())
          .then(results => {
            if (results.length > 0) {
              const r = results[0];
              const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
              map.setView([lat, lng], 16);
              if (marker) marker.setLatLng([lat, lng]);
              else marker = L.marker([lat, lng], { draggable: true }).addTo(map);
              document.getElementById(latInputId).value = lat.toFixed(8);
              document.getElementById(lngInputId).value = lng.toFixed(8);
              document.getElementById(addrInputId).value = r.display_name;
              marker.on('dragend', function() {
                const pos = marker.getLatLng();
                document.getElementById(latInputId).value = pos.lat.toFixed(8);
                document.getElementById(lngInputId).value = pos.lng.toFixed(8);
                reverseGeocode(pos.lat, pos.lng, addrInputId);
              });
            }
          }).catch(() => {});
      }, 500);
    });
  }

  return map;
}

function reverseGeocode(lat, lng, addrInputId) {
  fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
    .then(r => r.json())
    .then(data => {
      if (data.display_name) {
        document.getElementById(addrInputId).value = data.display_name;
      }
    }).catch(() => {});
}

// Use current location for pickup
document.getElementById('pickupCurrentLoc')?.addEventListener('click', function() {
  if (!navigator.geolocation) {
    showAlert('orderPageAlert', 'Geolocation is not supported by your browser', 'error');
    return;
  }
  const btn = this;
  setLoading(btn, true);
  navigator.geolocation.getCurrentPosition(
    pos => {
      setLoading(btn, false);
      const { latitude, longitude } = pos.coords;
      // Open map if not already open
      const container = document.getElementById('pickupMapContainer');
      const toggle = document.getElementById('togglePickupMap');
      if (container.style.display === 'none') toggle.click();
      setTimeout(() => {
        if (!pickupMap) return;
        pickupMap.setView([latitude, longitude], 16);
        if (pickupMarker) pickupMarker.setLatLng([latitude, longitude]);
        else pickupMarker = L.marker([latitude, longitude], { draggable: true }).addTo(pickupMap);
        document.getElementById('pickupLat').value = latitude.toFixed(8);
        document.getElementById('pickupLng').value = longitude.toFixed(8);
        pickupMarker.on('dragend', function() {
          const p = pickupMarker.getLatLng();
          document.getElementById('pickupLat').value = p.lat.toFixed(8);
          document.getElementById('pickupLng').value = p.lng.toFixed(8);
          reverseGeocode(p.lat, p.lng, 'pickupAddr');
        });
        reverseGeocode(latitude, longitude, 'pickupAddr');
      }, 300);
    },
    err => {
      setLoading(btn, false);
      showAlert('orderPageAlert', 'Unable to retrieve location: ' + (err.message || 'unknown error'), 'error');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
});

// Toggle pickup map
document.getElementById('togglePickupMap')?.addEventListener('click', function() {
  const container = document.getElementById('pickupMapContainer');
  const isHidden = container.style.display === 'none';
  container.style.display = isHidden ? 'block' : 'none';
  this.innerHTML = isHidden ? '&#x2716; Hide Map' : '&#x1F4CD; Pick Location on Map';
  if (isHidden && !pickupMap) {
    setTimeout(() => {
      pickupMap = initMapPicker('pickupMap', 'pickupSearch', 'pickupLat', 'pickupLng', 'pickupAddr');
      pickupMap.invalidateSize();
    }, 100);
  } else if (pickupMap) {
    setTimeout(() => pickupMap.invalidateSize(), 100);
  }
});

// Use current location for delivery
document.getElementById('deliveryCurrentLoc')?.addEventListener('click', function() {
  if (!navigator.geolocation) {
    showAlert('orderPageAlert', 'Geolocation is not supported by your browser', 'error');
    return;
  }
  const btn = this;
  setLoading(btn, true);
  navigator.geolocation.getCurrentPosition(
    pos => {
      setLoading(btn, false);
      const { latitude, longitude } = pos.coords;
      const container = document.getElementById('deliveryMapContainer');
      const toggle = document.getElementById('toggleDeliveryMap');
      if (container.style.display === 'none') toggle.click();
      setTimeout(() => {
        if (!deliveryMap) return;
        deliveryMap.setView([latitude, longitude], 16);
        if (deliveryMarker) deliveryMarker.setLatLng([latitude, longitude]);
        else deliveryMarker = L.marker([latitude, longitude], { draggable: true }).addTo(deliveryMap);
        document.getElementById('deliveryLat').value = latitude.toFixed(8);
        document.getElementById('deliveryLng').value = longitude.toFixed(8);
        deliveryMarker.on('dragend', function() {
          const p = deliveryMarker.getLatLng();
          document.getElementById('deliveryLat').value = p.lat.toFixed(8);
          document.getElementById('deliveryLng').value = p.lng.toFixed(8);
          reverseGeocode(p.lat, p.lng, 'deliveryAddr');
        });
        reverseGeocode(latitude, longitude, 'deliveryAddr');
      }, 300);
    },
    err => {
      setLoading(btn, false);
      showAlert('orderPageAlert', 'Unable to retrieve location: ' + (err.message || 'unknown error'), 'error');
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
});

// Toggle delivery map
document.getElementById('toggleDeliveryMap')?.addEventListener('click', function() {
  const container = document.getElementById('deliveryMapContainer');
  const isHidden = container.style.display === 'none';
  container.style.display = isHidden ? 'block' : 'none';
  this.innerHTML = isHidden ? '&#x2716; Hide Map' : '&#x1F4CD; Pick Delivery Location on Map';
  if (isHidden && !deliveryMap) {
    setTimeout(() => {
      deliveryMap = initMapPicker('deliveryMap', 'deliverySearch', 'deliveryLat', 'deliveryLng', 'deliveryAddr');
      deliveryMap.invalidateSize();
    }, 100);
  } else if (deliveryMap) {
    setTimeout(() => deliveryMap.invalidateSize(), 100);
  }
});

// Same as pickup checkbox
document.getElementById('sameAsPickup')?.addEventListener('change', function() {
  const df = document.getElementById('deliveryFields');
  df.style.display = this.checked ? 'none' : 'block';
  if (this.checked) {
    document.getElementById('deliveryLat').value = '';
    document.getElementById('deliveryLng').value = '';
    document.getElementById('deliveryAddr').value = '';
  }
});

// ── OVERVIEW ─────────────────────────────────────────────
async function loadOverview() {
  const res = await apiGet('./php/orders.php', { action: 'get_orders', page: 1 });
  if (!res.success) return;
  const orders = res.orders || [];
  let inProg = 0, delivered = 0, spent = 0;
  orders.forEach(o => {
    if (['pending','confirmed','picked_up','in_process','ready'].includes(o.status)) inProg++;
    if (o.status === 'delivered') delivered++;
    if (o.status !== 'cancelled') spent += parseFloat(o.total_amount);
  });
  const icons  = ['&#x1F4CB;','&#x23F3;','&#x2705;','&#x1F4B0;'];
  const values = [res.total, inProg, delivered, formatNPR(spent)];
  document.querySelectorAll('#statsGrid .stat-card').forEach((card, i) => {
    card.querySelector('.stat-card-icon').innerHTML = icons[i];
    const v = card.querySelector('.stat-card-value');
    v.textContent = values[i];
    if (i < 3) v.style.fontSize = '2rem';
  });
  buildOrderTable(orders.slice(0, 5), 'recentOrdersWrap');
}

// ── CLOTH TYPES & LINE ITEMS ─────────────────────────────
let clothTypes = {};  // { service_type: [{id,name,unit_price},...] }
let lineItemCounter = 0;

async function loadClothTypes() {
  const res = await apiGet('./php/orders.php', { action: 'get_cloth_types' });
  if (!res.success) return;
  clothTypes = res.cloth_types || {};
  populateServiceTypeDropdowns();
}

function populateServiceTypeDropdowns() {
  document.querySelectorAll('.svc-type-sel').forEach(sel => {
    if (sel.options.length > 1) return; // already populated
    Object.keys(clothTypes).forEach(st => {
      const opt = document.createElement('option');
      opt.value = st; opt.textContent = st;
      sel.appendChild(opt);
    });
  });
}

function getNextLineIdx() {
  return ++lineItemCounter;
}

function addLineItem() {
  const idx = getNextLineIdx();
  const wrap = document.getElementById('lineItemsWrap');
  const row = document.createElement('div');
  row.className = 'line-item-row';
  row.id = 'lineItem_' + idx;

  const fields = document.createElement('div');
  fields.className = 'line-item-fields';

  const svcSel = document.createElement('select');
  svcSel.className = 'form-control svc-type-sel';
  svcSel.dataset.idx = idx;
  svcSel.setAttribute('aria-label', 'Service type');
  const defaultOpt = document.createElement('option');
  defaultOpt.value = ''; defaultOpt.textContent = 'Select service type...';
  svcSel.appendChild(defaultOpt);
  Object.keys(clothTypes).forEach(st => {
    const opt = document.createElement('option');
    opt.value = st; opt.textContent = st;
    svcSel.appendChild(opt);
  });

  const clothSel = document.createElement('select');
  clothSel.className = 'form-control cloth-sel';
  clothSel.dataset.idx = idx;
  clothSel.setAttribute('aria-label', 'Cloth type');
  clothSel.disabled = true;
  const clothDefault = document.createElement('option');
  clothDefault.value = ''; clothDefault.textContent = 'Select item...';
  clothSel.appendChild(clothDefault);

  const qtyCtrl = document.createElement('div');
  qtyCtrl.className = 'qty-ctrl';
  const minus = document.createElement('button');
  minus.type = 'button'; minus.className = 'qty-btn qty-minus'; minus.dataset.idx = idx; minus.textContent = '−';
  minus.setAttribute('aria-label', 'Decrease quantity');
  const qtyVal = document.createElement('span');
  qtyVal.className = 'qty-val'; qtyVal.id = 'qty_' + idx; qtyVal.textContent = '0';
  qtyVal.setAttribute('aria-live', 'polite');
  const plus = document.createElement('button');
  plus.type = 'button'; plus.className = 'qty-btn qty-plus'; plus.dataset.idx = idx; plus.textContent = '+';
  plus.setAttribute('aria-label', 'Increase quantity');
  qtyCtrl.append(minus, qtyVal, plus);

  const priceSpan = document.createElement('span');
  priceSpan.className = 'line-item-price'; priceSpan.id = 'price_' + idx; priceSpan.textContent = '—';

  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.className = 'btn btn-ghost btn-sm line-item-remove';
  removeBtn.dataset.idx = idx;
  removeBtn.innerHTML = '&times;';
  removeBtn.setAttribute('aria-label', 'Remove item');

  fields.append(svcSel, clothSel, qtyCtrl, priceSpan, removeBtn);
  row.appendChild(fields);
  wrap.appendChild(row);

  bindLineItemEvents(idx);
  updateRemoveButtons();
}

function bindLineItemEvents(idx) {
  const svcSel = document.querySelector(`.svc-type-sel[data-idx="${idx}"]`);
  const clothSel = document.querySelector(`.cloth-sel[data-idx="${idx}"]`);
  const minus = document.querySelector(`.qty-minus[data-idx="${idx}"]`);
  const plus = document.querySelector(`.qty-plus[data-idx="${idx}"]`);
  const removeBtn = document.querySelector(`.line-item-remove[data-idx="${idx}"]`);

  svcSel?.addEventListener('change', () => onServiceTypeChange(idx));
  clothSel?.addEventListener('change', () => updateLinePrice(idx));
  minus?.addEventListener('click', () => changeQty(idx, -1));
  plus?.addEventListener('click', () => changeQty(idx, 1));
  removeBtn?.addEventListener('click', () => removeLineItem(idx));
}

function onServiceTypeChange(idx) {
  const svcSel = document.querySelector(`.svc-type-sel[data-idx="${idx}"]`);
  const clothSel = document.querySelector(`.cloth-sel[data-idx="${idx}"]`);
  const st = svcSel.value;
  clothSel.innerHTML = '';
  const defaultOpt = document.createElement('option');
  defaultOpt.value = ''; defaultOpt.textContent = 'Select item...';
  clothSel.appendChild(defaultOpt);

  if (st && clothTypes[st]) {
    clothSel.disabled = false;
    clothTypes[st].forEach(ct => {
      const opt = document.createElement('option');
      opt.value = ct.id;
      opt.textContent = `${ct.name} — ${formatNPR(ct.unit_price)}`;
      opt.dataset.price = ct.unit_price;
      clothSel.appendChild(opt);
    });
  } else {
    clothSel.disabled = true;
  }
  // Reset qty when type changes
  document.getElementById('qty_' + idx).textContent = '0';
  updateLinePrice(idx);
}

function changeQty(idx, delta) {
  const qtyEl = document.getElementById('qty_' + idx);
  let qty = Math.max(0, (parseInt(qtyEl.textContent) || 0) + delta);
  qtyEl.textContent = qty;
  updateLinePrice(idx);
}

function updateLinePrice(idx) {
  const clothSel = document.querySelector(`.cloth-sel[data-idx="${idx}"]`);
  const qty = parseInt(document.getElementById('qty_' + idx).textContent) || 0;
  const priceSpan = document.getElementById('price_' + idx);
  const selected = clothSel.options[clothSel.selectedIndex];
  const price = parseFloat(selected?.dataset?.price || 0);
  if (qty > 0 && price > 0) {
    priceSpan.textContent = formatNPR(qty * price);
  } else {
    priceSpan.textContent = '—';
  }
  updateTotal();
}

function updateTotal() {
  let total = 0;
  document.querySelectorAll('.line-item-row').forEach(row => {
    const idx = row.id.replace('lineItem_', '');
    const clothSel = document.querySelector(`.cloth-sel[data-idx="${idx}"]`);
    const qty = parseInt(document.getElementById('qty_' + idx)?.textContent) || 0;
    const price = parseFloat(clothSel?.options[clothSel.selectedIndex]?.dataset?.price || 0);
    if (qty > 0 && price > 0) total += qty * price;
  });
  const el = document.getElementById('orderTotal');
  if (el) el.textContent = formatNPR(total);
}

function removeLineItem(idx) {
  const rows = document.querySelectorAll('.line-item-row');
  if (rows.length <= 1) return; // keep at least one
  document.getElementById('lineItem_' + idx)?.remove();
  updateTotal();
  updateRemoveButtons();
}

function updateRemoveButtons() {
  const rows = document.querySelectorAll('.line-item-row');
  const show = rows.length > 1;
  document.querySelectorAll('.line-item-remove').forEach(btn => {
    btn.style.visibility = show ? 'visible' : 'hidden';
  });
}

document.getElementById('addLineItemBtn')?.addEventListener('click', addLineItem);

function getLineItems() {
  const items = [];
  document.querySelectorAll('.line-item-row').forEach(row => {
    const idx = row.id.replace('lineItem_', '');
    const clothSel = document.querySelector(`.cloth-sel[data-idx="${idx}"]`);
    const clothId = parseInt(clothSel?.value) || 0;
    const qty = parseInt(document.getElementById('qty_' + idx)?.textContent) || 0;
    if (clothId > 0 && qty > 0) {
      items.push({ cloth_type_id: clothId, quantity: qty });
    }
  });
  return items;
}

document.getElementById('orderForm')?.addEventListener('submit', async e => {
  e.preventDefault();

  const pickupAddr = document.getElementById('pickupAddr').value.trim();
  const pickupDate = document.getElementById('pickupDate').value;
  const payMethod  = document.getElementById('payMethod').value;
  const samePickup = document.getElementById('sameAsPickup').checked;
  const delivAddr  = document.getElementById('deliveryAddr')?.value.trim() || '';

  const items = getLineItems();

  if (!pickupAddr) { showAlert('orderPageAlert','Pickup address is required','error'); document.getElementById('pickupAddr').focus(); return; }
  if (!pickupDate) { showAlert('orderPageAlert','Pickup date is required','error'); document.getElementById('pickupDate').focus(); return; }
  if (!samePickup && !delivAddr) { showAlert('orderPageAlert','Delivery address is required when not same as pickup','error'); document.getElementById('deliveryAddr').focus(); return; }
  if (!items.length) { showAlert('orderPageAlert','Please add at least one item with quantity','error'); return; }

  const btn = document.getElementById('placeOrderBtn');
  setLoading(btn, true);
  const res = await apiCall('./php/orders.php', {
    action:           'place',
    items:            JSON.stringify(items),
    pickup_address:   pickupAddr,
    delivery_address: samePickup ? pickupAddr : delivAddr,
    pickup_date:      pickupDate,
    payment_method:   payMethod,
    notes:            document.getElementById('orderNotes').value.trim(),
    pickup_lat:       document.getElementById('pickupLat').value,
    pickup_lng:       document.getElementById('pickupLng').value,
    delivery_lat:     samePickup ? '' : document.getElementById('deliveryLat').value,
    delivery_lng:     samePickup ? '' : document.getElementById('deliveryLng').value,
  });
  setLoading(btn, false);

  if (res.success) {
    const alertEl = document.getElementById('orderPageAlert');
    alertEl.innerHTML = '';
    const div = document.createElement('div');
    div.className = 'alert alert-success';
    div.textContent = `✓ Order ${res.order_number} placed! Invoice: ${res.invoice_number}. Total: ${formatNPR(res.total)}. Check your email for confirmation.`;
    alertEl.appendChild(div);
    // Reset line items
    document.querySelectorAll('.line-item-row').forEach((r, i) => { if (i > 0) r.remove(); });
    document.getElementById('qty_0').textContent = '0';
    document.getElementById('price_0').textContent = '—';
    document.querySelector('.svc-type-sel').value = '';
    document.querySelector('.cloth-sel').innerHTML = '<option value="">Select item...</option>';
    document.querySelector('.cloth-sel').disabled = true;
    updateTotal();
    updateRemoveButtons();
    document.getElementById('orderForm').reset();
    document.getElementById('deliveryFields').style.display = 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  } else {
    showAlert('orderPageAlert', res.error || 'Failed to place order', 'error');
  }
});

// ── MY ORDERS ────────────────────────────────────────────
async function loadOrders() {
  const res = await apiGet('./php/orders.php', { action: 'get_orders', page: 1 });
  if (!res.success) { document.getElementById('ordersWrap').textContent = 'Failed to load orders.'; return; }
  buildOrderTable(res.orders || [], 'ordersWrap');
}

// ── ORDER DETAIL MODAL ────────────────────────────────────
async function viewOrderDetail(id) {
  document.getElementById('orderModalTitle').textContent = 'Order Details';
  document.getElementById('orderModalBody').innerHTML = '<div style="padding:32px;text-align:center;color:var(--gray-400);">Loading…</div>';
  openModal('orderModal');

  const res = await apiGet('./php/orders.php', { action: 'get_order', id });
  if (!res.success) {
    document.getElementById('orderModalBody').innerHTML = '';
    showAlert('orderModalBody', res.error || 'Failed to load order', 'error');
    return;
  }
  const o = res.order;
  document.getElementById('orderModalTitle').textContent = 'Order #' + o.order_number;

  const steps   = ['pending','confirmed','picked_up','in_process','ready','delivered'];
  const labels  = ['Received','Confirmed','Picked Up','Cleaning','Ready','Delivered'];
  const curIdx  = o.status === 'cancelled' ? -1 : steps.indexOf(o.status);

  // Build progress — all DOM, no innerHTML with user data
  const body = document.createElement('div');

  // Progress bar
  const progWrap = document.createElement('div');
  progWrap.className = 'prog-wrap'; progWrap.setAttribute('aria-label','Order progress');
  if (o.status === 'cancelled') {
    const alert = document.createElement('div');
    alert.className = 'alert alert-error'; alert.style.width = '100%';
    alert.textContent = '❌ This order was cancelled.';
    progWrap.appendChild(alert);
  } else {
    steps.forEach((s, i) => {
      const step = document.createElement('div'); step.className = 'prog-step';
      const dot  = document.createElement('div');
      dot.className = 'prog-dot ' + (i < curIdx ? 'done' : i === curIdx ? 'active' : '');
      dot.textContent = i < curIdx ? '✓' : String(i + 1);
      const lbl = document.createElement('div');
      lbl.className = 'prog-label ' + (i < curIdx ? 'done' : i === curIdx ? 'active' : '');
      lbl.textContent = labels[i];
      step.append(dot, lbl);
      progWrap.appendChild(step);
      if (i < steps.length - 1) {
        const line = document.createElement('div');
        line.className = 'prog-line ' + (i < curIdx ? 'done' : '');
        progWrap.appendChild(line);
      }
    });
  }
  body.appendChild(progWrap);

  // Invoice & payment info
  const infoRow = document.createElement('div');
  infoRow.style.cssText = 'display:flex;gap:16px;flex-wrap:wrap;margin:14px 0;font-size:.82rem;';
  if (o.invoice_number) {
    const inv = document.createElement('span');
    inv.style.cssText = 'background:var(--gray-100);padding:4px 10px;border-radius:6px;font-weight:600;';
    inv.textContent = 'Invoice: ' + o.invoice_number;
    infoRow.appendChild(inv);
  }
  const payBadge = document.createElement('span');
  payBadge.className = 'status-badge ' + (o.pay_status === 'paid' ? 'status-delivered' : 'status-pending');
  payBadge.textContent = (o.payment_method || 'cash').toUpperCase() + ' · ' + (o.pay_status || 'pending');
  infoRow.appendChild(payBadge);
  body.appendChild(infoRow);

  // Meta
  const meta = document.createElement('div');
  meta.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:18px 0;font-size:.85rem;';
  [
    ['Pickup',    o.pickup_address    || '—'],
    ['Delivery',  o.delivery_address  || 'Same as pickup'],
    ['Pickup Date', formatDate(o.pickup_date)],
    ['Payment',   (o.payment_method||'cash') + ' · ' + (o.pay_status||'pending')],
  ].forEach(([label, value]) => {
    const d = document.createElement('div');
    const b = document.createElement('strong'); b.textContent = label + ':';
    const p = document.createElement('p'); p.textContent = value;
    p.style.color = 'var(--gray-600)'; p.style.marginTop = '2px';
    d.append(b, p); meta.appendChild(d);
  });
  body.appendChild(meta);

  // Items
  const itemsTitle = document.createElement('strong');
  itemsTitle.style.cssText = 'font-size:.85rem;display:block;margin-bottom:8px;';
  itemsTitle.textContent = 'Items';
  body.appendChild(itemsTitle);
  (o.items || []).forEach(item => {
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--gray-100);font-size:.86rem;';
    const name = document.createElement('span');
    name.textContent = item.cloth_name + ' (' + item.service_type + ')';
    const qty  = document.createElement('span'); qty.textContent = item.quantity + ' × ' + formatNPR(item.unit_price_snapshot);
    qty.style.color = 'var(--gray-500)';
    const sub  = document.createElement('strong'); sub.textContent = formatNPR(item.line_total);
    row.append(name, qty, sub); body.appendChild(row);
  });
  // Subtotal / Discount / Total
  const subRow = document.createElement('div');
  subRow.style.cssText = 'display:flex;justify-content:space-between;padding:8px 0;font-size:.86rem;';
  const subLabel = document.createElement('span'); subLabel.textContent = 'Subtotal';
  const subVal   = document.createElement('span'); subVal.textContent = formatNPR(o.subtotal || o.total_amount);
  subRow.append(subLabel, subVal); body.appendChild(subRow);
  if (parseFloat(o.discount) > 0) {
    const discRow = document.createElement('div');
    discRow.style.cssText = 'display:flex;justify-content:space-between;padding:8px 0;font-size:.86rem;color:var(--red);';
    const discLabel = document.createElement('span'); discLabel.textContent = 'Discount';
    const discVal   = document.createElement('span'); discVal.textContent = '−' + formatNPR(o.discount);
    discRow.append(discLabel, discVal); body.appendChild(discRow);
  }
  const totRow = document.createElement('div');
  totRow.style.cssText = 'display:flex;justify-content:space-between;padding:12px 0;font-size:.95rem;border-top:2px solid var(--gray-100);';
  const totLabel = document.createElement('strong'); totLabel.textContent = 'Total';
  const totVal   = document.createElement('strong'); totVal.style.color = 'var(--red)'; totVal.textContent = formatNPR(o.total_amount);
  totRow.append(totLabel, totVal); body.appendChild(totRow);

  // Location map
  if (o.pickup_lat && o.pickup_lng) {
    const mapTitle = document.createElement('strong');
    mapTitle.style.cssText = 'font-size:.85rem;display:block;margin:16px 0 8px;';
    mapTitle.textContent = 'Pickup Location';
    body.appendChild(mapTitle);
    const mapDiv = document.createElement('div');
    mapDiv.style.cssText = 'height:220px;border-radius:8px;overflow:hidden;border:1px solid var(--gray-300);';
    mapDiv.id = 'detailMap_' + o.id;
    body.appendChild(mapDiv);
    setTimeout(() => {
      const m = L.map(mapDiv, { zoomControl: true, dragging: false, scrollWheelZoom: false }).setView([o.pickup_lat, o.pickup_lng], 16);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OSM' }).addTo(m);
      L.marker([o.pickup_lat, o.pickup_lng]).addTo(m).bindPopup('Pickup: ' + (o.pickup_address || '')).openPopup();
    }, 200);
  }

  document.getElementById('orderModalBody').innerHTML = '';
  document.getElementById('orderModalBody').appendChild(body);
}

// ── PROFILE ──────────────────────────────────────────────
async function loadProfile() {
  const res = await apiGet('./php/profile.php', { action: 'get' });
  if (!res.success) return;
  const u = res.user;
  const pName  = document.getElementById('pName');
  const pPhone = document.getElementById('pPhone');
  const pAddr  = document.getElementById('pAddr');
  if (pName)  pName.value  = u.full_name || '';
  if (pPhone) pPhone.value = u.phone     || '';
  if (pAddr)  pAddr.value  = u.address   || '';
}

document.getElementById('profileForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  clearFieldErrors();
  const btn = document.getElementById('saveProfileBtn');
  setLoading(btn, true);
  const res = await apiCall('./php/profile.php', {
    action:    'update',
    full_name: document.getElementById('pName').value,
    phone:     document.getElementById('pPhone').value,
    address:   document.getElementById('pAddr').value,
  });
  setLoading(btn, false);
  if (res.success) {
    showAlert('profileAlert', res.message || 'Profile updated!', 'success');
  } else {
    if (res.fields) showFieldErrors(res.fields);
    else showAlert('profileAlert', res.error || 'Update failed', 'error');
  }
});

// ── CHANGE PASSWORD ───────────────────────────────────────
document.getElementById('passForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  clearFieldErrors();
  const btn = document.getElementById('chPassBtn');
  setLoading(btn, true);
  const res = await apiCall('./php/profile.php', {
    action:           'change_password',
    current_password: document.getElementById('currPass').value,
    new_password:     document.getElementById('newPass').value,
    confirm_password: document.getElementById('confPass').value,
  });
  setLoading(btn, false);
  if (res.success) {
    showAlert('passAlert', res.message || 'Password changed!', 'success');
    document.getElementById('passForm').reset();
  } else {
    if (res.fields) showFieldErrors(res.fields);
    else showAlert('passAlert', res.error || 'Failed to change password', 'error');
  }
});

// ── FEEDBACK ─────────────────────────────────────────────
let selectedRating = 0;

document.querySelectorAll('.star-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    selectedRating = parseInt(btn.dataset.rating);
    document.getElementById('fbRatingVal').value = selectedRating;
    document.querySelectorAll('.star-btn').forEach(b => {
      const r = parseInt(b.dataset.rating);
      b.classList.toggle('active', r <= selectedRating);
      b.setAttribute('aria-pressed', r <= selectedRating ? 'true' : 'false');
    });
  });
});

document.getElementById('feedbackForm')?.addEventListener('submit', async e => {
  e.preventDefault();
  const rating = document.getElementById('fbRatingVal').value;
  const message = document.getElementById('fbMessage').value.trim();

  if (!rating || parseInt(rating) < 1) {
    showAlert('feedbackAlert', 'Please select a rating', 'error'); return;
  }
  if (!message) {
    showAlert('feedbackAlert', 'Please write your feedback', 'error'); return;
  }

  const btn = document.getElementById('submitFeedbackBtn');
  setLoading(btn, true);
  const res = await apiCall('./php/orders.php', {
    action:  'submit_feedback',
    rating:  rating,
    message: message,
  });
  setLoading(btn, false);
  if (res.success) {
    showAlert('feedbackAlert', 'Thank you! Your feedback has been submitted for review.', 'success');
    document.getElementById('feedbackForm').reset();
    document.querySelectorAll('.star-btn').forEach(b => { b.classList.remove('active'); b.setAttribute('aria-pressed','false'); });
    selectedRating = 0;
    document.getElementById('fbRatingVal').value = '';
  } else {
    showAlert('feedbackAlert', res.error || 'Failed to submit', 'error');
  }
});

// ── INIT ─────────────────────────────────────────────────
const tab = '<?= $activeTab ?>';
if (tab === 'overview') loadOverview();
if (tab === 'order')    {
  loadClothTypes().then(() => {
    // Bind events to the existing static row (index 0)
    bindLineItemEvents(0);
  });
}
if (tab === 'orders')   loadOrders();
if (tab === 'profile')  loadProfile();
</script>
</body>
</html>
