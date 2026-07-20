<?php
header('Content-Type: application/json');
require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'login':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Email and password required']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
            exit;
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        
        // Log login
        $stmt = $pdo->prepare("INSERT INTO login_log (user_id, ip_address) VALUES (?, ?)");
        $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);
        
        echo json_encode(['success' => true, 'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ]]);
        break;
        
    case 'logout':
        session_destroy();
        echo json_encode(['success' => true]);
        break;
        
    case 'dashboard':
        requireLogin();
        
        // Get stats
        $total_assets = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        $available = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'available'")->fetchColumn();
        $checked_out = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'checked_out'")->fetchColumn();
        $maintenance = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'maintenance'")->fetchColumn();
        
        // Recent transactions
        $stmt = $pdo->query("
            SELECT t.*, a.name as asset_name, a.asset_code, e.name as employee_name 
            FROM asset_transactions t 
            JOIN assets a ON t.asset_id = a.id 
            JOIN employees e ON t.employee_id = e.id 
            ORDER BY t.checkout_date DESC LIMIT 10
        ");
        $recent = $stmt->fetchAll();
        
        echo json_encode([
            'stats' => [
                'total' => $total_assets,
                'available' => $available,
                'checked_out' => $checked_out,
                'maintenance' => $maintenance
            ],
            'recent_transactions' => $recent
        ]);
        break;
        
    case 'checkout':
        requireLogin();
        
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $asset_code = $_POST['asset_code'] ?? '';
        $employee_id = $_POST['employee_id'] ?? 0;
        $notes = $_POST['notes'] ?? '';
        
        // Find asset
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE asset_code = ?");
        $stmt->execute([$asset_code]);
        $asset = $stmt->fetch();
        
        if (!$asset) {
            http_response_code(404);
            echo json_encode(['error' => 'Asset not found']);
            exit;
        }
        
        if ($asset['status'] !== 'available') {
            http_response_code(400);
            echo json_encode(['error' => 'Asset is not available']);
            exit;
        }
        
        // Update asset
        $stmt = $pdo->prepare("UPDATE assets SET status = 'checked_out', assigned_to = ?, last_checkout = NOW() WHERE id = ?");
        $stmt->execute([$employee_id, $asset['id']]);
        
        // Create transaction
        $stmt = $pdo->prepare("INSERT INTO asset_transactions (asset_id, employee_id, type, notes) VALUES (?, ?, 'checkout', ?)");
        $stmt->execute([$asset['id'], $employee_id, $notes]);
        
        echo json_encode(['success' => true, 'message' => 'Asset checked out']);
        break;
        
    case 'checkin':
        requireLogin();
        
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $asset_code = $_POST['asset_code'] ?? '';
        
        // Find asset
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE asset_code = ?");
        $stmt->execute([$asset_code]);
        $asset = $stmt->fetch();
        
        if (!$asset) {
            http_response_code(404);
            echo json_encode(['error' => 'Asset not found']);
            exit;
        }
        
        if ($asset['status'] !== 'checked_out') {
            http_response_code(400);
            echo json_encode(['error' => 'Asset is not checked out']);
            exit;
        }
        
        // Update asset
        $stmt = $pdo->prepare("UPDATE assets SET status = 'available', assigned_to = NULL, last_checkin = NOW() WHERE id = ?");
        $stmt->execute([$asset['id']]);
        
        // Update transaction
        $stmt = $pdo->prepare("UPDATE asset_transactions SET checkin_date = NOW() WHERE asset_id = ? AND type = 'checkout' AND checkin_date IS NULL");
        $stmt->execute([$asset['id']]);
        
        echo json_encode(['success' => true, 'message' => 'Asset checked in']);
        break;
        
    case 'assets':
        requireLogin();
        
        if ($method === 'GET') {
            $stmt = $pdo->query("
                SELECT a.*, e.name as assigned_to_name 
                FROM assets a 
                LEFT JOIN employees e ON a.assigned_to = e.id 
                ORDER BY a.name
            ");
            echo json_encode($stmt->fetchAll());
        } elseif ($method === 'POST') {
            $asset_code = $_POST['asset_code'] ?? '';
            $name = $_POST['name'] ?? '';
            $category = $_POST['category'] ?? '';
            
            $stmt = $pdo->prepare("INSERT INTO assets (asset_code, name, category) VALUES (?, ?, ?)");
            $stmt->execute([$asset_code, $name, $category]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        break;
        
    case 'employees':
        requireLogin();
        
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM employees ORDER BY name");
            echo json_encode($stmt->fetchAll());
        } elseif ($method === 'POST') {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $department = $_POST['department'] ?? '';
            
            $stmt = $pdo->prepare("INSERT INTO employees (name, email, department) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $department]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        }
        break;
        
    case 'analytics':
        requireLogin();
        
        // Checkouts per employee
        $stmt = $pdo->query("
            SELECT e.name, COUNT(*) as count 
            FROM asset_transactions t 
            JOIN employees e ON t.employee_id = e.id 
            WHERE t.type = 'checkout' 
            GROUP BY e.id, e.name 
            ORDER BY count DESC
        ");
        $by_employee = $stmt->fetchAll();
        
        // Checkouts per category
        $stmt = $pdo->query("
            SELECT a.category, COUNT(*) as count 
            FROM asset_transactions t 
            JOIN assets a ON t.asset_id = a.id 
            WHERE t.type = 'checkout' 
            GROUP BY a.category 
            ORDER BY count DESC
        ");
        $by_category = $stmt->fetchAll();
        
        // Recent activity
        $stmt = $pdo->query("
            SELECT DATE(checkout_date) as date, COUNT(*) as count 
            FROM asset_transactions 
            WHERE checkout_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(checkout_date) 
            ORDER BY date
        ");
        $timeline = $stmt->fetchAll();
        
        echo json_encode([
            'by_employee' => $by_employee,
            'by_category' => $by_category,
            'timeline' => $timeline
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
