<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /inventory-manager/index.php');
    exit;
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
                    <label for="loginEmail">E-mail</label>
                    <input type="email" id="loginEmail" name="login_email" required value="admin@demo.nl" autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="loginPass">Wachtwoord</label>
                    <input type="password" id="loginPass" name="login_pass" required value="demo123" autocomplete="current-password">
                </div>
                <div id="error" class="error-message" style="display: none;"></div>
                <button type="submit" class="btn btn-primary btn-block">Inloggen</button>
            </form>
            <div class="login-footer">
                <p>Demo: admin@demo.nl / demo123</p>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const errorEl = document.getElementById('error');
            errorEl.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'login');
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
