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
    <title>Inventory Manager - Orders</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <meta name="theme-color" content="#2563eb">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">Inventory Manager</div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="checkout.php">Uitgifte</a>
            <a href="checkin.php">Inname</a>
            <a href="orders.php" class="active">Orders</a>
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
        <a href="index.php" class="hz-bottomnav__item"><?= hz_icon('bar-chart') ?><span>Dashboard</span></a>
        <a href="checkout.php" class="hz-bottomnav__item"><?= hz_icon('upload') ?><span>Uitgifte</span></a>
        <a href="checkin.php" class="hz-bottomnav__item"><?= hz_icon('download') ?><span>Inname</span></a>
        <a href="orders.php" class="hz-bottomnav__item hz-is-active"><?= hz_icon('box') ?><span>Orders</span></a>
        <a href="admin.php" class="hz-bottomnav__item"><?= hz_icon('settings') ?><span>Beheer</span></a>
    </div>

    <main class="container">
        <h1>Orders</h1>
        <p style="color:var(--hz-text-muted); margin-bottom:1rem;">Inkooporders (van leverancier) en verkooporders (naar klant). Nieuwe orders aanmaken en orders afhandelen is voorbehouden aan de rollen <strong>admin</strong> en <strong>manager</strong>.</p>

        <div class="tabs">
            <button class="tab active" data-tab="purchase" id="receive">Inkooporders</button>
            <button class="tab" data-tab="sales">Verkooporders</button>
        </div>

        <!-- Purchase Orders Tab -->
        <div class="tab-content active" id="purchaseTab">
            <?php if (canManageStock()): ?>
            <div class="form-card">
                <h3>Nieuwe inkooporder</h3>
                <form id="poForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="poSupplier">Leverancier</label>
                            <input type="text" id="poSupplier" name="supplier_name" required placeholder="bijv. Tech Distributie B.V.">
                        </div>
                        <div class="form-group">
                            <label for="poRecurring">Herhalend</label>
                            <select id="poRecurring" name="recurring">
                                <option value="none">Eenmalig</option>
                                <option value="weekly">Wekelijks</option>
                                <option value="monthly">Maandelijks</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="poExpected">Verwachte leverdatum</label>
                            <input type="date" id="poExpected" name="expected_date">
                        </div>
                    </div>
                    <div id="poItems"></div>
                    <button type="button" class="hz-btn hz-btn--secondary" id="poAddItem" style="margin-bottom:1rem;"><?= hz_icon('plus') ?> Artikel toevoegen</button>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Inkooporder aanmaken</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <table class="hz-table">
                    <thead>
                        <tr>
                            <th>Nummer</th><th>Leverancier</th><th>Status</th><th>Herhalend</th>
                            <th>Verwacht</th><th>Artikelen</th><?php if (canManageStock()): ?><th>Actie</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="poBody"><tr><td colspan="7">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Sales Orders Tab -->
        <div class="tab-content" id="salesTab">
            <?php if (canManageStock()): ?>
            <div class="form-card">
                <h3>Nieuwe verkooporder</h3>
                <form id="soForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="soCustomer">Klant</label>
                            <input type="text" id="soCustomer" name="customer_name" required placeholder="bijv. Contoso Nederland B.V.">
                        </div>
                    </div>
                    <div id="soItems"></div>
                    <button type="button" class="hz-btn hz-btn--secondary" id="soAddItem" style="margin-bottom:1rem;"><?= hz_icon('plus') ?> Artikel toevoegen</button>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Verkooporder aanmaken</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <table class="hz-table">
                    <thead>
                        <tr>
                            <th>Nummer</th><th>Klant</th><th>Status</th><th>Aangemaakt</th>
                            <th>Artikelen</th><?php if (canManageStock()): ?><th>Actie</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="soBody"><tr><td colspan="6">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="assets/js/components.js"></script>
    <script>
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        const CAN_MANAGE_STOCK = <?= canManageStock() ? 'true' : 'false' ?>;
        let allAssets = [];
        let allWarehouses = [];

        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab + 'Tab').classList.add('active');
            });
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        function formatDate(v) {
            return v ? new Date(v).toLocaleDateString('nl-NL') : '-';
        }

        async function loadRefData() {
            const [assetsRes, whRes] = await Promise.all([
                fetch('api.php?action=assets'),
                fetch('api.php?action=warehouses')
            ]);
            allAssets = await assetsRes.json();
            allWarehouses = await whRes.json();
        }

        function assetOptions() {
            return '<option value="">Kies artikel...</option>' + allAssets.map(a => `<option value="${a.id}">${escapeHtml(a.asset_code)} - ${escapeHtml(a.name)}</option>`).join('');
        }

        function addItemRow(container, namePrefix) {
            const row = document.createElement('div');
            row.className = 'form-row hz-order-item-row';
            row.style.alignItems = 'flex-end';
            row.innerHTML = `
                <div class="form-group" style="flex:2;">
                    <label>Artikel</label>
                    <select name="asset_id[]" required>${assetOptions()}</select>
                </div>
                <div class="form-group">
                    <label>Aantal</label>
                    <input type="number" name="quantity[]" min="1" value="1" required>
                </div>
                <div class="form-group" style="flex:0;">
                    <button type="button" class="btn btn-sm hz-order-item-remove">&times;</button>
                </div>
            `;
            row.querySelector('.hz-order-item-remove').addEventListener('click', () => row.remove());
            container.appendChild(row);
        }

        if (CAN_MANAGE_STOCK) {
            document.getElementById('poAddItem').addEventListener('click', () => addItemRow(document.getElementById('poItems')));
            document.getElementById('soAddItem').addEventListener('click', () => addItemRow(document.getElementById('soItems')));
        }

        // ---------- Purchase Orders ----------
        async function loadPurchaseOrders() {
            const res = await fetch('api.php?action=purchase_orders');
            const rows = await res.json();
            const tbody = document.getElementById('poBody');
            const cols = CAN_MANAGE_STOCK ? 7 : 6;
            if (rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${cols}">Geen inkooporders</td></tr>`;
                return;
            }
            const recurringLabels = { none: 'Eenmalig', weekly: 'Wekelijks', monthly: 'Maandelijks' };
            const statusLabels = { draft: 'Concept', pending: 'In afwachting', ordered: 'Besteld', received: 'Ontvangen', cancelled: 'Geannuleerd' };
            tbody.innerHTML = rows.map(po => {
                const items = (po.items || []).map(i => `${escapeHtml(i.asset_code)} (${i.quantity}x)`).join(', ') || '-';
                let actionCell = '';
                if (CAN_MANAGE_STOCK) {
                    actionCell = po.status === 'received'
                        ? '<span class="hz-badge hz-badge--gray">Afgehandeld</span>'
                        : `<div style="display:flex; gap:.35rem; align-items:center;">
                            <select class="po-warehouse-select" data-po="${po.id}" style="padding:.3rem .5rem; border:1px solid var(--hz-border); border-radius:var(--hz-radius);">
                                ${allWarehouses.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('')}
                            </select>
                            <button class="btn btn-sm btn-primary" onclick="receivePO(${po.id})">Ontvangen</button>
                        </div>`;
                }
                return `
                    <tr>
                        <td>${escapeHtml(po.po_number)}</td>
                        <td>${escapeHtml(po.supplier_name)}</td>
                        <td><span class="hz-badge ${po.status === 'received' ? 'hz-badge--green' : 'hz-badge--gray'}">${statusLabels[po.status] || escapeHtml(po.status)}</span></td>
                        <td>${recurringLabels[po.recurring] || '-'}</td>
                        <td>${formatDate(po.expected_date)}</td>
                        <td style="font-size:.85rem;">${items}</td>
                        ${CAN_MANAGE_STOCK ? '<td>' + actionCell + '</td>' : ''}
                    </tr>
                `;
            }).join('');
        }

        async function receivePO(id) {
            const select = document.querySelector(`.po-warehouse-select[data-po="${id}"]`);
            const warehouseId = select ? select.value : '';
            if (!warehouseId) { hzToast('Kies eerst een magazijn', 'error'); return; }
            if (!confirm('Deze levering ontvangen en de voorraad bijboeken?')) return;
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('id', id);
            formData.append('warehouse_id', warehouseId);
            const res = await fetch('api.php?action=receive_purchase_order', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast(data.message || 'Levering ontvangen', 'success');
                loadPurchaseOrders();
            } else {
                hzToast(data.error || 'Actie mislukt', 'error');
            }
        }

        if (CAN_MANAGE_STOCK) {
            document.getElementById('poForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const rows = document.querySelectorAll('#poItems .hz-order-item-row');
                if (rows.length === 0) { hzToast('Voeg minstens één artikel toe', 'error'); return; }
                const formData = new FormData(e.target);
                formData.append('csrf_token', CSRF_TOKEN);
                const res = await fetch('api.php?action=purchase_orders', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    hzToast(`Inkooporder ${data.po_number} aangemaakt`, 'success');
                    e.target.reset();
                    document.getElementById('poItems').innerHTML = '';
                    loadPurchaseOrders();
                } else {
                    hzToast(data.error || 'Aanmaken mislukt', 'error');
                }
            });
        }

        // ---------- Sales Orders ----------
        async function loadSalesOrders() {
            const res = await fetch('api.php?action=sales_orders');
            const rows = await res.json();
            const tbody = document.getElementById('soBody');
            const cols = CAN_MANAGE_STOCK ? 6 : 5;
            if (rows.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${cols}">Geen verkooporders</td></tr>`;
                return;
            }
            const statusLabels = { open: 'Open', fulfilled: 'Uitgeleverd', cancelled: 'Geannuleerd' };
            tbody.innerHTML = rows.map(so => {
                const items = (so.items || []).map(i => `${escapeHtml(i.asset_code)} (${i.quantity}x)`).join(', ') || '-';
                let actionCell = '';
                if (CAN_MANAGE_STOCK) {
                    actionCell = so.status === 'open'
                        ? `<button class="btn btn-sm btn-primary" onclick="fulfillSO(${so.id})">Uitleveren</button>`
                        : '<span class="hz-badge hz-badge--gray">Afgehandeld</span>';
                }
                return `
                    <tr>
                        <td>${escapeHtml(so.so_number)}</td>
                        <td>${escapeHtml(so.customer_name)}</td>
                        <td><span class="hz-badge ${so.status === 'fulfilled' ? 'hz-badge--green' : 'hz-badge--gray'}">${statusLabels[so.status] || escapeHtml(so.status)}</span></td>
                        <td>${new Date(so.created_at).toLocaleDateString('nl-NL')}</td>
                        <td style="font-size:.85rem;">${items}</td>
                        ${CAN_MANAGE_STOCK ? '<td>' + actionCell + '</td>' : ''}
                    </tr>
                `;
            }).join('');
        }

        async function fulfillSO(id) {
            if (!confirm('Deze verkooporder uitleveren en de voorraad afboeken?')) return;
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('id', id);
            const res = await fetch('api.php?action=fulfill_sales_order', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast(data.message || 'Verkooporder uitgeleverd', 'success');
                loadSalesOrders();
            } else {
                hzToast(data.error || 'Actie mislukt', 'error');
            }
        }

        if (CAN_MANAGE_STOCK) {
            document.getElementById('soForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const rows = document.querySelectorAll('#soItems .hz-order-item-row');
                if (rows.length === 0) { hzToast('Voeg minstens één artikel toe', 'error'); return; }
                const formData = new FormData(e.target);
                formData.append('csrf_token', CSRF_TOKEN);
                const res = await fetch('api.php?action=sales_orders', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    hzToast(`Verkooporder ${data.so_number} aangemaakt`, 'success');
                    e.target.reset();
                    document.getElementById('soItems').innerHTML = '';
                    loadSalesOrders();
                } else {
                    hzToast(data.error || 'Aanmaken mislukt', 'error');
                }
            });
        }

        (async function init() {
            await loadRefData();
            if (CAN_MANAGE_STOCK) {
                addItemRow(document.getElementById('poItems'));
                addItemRow(document.getElementById('soItems'));
            }
            loadPurchaseOrders();
            loadSalesOrders();
        })();
    </script>
</body>
</html>
