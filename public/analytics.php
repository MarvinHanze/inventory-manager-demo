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
    <title>Inventory Manager - Rapportages</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="theme-color" content="#2563eb">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">Inventory Manager</div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="checkout.php">Uitgifte</a>
            <a href="checkin.php">Inname</a>
            <a href="admin.php">Beheer</a>
            <a href="analytics.php" class="active">Rapportages</a>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($user['name']) ?></span>
            <a href="api.php?action=logout" class="btn btn-sm">Uitloggen</a>
        </div>
    </nav>
    
    <main class="container">
        <h1>Rapportages</h1>
        
        <div class="stats-grid">
            <div class="section">
                <h2>Uitgiftes per medewerker</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Medewerker</th>
                                <th>Aantal</th>
                            </tr>
                        </thead>
                        <tbody id="employeeStats">
                            <tr><td colspan="2">Laden...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="section">
                <h2>Uitgiftes per categorie</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Categorie</th>
                                <th>Aantal</th>
                            </tr>
                        </thead>
                        <tbody id="categoryStats">
                            <tr><td colspan="2">Laden...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Activiteit (afgelopen 30 dagen)</h2>
            <div class="chart-container">
                <canvas id="activityChart"></canvas>
            </div>
        </div>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        async function loadAnalytics() {
            try {
                const response = await fetch('api.php?action=analytics');
                const data = await response.json();
                
                // Employee stats
                const empTbody = document.getElementById('employeeStats');
                if (data.by_employee.length === 0) {
                    empTbody.innerHTML = '<tr><td colspan="2">Geen data</td></tr>';
                } else {
                    empTbody.innerHTML = data.by_employee.map(e => `
                        <tr>
                            <td>${escapeHtml(e.name)}</td>
                            <td>${e.count}</td>
                        </tr>
                    `).join('');
                }
                
                // Category stats
                const catTbody = document.getElementById('categoryStats');
                if (data.by_category.length === 0) {
                    catTbody.innerHTML = '<tr><td colspan="2">Geen data</td></tr>';
                } else {
                    catTbody.innerHTML = data.by_category.map(c => `
                        <tr>
                            <td>${escapeHtml(c.category)}</td>
                            <td>${c.count}</td>
                        </tr>
                    `).join('');
                }
                
                // Activity chart
                if (data.timeline.length > 0) {
                    const ctx = document.getElementById('activityChart').getContext('2d');
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.timeline.map(t => new Date(t.date).toLocaleDateString('nl-NL')),
                            datasets: [{
                                label: 'Uitgiftes',
                                data: data.timeline.map(t => t.count),
                                backgroundColor: '#3b82f6'
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                }
                            }
                        }
                    });
                }
            } catch (err) {
                console.error('Failed to load analytics:', err);
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        loadAnalytics();
    </script>
</body>
</html>
