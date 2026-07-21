<?php
require_once 'config.php';
requireLogin();
$user = getUser();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <base href="/inventory-manager/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager - Inname</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="theme-color" content="#2563eb">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">Inventory Manager</div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="checkout.php">Uitgifte</a>
            <a href="checkin.php" class="active">Inname</a>
            <a href="admin.php">Beheer</a>
            <a href="analytics.php">Rapportages</a>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($user['name']) ?></span>
            <form method="post" action="api.php?action=logout" style="display:inline">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-sm">Uitloggen</button>
            </form>
        </div>
    </nav>
    
    <main class="container">
        <h1>Asset Inname</h1>
        
        <div class="form-card">
            <form id="checkinForm">
                <div class="form-group">
                    <label for="assetCode">Asset Code (scan of voer in)</label>
                    <input type="text" id="assetCode" name="scan_input" required 
                           placeholder="bijv. INV-001" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
                </div>
                
                <div id="result" class="result-message" style="display: none;"></div>
                
                <button type="submit" class="btn btn-primary btn-block">Asset innemen</button>
            </form>
        </div>
        
        <div class="section">
            <h2>Actief uitgegeven</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Code</th>
                            <th>Medewerker</th>
                            <th>Sinds</th>
                        </tr>
                    </thead>
                    <tbody id="activeCheckouts">
                        <tr><td colspan="4">Laden...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <script>
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        
        // Load active checkouts
        async function loadActive() {
            const response = await fetch('api.php?action=assets');
            const assets = await response.json();
            const tbody = document.getElementById('activeCheckouts');
            
            const checkedOut = assets.filter(a => a.status === 'checked_out');
            
            if (checkedOut.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">Geen actieve uitgiftes</td></tr>';
                return;
            }
            
            tbody.innerHTML = checkedOut.map(a => `
                <tr>
                    <td>${escapeHtml(a.name)}</td>
                    <td>${escapeHtml(a.asset_code)}</td>
                    <td>${escapeHtml(a.assigned_to_name || '-')}</td>
                    <td>${a.last_checkout ? new Date(a.last_checkout).toLocaleString('nl-NL') : '-'}</td>
                </tr>
            `).join('');
        }
        
        // Handle checkin
        document.getElementById('checkinForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const resultEl = document.getElementById('result');
            resultEl.style.display = 'none';
            
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            
            try {
                const response = await fetch('api.php?action=checkin', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    resultEl.className = 'result-message result-success';
                    resultEl.textContent = data.message;
                    resultEl.style.display = 'block';
                    e.target.reset();
                    loadActive();
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
        
        loadActive();
    </script>
</body>
</html>
