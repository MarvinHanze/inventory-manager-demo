<?php
require_once 'config.php';
requireLogin();
$user = getUser();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager - Uitgifte</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="theme-color" content="#2563eb">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">Inventory Manager</div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="checkout.php" class="active">Uitgifte</a>
            <a href="checkin.php">Inname</a>
            <a href="admin.php">Beheer</a>
            <a href="analytics.php">Rapportages</a>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($user['name']) ?></span>
            <a href="api.php?action=logout" class="btn btn-sm">Uitloggen</a>
        </div>
    </nav>
    
    <main class="container">
        <h1>Asset Uitgifte</h1>
        
        <div class="form-card">
            <form id="checkoutForm">
                <div class="form-group">
                    <label for="assetCode">Asset Code (scan of voer in)</label>
                    <input type="text" id="assetCode" name="asset_code" required 
                           placeholder="bijv. AST-001" autofocus>
                </div>
                
                <div class="form-group">
                    <label for="employeeId">Medewerker</label>
                    <select id="employeeId" name="employee_id" required>
                        <option value="">Selecteer medewerker...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Opmerkingen (optioneel)</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Optionele opmerkingen..."></textarea>
                </div>
                
                <div id="result" class="result-message" style="display: none;"></div>
                
                <button type="submit" class="btn btn-primary btn-block">Asset uitgeven</button>
            </form>
        </div>
        
        <div class="section">
            <h2>Recent uitgegeven</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Code</th>
                            <th>Medewerker</th>
                            <th>Tijd</th>
                        </tr>
                    </thead>
                    <tbody id="recentCheckouts">
                        <tr><td colspan="4">Laden...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script>
        // Load employees
        async function loadEmployees() {
            const response = await fetch('api.php?action=employees');
            const employees = await response.json();
            const select = document.getElementById('employeeId');
            employees.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = emp.name;
                select.appendChild(option);
            });
        }
        
        // Load recent checkouts
        async function loadRecent() {
            const response = await fetch('api.php?action=dashboard');
            const data = await response.json();
            const tbody = document.getElementById('recentCheckouts');
            
            const checkouts = data.recent_transactions.filter(t => t.type === 'checkout').slice(0, 5);
            
            if (checkouts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">Geen recente uitgiftes</td></tr>';
                return;
            }
            
            tbody.innerHTML = checkouts.map(t => `
                <tr>
                    <td>${escapeHtml(t.asset_name)}</td>
                    <td>${escapeHtml(t.asset_code)}</td>
                    <td>${escapeHtml(t.employee_name)}</td>
                    <td>${new Date(t.checkout_date).toLocaleString('nl-NL')}</td>
                </tr>
            `).join('');
        }
        
        // Handle checkout
        document.getElementById('checkoutForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const resultEl = document.getElementById('result');
            resultEl.style.display = 'none';
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('api.php?action=checkout', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    resultEl.className = 'result-message result-success';
                    resultEl.textContent = data.message;
                    resultEl.style.display = 'block';
                    e.target.reset();
                    loadRecent();
                } else {
                    resultEl.className = 'result-message result-error';
                    resultEl.textContent = data.error;
                    resultEl.style.display = 'block';
                }
            } catch (err) {
                resultEl.className = 'result-message result-error';
                resultEl.textContent = 'Netwerkfout';
                resultEl.style.display = 'block';
            }
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        loadEmployees();
        loadRecent();
    </script>
</body>
</html>
