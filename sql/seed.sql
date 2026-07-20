CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    department VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS assets (
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
);

CREATE TABLE IF NOT EXISTS asset_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    employee_id INT NOT NULL,
    type ENUM('checkout', 'checkin') NOT NULL,
    checkout_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checkin_date TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);

CREATE TABLE IF NOT EXISTS login_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Demo users (password: demo123)
INSERT INTO users (email, password, name, role) VALUES
('admin@demo.nl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin'),
('user@demo.nl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Regular User', 'user');

-- Demo employees
INSERT INTO employees (name, email, department) VALUES
('Jan de Vries', 'jan@demo.nl', 'Logistics'),
('Piet Jansen', 'piet@demo.nl', 'Warehouse'),
('Marie Bakker', 'marie@demo.nl', 'Operations'),
('Cor Visser', 'cor@demo.nl', 'IT'),
('Lisa Mulder', 'lisa@demo.nl', 'HR');

-- Demo assets
INSERT INTO assets (asset_code, name, category, status) VALUES
('AST-001', 'Laptop Dell XPS', 'Electronics', 'available'),
('AST-002', 'Monitor Samsung 27', 'Electronics', 'available'),
('AST-003', 'Barcode Scanner', 'Hardware', 'checked_out'),
('AST-004', 'Printer HP LaserJet', 'Electronics', 'available'),
('AST-005', 'Keyboard Wireless', 'Accessories', 'available'),
('AST-006', 'Mouse Logitech', 'Accessories', 'maintenance'),
('AST-007', 'Headset Jabra', 'Accessories', 'available'),
('AST-008', 'Webcam Logitech', 'Electronics', 'available'),
('AST-009', 'Docking Station', 'Hardware', 'available'),
('AST-010', 'Tablet iPad', 'Electronics', 'retired');

-- Demo transactions
INSERT INTO asset_transactions (asset_id, employee_id, type, checkout_date, checkin_date) VALUES
(3, 1, 'checkout', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL),
(1, 2, 'checkout', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 3, 'checkout', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)),
(4, 1, 'checkout', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 4, 'checkout', DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL);
