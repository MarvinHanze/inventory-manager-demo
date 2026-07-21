<?php
header('Content-Type: application/json');
require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Kleine helper voor consistente validatie van verplichte velden.
function requireFields($fields, $source) {
    foreach ($fields as $f) {
        if (!isset($source[$f]) || trim((string)$source[$f]) === '') {
            http_response_code(400);
            echo json_encode(['error' => "Veld '$f' is verplicht"]);
            exit;
        }
    }
}

switch ($action) {
    case 'login':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        verifyCSRF();

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
        logAudit($pdo, 'login', 'user', $user['id'], 'Ingelogd');

        echo json_encode(['success' => true, 'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ]]);
        break;

    case 'logout':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'dashboard':
        requireLogin();

        // Kernstatistieken
        $total_assets = $pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        $available = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'available'")->fetchColumn();
        $checked_out = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'checked_out'")->fetchColumn();
        $maintenance = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'maintenance'")->fetchColumn();
        $stock_value = (float)$pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM inv_stock")->fetchColumn();

        // Laagste voorraad (quantity <= min_stock), voor het "lage voorraad" dashboardblok
        $stmt = $pdo->query("
            SELECT a.name, a.asset_code, s.quantity, s.min_stock, w.name as warehouse_name
            FROM inv_stock s
            JOIN assets a ON a.id = s.asset_id
            JOIN inv_warehouses w ON w.id = s.warehouse_id
            WHERE s.quantity <= s.min_stock
            ORDER BY (s.min_stock - s.quantity) DESC
            LIMIT 6
        ");
        $low_stock = $stmt->fetchAll();

        // Best verkopende / meest uitgegeven items (laatste 30 dagen)
        $stmt = $pdo->query("
            SELECT a.name, a.asset_code, COUNT(*) as count
            FROM asset_transactions t
            JOIN assets a ON t.asset_id = a.id
            WHERE t.type = 'checkout' AND t.checkout_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY a.id, a.name, a.asset_code
            ORDER BY count DESC
            LIMIT 5
        ");
        $top_items = $stmt->fetchAll();

        // Voorraadverloop over tijd (laatste 14 dagen) voor de dashboard-grafiek
        $stmt = $pdo->query("
            SELECT DATE(checkout_date) as date, COUNT(*) as count
            FROM asset_transactions
            WHERE checkout_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            GROUP BY DATE(checkout_date)
            ORDER BY date
        ");
        $timeline = $stmt->fetchAll();

        // Recente transacties
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
                'maintenance' => $maintenance,
                'low_stock_count' => count($low_stock),
                'stock_value' => $stock_value
            ],
            'low_stock' => $low_stock,
            'top_items' => $top_items,
            'timeline' => $timeline,
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

        verifyCSRF();

        $asset_code = trim((string)($_POST['scan_input'] ?? $_POST['asset_code'] ?? ''));
        $employee_id = filter_var($_POST['employee_id'] ?? 0, FILTER_VALIDATE_INT);
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($asset_code === '' || !$employee_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Asset code en medewerker zijn verplicht']);
            exit;
        }

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

        logAudit($pdo, 'checkout', 'asset', $asset['id'], "Uitgegeven: {$asset['asset_code']} ({$asset['name']})");

        echo json_encode(['success' => true, 'message' => 'Asset checked out']);
        break;

    case 'checkin':
        requireLogin();

        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        verifyCSRF();

        $asset_code = trim((string)($_POST['scan_input'] ?? $_POST['asset_code'] ?? ''));

        if ($asset_code === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Asset code is verplicht']);
            exit;
        }

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

        logAudit($pdo, 'checkin', 'asset', $asset['id'], "Ingenomen: {$asset['asset_code']} ({$asset['name']})");

        echo json_encode(['success' => true, 'message' => 'Asset checked in']);
        break;

    case 'assets':
        requireLogin();

        if ($method === 'GET') {
            $paginated = isset($_GET['page']) || isset($_GET['q']);

            if (!$paginated) {
                // Legacy/simpel gedrag (ongepagineerd) blijft bestaan voor pagina's die de volledige lijst nodig
                // hebben (bijv. checkin.php dat lokaal filtert op status).
                $stmt = $pdo->query("
                    SELECT a.*, e.name as assigned_to_name
                    FROM assets a
                    LEFT JOIN employees e ON a.assigned_to = e.id
                    ORDER BY a.name
                ");
                echo json_encode($stmt->fetchAll());
                break;
            }

            // Zoeken + paginering + sortering (voorkomt trage pagina's bij veel artikelen)
            $q = trim((string)($_GET['q'] ?? ''));
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(5, (int)($_GET['per_page'] ?? 10)));
            $offset = ($page - 1) * $perPage;

            $sortWhitelist = ['name', 'asset_code', 'category', 'status', 'total_quantity'];
            $sort = in_array($_GET['sort'] ?? '', $sortWhitelist, true) ? $_GET['sort'] : 'name';
            $dir = (strtoupper($_GET['dir'] ?? 'ASC') === 'DESC') ? 'DESC' : 'ASC';

            $where = '';
            $params = [];
            if ($q !== '') {
                $where = "WHERE a.name LIKE ? OR a.asset_code LIKE ? OR a.category LIKE ?";
                $like = '%' . $q . '%';
                $params = [$like, $like, $like];
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM assets a $where");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $sql = "
                SELECT a.*, e.name as assigned_to_name,
                    COALESCE(s.total_qty, 0) as total_quantity,
                    COALESCE(s.total_value, 0) as total_value,
                    COALESCE(s.low_stock, 0) as low_stock
                FROM assets a
                LEFT JOIN employees e ON a.assigned_to = e.id
                LEFT JOIN (
                    SELECT asset_id, SUM(quantity) as total_qty, SUM(quantity * unit_price) as total_value,
                        MAX(CASE WHEN quantity <= min_stock THEN 1 ELSE 0 END) as low_stock
                    FROM inv_stock GROUP BY asset_id
                ) s ON s.asset_id = a.id
                $where
                ORDER BY $sort $dir
                LIMIT $perPage OFFSET $offset
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode([
                'data' => $stmt->fetchAll(),
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'pages' => (int)ceil($total / $perPage)
            ]);
        } elseif ($method === 'POST') {
            requireStockManager();
            verifyCSRF();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $asset_code = trim((string)($_POST['asset_code'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));

            requireFields(['asset_code', 'name'], ['asset_code' => $asset_code, 'name' => $name]);

            if ($id) {
                $stmt = $pdo->prepare("UPDATE assets SET asset_code = ?, name = ?, category = ? WHERE id = ?");
                $stmt->execute([$asset_code, $name, $category, $id]);
                logAudit($pdo, 'update', 'asset', $id, "Asset bijgewerkt: $asset_code ($name)");
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO assets (asset_code, name, category) VALUES (?, ?, ?)");
                $stmt->execute([$asset_code, $name, $category]);
                $newId = $pdo->lastInsertId();
                logAudit($pdo, 'create', 'asset', $newId, "Asset aangemaakt: $asset_code ($name)");
                echo json_encode(['success' => true, 'id' => $newId]);
            }
        }
        break;

    case 'retire_asset':
        requireStockManager();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldig asset']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE assets SET status = 'retired', assigned_to = NULL WHERE id = ?");
        $stmt->execute([$id]);
        logAudit($pdo, 'retire', 'asset', $id, 'Asset buiten gebruik gesteld');
        echo json_encode(['success' => true]);
        break;

    case 'bulk_maintenance':
        requireStockManager();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'Geen assets geselecteerd']);
            exit;
        }
        $ids = array_values(array_filter(array_map(fn($v) => filter_var($v, FILTER_VALIDATE_INT), $ids)));
        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'Geen geldige assets geselecteerd']);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE assets SET status = 'maintenance' WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        logAudit($pdo, 'bulk_maintenance', 'asset', null, count($ids) . ' asset(s) op onderhoud gezet: ' . implode(',', $ids));
        echo json_encode(['success' => true, 'count' => count($ids)]);
        break;

    case 'employees':
        requireLogin();

        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM employees ORDER BY name");
            echo json_encode($stmt->fetchAll());
        } elseif ($method === 'POST') {
            verifyCSRF();
            $name = trim((string)($_POST['name'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $department = trim((string)($_POST['department'] ?? ''));

            requireFields(['name'], ['name' => $name]);
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Ongeldig e-mailadres']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO employees (name, email, department) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $department]);
            $newId = $pdo->lastInsertId();
            logAudit($pdo, 'create', 'employee', $newId, "Medewerker aangemaakt: $name");

            echo json_encode(['success' => true, 'id' => $newId]);
        }
        break;

    case 'warehouses':
        requireLogin();
        if ($method === 'GET') {
            $stmt = $pdo->query("
                SELECT w.*, COALESCE(SUM(s.quantity), 0) as total_quantity
                FROM inv_warehouses w
                LEFT JOIN inv_stock s ON s.warehouse_id = w.id
                GROUP BY w.id
                ORDER BY w.name
            ");
            echo json_encode($stmt->fetchAll());
        } elseif ($method === 'POST') {
            requireStockManager();
            verifyCSRF();
            $code = trim((string)($_POST['code'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            requireFields(['code', 'name'], ['code' => $code, 'name' => $name]);

            $stmt = $pdo->prepare("INSERT INTO inv_warehouses (code, name, location) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $location]);
            $newId = $pdo->lastInsertId();
            logAudit($pdo, 'create', 'warehouse', $newId, "Magazijn aangemaakt: $code ($name)");
            echo json_encode(['success' => true, 'id' => $newId]);
        }
        break;

    case 'stock':
        requireLogin();
        if ($method === 'GET') {
            $assetId = filter_var($_GET['asset_id'] ?? null, FILTER_VALIDATE_INT);
            if ($assetId) {
                $stmt = $pdo->prepare("
                    SELECT s.*, w.name as warehouse_name, w.code as warehouse_code
                    FROM inv_stock s JOIN inv_warehouses w ON w.id = s.warehouse_id
                    WHERE s.asset_id = ? ORDER BY w.name
                ");
                $stmt->execute([$assetId]);
            } else {
                $stmt = $pdo->query("
                    SELECT s.*, a.name as asset_name, a.asset_code, w.name as warehouse_name, w.code as warehouse_code
                    FROM inv_stock s
                    JOIN assets a ON a.id = s.asset_id
                    JOIN inv_warehouses w ON w.id = s.warehouse_id
                    ORDER BY a.name
                ");
            }
            echo json_encode($stmt->fetchAll());
        } elseif ($method === 'POST') {
            requireStockManager();
            verifyCSRF();
            $assetId = filter_var($_POST['asset_id'] ?? null, FILTER_VALIDATE_INT);
            $warehouseId = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $minStock = filter_var($_POST['min_stock'] ?? 0, FILTER_VALIDATE_INT);
            $unitPrice = filter_var($_POST['unit_price'] ?? 0, FILTER_VALIDATE_FLOAT);

            if (!$assetId || !$warehouseId || $quantity === false || $quantity === null || $quantity < 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Ongeldige voorraadgegevens']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO inv_stock (asset_id, warehouse_id, quantity, min_stock, unit_price)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), min_stock = VALUES(min_stock), unit_price = VALUES(unit_price)
            ");
            $stmt->execute([$assetId, $warehouseId, $quantity, $minStock, $unitPrice ?: 0]);
            logAudit($pdo, 'stock_adjust', 'asset', $assetId, "Voorraad bijgewerkt naar $quantity (min: $minStock, prijs: $unitPrice) in magazijn #$warehouseId");
            echo json_encode(['success' => true]);
        }
        break;

    case 'batches':
        requireLogin();
        if ($method === 'GET') {
            $stmt = $pdo->query("
                SELECT b.*, a.name as asset_name, a.asset_code
                FROM inv_batches b JOIN assets a ON a.id = b.asset_id
                ORDER BY b.received_at DESC
            ");
            echo json_encode($stmt->fetchAll());
        } elseif ($method === 'POST') {
            requireStockManager();
            verifyCSRF();
            $assetId = filter_var($_POST['asset_id'] ?? null, FILTER_VALIDATE_INT);
            $batchNumber = trim((string)($_POST['batch_number'] ?? ''));
            $serialNumber = trim((string)($_POST['serial_number'] ?? ''));
            $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT) ?: 1;
            $expiry = trim((string)($_POST['expiry_date'] ?? ''));

            if (!$assetId) {
                http_response_code(400);
                echo json_encode(['error' => 'Asset is verplicht']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO inv_batches (asset_id, batch_number, serial_number, quantity, expiry_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$assetId, $batchNumber ?: null, $serialNumber ?: null, $quantity, $expiry ?: null]);
            $newId = $pdo->lastInsertId();
            logAudit($pdo, 'create', 'batch', $newId, "Batch/serie geregistreerd voor asset #$assetId ($batchNumber / $serialNumber)");
            echo json_encode(['success' => true, 'id' => $newId]);
        }
        break;

    case 'allocations':
        requireLogin();
        if ($method === 'GET') {
            $stmt = $pdo->query("
                SELECT al.*, a.name as asset_name, a.asset_code, w.name as warehouse_name
                FROM inv_stock_allocations al
                JOIN assets a ON a.id = al.asset_id
                JOIN inv_warehouses w ON w.id = al.warehouse_id
                WHERE al.released_at IS NULL
                ORDER BY al.allocated_at DESC
            ");
            echo json_encode($stmt->fetchAll());
        } elseif ($method === 'POST') {
            requireStockManager();
            verifyCSRF();
            $assetId = filter_var($_POST['asset_id'] ?? null, FILTER_VALIDATE_INT);
            $warehouseId = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $purpose = trim((string)($_POST['purpose'] ?? ''));

            if (!$assetId || !$warehouseId || !$quantity || $quantity <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Ongeldige allocatiegegevens']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO inv_stock_allocations (asset_id, warehouse_id, quantity, purpose, allocated_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$assetId, $warehouseId, $quantity, $purpose, $_SESSION['user_id']]);
            $newId = $pdo->lastInsertId();
            logAudit($pdo, 'allocate', 'stock_allocation', $newId, "$quantity stuks van asset #$assetId gealloceerd voor: $purpose");
            echo json_encode(['success' => true, 'id' => $newId]);
        }
        break;

    case 'release_allocation':
        requireStockManager();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige allocatie']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE inv_stock_allocations SET released_at = NOW() WHERE id = ? AND released_at IS NULL");
        $stmt->execute([$id]);
        logAudit($pdo, 'release_allocation', 'stock_allocation', $id, 'Allocatie vrijgegeven');
        echo json_encode(['success' => true]);
        break;

    case 'purchase_orders':
        requireLogin();
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM inv_purchase_orders ORDER BY created_at DESC");
            $orders = $stmt->fetchAll();
            $itemStmt = $pdo->prepare("
                SELECT poi.*, a.name as asset_name, a.asset_code
                FROM inv_purchase_order_items poi JOIN assets a ON a.id = poi.asset_id
                WHERE poi.po_id = ?
            ");
            foreach ($orders as &$o) {
                $itemStmt->execute([$o['id']]);
                $o['items'] = $itemStmt->fetchAll();
            }
            unset($o);
            echo json_encode($orders);
        } elseif ($method === 'POST') {
            requireStockManager();
            verifyCSRF();
            $supplier = trim((string)($_POST['supplier_name'] ?? ''));
            $recurring = in_array($_POST['recurring'] ?? 'none', ['none', 'weekly', 'monthly'], true) ? $_POST['recurring'] : 'none';
            $expectedDate = trim((string)($_POST['expected_date'] ?? ''));
            $assetIds = $_POST['asset_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];

            requireFields(['supplier_name'], ['supplier_name' => $supplier]);
            if (!is_array($assetIds) || empty($assetIds)) {
                http_response_code(400);
                echo json_encode(['error' => 'Voeg minstens één artikel toe aan de inkooporder']);
                exit;
            }

            $poNumber = 'PO-' . date('Y') . '-' . str_pad((string)($pdo->query("SELECT COUNT(*) FROM inv_purchase_orders")->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO inv_purchase_orders (po_number, supplier_name, status, recurring, expected_date, created_by) VALUES (?, ?, 'pending', ?, ?, ?)");
            $stmt->execute([$poNumber, $supplier, $recurring, $expectedDate ?: null, $_SESSION['user_id']]);
            $poId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO inv_purchase_order_items (po_id, asset_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            foreach ($assetIds as $i => $aid) {
                $aid = filter_var($aid, FILTER_VALIDATE_INT);
                $qty = filter_var($quantities[$i] ?? 0, FILTER_VALIDATE_INT);
                if ($aid && $qty > 0) {
                    $itemStmt->execute([$poId, $aid, $qty, 0]);
                }
            }

            logAudit($pdo, 'create', 'purchase_order', $poId, "Inkooporder $poNumber aangemaakt bij $supplier (herhalend: $recurring)");
            echo json_encode(['success' => true, 'id' => $poId, 'po_number' => $poNumber]);
        }
        break;

    case 'receive_purchase_order':
        requireStockManager();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();
        $poId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $warehouseId = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$poId || !$warehouseId) {
            http_response_code(400);
            echo json_encode(['error' => 'Magazijn en inkooporder zijn verplicht']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM inv_purchase_orders WHERE id = ?");
        $stmt->execute([$poId]);
        $po = $stmt->fetch();
        if (!$po) {
            http_response_code(404);
            echo json_encode(['error' => 'Inkooporder niet gevonden']);
            exit;
        }
        if ($po['status'] === 'received') {
            http_response_code(400);
            echo json_encode(['error' => 'Inkooporder is al ontvangen']);
            exit;
        }

        $itemStmt = $pdo->prepare("SELECT * FROM inv_purchase_order_items WHERE po_id = ?");
        $itemStmt->execute([$poId]);
        $items = $itemStmt->fetchAll();

        $upsert = $pdo->prepare("
            INSERT INTO inv_stock (asset_id, warehouse_id, quantity, min_stock, unit_price)
            VALUES (?, ?, ?, 5, ?)
            ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
        ");
        $received = 0;
        foreach ($items as $item) {
            $upsert->execute([$item['asset_id'], $warehouseId, $item['quantity'], $item['unit_price']]);
            $received += (int)$item['quantity'];
        }

        $stmt = $pdo->prepare("UPDATE inv_purchase_orders SET status = 'received' WHERE id = ?");
        $stmt->execute([$poId]);

        logAudit($pdo, 'receive', 'purchase_order', $poId, "Levering ontvangen voor {$po['po_number']}: $received stuks in magazijn #$warehouseId");
        echo json_encode(['success' => true, 'message' => "Levering ontvangen: $received stuks bijgeboekt"]);
        break;

    case 'sales_orders':
        requireLogin();
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM inv_sales_orders ORDER BY created_at DESC");
            $orders = $stmt->fetchAll();
            $itemStmt = $pdo->prepare("
                SELECT soi.*, a.name as asset_name, a.asset_code
                FROM inv_sales_order_items soi JOIN assets a ON a.id = soi.asset_id
                WHERE soi.so_id = ?
            ");
            foreach ($orders as &$o) {
                $itemStmt->execute([$o['id']]);
                $o['items'] = $itemStmt->fetchAll();
            }
            unset($o);
            echo json_encode($orders);
        } elseif ($method === 'POST') {
            requireStockManager();
            verifyCSRF();
            $customer = trim((string)($_POST['customer_name'] ?? ''));
            $assetIds = $_POST['asset_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];

            requireFields(['customer_name'], ['customer_name' => $customer]);
            if (!is_array($assetIds) || empty($assetIds)) {
                http_response_code(400);
                echo json_encode(['error' => 'Voeg minstens één artikel toe aan de verkooporder']);
                exit;
            }

            $soNumber = 'SO-' . date('Y') . '-' . str_pad((string)($pdo->query("SELECT COUNT(*) FROM inv_sales_orders")->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO inv_sales_orders (so_number, customer_name, status, created_by) VALUES (?, ?, 'open', ?)");
            $stmt->execute([$soNumber, $customer, $_SESSION['user_id']]);
            $soId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO inv_sales_order_items (so_id, asset_id, quantity) VALUES (?, ?, ?)");
            foreach ($assetIds as $i => $aid) {
                $aid = filter_var($aid, FILTER_VALIDATE_INT);
                $qty = filter_var($quantities[$i] ?? 0, FILTER_VALIDATE_INT);
                if ($aid && $qty > 0) {
                    $itemStmt->execute([$soId, $aid, $qty]);
                }
            }

            logAudit($pdo, 'create', 'sales_order', $soId, "Verkooporder $soNumber aangemaakt voor $customer");
            echo json_encode(['success' => true, 'id' => $soId, 'so_number' => $soNumber]);
        }
        break;

    case 'fulfill_sales_order':
        requireStockManager();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();
        $soId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$soId) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldige verkooporder']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT * FROM inv_sales_orders WHERE id = ?");
        $stmt->execute([$soId]);
        $so = $stmt->fetch();
        if (!$so) {
            http_response_code(404);
            echo json_encode(['error' => 'Verkooporder niet gevonden']);
            exit;
        }
        if ($so['status'] !== 'open') {
            http_response_code(400);
            echo json_encode(['error' => 'Verkooporder is niet meer open']);
            exit;
        }

        $itemStmt = $pdo->prepare("SELECT * FROM inv_sales_order_items WHERE so_id = ?");
        $itemStmt->execute([$soId]);
        $items = $itemStmt->fetchAll();

        $decStmt = $pdo->prepare("
            UPDATE inv_stock SET quantity = GREATEST(0, quantity - ?)
            WHERE asset_id = ? ORDER BY quantity DESC LIMIT 1
        ");
        foreach ($items as $item) {
            $decStmt->execute([$item['quantity'], $item['asset_id']]);
        }

        $stmt = $pdo->prepare("UPDATE inv_sales_orders SET status = 'fulfilled' WHERE id = ?");
        $stmt->execute([$soId]);

        logAudit($pdo, 'fulfill', 'sales_order', $soId, "Verkooporder {$so['so_number']} uitgeleverd (voorraad afgeboekt)");
        echo json_encode(['success' => true, 'message' => 'Verkooporder uitgeleverd']);
        break;

    case 'reports':
        requireLogin();

        // Totale voorraadwaarde (totaal en per magazijn)
        $totalValue = (float)$pdo->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM inv_stock")->fetchColumn();
        $stmt = $pdo->query("
            SELECT w.name as warehouse_name, COALESCE(SUM(s.quantity * s.unit_price), 0) as value, COALESCE(SUM(s.quantity), 0) as quantity
            FROM inv_warehouses w LEFT JOIN inv_stock s ON s.warehouse_id = w.id
            GROUP BY w.id, w.name ORDER BY w.name
        ");
        $valueByWarehouse = $stmt->fetchAll();

        // Omloopsnelheid (turnover) per categorie: uitgiftes laatste 30 dagen t.o.v. gemiddelde voorraad.
        // Vereenvoudigde formule voor demo-doeleinden (echte turnover rate gebruikt COGS/gemiddelde voorraadwaarde).
        $stmt = $pdo->query("
            SELECT a.category,
                COUNT(t.id) as checkouts_30d
            FROM assets a
            LEFT JOIN asset_transactions t ON t.asset_id = a.id AND t.type = 'checkout' AND t.checkout_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY a.category
        ");
        $checkoutsByCategory = [];
        foreach ($stmt->fetchAll() as $row) {
            $checkoutsByCategory[$row['category'] ?: 'Overig'] = (int)$row['checkouts_30d'];
        }

        $stmt = $pdo->query("
            SELECT a.category, AVG(s.quantity) as avg_qty
            FROM assets a JOIN inv_stock s ON s.asset_id = a.id
            GROUP BY a.category
        ");
        $turnover = [];
        foreach ($stmt->fetchAll() as $row) {
            $cat = $row['category'] ?: 'Overig';
            $avgQty = max(0.01, (float)$row['avg_qty']);
            $checkouts = $checkoutsByCategory[$cat] ?? 0;
            $turnover[] = [
                'category' => $cat,
                'checkouts_30d' => $checkouts,
                'avg_stock' => round($avgQty, 1),
                'turnover_rate' => round($checkouts / $avgQty, 2)
            ];
        }
        usort($turnover, fn($a, $b) => $b['turnover_rate'] <=> $a['turnover_rate']);

        echo json_encode([
            'total_stock_value' => $totalValue,
            'value_by_warehouse' => $valueByWarehouse,
            'turnover_by_category' => $turnover
        ]);
        break;

    case 'audit_log':
        requireRole(['admin']);
        $stmt = $pdo->query("SELECT * FROM inv_audit_log ORDER BY created_at DESC LIMIT 100");
        echo json_encode($stmt->fetchAll());
        break;

    case 'erp_settings':
        if ($method === 'GET') {
            requireRole(['admin']);
            $stmt = $pdo->query("SELECT * FROM inv_erp_settings WHERE id = 1");
            $settings = $stmt->fetch();
            if ($settings) {
                // Toon nooit de volledige (nep-)sleutel terug, alleen of er iets is ingesteld.
                $settings['api_key_set'] = $settings['api_key'] !== '';
                unset($settings['api_key']);
            }
            echo json_encode($settings ?: null);
        } elseif ($method === 'POST') {
            requireRole(['admin']);
            verifyCSRF();
            $provider = trim((string)($_POST['provider'] ?? 'Exact Online (demo)'));
            $apiKey = trim((string)($_POST['api_key'] ?? ''));

            $stmt = $pdo->prepare("
                INSERT INTO inv_erp_settings (id, provider, api_key) VALUES (1, ?, ?)
                ON DUPLICATE KEY UPDATE provider = VALUES(provider), api_key = VALUES(api_key)
            ");
            $stmt->execute([$provider, $apiKey]);
            logAudit($pdo, 'update', 'erp_settings', 1, "ERP-instellingen bijgewerkt (provider: $provider) [MOCK, geen echte koppeling]");
            echo json_encode(['success' => true]);
        }
        break;

    case 'erp_sync':
        requireRole(['admin']);
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();

        // LET OP: dit is een MOCK-synchronisatie. Er wordt geen enkele externe API aangeroepen.
        $stmt = $pdo->query("SELECT provider, api_key FROM inv_erp_settings WHERE id = 1");
        $settings = $stmt->fetch();
        $provider = $settings['provider'] ?? 'Exact Online (demo)';

        if (empty($settings['api_key'])) {
            $log = "✗ Synchronisatie geweigerd: geen API-sleutel ingesteld voor $provider (demo).";
            echo json_encode(['success' => false, 'log' => $log]);
            break;
        }

        $assetCount = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
        $stockCount = (int)$pdo->query("SELECT COUNT(*) FROM inv_stock")->fetchColumn();
        $poCount = (int)$pdo->query("SELECT COUNT(*) FROM inv_purchase_orders WHERE status = 'received'")->fetchColumn();

        $lines = [
            "→ Verbinding maken met $provider (demo)...",
            "✓ Authenticatie geslaagd met opgeslagen (nep-)API-sleutel.",
            "✓ $assetCount artikelen gesynchroniseerd naar $provider (demo).",
            "✓ $stockCount voorraadregels bijgewerkt.",
            "✓ $poCount ontvangen inkooporders doorgeboekt naar de boekhouding (demo).",
            "✓ Synchronisatie voltooid op " . date('d-m-Y H:i:s') . "."
        ];
        $log = implode("\n", $lines);

        $stmt = $pdo->prepare("UPDATE inv_erp_settings SET last_sync_at = NOW(), last_sync_log = ? WHERE id = 1");
        $stmt->execute([$log]);
        logAudit($pdo, 'erp_sync', 'erp_settings', 1, "Mock-synchronisatie uitgevoerd naar $provider");

        echo json_encode(['success' => true, 'log' => $log]);
        break;

    case 'export_assets_csv':
        requireLogin();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="assets_export_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['asset_code', 'name', 'category', 'status', 'total_quantity', 'total_value']);
        $stmt = $pdo->query("
            SELECT a.asset_code, a.name, a.category, a.status,
                COALESCE(SUM(s.quantity), 0) as total_quantity,
                COALESCE(SUM(s.quantity * s.unit_price), 0) as total_value
            FROM assets a
            LEFT JOIN inv_stock s ON s.asset_id = a.id
            GROUP BY a.id ORDER BY a.name
        ");
        foreach ($stmt->fetchAll() as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;

    case 'import_assets_csv':
        requireStockManager();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();

        if (empty($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'Geen (geldig) CSV-bestand ontvangen']);
            exit;
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) {
            http_response_code(400);
            echo json_encode(['error' => 'Kan bestand niet lezen']);
            exit;
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            http_response_code(400);
            echo json_encode(['error' => 'Leeg CSV-bestand']);
            exit;
        }
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        $upsert = $pdo->prepare("
            INSERT INTO assets (asset_code, name, category, status) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category)
        ");
        $count = 0;
        $skipped = 0;
        $validStatuses = ['available', 'checked_out', 'maintenance', 'retired'];

        while (($row = fgetcsv($handle)) !== false) {
            // array_combine() gooit een ValueError bij een ongelijk aantal kolommen (bijv. lege regel) in PHP 8,
            // dus eerst expliciet de kolomaantallen vergelijken in plaats van op een onderdrukte warning te leunen.
            if (count($row) !== count($header)) {
                $skipped++;
                continue;
            }
            $rowAssoc = array_combine($header, $row);
            if (empty($rowAssoc['asset_code']) || empty($rowAssoc['name'])) {
                $skipped++;
                continue;
            }
            $status = in_array($rowAssoc['status'] ?? '', $validStatuses, true) ? $rowAssoc['status'] : 'available';
            $upsert->execute([
                trim($rowAssoc['asset_code']),
                trim($rowAssoc['name']),
                trim($rowAssoc['category'] ?? ''),
                $status
            ]);
            $count++;
        }
        fclose($handle);

        logAudit($pdo, 'import_csv', 'asset', null, "CSV-import: $count artikelen verwerkt, $skipped overgeslagen");
        echo json_encode(['success' => true, 'message' => "$count artikelen geïmporteerd ($skipped rijen overgeslagen)"]);
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

    case 'seed':
        requireLogin();
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        verifyCSRF();
        if ($_SESSION['user_role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Admin only']);
            exit;
        }

        $employees = [
            ['Sophie de Boer', 'sophie@demo.nl', 'Operations'],
            ['Thomas Visser', 'thomas@demo.nl', 'Logistics'],
            ['Emma Bakker', 'emma@demo.nl', 'Warehouse'],
            ['Daan Mulder', 'daan@demo.nl', 'IT'],
            ['Lisa Jansen', 'lisa@demo.nl', 'HR'],
            ['Max Smit', 'max@demo.nl', 'Finance'],
            ['Fleur Dijkstra', 'fleur@demo.nl', 'Marketing'],
            ['Ruben de Groot', 'ruben@demo.nl', 'Operations'],
            ['Nina Schouten', 'nina@demo.nl', 'Logistics'],
            ['Bas Kok', 'bas@demo.nl', 'Warehouse'],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO employees (name, email, department) VALUES (?, ?, ?)");
        foreach ($employees as $emp) {
            $stmt->execute($emp);
        }

        $assets = [
            ['INV-001', 'Dell XPS 15 Laptop', 'Laptops', 'available'],
            ['INV-002', 'MacBook Pro 14"', 'Laptops', 'available'],
            ['INV-003', 'ThinkPad X1 Carbon', 'Laptops', 'checked_out'],
            ['INV-004', 'Samsung 27" Monitor', 'Monitors', 'available'],
            ['INV-005', 'LG UltraWide 34"', 'Monitors', 'available'],
            ['INV-006', 'HP LaserJet Pro', 'Printers', 'maintenance'],
            ['INV-007', 'Brother MFC Printer', 'Printers', 'available'],
            ['INV-008', 'Logitech MX Keys', 'Accessories', 'available'],
            ['INV-009', 'Logitech MX Master 3', 'Accessories', 'available'],
            ['INV-010', 'Jabra Evolve2 75', 'Headsets', 'available'],
            ['INV-011', 'Sony WH-1000XM5', 'Headsets', 'checked_out'],
            ['INV-012', 'Elgato Stream Deck', 'Accessories', 'available'],
            ['INV-013', 'Dell Docking Station', 'Accessories', 'available'],
            ['INV-014', 'iPad Pro 12.9"', 'Tablets', 'available'],
            ['INV-015', 'Logitech Brio Webcam', 'Accessories', 'checked_out'],
            ['INV-016', 'Keychron K2 Keyboard', 'Accessories', 'available'],
            ['INV-017', 'BenQ ScreenBar Monitor Light', 'Accessories', 'available'],
            ['INV-018', 'Samsung T7 SSD 1TB', 'Storage', 'available'],
            ['INV-019', 'APC UPS 1500VA', 'Power', 'available'],
            ['INV-020', 'Cisco Webex Room Kit', 'Meeting', 'available'],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO assets (asset_code, name, category, status) VALUES (?, ?, ?, ?)");
        foreach ($assets as $a) {
            $stmt->execute($a);
        }

        $empStmt = $pdo->query("SELECT id FROM employees ORDER BY RAND() LIMIT 10");
        $empIds = $empStmt->fetchAll(PDO::FETCH_COLUMN);

        $assetStmt = $pdo->query("SELECT id FROM assets ORDER BY RAND() LIMIT 15");
        $assetIds = $assetStmt->fetchAll(PDO::FETCH_COLUMN);

        $pdo->exec("DELETE FROM asset_transactions");
        $transStmt = $pdo->prepare("INSERT INTO asset_transactions (asset_id, employee_id, type, checkout_date, checkin_date, notes) VALUES (?, ?, ?, ?, ?, ?)");

        for ($i = 0; $i < 25; $i++) {
            $assetId = $assetIds[array_rand($assetIds)];
            $empId = $empIds[array_rand($empIds)];
            $daysAgo = rand(1, 30);
            $hoursOut = rand(1, 48);
            $checkoutDate = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));
            $checkinDate = ($i % 3 !== 0) ? date('Y-m-d H:i:s', strtotime($checkoutDate . "+{$hoursOut} hours")) : null;
            $notes = ['Gebruikt voor project', 'Tijdelijk geleend', 'Vergadering', 'Thuiswerken', ''][array_rand([0,1,2,3,4])];
            $transStmt->execute([$assetId, $empId, 'checkout', $checkoutDate, $checkinDate, $notes]);
        }

        logAudit($pdo, 'reseed', 'system', null, 'Handmatige herseed via Beheer-pagina');
        echo json_encode(['success' => true, 'message' => 'Test data toegevoegd: 10 medewerkers, 20 assets, 25 transacties']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
