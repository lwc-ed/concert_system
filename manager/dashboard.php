<?php
require_once __DIR__ . '/../includes/manager_auth.php';

requireManager();
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理後台 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/concert_system/manager/dashboard.php" aria-label="ConcertNow 管理後台">
            <span class="brand-mark">CN</span>
            <span>ConcertNow 管理後台</span>
        </a>

        <nav class="main-nav" aria-label="管理功能">
            <a href="../index.php">前台首頁</a>
            <a href="/concert_system/manager/concerts.php">演唱會管理</a>
            <a href="/concert_system/manager/shows.php">場次管理</a>
            <a href="/concert_system/manager/promocodes.php">優惠碼管理</a>
            <a href="change_password.php">修改密碼</a>
            <a class="login-button" href="logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-dashboard">
        <div class="section-title">
            <div>
                <p>Dashboard</p>
                <h2>管理後台</h2>
            </div>
        </div>

        <section class="placeholder-card manager-panel">
            <h1>歡迎，<?= h($_SESSION['manager_username'] ?? 'manager') ?></h1>
            <p>你已登入管理員帳號，可以管理演唱會、場次、優惠碼與後續訂單資料。</p>
            <div class="manager-actions">
                <a class="placeholder-link" href="/concert_system/manager/concerts.php">演唱會管理 Concert Management</a>
                <a class="placeholder-link" href="/concert_system/manager/shows.php">場次管理 ShowDate Management</a>
                <a class="placeholder-link" href="/concert_system/manager/promocodes.php">優惠碼管理 Promo Code Management</a>
                <a class="placeholder-link" href="change_password.php">修改密碼</a>
                <a class="secondary-action" href="../index.php">查看前台</a>
            </div>
        </section>
    </main>
</body>
</html>
