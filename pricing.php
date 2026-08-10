<?php


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
require_once __DIR__ . '/php/config.php';
sendSecurityHeaders();
$isLoggedIn = isLoggedIn();
$csrf       = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Pricing &mdash; DD Laundry</title>
  <meta name="description" content="Transparent, honest pricing for all DD Laundry services in Imadol, Lalitpur, Nepal.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .pricing-hero { background:linear-gradient(135deg,#922B21,#C0392B); padding:120px 0 80px; text-align:center; }
    .pricing-layout { display:grid; grid-template-columns:1fr 340px; gap:48px; align-items:start; }
    @media(max-width:900px) { .pricing-layout { grid-template-columns:1fr; } }
    .sticky-col { position:sticky; top:88px; }
    .summary-table-wrap { border-radius:var(--radius-lg); overflow:hidden; border:1px solid var(--gray-300); margin-bottom:20px; }
    .summary-table { width:100%; border-collapse:collapse; }
    .summary-table th { background:var(--red); color:#fff; padding:13px 16px; text-align:left; font-size:.83rem; font-weight:600; letter-spacing:.04em; }
    .summary-table td { padding:13px 16px; border-bottom:1px solid var(--gray-100); font-size:.9rem; }
    .summary-table tr:last-child td { border-bottom:none; }
    .summary-table tbody tr:hover { background:var(--red-pale); }
    .price-val { font-weight:700; color:var(--red); }
    .order-cta-card { background:linear-gradient(135deg,#922B21,#C0392B); border-radius:var(--radius-lg); padding:28px; text-align:center; }
  </style>
</head>
<body>

<!-- Navbar (always scrolled style on inner pages) -->
<nav class="navbar scrolled" id="navbar" role="navigation" aria-label="Main navigation"
     style="background:rgba(255,255,255,.97);">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo" style="color:var(--charcoal);">&#x1F9BA; DD<span>Laundry</span></a>
    <ul class="nav-links">
      <li><a href="index.php#services">Services</a></li>
      <li><a href="pricing.php" style="color:var(--red);font-weight:600;">Pricing</a></li>
      <li><a href="index.php#contact">Contact</a></li>
    </ul>
    <div class="nav-cta">
      <?php if ($isLoggedIn): ?>
        <a href="dashboard.php" class="btn btn-primary btn-sm">Dashboard &rarr;</a>
      <?php else: ?>
        <a href="login.php"    class="btn btn-ghost btn-sm" style="color:var(--charcoal);">Login</a>
        <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
      <span style="background:var(--charcoal);"></span>
      <span style="background:var(--charcoal);"></span>
      <span style="background:var(--charcoal);"></span>
    </button>
  </div>
</nav>
<div class="mobile-menu" id="mobileMenu" role="navigation">
  <a href="index.php#services">Services</a>
  <a href="pricing.php">Pricing</a>
  <a href="index.php#contact">Contact</a>
  <?php if ($isLoggedIn): ?>
    <a href="dashboard.php">Dashboard</a>
  <?php else: ?>
    <a href="login.php">Login</a>
    <a href="register.php">Register</a>
  <?php endif; ?>
</div>

<!-- Hero -->
<div class="pricing-hero" role="banner">
  <div class="container">
    <span class="eyebrow" style="color:rgba(255,200,180,.9);">Transparent Pricing</span>
    <h1 class="display-xl" style="color:#fff;margin-bottom:14px;">Simple, Honest Rates</h1>
    <p style="color:rgba(255,255,255,.8);font-size:1.05rem;max-width:520px;margin:0 auto;">
      No hidden charges. No surprises. Just clean clothes at fair prices.
    </p>
  </div>
</div>

<!-- Pricing Section -->
<section class="section" aria-labelledby="pricingHeading">
  <div class="container">
    <div class="pricing-layout">

      <!-- Service Cards -->
      <div>
        <span class="eyebrow">Service Rates</span>
        <h2 class="display-md" id="pricingHeading" style="margin-bottom:6px;">All Services</h2>
        <p style="color:var(--gray-500);font-size:.92rem;margin-bottom:28px;">
          Click any service to see what&rsquo;s included.
        </p>

        <?php
        require_once __DIR__ . '/php/config.php';
        $db = getDB();
        $svcStmt = $db->query("SELECT name,description,price,unit,icon FROM services WHERE is_active=1 ORDER BY id");
        $dbServices = $svcStmt->fetchAll();

        $iconMap = ['tshirt'=>'&#x1F455;','star'=>'&#x2B50;','brush'=>'&#x1F9E5;','lightning'=>'&#x1F525;'];
        $svcDetails = [
            'Regular Wash'  => ['Machine Wash','Premium Detergent','Neatly Folded','24hr Turnaround','Shirts, Pants, T-shirts'],
            'Premium Wash'  => ['Fabric Conditioner','Gentle Cycle','Color Protection','Formal Shirts, Kurtas'],
            'Dry Cleaning'  => ['Suits & Blazers','Sarees & Silk','Stain Removal','Coats & Jackets'],
            'Ironing'        => ['Steam Press','All Fabrics','Collar & Cuff Pressing','Office Ready'],
        ];

        foreach ($dbServices as $i => $svc):
          $icon = $iconMap[$svc['icon']] ?? '&#x1F9BA;';
          $tags = $svcDetails[$svc['name']] ?? ['Professional Service','Quality Guaranteed'];
        ?>
        <article class="pricing-card animate-on-scroll" style="transition-delay:<?= $i * .06 ?>s;"
                 role="button" tabindex="0" aria-expanded="false"
                 aria-label="<?= htmlspecialchars($svc['name']) ?> — click to expand"
                 onclick="this.classList.toggle('open'); this.getAttribute('aria-expanded')==='true' ? this.setAttribute('aria-expanded','false') : this.setAttribute('aria-expanded','true')">
          <div class="pricing-card-header">
            <div class="pricing-icon" aria-hidden="true"><?= $icon ?></div>
            <div>
              <div style="font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:600;margin-bottom:4px;">
                <?= htmlspecialchars($svc['name']) ?>
              </div>
              <div style="font-size:.82rem;color:var(--gray-500);"><?= htmlspecialchars($svc['description']) ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <div style="font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;color:var(--red);">
                NPR <?= number_format((float)$svc['price'], 0) ?>
              </div>
              <div style="font-size:.75rem;color:var(--gray-500);"><?= htmlspecialchars($svc['unit']) ?></div>
              <div class="pricing-chevron" aria-hidden="true">&#x25BE;</div>
            </div>
          </div>
          <div class="pricing-details" role="region" aria-label="Details for <?= htmlspecialchars($svc['name']) ?>">
            <div style="padding-top:6px;border-top:1px solid var(--gray-100);">
              <strong style="font-size:.82rem;">Suitable for:</strong>
              <div class="tag-list">
                <?php foreach ($tags as $tag): ?>
                <span class="tag">&#x2713; <?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>

      <!-- Sticky sidebar -->
      <div class="sticky-col">
        <!-- Summary table -->
        <div class="summary-table-wrap animate-on-scroll">
          <table class="summary-table" aria-label="Pricing summary">
            <thead><tr><th scope="col">Service</th><th scope="col">Rate</th></tr></thead>
            <tbody>
              <?php foreach ($dbServices as $svc): ?>
              <tr>
                <td><?= htmlspecialchars($svc['name']) ?></td>
                <td class="price-val">NPR <?= number_format((float)$svc['price'], 0) ?> / <?= htmlspecialchars($svc['unit']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Order CTA -->
        <div class="order-cta-card animate-on-scroll">
          <div style="font-size:2.5rem;margin-bottom:10px;">&#x1F9BA;</div>
          <h2 style="font-family:'Playfair Display',serif;color:#fff;font-size:1.2rem;margin-bottom:8px;">
            Ready to Order?
          </h2>
          <p style="color:rgba(255,255,255,.8);font-size:.87rem;margin-bottom:18px;line-height:1.6;">
            Free pickup &amp; delivery in Imadol, Lalitpur.
          </p>
          <?php if ($isLoggedIn): ?>
            <a href="dashboard.php?tab=order" class="btn btn-white btn-full">
              Place Order Now &rarr;
            </a>
          <?php else: ?>
            <a href="register.php" class="btn btn-white btn-full" style="margin-bottom:10px;">
              Create Free Account
            </a>
            <a href="tel:+9779749863285" class="btn btn-outline-white btn-full">
              &#x1F4DE; Call Us
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- FAQ -->
    <div style="max-width:700px;margin:80px auto 0;">
      <div class="section-header animate-on-scroll">
        <span class="eyebrow">FAQs</span>
        <h2 class="display-md section-title">Common Questions</h2>
        <div class="section-divider" aria-hidden="true"></div>
      </div>

      <?php
      $faqs = [
        ['Is there a minimum order?',       'For all services, there is no minimum &mdash; even a single piece is welcome.'],
        ['How long does it take?',           'Most orders are returned within 24&nbsp;hours. Dry cleaning may take 48&nbsp;hours. Express same-day service is available for an additional charge.'],
        ['Is pickup really free?',           'Yes! Pickup and delivery within Imadol and nearby areas is completely free with any order.'],
        ['How do I pay?',                    'We accept cash on delivery. Online payment options are coming soon. You can also pay in-store at our Imadol location.'],
        ['Can I track my order?',            'Absolutely! Once you create an account and place an order, you can track its real-time status from your dashboard. Email notifications are sent at every step.'],
        ['What if my clothes are damaged?',  'We handle all garments with utmost care. In the rare event of any issue, please contact us within 24&nbsp;hours of delivery and we will resolve it immediately.'],
      ];
      foreach ($faqs as $i => [$q, $a]):
      ?>
      <div class="faq-item animate-on-scroll" style="transition-delay:<?= $i * .06 ?>s;">
        <div class="faq-q" role="button" tabindex="0" aria-expanded="false"
             onclick="this.parentElement.classList.toggle('open'); this.getAttribute('aria-expanded')==='true'?this.setAttribute('aria-expanded','false'):this.setAttribute('aria-expanded','true')">
          <span><?= htmlspecialchars($q) ?></span>
          <span aria-hidden="true">&#x25BE;</span>
        </div>
        <div class="faq-a" role="region">
          <?= $a /* safe — author-controlled, no user data */ ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Footer -->
<footer class="footer" role="contentinfo">
  <div class="container">
    <div style="text-align:center;padding:20px 0;">
      <div class="footer-logo" style="display:inline-block;margin-bottom:10px;">&#x1F9BA; DD<span>Laundry</span></div>
      <p style="color:rgba(255,255,255,.5);font-size:.85rem;">Imadol, Lalitpur, Nepal &middot; +977&nbsp;9749863285</p>
      <p style="color:rgba(255,255,255,.3);font-size:.78rem;margin-top:14px;">
        &copy; <?= date('Y') ?> DD Laundry. All rights reserved.
      </p>
    </div>
  </div>
</footer>

<script src="js/main.js"></script>
<script>
document.querySelectorAll('.pricing-card, .faq-q').forEach(el => {
  el.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      el.click();
    }
  });
});
</script>
</body>
</html>
