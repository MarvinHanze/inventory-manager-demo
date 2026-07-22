<?php
require_once 'config.php';
requireLogin();
$user = getUser();
if (!isAdmin()) {
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
    <title>Inventory Manager - ERP-koppeling</title>
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
            <a href="admin.php">Beheer</a>
            <a href="analytics.php">Rapportages</a>
            <a href="erp-integratie.php" class="active">ERP-koppeling</a>
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
        <a href="orders.php" class="hz-bottomnav__item"><?= hz_icon('box') ?><span>Orders</span></a>
        <a href="admin.php" class="hz-bottomnav__item"><?= hz_icon('settings') ?><span>Beheer</span></a>
    </div>

    <main class="container">
        <h1><?= hz_icon('plug') ?> ERP-koppeling</h1>
        <p style="color:var(--hz-text-muted); margin-bottom:1rem;">
            <strong>Let op:</strong> dit is een gelabelde <strong>mock-koppeling</strong> voor demonstratiedoeleinden.
            Er wordt geen enkele echte externe API benaderd — synchroniseren genereert alleen een simulatielog.
            Alleen zichtbaar en beheerbaar voor de rol <strong>admin</strong>.
        </p>

        <div class="form-card">
            <h3>Instellingen</h3>
            <form id="erpSettingsForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="erpProvider">Provider</label>
                        <input type="text" id="erpProvider" name="provider" placeholder="bijv. Exact Online (demo)">
                    </div>
                    <div class="form-group">
                        <label for="erpApiKey">API-sleutel
                            <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">Nep-sleutel voor de demo; wordt nooit teruggetoond, alleen of er iets is ingesteld</span></span>
                        </label>
                        <input type="password" id="erpApiKey" name="api_key" placeholder="(ongewijzigd laten om te behouden)" autocomplete="off">
                    </div>
                </div>
                <div id="erpKeyStatus" style="margin-bottom:1rem; color:var(--hz-text-muted); font-size:.9rem;"></div>
                <button type="submit" class="btn btn-primary">Instellingen opslaan</button>
            </form>
        </div>

        <div class="form-card">
            <h3>Synchronisatie</h3>
            <p style="color:var(--hz-text-muted);">Simuleert het doorboeken van artikelen, voorraad en ontvangen inkooporders naar de gekoppelde (nep-)ERP-provider.</p>
            <button class="btn btn-primary" id="erpSyncBtn"><?= hz_icon('refresh-cw') ?> Nu synchroniseren (mock)</button>
            <div id="erpLastSync" style="margin-top:1rem; color:var(--hz-text-muted); font-size:.9rem;"></div>
            <pre id="erpSyncLog" style="display:none; margin-top:1rem; background:var(--hz-bg-subtle, #f8fafc); padding:1rem; border-radius:var(--hz-radius); font-size:.85rem; white-space:pre-wrap;"></pre>
        </div>
    </main>

    <script src="assets/js/components.js"></script>
    <script>
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        async function loadSettings() {
            const res = await fetch('api.php?action=erp_settings');
            if (res.status === 403) {
                hzToast('Geen toegang', 'error');
                return;
            }
            const data = await res.json();
            if (!data) return;
            document.getElementById('erpProvider').value = data.provider || '';
            document.getElementById('erpKeyStatus').textContent = data.api_key_set
                ? 'Er is momenteel een API-sleutel ingesteld.'
                : 'Er is nog geen API-sleutel ingesteld.';
            if (data.last_sync_at) {
                document.getElementById('erpLastSync').textContent = 'Laatste synchronisatie: ' + new Date(data.last_sync_at).toLocaleString('nl-NL');
            }
            if (data.last_sync_log) {
                const pre = document.getElementById('erpSyncLog');
                pre.textContent = data.last_sync_log;
                pre.style.display = 'block';
            }
        }

        document.getElementById('erpSettingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            const res = await fetch('api.php?action=erp_settings', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                hzToast('Instellingen opgeslagen', 'success');
                document.getElementById('erpApiKey').value = '';
                loadSettings();
            } else {
                hzToast(data.error || 'Opslaan mislukt', 'error');
            }
        });

        document.getElementById('erpSyncBtn').addEventListener('click', async () => {
            const btn = document.getElementById('erpSyncBtn');
            hzSetLoading(btn, true);
            const formData = new FormData();
            formData.append('csrf_token', CSRF_TOKEN);
            try {
                const res = await fetch('api.php?action=erp_sync', { method: 'POST', body: formData });
                const data = await res.json();
                const pre = document.getElementById('erpSyncLog');
                pre.textContent = data.log || '';
                pre.style.display = data.log ? 'block' : 'none';
                if (data.success) {
                    hzToast('Synchronisatie voltooid (mock)', 'success');
                    loadSettings();
                } else {
                    hzToast('Synchronisatie geweigerd — zie log', 'error');
                }
            } finally {
                hzSetLoading(btn, false);
            }
        });

        loadSettings();
    </script>
</body>
</html>
