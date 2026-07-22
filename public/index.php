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
    <title>Inventory Manager - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
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
            <a href="orders.php">Orders</a>
            <a href="admin.php">Beheer</a>
            <a href="analytics.php">Rapportages</a>
            <?php if (isAdmin()): ?><a href="erp-integratie.php">ERP-koppeling</a><?php endif; ?>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($user['name']) ?> <span class="hz-badge hz-badge--gray"><?= htmlspecialchars($user['role']) ?></span></span>
            <form method="post" action="api.php?action=logout" style="display:inline">
                <?= csrfField() ?>
                <button type="submit" class="btn btn-sm" data-hz-confirm="Weet je zeker dat je wilt uitloggen?">Uitloggen</button>
            </form>
        </div>
    </nav>
    <div class="hz-bottomnav">
        <a href="index.php" class="hz-bottomnav__item hz-is-active"><?= hz_icon('bar-chart') ?><span>Dashboard</span></a>
        <a href="checkout.php" class="hz-bottomnav__item"><?= hz_icon('upload') ?><span>Uitgifte</span></a>
        <a href="checkin.php" class="hz-bottomnav__item"><?= hz_icon('download') ?><span>Inname</span></a>
        <a href="orders.php" class="hz-bottomnav__item"><?= hz_icon('box') ?><span>Orders</span></a>
        <a href="admin.php" class="hz-bottomnav__item"><?= hz_icon('settings') ?><span>Beheer</span></a>
    </div>

    <main class="container">
        <h1>Dashboard</h1>

        <!-- Prominente veelgebruikte acties (i.p.v. alleen iconen: duidelijke labels) -->
        <div class="hz-flex-wrap" style="margin-bottom: 1.5rem;">
            <a href="admin.php" class="hz-btn hz-btn--primary"><?= hz_icon('box') ?> Voorraad aanpassen</a>
            <a href="orders.php#receive" class="hz-btn hz-btn--secondary"><?= hz_icon('truck') ?> Levering ontvangen</a>
            <a href="checkout.php" class="hz-btn hz-btn--outline"><?= hz_icon('arrow-right') ?> Asset uitgeven</a>
            <a href="checkin.php" class="hz-btn hz-btn--outline"><?= hz_icon('arrow-left') ?> Asset innemen</a>
        </div>

        <div class="hz-grid hz-grid--3" style="margin-bottom: 1.5rem;">
            <div class="hz-card hz-card--stat">
                <div class="hz-card__value" id="totalAssets">-</div>
                <div class="hz-card__label">Totaal Assets</div>
            </div>
            <div class="hz-card hz-card--stat">
                <div class="hz-card__value" id="availableAssets" style="color: var(--hz-success);">-</div>
                <div class="hz-card__label">Beschikbaar</div>
            </div>
            <div class="hz-card hz-card--stat">
                <div class="hz-card__value" id="checkedOutAssets" style="color: var(--hz-warning);">-</div>
                <div class="hz-card__label">Uitgegeven</div>
            </div>
            <div class="hz-card hz-card--stat">
                <div class="hz-card__value" id="maintenanceAssets" style="color: var(--hz-danger);">-</div>
                <div class="hz-card__label">In onderhoud</div>
            </div>
            <div class="hz-card hz-card--stat">
                <div class="hz-card__value" id="lowStockCount" style="color: var(--hz-danger);">-</div>
                <div class="hz-card__label">Lage voorraad meldingen</div>
            </div>
            <div class="hz-card hz-card--stat">
                <div class="hz-card__value" id="stockValue">-</div>
                <div class="hz-card__label">Totale voorraadwaarde</div>
            </div>
        </div>

        <div class="hz-grid" style="grid-template-columns: 1.3fr 1fr; margin-bottom: 1.5rem;">
            <div class="hz-card">
                <div class="hz-card__header"><h2 style="font-size:1.1rem;">Voorraadverloop (laatste 14 dagen)</h2></div>
                <canvas id="trendChart" height="110"></canvas>
            </div>
            <div class="hz-card">
                <div class="hz-card__header"><h2 style="font-size:1.1rem;"><?= hz_icon('alert-triangle') ?> Lage voorraad</h2></div>
                <ul id="lowStockList" style="list-style:none; display:flex; flex-direction:column; gap:.5rem;">
                    <li class="hz-skeleton" style="height:20px;"></li>
                </ul>
            </div>
        </div>

        <div class="section">
            <h2><?= hz_icon('award') ?> Best verkopende items (laatste 30 dagen)</h2>
            <div class="table-container">
                <table class="hz-table">
                    <thead><tr><th>Asset</th><th>Code</th><th>Aantal uitgiftes</th></tr></thead>
                    <tbody id="topItemsBody"><tr><td colspan="3">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <h2>Recente transacties</h2>
            <div class="table-container">
                <table class="hz-table" id="transactionsTable">
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

    <script src="assets/js/components.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        let trendChart = null;

        function formatCurrency(v) {
            return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(v || 0);
        }

        async function loadDashboard() {
            try {
                const response = await fetch('api.php?action=dashboard');
                const data = await response.json();

                document.getElementById('totalAssets').textContent = data.stats.total;
                document.getElementById('availableAssets').textContent = data.stats.available;
                document.getElementById('checkedOutAssets').textContent = data.stats.checked_out;
                document.getElementById('maintenanceAssets').textContent = data.stats.maintenance;
                document.getElementById('lowStockCount').textContent = data.stats.low_stock_count;
                document.getElementById('stockValue').textContent = formatCurrency(data.stats.stock_value);

                const lowStockList = document.getElementById('lowStockList');
                if (data.low_stock.length === 0) {
                    lowStockList.innerHTML = '<li style="color:var(--hz-text-muted);">Geen lage-voorraad meldingen</li>';
                } else {
                    lowStockList.innerHTML = data.low_stock.map(i => `
                        <li style="display:flex; justify-content:space-between; align-items:center;">
                            <span><strong>${escapeHtml(i.name)}</strong> <small style="color:var(--hz-text-muted);">${escapeHtml(i.asset_code)} · ${escapeHtml(i.warehouse_name)}</small></span>
                            <span class="hz-badge hz-badge--red">${i.quantity}/${i.min_stock}</span>
                        </li>
                    `).join('');
                }

                const topBody = document.getElementById('topItemsBody');
                if (data.top_items.length === 0) {
                    topBody.innerHTML = '<tr><td colspan="3">Geen data</td></tr>';
                } else {
                    topBody.innerHTML = data.top_items.map(i => `
                        <tr><td>${escapeHtml(i.name)}</td><td>${escapeHtml(i.asset_code)}</td><td>${i.count}</td></tr>
                    `).join('');
                }

                const tbody = document.getElementById('transactionsBody');
                if (data.recent_transactions.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5">Geen transacties</td></tr>';
                } else {
                    tbody.innerHTML = data.recent_transactions.map(t => `
                        <tr>
                            <td>${escapeHtml(t.asset_name)}</td>
                            <td>${escapeHtml(t.asset_code)}</td>
                            <td>${escapeHtml(t.employee_name)}</td>
                            <td><span class="badge badge-${t.type}">${t.type === 'checkout' ? 'Uitgifte' : 'Inname'}</span></td>
                            <td>${new Date(t.checkout_date).toLocaleString('nl-NL')}</td>
                        </tr>
                    `).join('');
                }

                const labels = data.timeline.map(t => new Date(t.date).toLocaleDateString('nl-NL', { day: '2-digit', month: '2-digit' }));
                const values = data.timeline.map(t => t.count);
                if (trendChart) {
                    trendChart.data.labels = labels;
                    trendChart.data.datasets[0].data = values;
                    trendChart.update();
                } else {
                    const ctx = document.getElementById('trendChart').getContext('2d');
                    trendChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                label: 'Uitgiftes per dag',
                                data: values,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37,99,235,0.1)',
                                tension: 0.3,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                        }
                    });
                }
            } catch (err) {
                console.error('Failed to load dashboard:', err);
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        loadDashboard();
        // Vereenvoudiging t.o.v. "realtime updates via websockets": we pollen elke 15 seconden.
        // Een echte implementatie zou een websocket- of SSE-verbinding gebruiken voor pushupdates.
        setInterval(loadDashboard, 15000);

        // manifest.json verwijst hierboven al naar deze service worker, maar zonder registratie
        // deed die verder niets (geen offline-ondersteuning, geen installeerbare PWA). 'sw.js' is
        // relatief aan <base href="/inventory-manager/">, dus de scope komt vanzelf goed uit.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').catch((err) => {
                    console.warn('Service worker registratie mislukt:', err);
                });
            });
        }
    </script>
</body>
</html>
