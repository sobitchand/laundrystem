<?php
require_once __DIR__ . '/../php/config.php';
sendSecurityHeaders();
requireLogin();
$db = getDB();
$userId = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);

$isAdmin = isAdminLoggedIn();
if (!$isAdmin && $userId <= 0) { header('Location: login.php'); exit; }

$stmt = $db->prepare("SELECT o.*,p.status AS pay_status,p.amount AS pay_amount,p.method AS pay_method,p.paid_at,
    u.full_name AS customer_name,u.email AS customer_email,u.phone AS customer_phone,u.address AS customer_address
    FROM orders o
    JOIN users u ON o.user_id=u.id
    LEFT JOIN payments p ON p.order_id=o.id
    WHERE o.id=?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) { http_response_code(404); die('Invoice not found'); }

// Only the owner or admin can view
if (!$isAdmin && (int)$order['user_id'] !== $userId) { http_response_code(403); die('Access denied'); }

$items = $db->prepare("SELECT oi.*,ct.name AS cloth_name,ct.service_type
    FROM order_items_v2 oi JOIN cloth_types ct ON oi.cloth_type_id=ct.id WHERE oi.order_id=?");
$items->execute([$orderId]);
$order['items'] = $items->fetchAll();

$statusLabels = [
    'pending'=>'Received','confirmed'=>'Confirmed','picked_up'=>'Picked Up',
    'in_process'=>'Cleaning','ready'=>'Ready','delivered'=>'Delivered','cancelled'=>'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice <?= htmlspecialchars($order['invoice_number']) ?> — DD Laundry</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DM Sans',sans-serif; background:#f5f0eb; color:#2d2d2d; padding:24px; }
    .invoice { max-width:720px; margin:0 auto; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 2px 20px rgba(0,0,0,.08); }
    .invoice-header { background:#922B21; color:#fff; padding:32px 36px; display:flex; justify-content:space-between; align-items:flex-start; }
    .invoice-header h1 { font-family:'Playfair Display',serif; font-size:1.5rem; margin-bottom:4px; }
    .invoice-header .inv-num { font-size:1rem; opacity:.9; }
    .invoice-header .logo { font-size:1.8rem; }
    .invoice-body { padding:32px 36px; }
    .invoice-meta { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    .invoice-meta h3 { font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; color:#888; margin-bottom:4px; }
    .invoice-meta p { font-size:.9rem; }
    .invoice-table { width:100%; border-collapse:collapse; margin:20px 0; }
    .invoice-table th { text-align:left; padding:10px 12px; border-bottom:2px solid #922B21; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em; color:#922B21; }
    .invoice-table td { padding:10px 12px; border-bottom:1px solid #eee; font-size:.88rem; }
    .invoice-table .num { text-align:right; }
    .invoice-totals { margin-top:16px; text-align:right; }
    .invoice-totals .row { display:flex; justify-content:flex-end; gap:24px; padding:6px 0; font-size:.9rem; }
    .invoice-totals .row.total { font-size:1.1rem; font-weight:700; color:#922B21; border-top:2px solid #eee; padding-top:10px; }
    .invoice-footer { padding:20px 36px; background:#faf7f4; font-size:.8rem; color:#888; text-align:center; }
    .status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.75rem; font-weight:600; }
    .status-paid { background:#d4edda; color:#155724; }
    .status-pending { background:#fff3cd; color:#856404; }
    .status-refunded { background:#f8d7da; color:#721c24; }
    @media print {
      body { background:#fff; padding:0; }
      .invoice { box-shadow:none; border-radius:0; }
      .no-print { display:none !important; }
    }
  </style>
</head>
<body>
<div class="invoice">
  <div class="invoice-header">
    <div>
      <div class="logo">&#x1F9BA;</div>
      <h1>DD Laundry</h1>
      <p style="font-size:.85rem;opacity:.85;">Imadol, Lalitpur, Nepal &middot; +977 9749863285</p>
    </div>
    <div style="text-align:right;">
      <div class="inv-num">INVOICE</div>
      <div style="font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;">
        <?= htmlspecialchars($order['invoice_number']) ?>
      </div>
      <div style="font-size:.85rem;opacity:.85;margin-top:4px;">
        <?= date('M d, Y', strtotime($order['created_at'])) ?>
      </div>
    </div>
  </div>

  <div class="invoice-body">
    <div class="invoice-meta">
      <div>
        <h3>Billed To</h3>
        <p><strong><?= htmlspecialchars($order['customer_name']) ?></strong></p>
        <p><?= htmlspecialchars($order['customer_email']) ?></p>
        <p><?= htmlspecialchars($order['customer_phone'] ?? '') ?></p>
        <p><?= htmlspecialchars($order['customer_address'] ?? '') ?></p>
      </div>
      <div>
        <h3>Order Details</h3>
        <p>Order #: <?= htmlspecialchars($order['order_number']) ?></p>
        <p>Status: <strong><?= $statusLabels[$order['status']] ?? $order['status'] ?></strong></p>
        <p>Payment: <?= htmlspecialchars(ucfirst($order['pay_method'] ?? 'cash')) ?>
          <span class="status-badge status-<?= $order['pay_status'] === 'paid' ? 'paid' : ($order['pay_status'] === 'refunded' ? 'refunded' : 'pending') ?>">
            <?= htmlspecialchars(ucfirst($order['pay_status'] ?? 'pending')) ?>
          </span>
        </p>
        <?php if ($order['paid_at']): ?>
        <p>Paid on: <?= date('M d, Y H:i', strtotime($order['paid_at'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <table class="invoice-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Service</th>
          <th class="num">Qty</th>
          <th class="num">Unit Price</th>
          <th class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($order['items'] as $item): ?>
        <tr>
          <td><?= htmlspecialchars($item['cloth_name']) ?></td>
          <td><?= htmlspecialchars($item['service_type']) ?></td>
          <td class="num"><?= (int)$item['quantity'] ?></td>
          <td class="num">NPR <?= number_format((float)$item['unit_price_snapshot'], 2) ?></td>
          <td class="num">NPR <?= number_format((float)$item['line_total'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="invoice-totals">
      <div class="row">
        <span>Subtotal:</span>
        <span>NPR <?= number_format((float)$order['subtotal'], 2) ?></span>
      </div>
      <?php if ((float)$order['discount'] > 0): ?>
      <div class="row">
        <span>Discount:</span>
        <span>−NPR <?= number_format((float)$order['discount'], 2) ?></span>
      </div>
      <?php endif; ?>
      <div class="row total">
        <span>Total:</span>
        <span>NPR <?= number_format((float)$order['total_amount'], 2) ?></span>
      </div>
    </div>
  </div>

  <div class="invoice-footer">
    <p>Thank you for choosing DD Laundry. We appreciate your business.</p>
    <p style="margin-top:4px;">This is a computer-generated invoice and does not require a signature.</p>
  </div>
</div>

<div class="no-print" style="text-align:center;margin-top:20px;">
  <button onclick="window.print()" style="padding:10px 28px;background:#922B21;color:#fff;border:none;border-radius:8px;font-size:.9rem;cursor:pointer;font-family:'DM Sans',sans-serif;">
    Print Invoice
  </button>
  <button onclick="window.history.back()" style="padding:10px 28px;background:#eee;color:#333;border:none;border-radius:8px;font-size:.9rem;cursor:pointer;font-family:'DM Sans',sans-serif;margin-left:8px;">
    Go Back
  </button>
</div>
</body>
</html>
