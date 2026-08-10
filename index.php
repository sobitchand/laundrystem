<?php
require_once __DIR__ . '/php/config.php';
sendSecurityHeaders();
$isLoggedIn = isLoggedIn();
$userName   = htmlspecialchars($_SESSION['user_name'] ?? '', ENT_QUOTES, 'UTF-8');
$firstName  = htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? 'User')[0], ENT_QUOTES, 'UTF-8');
$csrf       = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= $csrf ?>">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>DD Laundry &mdash; Professional Laundry Service in Imadol, Lalitpur</title>
  <meta name="description" content="DD Laundry offers professional wash, dry cleaning, ironing and more in Imadol, Lalitpur, Nepal. Online ordering with free pickup and delivery.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- ── Navbar ──────────────────────────────────────────────── -->
<nav class="navbar" id="navbar" role="navigation" aria-label="Main navigation">
  <div class="navbar-inner">
    <a href="index.php" class="navbar-logo" aria-label="DD Laundry Home">&#x1F9BA; DD<span>Laundry</span></a>
    <ul class="nav-links" role="list">
      <li><a href="#services">Services</a></li>
      <li><a href="pricing.php">Pricing</a></li>
      <li><a href="#contact">Contact</a></li>
      <?php if ($isLoggedIn): ?>
      <li><a href="dashboard.php">Dashboard</a></li>
      <?php endif; ?>
    </ul>
    <div class="nav-cta">
      <?php if ($isLoggedIn): ?>
        <a href="dashboard.php" class="btn btn-primary btn-sm">Hello, <?= $firstName ?> &rarr;</a>
      <?php else: ?>
        <a href="login.php"    class="btn btn-ghost btn-sm">Login</a>
        <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
      <?php endif; ?>
    </div>
    <button class="hamburger" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu" role="navigation" aria-label="Mobile navigation">
  <a href="#services">Services</a>
  <a href="pricing.php">Pricing</a>
  <a href="#contact">Contact</a>
  <?php if ($isLoggedIn): ?>
    <a href="dashboard.php">Dashboard</a>
    <a href="#" id="mobileLogout">Logout</a>
  <?php else: ?>
    <a href="login.php">Login</a>
    <a href="register.php">Register</a>
  <?php endif; ?>
</div>

<!-- ── Hero ────────────────────────────────────────────────── -->
<section class="hero" id="hero" aria-label="Hero">
  <div class="hero-bg" aria-hidden="true"></div>
  <div class="hero-content container">
    <div class="hero-text">
      <div class="hero-badge">&#x1F9BA; Imadol, Lalitpur &middot; DD Laundry</div>
      <h1 class="hero-title">
        Fresh Clothes,<br>
        <em>Delivered to</em><br>
        Your Door
      </h1>
      <p class="hero-desc">
        Professional laundry services in the heart of Lalitpur. We wash, dry, iron, and
        deliver &mdash; so you can focus on what matters most.
      </p>
      <div class="hero-actions">
        <?php if ($isLoggedIn): ?>
          <a href="dashboard.php?tab=order" class="btn btn-white btn-lg">Place Order &rarr;</a>
        <?php else: ?>
          <a href="register.php" class="btn btn-white btn-lg">Book Now &rarr;</a>
        <?php endif; ?>
        <a href="#services" class="btn btn-outline-white btn-lg">Our Services</a>
      </div>
      <div class="hero-stats">
        <div><div class="hero-stat-num">500+</div><div class="hero-stat-label">Happy Customers</div></div>
        <div><div class="hero-stat-num">4</div><div class="hero-stat-label">Core Services</div></div>
        <div><div class="hero-stat-num">24hr</div><div class="hero-stat-label">Turnaround</div></div>
      </div>
    </div>
    <div class="hero-visual" aria-hidden="true">
      <div class="hero-card-stack">
        <div class="hero-card-main">
          <div class="hero-card-icon">&#x1F9BA;</div>
          <h3>Active Order #DDL-2026</h3>
          <p>Regular Wash &middot; 3 shirts, 2 trousers</p>
          <div class="hero-card-status">
            <span class="status-dot"></span> Ready for Delivery!
          </div>
        </div>
        <div class="hero-float hero-float--tr">
          <span class="badge-dot"></span> Pickup Available
        </div>
        <div class="hero-float hero-float--bl">
          &#x1F4CD; Imadol, Lalitpur
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ── Services ────────────────────────────────────────────── -->
<section class="section" id="services" aria-labelledby="servicesHeading">
  <div class="container">
    <div class="section-header animate-on-scroll">
      <span class="eyebrow">What We Offer</span>
      <h2 class="display-lg section-title" id="servicesHeading">Our Services</h2>
      <p class="section-subtitle">From everyday wash to delicate dry cleaning &mdash; we handle it all with care.</p>
      <div class="section-divider" aria-hidden="true"></div>
    </div>

    <div class="services-grid">
      <?php
      $db = getDB();
      $svcStmt = $db->query("SELECT name,description,price,unit,icon FROM services WHERE is_active=1 ORDER BY id");
      $dbServices = $svcStmt->fetchAll();

      $iconMap = ['tshirt'=>'&#x1F455;','star'=>'&#x2B50;','brush'=>'&#x1F9E5;','lightning'=>'&#x1F525;'];
      $svcDetails = [
          'Regular Wash'  => ['Machine Wash','Premium Detergent','Neatly Folded','24hr Turnaround'],
          'Premium Wash'  => ['Fabric Conditioner','Gentle Cycle','Delicate Care','Color Protection'],
          'Dry Cleaning'  => ['Suits & Blazers','Sarees & Silk','Stain Removal','Odor Treatment'],
          'Ironing'        => ['Steam Press','All Fabrics','Collar Shaping','Office Ready'],
      ];

      foreach ($dbServices as $i => $svc):
        $icon = $iconMap[$svc['icon']] ?? '&#x1F9BA;';
        $tags = $svcDetails[$svc['name']] ?? ['Professional Service','Quality Guaranteed'];
      ?>
      <article class="service-card animate-on-scroll" style="transition-delay:<?= $i * 0.07 ?>s"
               role="button" tabindex="0" aria-expanded="false"
               aria-label="<?= htmlspecialchars($svc['name']) ?> — expand for details">
        <div class="service-card-main">
          <div class="service-icon" aria-hidden="true"><?= $icon ?></div>
          <div class="service-card-info">
            <div class="service-card-title"><?= htmlspecialchars($svc['name']) ?></div>
            <div class="service-card-pricing">
              <span class="service-card-price">NPR <?= number_format((float)$svc['price'], 0) ?></span>
              <span class="service-card-unit"><?= htmlspecialchars($svc['unit']) ?></span>
            </div>
          </div>
          <span class="service-expand-icon" aria-hidden="true">&#x25BE;</span>
        </div>
        <div class="service-details" role="region">
          <p><?= htmlspecialchars($svc['description']) ?></p>
          <div class="service-detail-tags">
            <?php foreach ($tags as $tag): ?>
            <span class="service-tag">&#x2713; <?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-4 animate-on-scroll">
      <a href="pricing.php" class="btn btn-outline btn-lg">View Full Pricing &rarr;</a>
    </div>
  </div>
</section>

<!-- ── Why Us ───────────────────────────────────────────────── -->
<section class="section section-dark" aria-labelledby="whyHeading">
  <div class="container">
    <div class="section-header animate-on-scroll">
      <span class="eyebrow" style="color:#FFBAB4;">Why DD Laundry</span>
      <h2 class="display-lg section-title" id="whyHeading" style="color:#fff;">Trusted by Locals</h2>
      <div class="section-divider" aria-hidden="true"></div>
    </div>
    <div class="features-grid">
      <?php
      $features = [
        ['&#x1F697;','Free Pickup & Delivery', 'We come to you. Schedule a pickup and we handle the rest &mdash; no need to step out.'],
        ['&#x26A1;','Fast 24hr Turnaround',    'Most orders returned within 24 hours. Express same-day service available on request.'],
        ['&#x1F48E;','Quality Guaranteed',     'Professional-grade equipment and premium detergents ensure clothes look their best.'],
        ['&#x1F4F1;','Real-Time Tracking',     'Know exactly where your order is from pickup to delivery through your dashboard.'],
      ];
      foreach ($features as [$icon, $title, $desc]):
      ?>
      <div class="feature-card animate-on-scroll">
        <div class="feature-icon" aria-hidden="true"><?= $icon ?></div>
        <h3><?= htmlspecialchars($title) ?></h3>
        <p><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ── Customer Feedback ────────────────────────────────────── -->
<?php
$db = getDB();
$fbStmt = $db->query("SELECT name,rating,message,created_at FROM feedback WHERE is_approved=1 ORDER BY created_at DESC LIMIT 6");
$approvedFeedback = $fbStmt->fetchAll();
?>
<?php if (!empty($approvedFeedback)): ?>
<section class="section" aria-labelledby="feedbackHeading">
  <div class="container">
    <div class="section-header animate-on-scroll">
      <span class="eyebrow">Testimonials</span>
      <h2 class="display-lg section-title" id="feedbackHeading">What Our Customers Say</h2>
      <div class="section-divider" aria-hidden="true"></div>
    </div>
    <div class="feedback-grid">
      <?php foreach ($approvedFeedback as $fb): ?>
      <div class="feedback-card animate-on-scroll">
        <div class="feedback-stars" aria-label="<?= (int)$fb['rating'] ?> out of 5 stars">
          <?= str_repeat('&#x2605;', (int)$fb['rating']) . str_repeat('&#x2606;', 5 - (int)$fb['rating']) ?>
        </div>
        <p class="feedback-text"><?= htmlspecialchars($fb['message']) ?></p>
        <div class="feedback-author">
          <strong><?= htmlspecialchars($fb['name']) ?></strong>
          <span><?= date('M Y', strtotime($fb['created_at'])) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Contact & Map ────────────────────────────────────────── -->
<section class="section" id="contact" aria-labelledby="contactHeading">
  <div class="container">
    <div class="section-header animate-on-scroll">
      <span class="eyebrow">Find Us</span>
      <h2 class="display-lg section-title" id="contactHeading">Visit or Contact Us</h2>
      <p class="section-subtitle">Located in the heart of Imadol, Lalitpur. Drop by or reach out anytime.</p>
      <div class="section-divider" aria-hidden="true"></div>
    </div>

    <div class="contact-grid animate-on-scroll">
      <!-- Info Card -->
      <div class="contact-info-card">
        <div class="contact-info-item">
          <div class="contact-info-icon" aria-hidden="true">&#x1F4CD;</div>
          <div>
            <div class="contact-info-label">Address</div>
            <div class="contact-info-value">Imadol, Lalitpur, Nepal</div>
          </div>
        </div>
        <div class="contact-info-item">
          <div class="contact-info-icon" aria-hidden="true">&#x1F4DE;</div>
          <div>
            <div class="contact-info-label">Phone</div>
            <div class="contact-info-value">
              <a href="tel:+9779749863285">+977&nbsp;9749863285</a>
            </div>
          </div>
        </div>
        <div class="contact-info-item">
          <div class="contact-info-icon" aria-hidden="true">&#x1F550;</div>
          <div>
            <div class="contact-info-label">Hours</div>
            <div class="contact-info-value">7:00 AM &ndash; 8:00 PM<br><small>7 days a week</small></div>
          </div>
        </div>
        <div class="contact-info-item" style="margin-bottom:0">
          <div class="contact-info-icon" aria-hidden="true">&#x1F697;</div>
          <div>
            <div class="contact-info-label">Pickup Service</div>
            <div class="contact-info-value">Available &middot; Book Online</div>
          </div>
        </div>
        <div class="mt-3">
          <!-- Contact Form -->
          <div id="contactAlert"></div>
          <form id="contactForm" novalidate>
            <div class="form-group">
              <label class="form-label" for="cf_name">Your Name</label>
              <input type="text" class="form-control" id="cf_name" name="name" placeholder="Ram Bahadur" maxlength="100" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="cf_email">Email</label>
              <input type="email" class="form-control" id="cf_email" name="email" placeholder="you@example.com" maxlength="150">
            </div>
            <div class="form-group">
              <label class="form-label" for="cf_phone">Phone</label>
              <input type="tel" class="form-control" id="cf_phone" name="phone" placeholder="98XXXXXXXX" maxlength="20">
              <div class="form-hint">Nepal numbers: 98XXXXXXXX or 97XXXXXXXX</div>
            </div>
            <div class="form-group">
              <label class="form-label" for="cf_message">Message</label>
              <textarea class="form-control" id="cf_message" name="message" rows="3" placeholder="How can we help you?" maxlength="2000" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Send Message</button>
          </form>
        </div>
      </div>

      <!-- Map -->
      <div class="map-container">
        <iframe
          title="DD Laundry location on Google Maps"
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3533.9!2d85.3318!3d27.6535!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x39eb19c9ac800001%3A0x6b44b3d5e8b45f2c!2sImadol%2C%20Lalitpur!5e0!3m2!1sen!2snp!4v1700000000000"
          width="100%" height="480" style="border:0;" allowfullscreen loading="lazy"
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA Banner ───────────────────────────────────────────── -->
<?php if (!$isLoggedIn): ?>
<section class="cta-banner" aria-label="Sign up call to action">
  <div class="container text-center">
    <h2 class="animate-on-scroll">Ready for Fresh, Clean Clothes?</h2>
    <p class="animate-on-scroll">Create a free account and place your first order today. Free pickup &amp; delivery included.</p>
    <div class="cta-actions animate-on-scroll">
      <a href="register.php" class="btn btn-white btn-lg">Create Free Account &rarr;</a>
      <a href="login.php"    class="btn btn-outline-white btn-lg">Login</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Footer ───────────────────────────────────────────────── -->
<footer class="footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="footer-logo">&#x1F9BA; DD<span>Laundry</span></div>
        <p>Professional laundry in Imadol, Lalitpur. Clean clothes, delivered with care.</p>
      </div>
      <div>
        <div class="footer-heading">Services</div>
        <ul class="footer-links">
          <li><a href="pricing.php">All Services</a></li>
        </ul>
      </div>
      <div>
        <div class="footer-heading">Account</div>
        <ul class="footer-links">
          <?php if ($isLoggedIn): ?>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="dashboard.php?tab=orders">My Orders</a></li>
          <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php">Register</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div>
        <div class="footer-heading">Contact</div>
        <ul class="footer-links">
          <li><a href="#contact">Imadol, Lalitpur</a></li>
          <li><a href="tel:+9779749863285">+977 9749863285</a></li>
          <li><span>7AM &ndash; 8PM Daily</span></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; <?= date('Y') ?> DD Laundry. All rights reserved.</p>
      <p>Made with &#x2764;&#xFE0F; for Lalitpur</p>
    </div>
  </div>
</footer>

<script src="js/main.js"></script>
<?php if ($isLoggedIn): ?>
<script>
document.getElementById('mobileLogout')?.addEventListener('click', async e => {
  e.preventDefault();
  const res = await apiCall('./php/auth.php', { action: 'logout' });
  window.location.href = res.redirect || 'index.php';
});
</script>
<?php endif; ?>
</body>
</html>
