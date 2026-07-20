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
    <title>Inventory Manager - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="theme-color" content="#2563eb">
    <link rel="manifest" href="manifest.json">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">Inventory Manager</div>
        <div class="nav-links">
            <a href="index.php" class="active">Dashboard</a>
            <a href="checkout.php">Uitgifte</a>
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
        <h1>Dashboard</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="totalAssets">-</div>
                <div class="stat-label">Totaal Assets</div>
            </div>
            <div class="stat-card stat-available">
                <div class="stat-value" id="availableAssets">-</div>
                <div class="stat-label">Beschikbaar</div>
            </div>
            <div class="stat-card stat-checkedout">
                <div class="stat-value" id="checkedOutAssets">-</div>
                <div class="stat-label">Uitgegeven</div>
            </div>
            <div class="stat-card stat-maintenance">
                <div class="stat-value" id="maintenanceAssets">-</div>
                <div class="stat-label">In onderhoud</div>
            </div>
        </div>
        
        <div class="section">
            <h2>Recente transacties</h2>
            <div class="table-container">
                <table id="transactionsTable">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Code</th>
                            <th>Medewerker</th>
                            <th>Type</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody id="transactionsBody">
                        <tr><td colspan="5">Laden...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script>
        async function loadDashboard() {
            try {
                const response = await fetch('api.php?action=dashboard');
                const data = await response.json();
                
                document.getElementById('totalAssets').textContent = data.stats.total;
                document.getElementById('availableAssets').textContent = data.stats.available;
                document.getElementById('checkedOutAssets').textContent = data.stats.checked_out;
                document.getElementById('maintenanceAssets').textContent = data.stats.maintenance;
                
                const tbody = document.getElementById('transactionsBody');
                if (data.recent_transactions.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5">Geen transacties</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.recent_transactions.map(t => `
                    <tr>
                        <td>${escapeHtml(t.asset_name)}</td>
                        <td>${escapeHtml(t.asset_code)}</td>
                        <td>${escapeHtml(t.employee_name)}</td>
                        <td><span class="badge badge-${t.type}">${t.type === 'checkout' ? 'Uitgifte' : 'Inname'}</span></td>
                        <td>${new Date(t.checkout_date).toLocaleString('nl-NL')}</td>
                    </tr>
                `).join('');
            } catch (err) {
                console.error('Failed to load dashboard:', err);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        loadDashboard();
    </script>
</body>
</html>
