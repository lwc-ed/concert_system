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
    <style>
        .manager-dashboard {
            margin-top: 34px;
        }

        .dashboard-panel {
            width: 100%;
            max-width: none;
        }

        .dashboard-layout {
            display: grid;
            gap: 22px;
        }

        .dashboard-welcome {
            display: grid;
            gap: 10px;
        }

        .dashboard-welcome h1 {
            margin: 0;
        }

        .dashboard-welcome p {
            margin: 0;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .dashboard-card {
            display: grid;
            gap: 8px;
            min-height: 132px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .dashboard-card h3 {
            margin: 0;
            color: var(--ink);
            font-size: 22px;
            line-height: 1.25;
        }

        .dashboard-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .dashboard-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-self: end;
            margin-top: 6px;
        }

        @media (max-width: 820px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/concert_system/manager/dashboard.php" aria-label="ConcertNow 管理後台">
            <span class="brand-mark">CN</span>
            <span>ConcertNow 管理後台</span>
        </a>

        <nav class="main-nav" aria-label="管理功能">
            <a href="/concert_system/manager/concerts.php">演唱會管理</a>
            <a href="/concert_system/manager/shows.php">場次管理</a>
            <a href="/concert_system/manager/promocodes.php">優惠碼管理</a>
            <a href="change_password.php">修改密碼</a>
            <a class="login-button" href="logout.php">登出</a>
            <a href="/concert_system/manager/change_password.php">修改密碼</a>
            <a class="login-button" href="/concert_system/manager/logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-dashboard">
        <div class="section-title">
            <div>
                <p>Dashboard</p>
                <h2>管理後台</h2>
            </div>
            <a class="secondary-action" href="../index.php">查看前台</a>
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
        <section class="placeholder-card dashboard-panel">
            <div class="dashboard-layout">
                <div class="dashboard-welcome">
                    <h1>歡迎，<?= h($_SESSION['manager_username'] ?? 'manager') ?></h1>
                    <p>你已登入管理員帳號。可從這裡進入演唱會主資料與場次日期管理。</p>
                </div>

                <div class="dashboard-grid">
                    <article class="dashboard-card">
                        <div>
                            <h3>演唱會管理</h3>
                            <p>新增、修改、刪除演唱會主資料與售票時間。</p>
                        </div>
                        <div class="dashboard-actions">
                            <a class="placeholder-link" href="/concert_system/manager/concerts.php">進入管理</a>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <div>
                            <h3>場次管理</h3>
                            <p>管理每場演唱會底下的日期、時間與場次狀態。</p>
                        </div>
                        <div class="dashboard-actions">
                            <a class="placeholder-link" href="/concert_system/manager/shows.php">進入管理</a>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <div>
                            <h3>帳號安全</h3>
                            <p>管理員可更新自己的登入密碼。</p>
                        </div>
                        <div class="dashboard-actions">
                            <a class="placeholder-link" href="/concert_system/manager/change_password.php">修改密碼</a>
                        </div>
                    </article>

                    <article class="dashboard-card">
                        <div>
                            <h3>前台預覽</h3>
                            <p>回到前台確認首頁與顧客瀏覽流程。</p>
                        </div>
                        <div class="dashboard-actions">
                            <a class="secondary-action" href="../index.php">查看前台</a>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
