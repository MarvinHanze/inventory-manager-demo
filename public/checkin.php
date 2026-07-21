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
    <link rel="stylesheet" href="assets/css/components.css">
    <meta name="theme-color" content="#2563eb">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">Inventory Manager</div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="checkout.php">Uitgifte</a>
            <a href="checkin.php" class="active">Inname</a>
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
        <a href="index.php" class="hz-bottomnav__item">📊<span>Dashboard</span></a>
        <a href="checkout.php" class="hz-bottomnav__item">📤<span>Uitgifte</span></a>
        <a href="checkin.php" class="hz-bottomnav__item hz-is-active">📥<span>Inname</span></a>
        <a href="orders.php" class="hz-bottomnav__item">📦<span>Orders</span></a>
        <a href="admin.php" class="hz-bottomnav__item">⚙️<span>Beheer</span></a>
    </div>

    <main class="container">
        <h1>Asset Inname</h1>

        <div class="form-card">
            <form id="checkinForm">
                <div class="form-group">
                    <label for="assetCode">Asset Code (scan of voer in)
                        <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">Voer de assetcode handmatig in (bijv. INV-001) of gebruik de scanknop hieronder</span></span>
                    </label>
                    <div style="display:flex; gap:.5rem;">
                        <input type="text" id="assetCode" name="scan_input" required
                               placeholder="bijv. INV-001" autocomplete="off" data-lpignore="true" data-1p-ignore="true" style="flex:1;">
                        <button type="button" class="hz-btn hz-btn--secondary" id="openScanner">📷 Scan barcode/QR</button>
                    </div>
                </div>

                <div id="result" class="result-message" style="display: none;"></div>

                <button type="submit" class="btn btn-primary btn-block" id="submitBtn">Asset innemen</button>
            </form>
        </div>

        <div class="section">
            <h2>Actief uitgegeven</h2>
            <div class="table-container">
                <table class="hz-table">
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

    <!-- QR/barcode scanner modal -->
    <div class="hz-modal__backdrop" id="scannerModal">
        <div class="hz-modal">
            <div class="hz-modal__header">
                <h3>Scan barcode of QR-code</h3>
                <button type="button" class="hz-icon-btn" data-hz-modal-close aria-label="Sluiten">✕</button>
            </div>
            <div id="qr-reader" style="width:100%;"></div>
            <p style="color:var(--hz-text-muted); font-size:.85rem; margin-top:.5rem;">Geef de browser toegang tot de camera. Werkt het scannen niet (geen camera/HTTPS)? Voer de code dan handmatig in.</p>
            <div class="hz-modal__footer">
                <button type="button" class="hz-btn hz-btn--secondary" data-hz-modal-close>Annuleren</button>
            </div>
        </div>
    </div>

    <script src="assets/js/components.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        let html5QrCode = null;

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
            const submitBtn = document.getElementById('submitBtn');
            resultEl.style.display = 'none';

            const formData = new FormData(e.target);
            formData.append('csrf_token', CSRF_TOKEN);
            hzSetLoading(submitBtn, true);

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
                    hzToast('Asset ingenomen', 'success');
                    e.target.reset();
                    loadActive();
                } else {
                    resultEl.className = 'result-message result-error';
                    resultEl.textContent = data.error;
                    resultEl.style.display = 'block';
                    hzToast(data.error || 'Inname mislukt', 'error');
                }
            } catch (err) {
                resultEl.className = 'result-message result-error';
                resultEl.textContent = 'Netwerkfout';
                resultEl.style.display = 'block';
            } finally {
                hzSetLoading(submitBtn, false);
            }
        });

        // QR/barcode scanner (html5-qrcode via CDN, camera-toegang via de browser — geen native app nodig)
        function stopScanner() {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().catch(() => {});
            }
        }

        document.getElementById('openScanner').addEventListener('click', () => {
            document.getElementById('scannerModal').classList.add('hz-is-open');
            if (typeof Html5Qrcode === 'undefined') {
                hzToast('Scanner kon niet laden (geen internetverbinding naar CDN?)', 'error');
                return;
            }
            html5QrCode = new Html5Qrcode('qr-reader');
            html5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 220 },
                (decodedText) => {
                    document.getElementById('assetCode').value = decodedText;
                    stopScanner();
                    document.getElementById('scannerModal').classList.remove('hz-is-open');
                    hzToast('Code gescand: ' + decodedText, 'info');
                },
                () => { /* scanfout per frame negeren */ }
            ).catch(() => {
                hzToast('Camera niet beschikbaar. Voer de code handmatig in.', 'error');
            });
        });

        document.querySelectorAll('[data-hz-modal-close]').forEach(btn => {
            btn.addEventListener('click', stopScanner);
        });

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text ?? '';
            return div.innerHTML;
        }

        loadActive();
    </script>
</body>
</html>
