<?php
require_once __DIR__ . '/assets/icons.php';

// Fouten NOOIT rechtstreeks naar de browser (paden/queries/stack traces lekken anders naar de
// gebruiker) — wel altijd loggen server-side. Dit overschrijft eventuele php.ini-defaults van de
// image (het officiële php:8.2-apache image heeft standaard geen actieve php.ini, waardoor
// display_errors ongemerkt "On" kan staan).
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Centrale, generieke afhandeling van alles wat verder ontsnapt (onverwachte PDOException,
// TypeError, etc.): loggen server-side, gebruiker krijgt alleen een generieke melding terug in
// een vorm die past bij het aanroeptype (JSON voor api.php, simpele HTML voor de paginascripts).
function hzSafeErrorResponse() {
    if (!headers_sent()) {
        http_response_code(500);
    }
    $isJson = defined('IS_API');
    foreach (headers_list() as $h) {
        if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'application/json') !== false) {
            $isJson = true;
        }
    }
    if ($isJson) {
        echo json_encode(['error' => 'Er is een onverwachte fout opgetreden. Probeer het later opnieuw.']);
    } else {
        echo '<p style="font-family:sans-serif;padding:2rem;">Er is een onverwachte fout opgetreden. Probeer het later opnieuw.</p>';
    }
}
set_exception_handler(function (Throwable $e) {
    error_log('[inventory-manager] Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    hzSafeErrorResponse();
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('[inventory-manager] Fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        if (!headers_sent()) {
            hzSafeErrorResponse();
        }
    }
});

// Sessies goed beveiligen: httponly + samesite altijd, secure wanneer via HTTPS/proxy bediend.
// (Zie ook login.php dat dezelfde instellingen zet vóór zijn eigen session_start().)
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if ($__https) {
    ini_set('session.cookie_secure', '1');
}
session_start();

define('BASE', '/inventory-manager');
define('DEMO_RESET_MINUTES', 30);

$DB_HOST = getenv('DB_HOST') ?: 'y11ovnrne4yk4p9zbhe39tti';
$DB_NAME = getenv('DB_NAME') ?: 'demos';
$DB_USER = getenv('DB_USER') ?: 'mysql';
$DB_PASS = getenv('DB_PASS') ?: '23ns613Dyo1vgiAOQCt2ABFZzujOsxuyROvqNk4unUoZxWpwN9nIPrMNTt4QFkzG';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[inventory-manager] Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Probeer het later opnieuw.');
}

initDatabase($pdo);

function initDatabase($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, role ENUM('admin','user') DEFAULT 'user', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), department VARCHAR(100), status ENUM('active','inactive') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS assets (id INT AUTO_INCREMENT PRIMARY KEY, asset_code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL, category VARCHAR(100), status ENUM('available','checked_out','maintenance','retired') DEFAULT 'available', assigned_to INT, last_checkout TIMESTAMP NULL, last_checkin TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS asset_transactions (id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, employee_id INT NOT NULL, type ENUM('checkout','checkin') NOT NULL, checkout_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, checkin_date TIMESTAMP NULL, notes TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_log (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(45))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS demo_settings (id INT PRIMARY KEY DEFAULT 1, last_reset DATETIME NOT NULL)");
    // Brute-force-bescherming voor de login: houdt zowel mislukte als geslaagde pogingen bij per
    // e-mailadres + IP, zodat we recente mislukte pogingen kunnen tellen (zie attemptLogin()).
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL, ip_address VARCHAR(45) NOT NULL, success TINYINT(1) NOT NULL DEFAULT 0, attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_email_time (email, attempted_at), INDEX idx_ip_time (ip_address, attempted_at))");

    // Rollen uitbreiden met 'manager' als middenweg tussen admin en user (idempotent: alleen ALTER
    // uitvoeren als de enum 'manager' nog niet bevat, zodat dit niet elke request een table rewrite triggert).
    $roleCol = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
    if ($roleCol && strpos($roleCol['Type'], 'manager') === false) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','manager','user') DEFAULT 'user'");
    }

    // Nieuwe tabellen (prefix inv_ om botsing met andere demo-apps in de gedeelde database te voorkomen).
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_warehouses (id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL, location VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_stock (id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, warehouse_id INT NOT NULL, quantity INT NOT NULL DEFAULT 0, min_stock INT NOT NULL DEFAULT 0, unit_price DECIMAL(10,2) NOT NULL DEFAULT 0, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_asset_warehouse (asset_id, warehouse_id))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_batches (id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, batch_number VARCHAR(100), serial_number VARCHAR(100), quantity INT NOT NULL DEFAULT 1, received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, expiry_date DATE NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_stock_allocations (id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, warehouse_id INT NOT NULL, quantity INT NOT NULL, purpose VARCHAR(255), allocated_by INT NULL, allocated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, released_at TIMESTAMP NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_purchase_orders (id INT AUTO_INCREMENT PRIMARY KEY, po_number VARCHAR(50) NOT NULL UNIQUE, supplier_name VARCHAR(255) NOT NULL, status ENUM('draft','pending','ordered','received','cancelled') DEFAULT 'pending', recurring ENUM('none','weekly','monthly') DEFAULT 'none', created_by INT NULL, expected_date DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_purchase_order_items (id INT AUTO_INCREMENT PRIMARY KEY, po_id INT NOT NULL, asset_id INT NOT NULL, quantity INT NOT NULL, unit_price DECIMAL(10,2) DEFAULT 0)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_sales_orders (id INT AUTO_INCREMENT PRIMARY KEY, so_number VARCHAR(50) NOT NULL UNIQUE, customer_name VARCHAR(255) NOT NULL, status ENUM('open','fulfilled','cancelled') DEFAULT 'open', created_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_sales_order_items (id INT AUTO_INCREMENT PRIMARY KEY, so_id INT NOT NULL, asset_id INT NOT NULL, quantity INT NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_audit_log (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, user_name VARCHAR(255), action VARCHAR(100) NOT NULL, entity_type VARCHAR(50), entity_id INT NULL, details TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inv_erp_settings (id INT PRIMARY KEY DEFAULT 1, provider VARCHAR(100) DEFAULT 'Exact Online (demo)', api_key VARCHAR(255) DEFAULT '', last_sync_at DATETIME NULL, last_sync_log TEXT)");

    $row = $pdo->query("SELECT last_reset FROM demo_settings WHERE id=1")->fetch();
    $needsReset = !$row || (time() - strtotime($row['last_reset'])) >= (DEMO_RESET_MINUTES * 60);

    if ($needsReset) {
        seedDemoData($pdo);
        $pdo->exec("INSERT INTO demo_settings (id, last_reset) VALUES (1, NOW()) ON DUPLICATE KEY UPDATE last_reset=NOW()");
    }
}

function seedDemoData($pdo) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach (['asset_transactions','assets','employees','users','login_log',
              'inv_stock','inv_batches','inv_stock_allocations',
              'inv_purchase_order_items','inv_purchase_orders',
              'inv_sales_order_items','inv_sales_orders',
              'inv_audit_log','inv_warehouses'] as $t) {
        $pdo->exec("TRUNCATE TABLE $t");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    $hash = password_hash('demo123', PASSWORD_DEFAULT);
    $s = $pdo->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)");
    $s->execute(array('admin@demo.nl', $hash, 'Admin', 'admin'));
    $s->execute(array('manager@demo.nl', $hash, 'Manager', 'manager'));
    $s->execute(array('user@demo.nl', $hash, 'Gebruiker', 'user'));

    $e = $pdo->prepare("INSERT INTO employees (name, email, department) VALUES (?, ?, ?)");
    $e->execute(array('Sophie de Boer', 'sophie@demo.nl', 'Operations'));
    $e->execute(array('Thomas Visser', 'thomas@demo.nl', 'Logistics'));
    $e->execute(array('Emma Bakker', 'emma@demo.nl', 'Warehouse'));
    $e->execute(array('Daan Mulder', 'daan@demo.nl', 'IT'));
    $e->execute(array('Lisa Jansen', 'lisa@demo.nl', 'HR'));
    $e->execute(array('Max Smit', 'max@demo.nl', 'Finance'));
    $e->execute(array('Fleur Dijkstra', 'fleur@demo.nl', 'Marketing'));
    $e->execute(array('Ruben de Groot', 'ruben@demo.nl', 'Operations'));
    $e->execute(array('Nina Schouten', 'nina@demo.nl', 'Logistics'));
    $e->execute(array('Bas Kok', 'bas@demo.nl', 'Warehouse'));

    $a = $pdo->prepare("INSERT INTO assets (asset_code, name, category, status) VALUES (?, ?, ?, ?)");
    $a->execute(array('INV-001', 'Dell XPS 15 Laptop', 'Laptops', 'available'));
    $a->execute(array('INV-002', 'MacBook Pro 14 inch', 'Laptops', 'available'));
    $a->execute(array('INV-003', 'ThinkPad X1 Carbon', 'Laptops', 'checked_out'));
    $a->execute(array('INV-004', 'Samsung 27 inch Monitor', 'Monitors', 'available'));
    $a->execute(array('INV-005', 'LG UltraWide 34 inch', 'Monitors', 'available'));
    $a->execute(array('INV-006', 'HP LaserJet Pro', 'Printers', 'maintenance'));
    $a->execute(array('INV-007', 'Brother MFC Printer', 'Printers', 'available'));
    $a->execute(array('INV-008', 'Logitech MX Keys', 'Accessories', 'available'));
    $a->execute(array('INV-009', 'Logitech MX Master 3', 'Accessories', 'available'));
    $a->execute(array('INV-010', 'Jabra Evolve2 75', 'Headsets', 'available'));
    $a->execute(array('INV-011', 'Sony WH-1000XM5', 'Headsets', 'checked_out'));
    $a->execute(array('INV-012', 'Elgato Stream Deck', 'Accessories', 'available'));
    $a->execute(array('INV-013', 'Dell Docking Station', 'Accessories', 'available'));
    $a->execute(array('INV-014', 'iPad Pro 12.9 inch', 'Tablets', 'available'));
    $a->execute(array('INV-015', 'Logitech Brio Webcam', 'Accessories', 'checked_out'));
    $a->execute(array('INV-016', 'Keychron K2 Keyboard', 'Accessories', 'available'));
    $a->execute(array('INV-017', 'BenQ ScreenBar', 'Accessories', 'available'));
    $a->execute(array('INV-018', 'Samsung T7 SSD 1TB', 'Storage', 'available'));
    $a->execute(array('INV-019', 'APC UPS 1500VA', 'Power', 'available'));
    $a->execute(array('INV-020', 'Cisco Webex Room Kit', 'Meeting', 'available'));

    $empIds = $pdo->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    $astIds = $pdo->query("SELECT id FROM assets")->fetchAll(PDO::FETCH_COLUMN);
    $t = $pdo->prepare("INSERT INTO asset_transactions (asset_id, employee_id, type, checkout_date, checkin_date, notes) VALUES (?, ?, 'checkout', ?, ?, ?)");
    $notes = array('Project uitlening', 'Tijdelijk gebruik', 'Vergadering', 'Thuiswerken', 'Onderhoud');
    for ($i = 0; $i < 30; $i++) {
        $aid = $astIds[array_rand($astIds)];
        $eid = $empIds[array_rand($empIds)];
        $days = rand(1, 30);
        $hrs = rand(2, 48);
        $out = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $in = ($i % 4 !== 0) ? date('Y-m-d H:i:s', strtotime($out . "+{$hrs} hours")) : null;
        $t->execute(array($aid, $eid, $out, $in, $notes[array_rand($notes)]));
    }

    // --- Nieuw: magazijnen, voorraadniveaus, batches, orders, audit log, ERP mock-instellingen ---
    $w = $pdo->prepare("INSERT INTO inv_warehouses (code, name, location) VALUES (?, ?, ?)");
    $w->execute(array('WH-AMS', 'Hoofdmagazijn Amsterdam', 'Amsterdam'));
    $w->execute(array('WH-RTM', 'Distributiecentrum Rotterdam', 'Rotterdam'));
    $warehouseIds = $pdo->query("SELECT id FROM inv_warehouses ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

    $prices = array(
        'Laptops' => 900, 'Monitors' => 250, 'Printers' => 300, 'Accessories' => 60,
        'Headsets' => 150, 'Tablets' => 700, 'Storage' => 90, 'Power' => 120, 'Meeting' => 1500
    );
    $assetRows = $pdo->query("SELECT id, category FROM assets ORDER BY id")->fetchAll();
    $stock = $pdo->prepare("INSERT INTO inv_stock (asset_id, warehouse_id, quantity, min_stock, unit_price) VALUES (?, ?, ?, ?, ?)");
    foreach ($assetRows as $idx => $row) {
        $unitPrice = $prices[$row['category']] ?? 100;
        // Hoofdmagazijn krijgt altijd voorraad; om de 3 items ook een (kleinere) voorraad in Rotterdam.
        $qtyMain = rand(2, 25);
        $minStock = rand(3, 8);
        // Elk vijfde item bewust (net) onder het minimum zetten zodat de lage-voorraad-melding iets laat zien.
        if ($idx % 5 === 0) {
            $qtyMain = max(0, $minStock - rand(1, 3));
        }
        $stock->execute(array($row['id'], $warehouseIds[0], $qtyMain, $minStock, $unitPrice));
        if ($idx % 3 === 0) {
            $stock->execute(array($row['id'], $warehouseIds[1], rand(1, 10), rand(2, 5), $unitPrice));
        }
    }

    $batch = $pdo->prepare("INSERT INTO inv_batches (asset_id, batch_number, serial_number, quantity, expiry_date) VALUES (?, ?, ?, ?, ?)");
    foreach (array_slice($astIds, 0, 6) as $i => $aid) {
        $batch->execute(array(
            $aid,
            'BATCH-' . str_pad((string)($i + 1), 4, '0', STR_PAD_LEFT),
            'SN-' . strtoupper(bin2hex(random_bytes(4))),
            rand(1, 15),
            $i % 2 === 0 ? date('Y-m-d', strtotime('+' . rand(60, 400) . ' days')) : null
        ));
    }

    $po = $pdo->prepare("INSERT INTO inv_purchase_orders (po_number, supplier_name, status, recurring, expected_date) VALUES (?, ?, ?, ?, ?)");
    $po->execute(array('PO-2026-001', 'Tech Distributie B.V.', 'received', 'none', date('Y-m-d', strtotime('-5 days'))));
    $po->execute(array('PO-2026-002', 'OfficeSupplies Direct', 'pending', 'monthly', date('Y-m-d', strtotime('+7 days'))));
    $po->execute(array('PO-2026-003', 'Tech Distributie B.V.', 'ordered', 'none', date('Y-m-d', strtotime('+3 days'))));
    $poIds = $pdo->query("SELECT id FROM inv_purchase_orders ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $poItem = $pdo->prepare("INSERT INTO inv_purchase_order_items (po_id, asset_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
    foreach ($poIds as $i => $poId) {
        for ($j = 0; $j < 2; $j++) {
            $aid = $astIds[array_rand($astIds)];
            $poItem->execute(array($poId, $aid, rand(5, 20), rand(50, 500)));
        }
    }

    $so = $pdo->prepare("INSERT INTO inv_sales_orders (so_number, customer_name, status) VALUES (?, ?, ?)");
    $so->execute(array('SO-2026-001', 'Contoso Nederland B.V.', 'open'));
    $so->execute(array('SO-2026-002', 'Noordzee Retail', 'fulfilled'));
    $soIds = $pdo->query("SELECT id FROM inv_sales_orders ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $soItem = $pdo->prepare("INSERT INTO inv_sales_order_items (so_id, asset_id, quantity) VALUES (?, ?, ?)");
    foreach ($soIds as $soId) {
        for ($j = 0; $j < 2; $j++) {
            $aid = $astIds[array_rand($astIds)];
            $soItem->execute(array($soId, $aid, rand(1, 5)));
        }
    }

    $allocation = $pdo->prepare("INSERT INTO inv_stock_allocations (asset_id, warehouse_id, quantity, purpose) VALUES (?, ?, ?, ?)");
    $allocation->execute(array($astIds[0], $warehouseIds[0], 3, 'Productieorder #PR-4471'));
    $allocation->execute(array($astIds[2], $warehouseIds[0], 5, 'Assemblagelijn B'));

    $audit = $pdo->prepare("INSERT INTO inv_audit_log (user_id, user_name, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?, ?)");
    $audit->execute(array(null, 'Systeem', 'seed', 'system', null, 'Demo-data automatisch opnieuw ingeladen'));

    $pdo->exec("INSERT INTO inv_erp_settings (id, provider, api_key, last_sync_at, last_sync_log) VALUES (1, 'Exact Online (demo)', '', NULL, NULL) ON DUPLICATE KEY UPDATE provider=VALUES(provider)");
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';
}

function verifyCSRF() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// api.php definieert IS_API vóór het inladen van dit bestand, zodat een niet-ingelogd verzoek aan
// de JSON-API een nette 401 terugkrijgt in plaats van een redirect-Location-header (die een fetch()
// gewoon transparant zou volgen en die niet bij een JSON-API past). De losse PHP-pagina's
// (index.php, admin.php, ...) definiëren IS_API niet en behouden de redirect-naar-login.
function requireLogin() {
    if (!isLoggedIn()) {
        if (defined('IS_API') && IS_API) {
            http_response_code(401);
            echo json_encode(['error' => 'Niet ingelogd']);
            exit;
        }
        header('Location: ' . BASE . '/login.php');
        exit;
    }
}

function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Brute-force-bescherming: max. 8 mislukte pogingen per e-mailadres/IP binnen 15 minuten.
function isLoginRateLimited($pdo, $email) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM inv_login_attempts WHERE (email = ? OR ip_address = ?) AND success = 0 AND attempted_at > NOW() - INTERVAL 15 MINUTE"
    );
    $stmt->execute([$email, clientIp()]);
    return ((int)$stmt->fetchColumn()) >= 8;
}

function recordLoginAttempt($pdo, $email, $success) {
    $stmt = $pdo->prepare("INSERT INTO inv_login_attempts (email, ip_address, success) VALUES (?, ?, ?)");
    $stmt->execute([$email, clientIp(), $success ? 1 : 0]);
}

function getUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute(array($_SESSION['user_id']));
    return $stmt->fetch();
}

// Rollen & rechten: admin/manager mogen voorraadaanpassingen doen (assets beheren, voorraad
// bijwerken, orders ontvangen/aanmaken, allocaties, CSV-import, ERP-instellingen). 'user' mag
// wel gewoon dagelijkse uitgifte/inname doen (dat is geen "voorraadaanpassing" in de zin van
// aantallen/prijzen/inkoop wijzigen).
function canManageStock() {
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'manager'], true);
}

function isAdmin() {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', (array)$roles, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Onvoldoende rechten voor deze actie']);
        exit;
    }
}

function requireStockManager() {
    requireRole(['admin', 'manager']);
}

// Audit logging: wie, wat, wanneer (traceerbaarheid/integriteit).
function logAudit($pdo, $action, $entityType = null, $entityId = null, $details = '') {
    $userId = $_SESSION['user_id'] ?? null;
    $userName = 'Systeem';
    if ($userId) {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute(array($userId));
        $row = $stmt->fetch();
        if ($row) $userName = $row['name'];
    }
    $stmt = $pdo->prepare("INSERT INTO inv_audit_log (user_id, user_name, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(array($userId, $userName, $action, $entityType, $entityId, $details));
}
