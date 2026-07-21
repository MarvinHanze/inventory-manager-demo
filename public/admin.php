<?php
require_once 'config.php';
requireLogin();
$user = getUser();
if (!canManageStock()) {
    header('Location: ' . BASE . '/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <base href="/inventory-manager/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager - Beheer</title>
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
            <a href="orders.php">Orders</a>
            <a href="admin.php" class="active">Beheer</a>
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
        <a href="index.php" class="hz-bottomnav__item">📊<span>Dashboard</span></a>
        <a href="checkout.php" class="hz-bottomnav__item">📤<span>Uitgifte</span></a>
        <a href="checkin.php" class="hz-bottomnav__item">📥<span>Inname</span></a>
        <a href="orders.php" class="hz-bottomnav__item">📦<span>Orders</span></a>
        <a href="admin.php" class="hz-bottomnav__item hz-is-active">⚙️<span>Beheer</span></a>
    </div>

    <main class="container">
        <h1>Beheer</h1>
        <p style="color:var(--hz-text-muted); margin-bottom:1rem;">Voorraadaanpassingen zijn voorbehouden aan de rollen <strong>admin</strong> en <strong>manager</strong>.</p>

        <div class="tabs">
            <button class="tab active" data-tab="assets">Assets</button>
            <button class="tab" data-tab="employees">Medewerkers</button>
            <button class="tab" data-tab="stock">Voorraad</button>
            <button class="tab" data-tab="warehouses">Magazijnen &amp; Allocatie</button>
            <button class="tab" data-tab="batches">Batches &amp; Serienummers</button>
            <?php if (isAdmin()): ?><button class="tab" data-tab="audit">Audit log</button><?php endif; ?>
        </div>

        <!-- Assets Tab -->
        <div class="tab-content active" id="assetsTab">
            <div class="section-header">
                <h2>Assets</h2>
                <div class="hz-flex-wrap">
                    <a href="api.php?action=export_assets_csv" class="hz-btn hz-btn--secondary">⬇ Exporteer CSV</a>
                    <button class="hz-btn hz-btn--secondary" onclick="document.getElementById('csvFile').click()">⬆ Importeer CSV</button>
                    <input type="file" id="csvFile" accept=".csv" style="display:none;">
                    <button class="btn btn-primary" onclick="showAssetForm()">+ Nieuw asset</button>
                </div>
            </div>

            <div id="assetFormContainer" class="form-card" style="display: none;">
                <h3 id="assetFormTitle">Nieuw asset</h3>
                <form id="assetForm">
                    <input type="hidden" id="assetId" name="id">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="assetCode">Asset Code
                                <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">Unieke code, bijv. AST-011 of een barcode-waarde</span></span>
                            </label>
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

            <div class="hz-flex-wrap" style="margin-bottom:1rem;">
                <input type="search" id="assetSearch" placeholder="Zoeken op naam, code of categorie..." style="flex:1; min-width:200px; padding:.5rem .75rem; border:1px solid var(--hz-border); border-radius:var(--hz-radius);">
                <select id="assetPerPage" style="padding:.5rem .75rem; border:1px solid var(--hz-border); border-radius:var(--hz-radius);">
                    <option value="10">10 per pagina</option>
                    <option value="25">25 per pagina</option>
                    <option value="50">50 per pagina</option>
                </select>
                <button class="hz-btn hz-btn--danger" id="bulkMaintenanceBtn" style="display:none;">🔧 Bulk: markeer als in onderhoud</button>
            </div>

            <div class="table-container">
                <table class="hz-table">
                    <thead>
                        <tr>
                            <th style="width:2rem;"><input type="checkbox" id="selectAllAssets"></th>
                            <th data-key="asset_code" style="cursor:pointer;">Code</th>
                            <th data-key="name" style="cursor:pointer;">Naam</th>
                            <th data-key="category" style="cursor:pointer;">Categorie</th>
                            <th data-key="status" style="cursor:pointer;">Status</th>
                            <th data-key="total_quantity" style="cursor:pointer;">Voorraad</th>
                            <th>Toegewezen</th>
                            <th>Acties</th>
                        </tr>
                    </thead>
                    <tbody id="assetsBody">
                        <tr><td colspan="8">Laden...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="hz-pagination" id="assetsPagination" style="margin-top:1rem; justify-content:center;"></div>
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
                <table class="hz-table">
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

        <!-- Stock Tab -->
        <div class="tab-content" id="stockTab">
            <div class="section-header">
                <h2>Voorraad per magazijn</h2>
            </div>
            <div class="form-card">
                <h3>Voorraad aanpassen</h3>
                <form id="stockForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="stockAsset">Asset</label>
                            <select id="stockAsset" name="asset_id" required></select>
                        </div>
                        <div class="form-group">
                            <label for="stockWarehouse">Magazijn</label>
                            <select id="stockWarehouse" name="warehouse_id" required></select>
                        </div>
                        <div class="form-group">
                            <label for="stockQuantity">Aantal
                                <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">De nieuwe (absolute) voorraadhoeveelheid in dit magazijn</span></span>
                            </label>
                            <input type="number" id="stockQuantity" name="quantity" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="stockMin">Minimumvoorraad
                                <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">Onder deze grens verschijnt een lage-voorraad melding op het dashboard</span></span>
                            </label>
                            <input type="number" id="stockMin" name="min_stock" min="0" value="5">
                        </div>
                        <div class="form-group">
                            <label for="stockPrice">Stukprijs (€)</label>
                            <input type="number" id="stockPrice" name="unit_price" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Voorraad opslaan</button>
                </form>
            </div>
            <div class="table-container">
                <table class="hz-table">
                    <thead><tr><th>Asset</th><th>Magazijn</th><th>Aantal</th><th>Minimum</th><th>Stukprijs</th><th>Waarde</th></tr></thead>
                    <tbody id="stockBody"><tr><td colspan="6">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Warehouses Tab -->
        <div class="tab-content" id="warehousesTab">
            <div class="section-header">
                <h2>Magazijnen</h2>
            </div>
            <div class="form-card">
                <h3>Nieuw magazijn</h3>
                <form id="warehouseForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="whCode">Code</label>
                            <input type="text" id="whCode" name="code" required placeholder="bijv. WH-UTR">
                        </div>
                        <div class="form-group">
                            <label for="whName">Naam</label>
                            <input type="text" id="whName" name="name" required placeholder="bijv. Magazijn Utrecht">
                        </div>
                        <div class="form-group">
                            <label for="whLocation">Locatie</label>
                            <input type="text" id="whLocation" name="location" placeholder="bijv. Utrecht">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Magazijn toevoegen</button>
                </form>
            </div>
            <div class="table-container">
                <table class="hz-table">
                    <thead><tr><th>Code</th><th>Naam</th><th>Locatie</th><th>Totale voorraad</th></tr></thead>
                    <tbody id="warehousesBody"><tr><td colspan="4">Laden...</td></tr></tbody>
                </table>
            </div>

            <div class="section-header" style="margin-top:2rem;">
                <h2>Voorraadallocatie voor productie</h2>
            </div>
            <div class="form-card">
                <h3>Nieuwe allocatie</h3>
                <form id="allocationForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="allocAsset">Asset</label>
                            <select id="allocAsset" name="asset_id" required></select>
                        </div>
                        <div class="form-group">
                            <label for="allocWarehouse">Magazijn</label>
                            <select id="allocWarehouse" name="warehouse_id" required></select>
                        </div>
                        <div class="form-group">
                            <label for="allocQuantity">Aantal</label>
                            <input type="number" id="allocQuantity" name="quantity" min="1" required>
                        </div>
                        <div class="form-group">
                            <label for="allocPurpose">Doel / productieorder
                                <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">Bijv. "Productieorder #PR-1234" of "Assemblagelijn A"</span></span>
                            </label>
                            <input type="text" id="allocPurpose" name="purpose" placeholder="bijv. Productieorder #PR-1234">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Alloceren</button>
                </form>
            </div>
            <div class="table-container">
                <table class="hz-table">
                    <thead><tr><th>Asset</th><th>Magazijn</th><th>Aantal</th><th>Doel</th><th>Gealloceerd op</th><th>Actie</th></tr></thead>
                    <tbody id="allocationsBody"><tr><td colspan="6">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- Batches Tab -->
        <div class="tab-content" id="batchesTab">
            <div class="section-header">
                <h2>Batch- &amp; serienummerregistratie</h2>
            </div>
            <div class="form-card">
                <h3>Nieuwe batch/serie registreren</h3>
                <form id="batchForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="batchAsset">Asset</label>
                            <select id="batchAsset" name="asset_id" required></select>
                        </div>
                        <div class="form-group">
                            <label for="batchNumber">Batchnummer</label>
                            <input type="text" id="batchNumber" name="batch_number" placeholder="bijv. BATCH-0007">
                        </div>
                        <div class="form-group">
                            <label for="serialNumber">Serienummer</label>
                            <input type="text" id="serialNumber" name="serial_number" placeholder="bijv. SN-A1B2C3">
                        </div>
                        <div class="form-group">
                            <label for="batchQuantity">Aantal</label>
                            <input type="number" id="batchQuantity" name="quantity" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label for="expiryDate">Vervaldatum (optioneel)</label>
                            <input type="date" id="expiryDate" name="expiry_date">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Registreren</button>
                </form>
            </div>
            <div class="table-container">
                <table class="hz-table">
                    <thead><tr><th>Asset</th><th>Batch</th><th>Serienummer</th><th>Aantal</th><th>Ontvangen</th><th>Vervaldatum</th></tr></thead>
                    <tbody id="batchesBody"><tr><td colspan="6">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Audit log Tab -->
        <div class="tab-content" id="auditTab">
            <div class="section-header">
                <h2>Audit log</h2>
            </div>
            <p style="color:var(--hz-text-muted); margin-bottom:1rem;">Overzicht van wie, wat en wanneer heeft gewijzigd (laatste 100 acties).</p>
            <div class="table-container">
                <table class="hz-table">
                    <thead><tr><th>Datum/tijd</th><th>Gebruiker</th><th>Actie</th><th>Entiteit</th><th>Details</th></tr></thead>
                    <tbody id="auditBody"><tr><td colspan="5">Laden...</td></tr></tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script src="assets/js/components.js"></script>
    <script>
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        let assetPage = 1, assetSort = 'name', assetDir = 'ASC';
        let assetsCache = [];
        let allAssetsForSelects = [];
        let allWarehouses = [];

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab + 'Tab').classList.add('active');
                if (tab.dataset.tab === 'stock') loadStock();
                if (tab.dataset.tab === 'warehouses') { loadWarehouses(); loadAllocations(); }
                if (tab.dataset.tab === 'batches') loadBatches();
                if (tab.dataset.tab === 'audit') loadAudit();
            });
        });

        // ---------- Assets ----------
        async function loadAssets() {
            const q = document.getElementById('assetSearch').value;
            const perPage = document.getElementById('assetPerPage').value;
            const params = new URLSearchParams({ action: 'assets', page: assetPage, per_page: perPage, sort: assetSort, dir: assetDir });
            if (q) params.set('q', q);

            const response = await fetch('api.php?' + params.toString());
            const data = await response.json();
            assetsCache = data.data;
            const tbody = document.getElementById('assetsBody');

            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8">Geen assets gevonden</td></tr>';
            } else {
                tbody.innerHTML = data.data.map(a => `
                    <tr data-row>
                        <td><input type="checkbox" class="asset-select" value="${a.id}"></td>
                        <td data-col="asset_code">${escapeHtml(a.asset_code)}</td>
                        <td data-col="name">${escapeHtml(a.name)}</td>
                        <td data-col="category">${escapeHtml(a.category || '-')}</td>
                        <td data-col="status"><span class="badge badge-${a.status}">${a.status}</span></td>
                        <td data-col="total_quantity">
                            ${a.low_stock == 1 ? '<span class="hz-badge hz-badge--red" title="Lage voorraad">' + a.total_quantity + '</span>' : a.total_quantity}
                        </td>
                        <td>${escapeHtml(a.assigned_to_name || '-')}</td>
                        <td>
                            <button class="btn btn-sm" onclick="editAsset(${a.id})">Bewerken</button>
                            <button class="btn btn-sm" style="color:var(--danger, #dc2626);" onclick="retireAsset(${a.id})">Retire</button>
                        </td>
                    </tr>
                `).join('');
            }

            const pages = data.pages || 1;
            const pag = document.getElementById('assetsPagination');
            pag.innerHTML = `
                <button ${assetPage <= 1 ? 'disabled' : ''} id="prevPage">← Vorige</button>
                <span style="padding:0 .5rem; font-size:.85rem; color:var(--hz-text-muted);">Pagina ${data.page} van ${pages} (${data.total} assets)</span>
                <button ${assetPage >= pages ? 'disabled' : ''} id="nextPage">Volgende →</button>
            `;
            document.getElementById('prevPage')?.addEventListener('click', () => { assetPage--; loadAssets(); });
            document.getElementById('nextPage')?.addEventListener('click', () => { assetPage++; loadAssets(); });

            attachConfirms(tbody);
            updateBulkBar();
        }

        document.querySelectorAll('#assetsTab th[data-key]').forEach(th => {
            th.addEventListener('click', () => {
                const key = th.dataset.key;
                if (assetSort === key) {
                    assetDir = assetDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    assetSort = key;
                    assetDir = 'ASC';
                }
                document.querySelectorAll('#assetsTab th[data-key]').forEach(h => h.classList.remove('hz-sort-asc', 'hz-sort-desc'));
                th.classList.add(assetDir === 'ASC' ? 'hz-sort-asc' : 'hz-sort-desc');
                loadAssets();
            });
        });

        let searchTimer;
        document.getElementById('assetSearch').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { assetPage = 1; loadAssets(); }, 300);
        });
        document.getElementById('assetPerPage').addEventListener('change', () => { assetPage = 1; loadAssets(); });

        document.getElementById('selectAllAssets').addEventListener('change', (e) => {
            document.querySelectorAll('.asset-select').forEach(cb => cb.checked = e.target.checked);
            updateBulkBar();
        });
        document.getElementById('assetsBody').addEventListener('change', (e) => {
            if (e.target.classList.contains('asset-select')) updateBulkBar();
        });
        function updateBulkBar() {
            const selected = document.querySelectorAll('.asset-select:checked').length;
            const btn = document.getElementById('bulkMaintenanceBtn');
            btn.style.display = selected > 0 ? 'inline-flex' : 'none';
            btn.textContent = `🔧 Bulk: markeer ${selected} asset(s) als in onderhoud`;
        }
        document.getElementById('bulkMaintenanceBtn').addEventListener('click', async () => {
            const ids = Array.from(document.querySelectorAll('.asset-select:checked')).map(cb => cb.value);
            if (ids.length === 0) return;
            // Rechtstreekse confirm() i.p.v. vertrouwen op [data-hz-confirm]: deze knop heeft een eigen
            // addEventListener die vóór components.js's DOMContentLoaded-listener registreert (dit inline
            // <script> draait synchroon tijdens het parsen), dus de generieke confirm-listener zou hier
            // altijd te laat vuren. Zie ook retireAsset() hieronder voor hetzelfde patroon.
            if (!confirm(`Weet je zeker dat je ${ids.length} asset(s) op onderhoud wilt zetten?`)) return;
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            ids.forEach(id => formData.append('ids[]', id));
            const res = await fetch('api.php?action=bulk_maintenance', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast(`${data.count} asset(s) op onderhoud gezet`, 'success');
                loadAssets();
            } else {
                hzToast(data.error || 'Actie mislukt', 'error');
            }
        });

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

        function editAsset(id) {
            const asset = assetsCache.find(a => a.id === id);
            if (asset) showAssetForm(asset);
        }

        async function retireAsset(id) {
            // Rechtstreekse confirm() i.p.v. data-hz-confirm: deze knop wordt na paginaload dynamisch
            // gerenderd, en components.js scant [data-hz-confirm] alleen bij DOMContentLoaded.
            if (!confirm('Weet je zeker dat je dit asset buiten gebruik wilt stellen?')) return;
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('id', id);
            const res = await fetch('api.php?action=retire_asset', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Asset buiten gebruik gesteld', 'success');
                loadAssets();
            } else {
                hzToast(data.error || 'Actie mislukt', 'error');
            }
        }

        document.getElementById('assetForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch('api.php?action=assets', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Asset opgeslagen', 'success');
                hideAssetForm();
                loadAssets();
            } else {
                hzToast(data.error || 'Opslaan mislukt', 'error');
            }
        });

        // CSV import/export
        document.getElementById('csvFile').addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            if (!confirm(`Weet je zeker dat je "${file.name}" wilt importeren? Bestaande assets met dezelfde code worden bijgewerkt.`)) {
                e.target.value = '';
                return;
            }
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('csv_file', file);
            const res = await fetch('api.php?action=import_assets_csv', { method: 'POST', body: formData });
            const data = await res.json();
            e.target.value = '';
            if (data.success) {
                hzToast(data.message, 'success');
                loadAssets();
            } else {
                hzToast(data.error || 'Import mislukt', 'error');
            }
        });

        // ---------- Employees ----------
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
            document.getElementById('employeeForm').reset();
            document.getElementById('empId').value = '';
        }

        function hideEmployeeForm() {
            document.getElementById('employeeFormContainer').style.display = 'none';
        }

        document.getElementById('employeeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);

            const res = await fetch('api.php?action=employees', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Medewerker opgeslagen', 'success');
                hideEmployeeForm();
                loadEmployees();
            } else {
                hzToast(data.error || 'Opslaan mislukt', 'error');
            }
        });

        // ---------- Shared: dropdowns for asset/warehouse selects ----------
        async function loadSelectData() {
            const [assetsRes, whRes] = await Promise.all([
                fetch('api.php?action=assets'),
                fetch('api.php?action=warehouses')
            ]);
            allAssetsForSelects = await assetsRes.json();
            allWarehouses = await whRes.json();

            const assetSelects = ['stockAsset', 'allocAsset', 'batchAsset'];
            assetSelects.forEach(id => {
                const sel = document.getElementById(id);
                sel.innerHTML = allAssetsForSelects.map(a => `<option value="${a.id}">${escapeHtml(a.asset_code)} - ${escapeHtml(a.name)}</option>`).join('');
            });
            const whSelects = ['stockWarehouse', 'allocWarehouse'];
            whSelects.forEach(id => {
                const sel = document.getElementById(id);
                sel.innerHTML = allWarehouses.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
            });
        }

        // ---------- Stock ----------
        async function loadStock() {
            const res = await fetch('api.php?action=stock');
            const rows = await res.json();
            const tbody = document.getElementById('stockBody');
            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">Geen voorraadregels</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(r => `
                <tr>
                    <td>${escapeHtml(r.asset_name)} <small style="color:var(--hz-text-muted);">${escapeHtml(r.asset_code)}</small></td>
                    <td>${escapeHtml(r.warehouse_name)}</td>
                    <td>${r.quantity <= r.min_stock ? '<span class="hz-badge hz-badge--red">' + r.quantity + '</span>' : r.quantity}</td>
                    <td>${r.min_stock}</td>
                    <td>${formatCurrency(r.unit_price)}</td>
                    <td>${formatCurrency(r.quantity * r.unit_price)}</td>
                </tr>
            `).join('');
        }

        document.getElementById('stockForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('api.php?action=stock', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Voorraad bijgewerkt', 'success');
                loadStock();
                loadAssets();
            } else {
                hzToast(data.error || 'Opslaan mislukt', 'error');
            }
        });

        // ---------- Warehouses ----------
        async function loadWarehouses() {
            const res = await fetch('api.php?action=warehouses');
            const rows = await res.json();
            allWarehouses = rows;
            const tbody = document.getElementById('warehousesBody');
            tbody.innerHTML = rows.length === 0 ? '<tr><td colspan="4">Geen magazijnen</td></tr>' : rows.map(w => `
                <tr>
                    <td>${escapeHtml(w.code)}</td>
                    <td>${escapeHtml(w.name)}</td>
                    <td>${escapeHtml(w.location || '-')}</td>
                    <td>${w.total_quantity}</td>
                </tr>
            `).join('');
            const sel = document.getElementById('allocWarehouse');
            if (sel) sel.innerHTML = rows.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
            const sel2 = document.getElementById('stockWarehouse');
            if (sel2) sel2.innerHTML = rows.map(w => `<option value="${w.id}">${escapeHtml(w.name)}</option>`).join('');
        }

        document.getElementById('warehouseForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('api.php?action=warehouses', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Magazijn toegevoegd', 'success');
                e.target.reset();
                loadWarehouses();
            } else {
                hzToast(data.error || 'Opslaan mislukt', 'error');
            }
        });

        // ---------- Allocations ----------
        async function loadAllocations() {
            const res = await fetch('api.php?action=allocations');
            const rows = await res.json();
            const tbody = document.getElementById('allocationsBody');
            tbody.innerHTML = rows.length === 0 ? '<tr><td colspan="6">Geen actieve allocaties</td></tr>' : rows.map(a => `
                <tr>
                    <td>${escapeHtml(a.asset_name)} <small style="color:var(--hz-text-muted);">${escapeHtml(a.asset_code)}</small></td>
                    <td>${escapeHtml(a.warehouse_name)}</td>
                    <td>${a.quantity}</td>
                    <td>${escapeHtml(a.purpose || '-')}</td>
                    <td>${new Date(a.allocated_at).toLocaleString('nl-NL')}</td>
                    <td><button class="btn btn-sm" onclick="releaseAllocation(${a.id})">Vrijgeven</button></td>
                </tr>
            `).join('');
        }

        async function releaseAllocation(id) {
            if (!confirm('Allocatie vrijgeven?')) return;
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('id', id);
            const res = await fetch('api.php?action=release_allocation', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Allocatie vrijgegeven', 'success');
                loadAllocations();
            } else {
                hzToast(data.error || 'Actie mislukt', 'error');
            }
        }

        document.getElementById('allocationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('api.php?action=allocations', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Voorraad gealloceerd', 'success');
                e.target.reset();
                loadAllocations();
            } else {
                hzToast(data.error || 'Opslaan mislukt', 'error');
            }
        });

        // ---------- Batches ----------
        async function loadBatches() {
            const res = await fetch('api.php?action=batches');
            const rows = await res.json();
            const tbody = document.getElementById('batchesBody');
            tbody.innerHTML = rows.length === 0 ? '<tr><td colspan="6">Geen batches/serienummers</td></tr>' : rows.map(b => `
                <tr>
                    <td>${escapeHtml(b.asset_name)} <small style="color:var(--hz-text-muted);">${escapeHtml(b.asset_code)}</small></td>
                    <td>${escapeHtml(b.batch_number || '-')}</td>
                    <td>${escapeHtml(b.serial_number || '-')}</td>
                    <td>${b.quantity}</td>
                    <td>${new Date(b.received_at).toLocaleDateString('nl-NL')}</td>
                    <td>${b.expiry_date ? new Date(b.expiry_date).toLocaleDateString('nl-NL') : '-'}</td>
                </tr>
            `).join('');
        }

        document.getElementById('batchForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('api.php?action=batches', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Batch/serie geregistreerd', 'success');
                e.target.reset();
                loadBatches();
            } else {
                hzToast(data.error || 'Opslaan mislukt', 'error');
            }
        });

        // ---------- Audit log ----------
        async function loadAudit() {
            const tbody = document.getElementById('auditBody');
            if (!tbody) return;
            const res = await fetch('api.php?action=audit_log');
            if (res.status === 403) {
                tbody.innerHTML = '<tr><td colspan="5">Geen toegang</td></tr>';
                return;
            }
            const rows = await res.json();
            tbody.innerHTML = rows.length === 0 ? '<tr><td colspan="5">Nog geen audit-events</td></tr>' : rows.map(r => `
                <tr>
                    <td>${new Date(r.created_at).toLocaleString('nl-NL')}</td>
                    <td>${escapeHtml(r.user_name || 'Systeem')}</td>
                    <td>${escapeHtml(r.action)}</td>
                    <td>${escapeHtml(r.entity_type || '-')}${r.entity_id ? ' #' + r.entity_id : ''}</td>
                    <td>${escapeHtml(r.details || '-')}</td>
                </tr>
            `).join('');
        }

        function formatCurrency(v) {
            return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(v || 0);
        }

        // components.js wires up [data-hz-confirm] once on DOMContentLoaded; buttons rendered later
        // via innerHTML (retire/release) need to be (re)attached explicitly after each render.
        function attachConfirms(root) {
            root.querySelectorAll('[data-hz-confirm]').forEach(el => {
                el.addEventListener('click', function (e) {
                    if (!window.confirm(el.getAttribute('data-hz-confirm'))) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                    }
                });
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        loadAssets();
        loadEmployees();
        loadSelectData();
    </script>
</body>
</html>
