<?php
// ============================================================
// DD Laundry - Admin API (OWASP Hardened)
// A01: Separate admin session; requireAdmin() on all endpoints
// A03: PDO prepared statements; whitelist status values
// A04: Parameterised dashboard queries (no raw date injection)
// A05: Security headers
// A07: CSRF; rate-limited admin login; session regeneration
// A09: Security logging
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

// Public: login only (POST + CSRF)
if ($action === 'admin_login')  { requireCSRF(); handleAdminLogin();  exit; }
if ($action === 'admin_logout') { requireCSRF(); handleAdminLogout(); exit; }

// All other endpoints: must be authenticated admin
if (!isAdminLoggedIn()) jsonResponse(['error'=>'Admin authentication required'],401);

// Read-only GETs
$ga = $_GET['action'] ?? '';
if ($ga === 'get_orders')       { adminGetOrders();       exit; }
if ($ga === 'get_order_detail') { adminGetOrderDetail();  exit; }
if ($ga === 'get_dashboard')    { getDashboard();         exit; }
if ($ga === 'get_messages')     { getMessages();          exit; }
if ($ga === 'get_customer')     { getCustomerProfile();   exit; }
if ($ga === 'get_all_services') { adminGetAllServices();  exit; }
if ($ga === 'get_customers')    { adminGetCustomers();    exit; }
if ($ga === 'get_feedback')     { adminGetFeedback();     exit; }
if ($ga === 'get_invoice')      { adminGetInvoice();      exit; }
if ($ga === 'get_new_users')    { adminGetNewUsers();     exit; }

// Mutations (POST + CSRF)
switch ($action) {
    case 'update_status':   requireCSRF(); adminUpdateStatus();   break;
    case 'update_payment':  requireCSRF(); adminUpdatePayment();  break;
    case 'change_password': requireCSRF(); adminChangePassword(); break;
    case 'add_service':     requireCSRF(); adminAddService();     break;
    case 'update_service':  requireCSRF(); adminUpdateService();  break;
    case 'toggle_service':  requireCSRF(); adminToggleService();  break;
    case 'delete_service':  requireCSRF(); adminDeleteService();  break;
    case 'approve_feedback': requireCSRF(); adminApproveFeedback(); break;
    case 'reject_feedback':  requireCSRF(); adminRejectFeedback();   break;
    case 'delete_feedback':  requireCSRF(); adminDeleteFeedback();   break;
    case 'delete_order':     requireCSRF(); adminDeleteOrder();     break;
    case 'delete_user':      requireCSRF(); adminDeleteUser();      break;
    default: jsonResponse(['error'=>'Invalid action'],400);
}

// ──────────────────────────────────────────────────────────
/**
 * Handle admin login with rate limiting.
 * 
 * Authenticates admin by username or email.
 * Uses constant-time password verification to prevent timing attacks.
 * Regenerates session ID on success.
 * 
 * @return void Sends JSON response with redirect
 */
function handleAdminLogin() {
    $db   = getDB();
    $user = sanitize($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (!$user || !$pass) jsonResponse(['error'=>'Username and password required'],400);
    if (strlen($pass) > 128) jsonResponse(['error'=>'Invalid credentials'],400);

    $ip = filter_var($_SERVER['REMOTE_ADDR']??'',FILTER_VALIDATE_IP)?:'unknown';
    checkRateLimit("admin_login_{$ip}", RATE_LIMIT_MAX_LOGIN, RATE_LIMIT_WINDOW);

    $stmt = $db->prepare("SELECT id,username,email,password_hash FROM admins WHERE username=? OR email=?");
    $stmt->execute([$user,$user]);
    $admin = $stmt->fetch();

    $hash  = $admin['password_hash'] ?? '$2y$12$invalidhashpaddinginvalidpad0000000000000000000000000000';
    $valid = password_verify($pass,$hash);

    if (!$admin || !$valid) {
        securityLog('ADMIN_LOGIN_FAILED',['username'=>$user,'ip'=>$ip]);
        jsonResponse(['error'=>'Invalid credentials'],401);
    }

    session_regenerate_id(true);
    $_SESSION['admin_id']         = (int)$admin['id'];
    $_SESSION['admin_name']       = $admin['username'];
    $_SESSION['admin_last_regen'] = time();

    clearRateLimit("admin_login_{$ip}");
    securityLog('ADMIN_LOGIN_SUCCESS',['username'=>$user,'ip'=>$ip]);
    jsonResponse(['success'=>true,'redirect'=>SITE_URL.'/admin/index.php']);
}

/**
 * Handle admin logout.
 * 
 * Clears admin session and logs event.
 * 
 * @return void Sends JSON response with redirect
 */
function handleAdminLogout() {
    $adminId = $_SESSION['admin_id'] ?? null;
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
    securityLog('ADMIN_LOGOUT',['admin_id'=>$adminId]);
    jsonResponse(['success'=>true,'redirect'=>SITE_URL.'/admin/login.php']);
}

// ──────────────────────────────────────────────────────────
/**
 * Get paginated orders with filtering and search.
 * 
 * Supports status filter and text search (order number, invoice,
 * customer name, email). Returns customer info and item count.
 * 
 * @return void Sends JSON response with orders and pagination
 */
function adminGetOrders() {
    $db     = getDB();
    $status = sanitize($_GET['status'] ?? '');
    $search = sanitize($_GET['search'] ?? '');
    $page   = max(1,(int)($_GET['page']??1));
    $limit  = 15;
    $offset = ($page-1)*$limit;

    $validStatuses = ['pending','confirmed','picked_up','in_process','ready','delivered','cancelled'];
    if ($status && !in_array($status,$validStatuses,true)) $status = '';

    $where  = '1=1';
    $params = [];

    if ($status) {
        $where   .= ' AND o.status=?';
        $params[] = $status;
    }
    if ($search) {
        $where   .= ' AND (o.order_number LIKE ? OR o.invoice_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $s        = '%'.addcslashes($search,'%_\\').'%';
        $params   = array_merge($params,[$s,$s,$s,$s]);
    }

    $stmt = $db->prepare("SELECT o.id,o.order_number,o.invoice_number,o.total_amount,o.status,o.payment_method,
        o.payment_status,o.created_at,o.pickup_date,o.delivery_date,o.pickup_lat,o.pickup_lng,
        u.full_name AS customer_name,u.email AS customer_email,u.phone AS customer_phone,u.id AS customer_id,
        (SELECT COUNT(*) FROM order_items_v2 WHERE order_id=o.id) AS item_count,
        p.status AS pay_status
        FROM orders o JOIN users u ON o.user_id=u.id
        LEFT JOIN payments p ON p.order_id=o.id
        WHERE {$where} ORDER BY o.created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params,[$limit,$offset]));
    $orders = $stmt->fetchAll();

    $cst = $db->prepare("SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id=u.id WHERE {$where}");
    $cst->execute($params);
    $total = (int)$cst->fetchColumn();

    jsonResponse(['success'=>true,'orders'=>$orders,'total'=>$total,'pages'=>(int)ceil($total/$limit)]);
}

// ──────────────────────────────────────────────────────────
/**
 * Get complete order details for admin.
 * 
 * Returns order with customer info, items (with cloth type names),
 * status history, and payment details.
 * 
 * @return void Sends JSON response with order data
 */
function adminGetOrderDetail() {
    $db      = getDB();
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) jsonResponse(['error'=>'Invalid order ID'],400);

    $stmt = $db->prepare("SELECT o.*,p.status AS pay_status,p.amount AS pay_amount,p.method AS pay_method,p.transaction_ref,p.paid_at,
        u.full_name AS customer_name,u.email AS customer_email,u.phone AS customer_phone,u.address AS customer_address
        FROM orders o
        JOIN users u ON o.user_id=u.id
        LEFT JOIN payments p ON p.order_id=o.id
        WHERE o.id=?");
    $stmt->execute([$orderId]);
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
/**
 * Update order status (admin only).
 * 
 * Validates status against whitelist, updates order, logs to history,
 * sends email notification to customer.
 * Auto-marks COD payments as paid when delivered.
 * 
 * @return void Sends JSON response
 */
function adminUpdateStatus() {
    $db      = getDB();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = sanitize($_POST['status'] ?? '');
    $note    = sanitize($_POST['note']   ?? '');

    $validStatuses = ['pending','confirmed','picked_up','in_process','ready','delivered','cancelled'];
    if (!in_array($status,$validStatuses,true)) jsonResponse(['error'=>'Invalid status'],400);
    if ($orderId <= 0)                           jsonResponse(['error'=>'Order ID required'],400);
    if (strlen($note) > 500)                     jsonResponse(['error'=>'Note too long'],400);

    $stmt = $db->prepare("SELECT o.order_number,u.full_name,u.email,o.payment_method
        FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) jsonResponse(['error'=>'Order not found'],404);

    $db->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$status,$orderId]);
    $db->prepare("INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)")
       ->execute([$orderId,$status,$note]);

    // COD: when delivered, mark payment as paid
    if ($status === 'delivered' && $order['payment_method'] === 'cash') {
        $db->prepare("UPDATE payments SET status='paid',paid_at=NOW() WHERE order_id=? AND status='pending'")
           ->execute([$orderId]);
        $db->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")->execute([$orderId]);
    }

    sendOrderStatusEmail($order['email'],$order['full_name'],$order['order_number'],$status,$note);

    securityLog('ORDER_STATUS_UPDATED',[
        'admin_id'=>$_SESSION['admin_id'],
        'order_id'=>$orderId,
        'new_status'=>$status,
    ]);
    jsonResponse(['success'=>true,'message'=>'Status updated and customer notified.']);
}

// ──────────────────────────────────────────────────────────
/**
 * Update payment status (admin only).
 * 
 * Updates payment record and order payment_status.
 * Sets paid_at timestamp when marking as paid.
 * 
 * @return void Sends JSON response
 */
function adminUpdatePayment() {
    $db      = getDB();
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status  = sanitize($_POST['payment_status'] ?? '');

    $validStatuses = ['pending','paid','failed','refunded'];
    if (!in_array($status,$validStatuses,true)) jsonResponse(['error'=>'Invalid payment status'],400);
    if ($orderId <= 0) jsonResponse(['error'=>'Order ID required'],400);

    $stmt = $db->prepare("SELECT o.order_number,u.full_name,u.email
        FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) jsonResponse(['error'=>'Order not found'],404);

    $paidAt = $status === 'paid' ? date('Y-m-d H:i:s') : null;
    $db->prepare("UPDATE payments SET status=?,paid_at=? WHERE order_id=?")
       ->execute([$status,$paidAt,$orderId]);
    $db->prepare("UPDATE orders SET payment_status=? WHERE id=?")
       ->execute([$status,$orderId]);

    securityLog('PAYMENT_UPDATED',['admin_id'=>$_SESSION['admin_id'],'order_id'=>$orderId,'new_status'=>$status]);
    jsonResponse(['success'=>true,'message'=>'Payment status updated.']);
}

// ──────────────────────────────────────────────────────────
/**
 * Get admin dashboard statistics.
 * 
 * Returns: today/month/total revenue, order counts, pending orders,
 * user count, status breakdown, 7-day revenue trend, top 5 services.
 * 
 * @return void Sends JSON response with dashboard data
 */
function getDashboard() {
    $db    = getDB();
    $today = date('Y-m-d');
    $month = date('Y-m');

    $todayRev  = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(created_at)=? AND status!='cancelled'");
    $todayRev->execute([$today]); $todayRev = (float)$todayRev->fetchColumn();

    $monthRev  = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE_FORMAT(created_at,'%Y-%m')=? AND status!='cancelled'");
    $monthRev->execute([$month]); $monthRev = (float)$monthRev->fetchColumn();

    $totalRev  = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status!='cancelled'")->fetchColumn();
    $totalOrds = (int)$db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    $todayOrds = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=?");
    $todayOrds->execute([$today]); $todayOrds = (int)$todayOrds->fetchColumn();

    $pendOrds  = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
    $totalUsers= (int)$db->query("SELECT COUNT(*) FROM users WHERE is_verified=1")->fetchColumn();

    $statusCounts = $db->query("SELECT status,COUNT(*) AS count FROM orders GROUP BY status")->fetchAll();

    $rev7 = $db->query("SELECT DATE(created_at) AS date,SUM(total_amount) AS revenue
        FROM orders WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) AND status!='cancelled'
        GROUP BY DATE(created_at) ORDER BY date")->fetchAll();

    $topSvc = $db->query("SELECT ct.service_type AS name,SUM(oi.quantity) AS total_qty,SUM(oi.line_total) AS total_rev
        FROM order_items_v2 oi JOIN cloth_types ct ON oi.cloth_type_id=ct.id
        GROUP BY ct.service_type ORDER BY total_rev DESC LIMIT 5")->fetchAll();

    jsonResponse([
        'success'=>true,
        'stats'=>[
            'today_revenue' =>$todayRev,'month_revenue'=>$monthRev,'total_revenue'=>$totalRev,
            'total_orders'  =>$totalOrds,'today_orders'  =>$todayOrds,
            'pending_orders'=>$pendOrds,'total_users'   =>$totalUsers,
        ],
        'status_counts' =>$statusCounts,
        'recent_revenue'=>$rev7,
        'top_services'  =>$topSvc,
    ]);
}

// ──────────────────────────────────────────────────────────
/**
 * Get recently verified users (for admin notifications).
 * 
 * Tracks last seen user ID in session to show only new users.
 * Returns up to 10 most recent verified users.
 * 
 * @return void Sends JSON response with new users array
 */
function adminGetNewUsers() {
    $db = getDB();
    $lastSeen = (int)($_SESSION['admin_last_seen_user_id'] ?? 0);

    $stmt = $db->prepare("SELECT id, full_name, email, created_at FROM users WHERE is_verified=1 AND id > ? ORDER BY id ASC LIMIT 10");
    $stmt->execute([$lastSeen]);
    $users = $stmt->fetchAll();

    if (count($users)) {
        $maxId = (int)$users[count($users)-1]['id'];
        $_SESSION['admin_last_seen_user_id'] = $maxId;
    }

    jsonResponse(['success'=>true,'new_users'=>$users]);
}

// ──────────────────────────────────────────────────────────
/**
 * Change admin password.
 * 
 * Validates current password, then updates with bcrypt hash.
 * Logs security event on failure.
 * 
 * @return void Sends JSON response
 */
function adminChangePassword() {
    $db      = getDB();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$current||!$new||!$confirm) jsonResponse(['error'=>'All fields required'],400);
    if ($new !== $confirm)           jsonResponse(['error'=>'Passwords do not match'],400);
    if (strlen($new) < 8)            jsonResponse(['error'=>'Min 8 characters'],400);
    if (strlen($new) > 128)          jsonResponse(['error'=>'Password too long'],400);

    $stmt = $db->prepare("SELECT password_hash FROM admins WHERE id=?");
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($current,$admin['password_hash'])) {
        securityLog('ADMIN_CHANGE_PASS_FAILED',['admin_id'=>$_SESSION['admin_id']]);
        jsonResponse(['error'=>'Current password is incorrect'],400);
    }

    $hash = password_hash($new,PASSWORD_BCRYPT,['cost'=>12]);
    $db->prepare("UPDATE admins SET password_hash=? WHERE id=?")->execute([$hash,(int)$_SESSION['admin_id']]);
    securityLog('ADMIN_PASSWORD_CHANGED',['admin_id'=>$_SESSION['admin_id']]);
    jsonResponse(['success'=>true,'message'=>'Password updated successfully!']);
}

// ──────────────────────────────────────────────────────────
/**
 * Get contact form messages.
 * 
 * Returns 50 most recent messages from contact_messages table.
 * 
 * @return void Sends JSON response with messages array
 */
function getMessages() {
    $db   = getDB();
    $stmt = $db->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 50");
    jsonResponse(['success'=>true,'messages'=>$stmt->fetchAll()]);
}

// ──────────────────────────────────────────────────────────
/**
 * Get customer profile with order history (admin view).
 * 
 * Returns user info, stats (total orders, total spent, status counts),
 * and 20 most recent orders.
 * 
 * @return void Sends JSON response with customer data
 */
function getCustomerProfile() {
    $db         = getDB();
    $customerId = (int)($_GET['id'] ?? 0);
    if ($customerId <= 0) jsonResponse(['error'=>'Invalid customer ID'],400);

    $stmt = $db->prepare("SELECT id,full_name,email,phone,address,created_at FROM users WHERE id=?");
    $stmt->execute([$customerId]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error'=>'Customer not found'],404);

    $totalOrders = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
    $totalOrders->execute([$customerId]);
    $totalOrders = (int)$totalOrders->fetchColumn();

    $totalSpent = $db->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=? AND status!='cancelled'");
    $totalSpent->execute([$customerId]);
    $totalSpent = (float)$totalSpent->fetchColumn();

    $statusCounts = $db->prepare("SELECT status,COUNT(*) AS count FROM orders WHERE user_id=? GROUP BY status");
    $statusCounts->execute([$customerId]);
    $statusCounts = $statusCounts->fetchAll();

    $orders = $db->prepare("SELECT id,order_number,invoice_number,total_amount,status,payment_method,payment_status,created_at,pickup_date,delivery_date,
        pickup_address,delivery_address,pickup_lat,pickup_lng,
        (SELECT COUNT(*) FROM order_items_v2 WHERE order_id=o.id) AS item_count
        FROM orders o WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $orders->execute([$customerId]);
    $orders = $orders->fetchAll();

    jsonResponse([
        'success'=>true,
        'user'=>$user,
        'stats'=>[
            'total_orders'=>$totalOrders,
            'total_spent'=>$totalSpent,
            'status_counts'=>$statusCounts,
        ],
        'orders'=>$orders,
    ]);
}

// ── Customers List ──────────────────────────────────────────
/**
 * Get paginated customer list with search.
 * 
 * Searches by name, email, or phone. Includes order count and
 * total spent per customer.
 * 
 * @return void Sends JSON response with customers and pagination
 */
function adminGetCustomers() {
    $db     = getDB();
    $search = sanitize($_GET['search'] ?? '');
    $page   = max(1,(int)($_GET['page']??1));
    $limit  = 15;
    $offset = ($page-1)*$limit;

    $where = '1=1';
    $params = [];
    if ($search) {
        $where .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $s = '%'.addcslashes($search,'%_\\').'%';
        $params = [$s,$s,$s];
    }

    $stmt = $db->prepare("SELECT u.id,u.full_name,u.email,u.phone,u.address,u.created_at,u.is_verified,
        (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS order_count,
        (SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id=u.id AND status!='cancelled') AS total_spent
        FROM users u WHERE {$where} ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute(array_merge($params,[$limit,$offset]));
    $customers = $stmt->fetchAll();

    $cst = $db->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
    $cst->execute($params);
    $total = (int)$cst->fetchColumn();

    jsonResponse(['success'=>true,'customers'=>$customers,'total'=>$total,'pages'=>(int)ceil($total/$limit)]);
}

// ── Service CRUD ────────────────────────────────────────────
/**
 * Get all services (admin view).
 * 
 * Returns all services including inactive ones.
 * 
 * @return void Sends JSON response with services array
 */
function adminGetAllServices() {
    $db   = getDB();
    $stmt = $db->query("SELECT id,name,description,price,unit,icon,is_active FROM services ORDER BY id");
    jsonResponse(['success'=>true,'services'=>$stmt->fetchAll()]);
}

/**
 * Add new service (admin only).
 * 
 * Validates name, price, unit, and description.
 * Creates service record and logs event.
 * 
 * @return void Sends JSON response with new service ID
 */
function adminAddService() {
    $db          = getDB();
    $name        = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price       = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
    $unit        = sanitize($_POST['unit'] ?? 'per piece');
    $icon        = sanitize($_POST['icon'] ?? '');

    if (!$name)                  jsonResponse(['error'=>'Service name is required'],400);
    if (strlen($name) > 100)    jsonResponse(['error'=>'Name too long'],400);
    if ($price === false || $price < 0) jsonResponse(['error'=>'Valid price is required'],400);
    if (strlen($description) > 500) jsonResponse(['error'=>'Description too long'],400);

    $validUnits = ['per piece','per kg','flat rate'];
    if (!in_array($unit, $validUnits, true)) $unit = 'per piece';

    $stmt = $db->prepare("INSERT INTO services (name,description,price,unit,icon,is_active) VALUES (?,?,?,?,?,1)");
    $stmt->execute([$name,$description,$price,$unit,$icon]);
    $newId = (int)$db->lastInsertId();

    securityLog('SERVICE_ADDED',['admin_id'=>$_SESSION['admin_id'],'service_id'=>$newId,'name'=>$name]);
    jsonResponse(['success'=>true,'message'=>'Service added','id'=>$newId]);
}

/**
 * Update existing service (admin only).
 * 
 * Validates all fields and updates service record.
 * 
 * @return void Sends JSON response
 */
function adminUpdateService() {
    $db          = getDB();
    $serviceId   = (int)($_POST['service_id'] ?? 0);
    $name        = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price       = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
    $unit        = sanitize($_POST['unit'] ?? 'per piece');
    $icon        = sanitize($_POST['icon'] ?? '');

    if ($serviceId <= 0)        jsonResponse(['error'=>'Invalid service'],400);
    if (!$name)                 jsonResponse(['error'=>'Service name is required'],400);
    if (strlen($name) > 100)   jsonResponse(['error'=>'Name too long'],400);
    if ($price === false || $price < 0) jsonResponse(['error'=>'Valid price is required'],400);
    if (strlen($description) > 500) jsonResponse(['error'=>'Description too long'],400);

    $validUnits = ['per piece','per kg','flat rate'];
    if (!in_array($unit, $validUnits, true)) $unit = 'per piece';

    $stmt = $db->prepare("UPDATE services SET name=?,description=?,price=?,unit=?,icon=? WHERE id=?");
    $stmt->execute([$name,$description,$price,$unit,$icon,$serviceId]);

    securityLog('SERVICE_UPDATED',['admin_id'=>$_SESSION['admin_id'],'service_id'=>$serviceId,'name'=>$name]);
    jsonResponse(['success'=>true,'message'=>'Service updated']);
}

/**
 * Toggle service active/inactive status.
 * 
 * Flips is_active flag using NOT operator.
 * Returns new state.
 * 
 * @return void Sends JSON response with is_active boolean
 */
function adminToggleService() {
    $db        = getDB();
    $serviceId = (int)($_POST['service_id'] ?? 0);
    if ($serviceId <= 0) jsonResponse(['error'=>'Invalid service'],400);

    $stmt = $db->prepare("UPDATE services SET is_active = NOT is_active WHERE id=?");
    $stmt->execute([$serviceId]);

    $check = $db->prepare("SELECT is_active FROM services WHERE id=?");
    $check->execute([$serviceId]);
    $newState = (int)$check->fetchColumn();

    securityLog('SERVICE_TOGGLED',['admin_id'=>$_SESSION['admin_id'],'service_id'=>$serviceId,'is_active'=>$newState]);
    jsonResponse(['success'=>true,'is_active'=>$newState,'message'=>$newState?'Service activated':'Service deactivated']);
}

/**
 * Soft-delete service (set inactive).
 * 
 * Sets is_active=0 instead of hard delete to preserve order history.
 * 
 * @return void Sends JSON response
 */
function adminDeleteService() {
    $db        = getDB();
    $serviceId = (int)($_POST['service_id'] ?? 0);
    if ($serviceId <= 0) jsonResponse(['error'=>'Invalid service'],400);

    $db->prepare("UPDATE services SET is_active=0 WHERE id=?")->execute([$serviceId]);

    securityLog('SERVICE_DELETED',['admin_id'=>$_SESSION['admin_id'],'service_id'=>$serviceId]);
    jsonResponse(['success'=>true,'message'=>'Service deactivated']);
}

// ── Feedback ────────────────────────────────────────────────
/**
 * Get all feedback with user email.
 * 
 * Returns feedback joined with users table for admin moderation.
 * 
 * @return void Sends JSON response with feedback array
 */
function adminGetFeedback() {
    $db   = getDB();
    $stmt = $db->query("SELECT f.*,u.email FROM feedback f JOIN users u ON f.user_id=u.id ORDER BY f.created_at DESC");
    jsonResponse(['success'=>true,'feedback'=>$stmt->fetchAll()]);
}

/**
 * Approve feedback for public display.
 * 
 * Sets is_approved=1. Approved feedback appears on homepage.
 * 
 * @return void Sends JSON response
 */
function adminApproveFeedback() {
    $db = getDB();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error'=>'Invalid feedback'],400);
    $db->prepare("UPDATE feedback SET is_approved=1 WHERE id=?")->execute([$id]);
    securityLog('FEEDBACK_APPROVED',['admin_id'=>$_SESSION['admin_id'],'feedback_id'=>$id]);
    jsonResponse(['success'=>true,'message'=>'Feedback approved']);
}

/**
 * Reject/unapprove feedback.
 * 
 * Sets is_approved=0. Feedback removed from public display.
 * 
 * @return void Sends JSON response
 */
function adminRejectFeedback() {
    $db = getDB();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error'=>'Invalid feedback'],400);
    $db->prepare("UPDATE feedback SET is_approved=0 WHERE id=?")->execute([$id]);
    securityLog('FEEDBACK_REJECTED',['admin_id'=>$_SESSION['admin_id'],'feedback_id'=>$id]);
    jsonResponse(['success'=>true,'message'=>'Feedback unapproved']);
}

/**
 * Permanently delete feedback.
 * 
 * Removes feedback record from database.
 * 
 * @return void Sends JSON response
 */
function adminDeleteFeedback() {
    $db = getDB();
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(['error'=>'Invalid feedback'],400);
    $db->prepare("DELETE FROM feedback WHERE id=?")->execute([$id]);
    securityLog('FEEDBACK_DELETED',['admin_id'=>$_SESSION['admin_id'],'feedback_id'=>$id]);
    jsonResponse(['success'=>true,'message'=>'Feedback deleted']);
}

/**
 * Permanently delete order (admin only).
 * 
 * Cascading delete removes related items, payments, and status history.
 * Logs deletion event.
 * 
 * @return void Sends JSON response
 */
function adminDeleteOrder() {
    $db      = getDB();
    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($orderId <= 0) jsonResponse(['error'=>'Invalid order ID'],400);

    $stmt = $db->prepare("SELECT order_number FROM orders WHERE id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) jsonResponse(['error'=>'Order not found'],404);

    $db->prepare("DELETE FROM orders WHERE id=?")->execute([$orderId]);
    securityLog('ORDER_DELETED',['admin_id'=>$_SESSION['admin_id'],'order_id'=>$orderId,'order_number'=>$order['order_number']]);
    jsonResponse(['success'=>true,'message'=>'Order deleted permanently']);
}

/**
 * Permanently delete customer account.
 * 
 * Cascading delete removes orders, payments, feedback, etc.
 * Prevents deletion of admin accounts.
 * 
 * @return void Sends JSON response
 */
function adminDeleteUser() {
    $db       = getDB();
    $userId   = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) jsonResponse(['error'=>'Invalid user ID'],400);

    $stmt = $db->prepare("SELECT full_name,email FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error'=>'User not found'],404);

    // Prevent deleting the default admin (admins table is separate, but safety check)
    $chk = $db->prepare("SELECT COUNT(*) FROM admins WHERE email=?");
    $chk->execute([$user['email']]);
    if ((int)$chk->fetchColumn() > 0) jsonResponse(['error'=>'Cannot delete an admin account'],400);

    $db->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);
    securityLog('USER_DELETED',['admin_id'=>$_SESSION['admin_id'],'user_id'=>$userId,'email'=>$user['email']]);
    jsonResponse(['success'=>true,'message'=>'Customer profile deleted permanently']);
}

// ── Invoice ─────────────────────────────────────────────────
/**
 * Get invoice data for admin view.
 * 
 * Returns order with items, customer info, and payment details.
 * 
 * @return void Sends JSON response with invoice data
 */
function adminGetInvoice() {
    $db      = getDB();
    $orderId = (int)($_GET['id'] ?? 0);
    if ($orderId <= 0) jsonResponse(['error'=>'Invalid order ID'],400);

    $stmt = $db->prepare("SELECT o.*,p.status AS pay_status,p.amount AS pay_amount,p.method AS pay_method,p.paid_at,
        u.full_name AS customer_name,u.email AS customer_email,u.phone AS customer_phone,u.address AS customer_address
        FROM orders o
        JOIN users u ON o.user_id=u.id
        LEFT JOIN payments p ON p.order_id=o.id
        WHERE o.id=?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) jsonResponse(['error'=>'Order not found'],404);

    $items = $db->prepare("SELECT oi.*,ct.name AS cloth_name,ct.service_type
        FROM order_items_v2 oi JOIN cloth_types ct ON oi.cloth_type_id=ct.id WHERE oi.order_id=?");
    $items->execute([$orderId]);
    $order['items'] = $items->fetchAll();

    jsonResponse(['success'=>true,'order'=>$order]);
}
