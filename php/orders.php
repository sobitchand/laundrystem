<?php
// ============================================================
// DD Laundry - Orders API (OWASP Hardened)
// php/orders.php
// A01: CSRF on mutations; user scoped queries (no IDOR)
// A03: Parameterised queries; JSON items server-validated
// A04: Item count + quantity limits
// A05: Security headers
// A07: requireLogin + session regeneration
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) jsonResponse(['error' => 'Authentication required'], 401);

// Read-only GET actions (no CSRF needed)
$ga = $_GET['action'] ?? '';
if ($ga === 'get_orders')      { getUserOrders();      exit; }
if ($ga === 'get_order')       { getOrderDetails();     exit; }
if ($ga === 'get_cloth_types') { getClothTypes();       exit; }
if ($ga === 'get_services')    { getServices();         exit; }
if ($ga === 'get_invoice')     { getInvoice();          exit; }

// Mutations — require CSRF
$action = $_POST['action'] ?? '';
if ($action === 'place')            { requireCSRF(); placeOrder();       }
elseif ($action === 'submit_feedback') { requireCSRF(); submitFeedback(); }
else jsonResponse(['error' => 'Invalid action'], 400);

// ──────────────────────────────────────────────────────────
function placeOrder() {
    $db     = getDB();
    $userId = (int)$_SESSION['user_id'];

    $rawItems = $_POST['items'] ?? '[]';
    $items = json_decode($rawItems, true);
    if (!is_array($items) || empty($items)) jsonResponse(['error' => 'No items in order'], 400);
    if (count($items) > MAX_ORDER_ITEMS)    jsonResponse(['error' => 'Too many items in one order'], 400);

    $pickup       = sanitize($_POST['pickup_address']   ?? '');
    $delivery     = sanitize($_POST['delivery_address'] ?? '');
    $pickupDate   = validateDate($_POST['pickup_date']   ?? '');
    $deliveryDate = validateDate($_POST['delivery_date'] ?? '');
    $notes        = sanitize($_POST['notes']             ?? '');
    $payment      = validatePaymentMethod($_POST['payment_method'] ?? 'cash');
    $pickupLat    = filter_var($_POST['pickup_lat'] ?? '', FILTER_VALIDATE_FLOAT);
    $pickupLng    = filter_var($_POST['pickup_lng'] ?? '', FILTER_VALIDATE_FLOAT);
    $deliveryLat  = filter_var($_POST['delivery_lat'] ?? '', FILTER_VALIDATE_FLOAT);
    $deliveryLng  = filter_var($_POST['delivery_lng'] ?? '', FILTER_VALIDATE_FLOAT);

    $errors = [];
    if (!$pickup)               $errors['pickup_address'] = 'Pickup address is required';
    if (strlen($pickup) > 500)  $errors['pickup_address'] = 'Pickup address too long';
    if (!$pickupDate)           $errors['pickup_date'] = 'Pickup date is required';
    if ($delivery && strlen($delivery) > 500) $errors['delivery_address'] = 'Delivery address too long';
    if (strlen($notes)  > 1000) $errors['notes'] = 'Notes too long';
    if (!empty($errors)) jsonResponse(['error' => 'Validation failed', 'fields' => $errors], 400);

    $subtotal = 0.0;
    $validatedItems = [];
    $seenItems      = [];

    foreach ($items as $item) {
        $clothId = (int)($item['cloth_type_id'] ?? 0);
        $qty     = (int)($item['quantity']      ?? 0);
        if ($clothId <= 0 || $qty <= 0) continue;
        if ($qty > MAX_QTY_PER_ITEM)  jsonResponse(['error' => 'Quantity too large for one item (max ' . MAX_QTY_PER_ITEM . ')'], 400);
        if (in_array($clothId, $seenItems, true)) jsonResponse(['error' => 'Duplicate item in order'], 400);
        $seenItems[] = $clothId;

        // Server-side price lookup — never trust client price
        $stmt = $db->prepare("SELECT id, name, service_type, unit_price FROM cloth_types WHERE id = ? AND is_active = 1");
        $stmt->execute([$clothId]);
        $ct = $stmt->fetch();
        if (!$ct) jsonResponse(['error' => 'Invalid cloth type selected'], 400);

        $lineTotal = round((float)$ct['unit_price'] * $qty, 2);
        $subtotal += $lineTotal;
        $validatedItems[] = [
            'cloth_type_id' => $clothId,
            'name'          => $ct['name'],
            'service_type'  => $ct['service_type'],
            'quantity'      => $qty,
            'unit_price'    => (float)$ct['unit_price'],
            'line_total'    => $lineTotal,
        ];
    }

    if (empty($validatedItems)) jsonResponse(['error' => 'No valid items in order'], 400);

    $discount  = 0.0;
    $total     = round($subtotal - $discount, 2);
    $orderNum  = generateOrderNumber();
    $invoiceNum = generateInvoiceNumber();

    $stmt = $db->prepare("INSERT INTO orders
        (order_number,invoice_number,user_id,subtotal,discount,total_amount,pickup_address,delivery_address,pickup_date,delivery_date,notes,payment_method,pickup_lat,pickup_lng,delivery_lat,delivery_lng)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$orderNum,$invoiceNum,$userId,$subtotal,$discount,$total,$pickup,$delivery,$pickupDate,$deliveryDate,$notes,$payment,
        $pickupLat ?: null,$pickupLng ?: null,$deliveryLat ?: null,$deliveryLng ?: null]);
    $orderId = (int)$db->lastInsertId();

    // Insert order items into order_items_v2
    $ist = $db->prepare("INSERT INTO order_items_v2 (order_id,cloth_type_id,quantity,unit_price_snapshot,line_total) VALUES (?,?,?,?,?)");
    foreach ($validatedItems as $vi) {
        $ist->execute([$orderId,$vi['cloth_type_id'],$vi['quantity'],$vi['unit_price'],$vi['line_total']]);
    }

    // Create payment record (COD — pending until delivered)
    $db->prepare("INSERT INTO payments (order_id,method,amount,status) VALUES (?,?,?,?)")
       ->execute([$orderId,'cash',$total,'pending']);

    // Status history
    $db->prepare("INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)")
       ->execute([$orderId,'pending','Order placed by customer']);

    $uStmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $uStmt->execute([$userId]);
    $ud = $uStmt->fetch();
    if ($ud) sendOrderStatusEmail($ud['email'], $ud['full_name'], $orderNum, 'pending');

    securityLog('ORDER_PLACED', ['user_id'=>$userId,'order'=>$orderNum,'invoice'=>$invoiceNum,'total'=>$total]);
    jsonResponse([
        'success'=>true,
        'message'=>'Order placed successfully!',
        'order_number'=>$orderNum,
        'invoice_number'=>$invoiceNum,
        'order_id'=>$orderId,
        'total'=>$total
    ]);
}

// ──────────────────────────────────────────────────────────
function getUserOrders() {
    $db     = getDB();
    $userId = (int)$_SESSION['user_id'];
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 10;
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare("SELECT o.id,o.order_number,o.invoice_number,o.total_amount,o.status,o.payment_method,
        o.payment_status,o.created_at,o.pickup_date,o.delivery_date,o.pickup_lat,o.pickup_lng,
        (SELECT COUNT(*) FROM order_items_v2 WHERE order_id=o.id) AS item_count,
        p.status AS payment_status
        FROM orders o
        LEFT JOIN payments p ON p.order_id=o.id
        WHERE o.user_id=? ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$userId,$limit,$offset]);
    $orders = $stmt->fetchAll();

    $cnt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
    $cnt->execute([$userId]);
    $total = (int)$cnt->fetchColumn();

    jsonResponse(['success'=>true,'orders'=>$orders,'total'=>$total,'pages'=>(int)ceil($total/$limit)]);
}

// ──────────────────────────────────────────────────────────
function getOrderDetails() {
    $db      = getDB();
    $userId  = (int)$_SESSION['user_id'];
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) jsonResponse(['error'=>'Invalid order ID'],400);

    $stmt = $db->prepare("SELECT o.*,p.status AS pay_status,p.amount AS pay_amount,p.method AS pay_method,p.transaction_ref,p.paid_at
        FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?");
    $stmt->execute([$orderId,$userId]);
    $order = $stmt->fetch();
    if (!$order) jsonResponse(['error'=>'Order not found'],404);

    $items = $db->prepare("SELECT oi.*,ct.name AS cloth_name,ct.service_type
        FROM order_items_v2 oi JOIN cloth_types ct ON oi.cloth_type_id=ct.id WHERE oi.order_id=?");
    $items->execute([$orderId]);
    $order['items'] = $items->fetchAll();

    $hist = $db->prepare("SELECT * FROM order_status_history WHERE order_id=? ORDER BY changed_at ASC");
    $hist->execute([$orderId]);
    $order['history'] = $hist->fetchAll();

    jsonResponse(['success'=>true,'order'=>$order]);
}

// ──────────────────────────────────────────────────────────
function getClothTypes() {
    $db   = getDB();
    $stmt = $db->query("SELECT id,name,service_type,unit_price FROM cloth_types WHERE is_active=1 ORDER BY service_type,id");
    $rows = $stmt->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['service_type']][] = $r;
    }
    jsonResponse(['success'=>true,'cloth_types'=>$grouped]);
}

// ──────────────────────────────────────────────────────────
function getServices() {
    $db   = getDB();
    $stmt = $db->query("SELECT id,name,description,price,unit,icon FROM services WHERE is_active=1 ORDER BY id");
    jsonResponse(['success'=>true,'services'=>$stmt->fetchAll()]);
}

// ──────────────────────────────────────────────────────────
function submitFeedback() {
    $db      = getDB();
    $userId  = (int)$_SESSION['user_id'];
    $rating  = (int)($_POST['rating'] ?? 0);
    $message = sanitize($_POST['message'] ?? '');

    if ($rating < 1 || $rating > 5) jsonResponse(['error' => 'Please select a valid rating (1-5)'], 400);
    if (!$message)                  jsonResponse(['error' => 'Feedback message is required'], 400);
    if (strlen($message) > 1000)    jsonResponse(['error' => 'Message too long'], 400);

    $name = $_SESSION['user_name'] ?? 'Customer';
    $db->prepare("INSERT INTO feedback (user_id,name,rating,message) VALUES (?,?,?,?)")
       ->execute([$userId,$name,$rating,$message]);

    securityLog('FEEDBACK_SUBMITTED',['user_id'=>$userId,'rating'=>$rating]);
    jsonResponse(['success'=>true,'message'=>'Thank you for your feedback!']);
}

// ──────────────────────────────────────────────────────────
function getInvoice() {
    $db      = getDB();
    $userId  = (int)$_SESSION['user_id'];
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) jsonResponse(['error'=>'Invalid order ID'],400);

    $stmt = $db->prepare("SELECT o.*,p.status AS pay_status,p.amount AS pay_amount,p.method AS pay_method,p.paid_at
        FROM orders o LEFT JOIN payments p ON p.order_id=o.id WHERE o.id=? AND o.user_id=?");
    $stmt->execute([$orderId,$userId]);
    $order = $stmt->fetch();
    if (!$order) jsonResponse(['error'=>'Order not found'],404);

    $items = $db->prepare("SELECT oi.*,ct.name AS cloth_name,ct.service_type
        FROM order_items_v2 oi JOIN cloth_types ct ON oi.cloth_type_id=ct.id WHERE oi.order_id=?");
    $items->execute([$orderId]);
    $order['items'] = $items->fetchAll();

    $uStmt = $db->prepare("SELECT full_name, email, phone, address FROM users WHERE id = ?");
    $uStmt->execute([$order['user_id']]);
    $order['customer'] = $uStmt->fetch();

    jsonResponse(['success'=>true,'order'=>$order]);
}
