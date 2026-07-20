<?php
session_start();

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

if (!isset($_SESSION['db_initialized'])) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        department VARCHAR(100),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS assets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        status ENUM('available', 'checked_out', 'maintenance', 'retired') DEFAULT 'available',
        assigned_to INT,
        last_checkout TIMESTAMP NULL,
        last_checkin TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assigned_to) REFERENCES employees(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS asset_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        asset_id INT NOT NULL,
        employee_id INT NOT NULL,
        type ENUM('checkout', 'checkin') NOT NULL,
        checkout_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        checkin_date TIMESTAMP NULL,
        notes TEXT,
        FOREIGN KEY (asset_id) REFERENCES assets(id),
        FOREIGN KEY (employee_id) REFERENCES employees(id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count == 0) {
        $hash = password_hash('demo123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)")->execute(['admin@demo.nl', $hash, 'Admin', 'admin']);
        $pdo->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)')->execute(['user@demo.nl', $hash, 'Gebruiker', 'user']);

        $emps = [
            ['Sophie de Boer','sophie@demo.nl','Operations'],
            ['Thomas Visser','thomas@demo.nl','Logistics'],
            ['Emma Bakker','emma@demo.nl','Warehouse'],
            ['Daan Mulder','daan@demo.nl','IT'],
            ['Lisa Jansen','lisa@demo.nl','HR'],
            ['Max Smit','max@demo.nl','Finance'],
            ['Fleur Dijkstra','fleur@demo.nl','Marketing'],
            ['Ruben de Groot','ruben@demo.nl','Operations'],
            ['Nina Schouten','nina@demo.nl','Logistics'],
            ['Bas Kok','bas@demo.nl','Warehouse'],
        ];
        $empStmt = $pdo->prepare("INSERT INTO employees (name, email, department) VALUES (?, ?, ?)");
        foreach ($emps as $e) $empStmt->execute($e);

        $assets = [
            ['INV-001','Dell XPS 15 Laptop','Laptops','available'],
            ['INV-002','MacBook Pro 14"','Laptops','available'],
            ['INV-003','ThinkPad X1 Carbon','Laptops','checked_out'],
            ['INV-004','Samsung 27" Monitor','Monitors','available'],
            ['INV-005','LG UltraWide 34"','Monitors','available'],
            ['INV-006','HP LaserJet Pro','Printers','maintenance'],
            ['INV-007','Brother MFC Printer','Printers','available'],
            ['INV-008','Logitech MX Keys','Accessories','available'],
            ['INV-009','Logitech MX Master 3','Accessories','available'],
            ['INV-010','Jabra Evolve2 75','Headsets','available'],
            ['INV-011','Sony WH-1000XM5','Headsets','checked_out'],
            ['INV-012','Elgato Stream Deck','Accessories','available'],
            ['INV-013','Dell Docking Station','Accessories','available'],
            ['INV-014','iPad Pro 12.9"','Tablets','available'],
            ['INV-015','Logitech Brio Webcam','Accessories','checked_out'],
            ['INV-016','Keychron K2 Keyboard','Accessories','available'],
            ['INV-017','BenQ ScreenBar','Accessories','available'],
            ['INV-018','Samsung T7 SSD 1TB','Storage','available'],
            ['INV-019','APC UPS 1500VA','Power','available'],
            ['INV-020','Cisco Webex Room Kit','Meeting','available'],
        ];
        $astStmt = $pdo->prepare("INSERT INTO assets (asset_code, name, category, status) VALUES (?, ?, ?, ?)");
        foreach ($assets as $a) $astStmt->execute($a);

        $empIds = $pdo->query("SELECT id FROM employees")->fetchAll(PDO::FETCH_COLUMN);
        $astIds = $pdo->query("SELECT id FROM assets")->fetchAll(PDO::FETCH_COLUMN);

        $transStmt = $pdo->prepare("INSERT INTO asset_transactions (asset_id, employee_id, type, checkout_date, checkin_date, notes) VALUES (?, ?, 'checkout', ?, ?, ?)");
        for ($i = 0; $i < 30; $i++) {
            $aid = $astIds[array_rand($astIds)];
            $eid = $empIds[array_rand($empIds)];
            $days = rand(1, 30);
            $hrs = rand(2, 48);
            $out = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $in = ($i % 4 !== 0) ? date('Y-m-d H:i:s', strtotime($out . "+{$hrs} hours")) : null;
            $notes = ['Project uitlening','Tijdelijk gebruik','Vergadering','Thuiswerken','Onderhoud'][array_rand([0,1,2,3,4])];
            $transStmt->execute([$aid, $eid, $out, $in, $notes]);
        }
    }
    $_SESSION['db_initialized'] = true;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
