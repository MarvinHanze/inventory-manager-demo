<?php
require_once 'config.php';
requireLogin();
$user = getUser();
if ($user['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager - Beheer</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="theme-color" content="#2563eb">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">Inventory Manager</div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="checkout.php">Uitgifte</a>
            <a href="checkin.php">Inname</a>
            <a href="admin.php" class="active">Beheer</a>
            <a href="analytics.php">Rapportages</a>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($user['name']) ?></span>
            <a href="api.php?action=logout" class="btn btn-sm">Uitloggen</a>
        </div>
    </nav>
    
    <main class="container">
        <h1>Beheer</h1>
        
        <div class="tabs">
            <button class="tab active" data-tab="assets">Assets</button>
            <button class="tab" data-tab="employees">Medewerkers</button>
        </div>
        
        <!-- Assets Tab -->
        <div class="tab-content active" id="assetsTab">
            <div class="section-header">
                <h2>Assets</h2>
                <button class="btn btn-primary" onclick="showAssetForm()">+ Nieuw asset</button>
            </div>
            
            <div id="assetFormContainer" class="form-card" style="display: none;">
                <h3 id="assetFormTitle">Nieuw asset</h3>
                <form id="assetForm">
                    <input type="hidden" id="assetId">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="assetCode">Asset Code</label>
                            <input type="text" id="assetCode" name="asset_code" required placeholder="bijv. AST-011">
                        </div>
                        <div class="form-group">
                            <label for="assetName">Naam</label>
                            <input type="text" id="assetName" name="name" required placeholder="bijv. Laptop">
                        </div>
                        <div class="form-group">
                            <label for="assetCategory">Categorie</label>
                            <input type="text" id="assetCategory" name="category" placeholder="bijv. Electronics">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Opslaan</button>
                        <button type="button" class="btn" onclick="hideAssetForm()">Annuleren</button>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Naam</th>
                            <th>Categorie</th>
                            <th>Status</th>
                            <th>Toegewezen</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody id="assetsBody">
                        <tr><td colspan="6">Laden...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Employees Tab -->
        <div class="tab-content" id="employeesTab">
            <div class="section-header">
                <h2>Medewerkers</h2>
                <button class="btn btn-primary" onclick="showEmployeeForm()">+ Nieuwe medewerker</button>
            </div>
            
            <div id="employeeFormContainer" class="form-card" style="display: none;">
                <h3 id="employeeFormTitle">Nieuwe medewerker</h3>
                <form id="employeeForm">
                    <input type="hidden" id="empId">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="empName">Naam</label>
                            <input type="text" id="empName" name="name" required placeholder="bijv. Jan de Vries">
                        </div>
                        <div class="form-group">
                            <label for="empEmail">E-mail</label>
                            <input type="email" id="empEmail" name="email" placeholder="jan@demo.nl">
                        </div>
                        <div class="form-group">
                            <label for="empDept">Afdeling</label>
                            <input type="text" id="empDept" name="department" placeholder="bijv. IT">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Opslaan</button>
                        <button type="button" class="btn" onclick="hideEmployeeForm()">Annuleren</button>
                    </div>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Naam</th>
                            <th>E-mail</th>
                            <th>Afdeling</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="employeesBody">
                        <tr><td colspan="4">Laden...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab + 'Tab').classList.add('active');
            });
        });
        
        // Assets
        async function loadAssets() {
            const response = await fetch('api.php?action=assets');
            const assets = await response.json();
            const tbody = document.getElementById('assetsBody');
            
            if (assets.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">Geen assets</td></tr>';
                return;
            }
            
            tbody.innerHTML = assets.map(a => `
                <tr>
                    <td>${escapeHtml(a.asset_code)}</td>
                    <td>${escapeHtml(a.name)}</td>
                    <td>${escapeHtml(a.category || '-')}</td>
                    <td><span class="badge badge-${a.status}">${a.status}</span></td>
                    <td>${escapeHtml(a.assigned_to_name || '-')}</td>
                    <td>
                        <button class="btn btn-sm" onclick="editAsset(${a.id})">Bewerken</button>
                    </td>
                </tr>
            `).join('');
        }
        
        function showAssetForm(asset = null) {
            document.getElementById('assetFormContainer').style.display = 'block';
            document.getElementById('assetFormTitle').textContent = asset ? 'Asset bewerken' : 'Nieuw asset';
            if (asset) {
                document.getElementById('assetId').value = asset.id;
                document.getElementById('assetCode').value = asset.asset_code;
                document.getElementById('assetName').value = asset.name;
                document.getElementById('assetCategory').value = asset.category || '';
            } else {
                document.getElementById('assetForm').reset();
                document.getElementById('assetId').value = '';
            }
        }
        
        function hideAssetForm() {
            document.getElementById('assetFormContainer').style.display = 'none';
        }
        
        async function editAsset(id) {
            const response = await fetch('api.php?action=assets');
            const assets = await response.json();
            const asset = assets.find(a => a.id === id);
            if (asset) showAssetForm(asset);
        }
        
        document.getElementById('assetForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            await fetch('api.php?action=assets', {
                method: 'POST',
                body: formData
            });
            
            hideAssetForm();
            loadAssets();
        });
        
        // Employees
        async function loadEmployees() {
            const response = await fetch('api.php?action=employees');
            const employees = await response.json();
            const tbody = document.getElementById('employeesBody');
            
            if (employees.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">Geen medewerkers</td></tr>';
                return;
            }
            
            tbody.innerHTML = employees.map(e => `
                <tr>
                    <td>${escapeHtml(e.name)}</td>
                    <td>${escapeHtml(e.email || '-')}</td>
                    <td>${escapeHtml(e.department || '-')}</td>
                    <td><span class="badge badge-${e.status}">${e.status}</span></td>
                </tr>
            `).join('');
        }
        
        function showEmployeeForm(emp = null) {
            document.getElementById('employeeFormContainer').style.display = 'block';
            document.getElementById('employeeFormTitle').textContent = emp ? 'Medewerker bewerken' : 'Nieuwe medewerker';
            if (emp) {
                document.getElementById('empId').value = emp.id;
                document.getElementById('empName').value = emp.name;
                document.getElementById('empEmail').value = emp.email || '';
                document.getElementById('empDept').value = emp.department || '';
            } else {
                document.getElementById('employeeForm').reset();
                document.getElementById('empId').value = '';
            }
        }
        
        function hideEmployeeForm() {
            document.getElementById('employeeFormContainer').style.display = 'none';
        }
        
        document.getElementById('employeeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            
            await fetch('api.php?action=employees', {
                method: 'POST',
                body: formData
            });
            
            hideEmployeeForm();
            loadEmployees();
        });
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        loadAssets();
        loadEmployees();
    </script>
</body>
</html>
