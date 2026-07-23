<?php
// Zelfde sessiebeveiliging als config.php (login.php draait vóór de DB-verbinding, dus dupliceert
// bewust alleen deze paar regels i.p.v. config.php's DB-connectie hier al op te zetten).
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    ini_set('session.cookie_secure', '1');
}
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /inventory-manager/index.php');
    exit;
}
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <base href="/inventory-manager/">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Manager - Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <meta name="theme-color" content="#0f1f3d">
    <style>
        .iv-split { min-height: 100vh; display: flex; }
        .iv-panel {
            flex: 1 1 50%;
            background: linear-gradient(160deg, #0b1a33 0%, #14285a 55%, #0f1f3d 100%);
            color: #dbe4ff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 3.5rem;
            position: relative;
            overflow: hidden;
        }
        .iv-panel::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 36px 36px;
            pointer-events: none;
        }
        .iv-panel__content { position: relative; z-index: 1; max-width: 420px; }
        .iv-panel__eyebrow {
            display: inline-flex; align-items: center; gap: .5rem;
            font-size: .75rem; letter-spacing: .08em; text-transform: uppercase;
            color: #7ea3ff; font-weight: 600; margin-bottom: 1.25rem;
        }
        .iv-panel__eyebrow::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: #7ea3ff; box-shadow: 0 0 0 4px rgba(126,163,255,0.2); }
        .iv-panel h2 { font-size: 1.9rem; font-weight: 700; color: #fff; margin-bottom: .75rem; line-height: 1.25; }
        .iv-panel p.iv-lead { color: #a7bbe8; font-size: .95rem; line-height: 1.6; margin-bottom: 2rem; }
        .iv-illustration { width: 100%; max-width: 340px; }
        .iv-formside {
            flex: 1 1 50%;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .iv-form-wrap { width: 100%; max-width: 380px; }
        .iv-form-wrap .login-header { text-align: left; margin-bottom: 2rem; }
        .iv-form-wrap .login-header h1 { color: var(--gray-900); font-size: 1.5rem; }
        .iv-form-wrap .login-header p { color: var(--gray-500); }
        .iv-badge-row { display: flex; gap: 1.25rem; margin-top: .5rem; }
        .iv-badge-row .iv-badge { display: flex; align-items: center; gap: .5rem; color: #91a8dd; font-size: .8rem; }
        .iv-badge-row svg { width: 16px; height: 16px; flex-shrink: 0; }
        @media (max-width: 860px) {
            .iv-panel { display: none; }
            .iv-formside { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <div class="iv-split">
        <div class="iv-panel">
            <div class="iv-panel__content">
                <span class="iv-panel__eyebrow">Enterprise Asset Tracking</span>
                <h2>Al uw assets, altijd in beeld.</h2>
                <p class="iv-lead">Scan, volg en beheer voorraad en apparatuur in real-time — van magazijnstelling tot eindgebruiker.</p>
                <svg class="iv-illustration" viewBox="0 0 340 220" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="18" y="120" width="70" height="60" rx="4" fill="#1c3568" stroke="#3a5aa8" stroke-width="1.5"/>
                    <rect x="30" y="132" width="46" height="6" fill="#5b7fd6"/>
                    <rect x="30" y="144" width="30" height="6" fill="#3a5aa8"/>
                    <rect x="100" y="95" width="80" height="85" rx="4" fill="#1e3a70" stroke="#4568b8" stroke-width="1.5"/>
                    <rect x="114" y="110" width="52" height="7" fill="#6c8ee0"/>
                    <rect x="114" y="124" width="35" height="7" fill="#4568b8"/>
                    <rect x="192" y="130" width="60" height="50" rx="4" fill="#1c3568" stroke="#3a5aa8" stroke-width="1.5"/>
                    <rect x="204" y="142" width="38" height="6" fill="#5b7fd6"/>
                    <g transform="translate(140,40)">
                        <rect x="0" y="0" width="60" height="34" rx="6" fill="#0d1f42" stroke="#7ea3ff" stroke-width="1.5"/>
                        <rect x="8" y="10" width="3" height="14" fill="#7ea3ff"/>
                        <rect x="14" y="10" width="2" height="14" fill="#7ea3ff"/>
                        <rect x="19" y="10" width="4" height="14" fill="#7ea3ff"/>
                        <rect x="26" y="10" width="2" height="14" fill="#7ea3ff"/>
                        <rect x="31" y="10" width="3" height="14" fill="#7ea3ff"/>
                        <rect x="37" y="10" width="2" height="14" fill="#7ea3ff"/>
                        <rect x="42" y="10" width="4" height="14" fill="#7ea3ff"/>
                        <rect x="49" y="10" width="3" height="14" fill="#7ea3ff"/>
                    </g>
                    <circle cx="270" cy="60" r="3" fill="#7ea3ff"/>
                    <circle cx="290" cy="80" r="2" fill="#5b7fd6"/>
                    <circle cx="40" cy="60" r="2.5" fill="#5b7fd6"/>
                </svg>
                <div class="iv-badge-row">
                    <span class="iv-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4"/><circle cx="12" cy="12" r="9"/></svg>Realtime scans</span>
                    <span class="iv-badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>Volledig auditlog</span>
                </div>
            </div>
        </div>
        <div class="iv-formside">
            <div class="iv-form-wrap">
                <div class="login-header">
                    <h1>Inventory Manager</h1>
                    <p>Meld u aan om door te gaan</p>
                </div>
                <form id="loginForm" class="login-form">
                    <div class="form-group">
                        <label for="loginEmail">E-mail
                            <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">Gebruik admin@demo.nl, manager@demo.nl of user@demo.nl</span></span>
                        </label>
                        <input type="email" id="loginEmail" name="login_email" required value="admin@demo.nl" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="loginPass">Wachtwoord
                            <span class="hz-tooltip" tabindex="0">ⓘ<span class="hz-tooltip__bubble">Demo-wachtwoord voor alle accounts: demo123</span></span>
                        </label>
                        <input type="password" id="loginPass" name="login_pass" required value="demo123" autocomplete="current-password">
                    </div>
                    <div id="error" class="error-message" style="display: none;"></div>
                    <button type="submit" class="btn btn-primary btn-block">Inloggen</button>
                </form>
                <div class="login-footer">
                    <p>Demo: admin@demo.nl (admin) / manager@demo.nl (manager) / user@demo.nl (user) — wachtwoord: demo123</p>
                </div>
            </div>
        </div>
    </div>
    <script>
        const CSRF_TOKEN = '<?= generateCSRFToken() ?>';
        
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorEl = document.getElementById('error');
            errorEl.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('csrf_token', CSRF_TOKEN);
            formData.append('email', document.getElementById('loginEmail').value);
            formData.append('password', document.getElementById('loginPass').value);

            try {
                const response = await fetch('api.php?action=login', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    window.location.href = '/inventory-manager/index.php';
                } else {
                    errorEl.textContent = data.error || 'Inloggen mislukt';
                    errorEl.style.display = 'block';
                }
            } catch (err) {
                errorEl.textContent = 'Netwerkfout';
                errorEl.style.display = 'block';
            }
        });
    </script>
</body>
</html>
