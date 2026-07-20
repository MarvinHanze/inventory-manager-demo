<?php
session_start();

define('BASE', '/inventory-manager');

$DB_HOST = getenv('DB_HOST') ?: 'y11ovnrne4yk4p9zbhe39tti';
$DB_NAME = getenv('DB_NAME') ?: 'default';
$DB_USER = getenv('DB_USER') ?: 'mysql';
$DB_PASS = getenv('DB_PASS') ?: '23ns613Dyo1vgiAOQCt2ABFZzujOsxuyROvqNk4unUoZxWpwN9nIPrMNTt4QFkzG';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

initDatabase($pdo);

define('DEMO_RESET_MINUTES', 30);

function initDatabase($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, role ENUM('admin','user') DEFAULT 'user', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255), department VARCHAR(100), status ENUM('active','inactive') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS assets (id INT AUTO_INCREMENT PRIMARY KEY, asset_code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(255) NOT NULL, category VARCHAR(100), status ENUM('available','checked_out','maintenance','retired') DEFAULT 'available', assigned_to INT, last_checkout TIMESTAMP NULL, last_checkin TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS asset_transactions (id INT AUTO_INCREMENT PRIMARY KEY, asset_id INT NOT NULL, employee_id INT NOT NULL, type ENUM('checkout','checkin') NOT NULL, checkout_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, checkin_date TIMESTAMP NULL, notes TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_log (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP, ip_address VARCHAR(45))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS demo_settings (id INT PRIMARY KEY DEFAULT 1, last_reset DATETIME NOT NULL)");

    $row = $pdo->query("SELECT last_reset FROM demo_settings WHERE id=1")->fetch();
    $needsReset = !$row || (time() - strtotime($row['last_reset'])) >= (DEMO_RESET_MINUTES * 60);

    if ($needsReset) {
        seedDemoData($pdo);
        $pdo->exec("INSERT INTO demo_settings (id, last_reset) VALUES (1, NOW()) ON DUPLICATE KEY UPDATE last_reset=NOW()");
    }
}

function seedDemoData($pdo) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    foreach (['asset_transactions','assets','employees','users','login_log'] as $t) {
        $pdo->exec("TRUNCATE TABLE $t");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    $hash = password_hash('demo123', PASSWORD_DEFAULT);
    $s = $pdo->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)");
    $s->execute(array('admin@demo.nl', $hash, 'Admin', 'admin'));
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
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE . '/login.php');
        exit;
    }
}

function getUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute(array($_SESSION['user_id']));
    return $stmt->fetch();
}
