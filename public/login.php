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
    <meta name="theme-color" content="#2563eb">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
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
