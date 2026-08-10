// ============================================================
// DD Laundry — main.js  (XSS-safe, CSRF-aware)
// ============================================================

// ── XSS-safe DOM helpers ──────────────────────────────────
// Never use innerHTML with user data — always use these
function escHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(String(str ?? '')));
  return d.innerHTML;
}
function setTextSafe(el, text) {
  if (el) el.textContent = String(text ?? '');
}
// Safe innerHTML for TRUSTED template strings only (no user data)
function safeHTML(el, html) {
  if (el) el.innerHTML = html;
}

// ── CSRF Token ────────────────────────────────────────────
// PHP pages embed the token in a <meta> tag
function getCSRFToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

// ── API Helpers ───────────────────────────────────────────
async function apiCall(url, data = {}) {
  const body = new FormData();
  body.append('csrf_token', getCSRFToken());
  Object.entries(data).forEach(([k, v]) => body.append(k, v));
  try {
    const res  = await fetch(url, { method: 'POST', body, credentials: 'same-origin' });
    const json = await res.json();
    return json;
  } catch (e) {
    return { error: 'Network error. Please try again.' };
  }
}

async function apiGet(url, params = {}) {
  const qs  = new URLSearchParams(params).toString();
  const full = qs ? `${url}?${qs}` : url;
  try {
    const res = await fetch(full, { credentials: 'same-origin' });
    return res.json();
  } catch (e) {
    return { error: 'Network error.' };
  }
}

// ── Toast Notifications ───────────────────────────────────
const ToastManager = {
  container: null,
  init() {
    this.container = document.querySelector('.toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      document.body.appendChild(this.container);
    }
  },
  show(message, type = 'info', duration = 4500) {
    const icons = { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' };
    const toast = document.createElement('div');
    toast.className = `toast ${escHtml(type)}`;
    // Use textContent for icon and message — no innerHTML with user data
    const iconSpan = document.createElement('span');
    iconSpan.textContent = icons[type] || icons.info;
    const msgSpan = document.createElement('span');
    msgSpan.textContent = message;   // textContent — XSS safe
    toast.appendChild(iconSpan);
    toast.appendChild(msgSpan);
    this.container.appendChild(toast);
    setTimeout(() => {
      toast.style.animation = 'slideOutRight 0.3s ease forwards';
      setTimeout(() => toast.remove(), 310);
    }, duration);
  }
};

// ── Button Loading ────────────────────────────────────────
function setLoading(btn, loading) {
  if (!btn) return;
  if (loading) {
    btn.dataset.origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('btn-loading');
  } else {
    btn.disabled = false;
    btn.classList.remove('btn-loading');
    if (btn.dataset.origHtml != null) btn.innerHTML = btn.dataset.origHtml;
  }
}

// ── Navbar Scroll ─────────────────────────────────────────
function initNavbar() {
  const nav = document.querySelector('.navbar');
  if (!nav) return;
  const update = () => nav.classList.toggle('scrolled', window.scrollY > 40);
  window.addEventListener('scroll', update, { passive: true });
  update();
}

// ── Mobile Menu ───────────────────────────────────────────
function initMobileMenu() {
  const ham  = document.querySelector('.hamburger');
  const menu = document.querySelector('.mobile-menu');
  if (!ham || !menu) return;
  const setOpen = (open) => {
    menu.classList.toggle('open', open);
    ham.setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  ham.addEventListener('click', (e) => { e.stopPropagation(); setOpen(!menu.classList.contains('open')); });
  document.addEventListener('click', (e) => {
    if (!ham.contains(e.target) && !menu.contains(e.target)) setOpen(false);
  });
  // Close on mobile link click
  menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setOpen(false)));
}

// ── Service Cards Accordion ───────────────────────────────
function initServiceCards() {
  const closeCards = () => {
    document.querySelectorAll('.service-card').forEach(c => {
      c.classList.remove('active');
      c.setAttribute('aria-expanded', 'false');
    });
  };
  document.querySelectorAll('.service-card').forEach(card => {
    const toggle = () => {
      const isActive = card.classList.contains('active');
      closeCards();
      if (!isActive) {
        card.classList.add('active');
        card.setAttribute('aria-expanded', 'true');
      }
    };
    card.addEventListener('click', toggle);
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggle();
      }
    });
  });
}

// ── Scroll Animations ─────────────────────────────────────
function initScrollAnimations() {
  if (!window.IntersectionObserver) {
    document.querySelectorAll('.animate-on-scroll').forEach(el => el.classList.add('visible'));
    return;
  }
  const obs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('visible'); obs.unobserve(entry.target); }
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.animate-on-scroll').forEach(el => obs.observe(el));
}

// ── Modal ─────────────────────────────────────────────────
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) { e.target.classList.remove('open'); document.body.style.overflow = ''; }
  if (e.target.classList.contains('modal-close'))   { e.target.closest('.modal-overlay')?.classList.remove('open'); document.body.style.overflow = ''; }
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { document.querySelectorAll('.modal-overlay.open').forEach(m => { m.classList.remove('open'); document.body.style.overflow = ''; }); }
});

// ── Password Toggle ───────────────────────────────────────
function initPasswordToggles() {
  document.querySelectorAll('.input-toggle[data-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const show = input.type === 'password';
      input.type  = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      btn.textContent = show ? '🙈' : '👁️';
    });
  });
}

// ── OTP Input ─────────────────────────────────────────────
function initOTPInputs() {
  const inputs = Array.from(document.querySelectorAll('.otp-input'));
  inputs.forEach((input, i) => {
    input.addEventListener('input', () => {
      // Only allow single digit
      input.value = input.value.replace(/\D/g, '').slice(0, 1);
      if (input.value && i < inputs.length - 1) inputs[i + 1].focus();
    });
    input.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !input.value && i > 0) { e.preventDefault(); inputs[i - 1].focus(); }
    });
    input.addEventListener('paste', e => {
      e.preventDefault();
      const digits = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
      digits.split('').slice(0, inputs.length).forEach((ch, j) => { if (inputs[j]) inputs[j].value = ch; });
      const next = Math.min(digits.length, inputs.length - 1);
      inputs[next]?.focus();
    });
  });
}

function getOTPValue() {
  return Array.from(document.querySelectorAll('.otp-input')).map(i => i.value).join('');
}

// ── Countdown ─────────────────────────────────────────────
function startCountdown(elementId, seconds) {
  const el = document.getElementById(elementId);
  if (!el) return;
  let rem = seconds;
  el.style.pointerEvents = 'none';
  el.style.opacity = '0.5';
  el.textContent = `Resend in ${rem}s`;
  const t = setInterval(() => {
    rem--;
    if (rem <= 0) {
      clearInterval(t);
      el.textContent = 'Resend OTP';
      el.style.pointerEvents = '';
      el.style.opacity = '';
    } else {
      el.textContent = `Resend in ${rem}s`;
    }
  }, 1000);
}

// ── Formatters ────────────────────────────────────────────
function formatNPR(amount) {
  const n = parseFloat(amount) || 0;
  return 'NPR\u00a0' + n.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
  if (!dateStr) return '\u2014';
  try {
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
  } catch { return dateStr; }
}

// ── Status Config ─────────────────────────────────────────
const STATUS_CONFIG = {
  pending:    { label: 'Pending',     icon: '📋' },
  confirmed:  { label: 'Confirmed',   icon: '✅' },
  picked_up:  { label: 'Picked Up',   icon: '🚗' },
  in_process: { label: 'In Process',  icon: '🧺' },
  ready:      { label: 'Ready',       icon: '✨' },
  delivered:  { label: 'Delivered',   icon: '🎉' },
  cancelled:  { label: 'Cancelled',   icon: '❌' },
};

// ── Contact Form ──────────────────────────────────────────
function initContactForm() {
  const form = document.getElementById('contactForm');
  if (!form) return;
  form.addEventListener('submit', async e => {
    e.preventDefault();
    clearFieldErrors();
    const btn = form.querySelector('[type="submit"]');
    const alertEl = document.getElementById('contactAlert');
    if (alertEl) alertEl.innerHTML = '';
    setLoading(btn, true);
    const res = await apiCall('./php/contact.php', {
      name:    form.querySelector('[name="name"]')?.value    ?? '',
      email:   form.querySelector('[name="email"]')?.value   ?? '',
      phone:   form.querySelector('[name="phone"]')?.value   ?? '',
      message: form.querySelector('[name="message"]')?.value ?? '',
    });
    setLoading(btn, false);
    if (res.success) {
      ToastManager.show('Message sent! We\'ll be in touch soon.', 'success');
      form.reset();
    } else {
      if (res.fields) {
        showFieldErrors(res.fields);
      } else {
        ToastManager.show(res.error || 'Something went wrong', 'error');
      }
    }
  });
}

// ── Alert helper ──────────────────────────────────────────
function showAlert(containerId, message, type = 'error') {
  const el = document.getElementById(containerId);
  if (!el) return;
  const div = document.createElement('div');
  div.className = `alert alert-${escHtml(type)}`;
  div.textContent = message;   // textContent — XSS safe
  el.innerHTML = '';
  el.appendChild(div);
}

// ── Field-level error display ─────────────────────────────
function showFieldErrors(fields) {
  // Clear previous field errors
  document.querySelectorAll('.field-error').forEach(el => el.remove());
  document.querySelectorAll('.form-control.error').forEach(el => el.classList.remove('error'));
  if (!fields || typeof fields !== 'object') return;
  Object.entries(fields).forEach(([name, msg]) => {
    // Find input by name or id
    const input = document.querySelector(`[name="${CSS.escape(name)}"]`) || document.getElementById(name);
    if (!input) return;
    input.classList.add('error');
    const err = document.createElement('div');
    err.className = 'field-error';
    err.textContent = msg;
    input.closest('.form-group')?.appendChild(err);
  });
  // Focus first error field
  const firstErr = document.querySelector('.form-control.error');
  if (firstErr) firstErr.focus();
}

function clearFieldErrors() {
  document.querySelectorAll('.field-error').forEach(el => el.remove());
  document.querySelectorAll('.form-control.error').forEach(el => el.classList.remove('error'));
}

// ── Init ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  ToastManager.init();
  initNavbar();
  initMobileMenu();
  initServiceCards();
  initScrollAnimations();
  initPasswordToggles();
  initOTPInputs();
  initContactForm();
});

// Inject exit animation keyframe once
const _ks = document.createElement('style');
_ks.textContent = '@keyframes slideOutRight{to{opacity:0;transform:translateX(32px)}}';
document.head.appendChild(_ks);
