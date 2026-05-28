<?php
session_start();
require_once __DIR__ . '/includes/concerts.php';

$isCustomerLoggedIn = isset($_SESSION['customer_id']);
$isManagerLoggedIn = isset($_SESSION['manager_id']) && ($_SESSION['manager_role'] ?? '') === 'manager';
$isLoggedIn = $isCustomerLoggedIn || $isManagerLoggedIn;
$memberLink = 'customer/login.php';
$memberText = '會員登入';

if ($isManagerLoggedIn) {
    $memberLink = 'manager/dashboard.php';
    $memberText = $_SESSION['manager_username'] ?? 'manager';
} elseif ($isCustomerLoggedIn) {
    $memberLink = 'customer/member.php';
    $memberText = $_SESSION['customer_username'] ?? '會員';
}
$concerts = getConcerts();
$styleVersion = filemtime(__DIR__ . '/assets/css/style.css');
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ConcertNow 演唱會訂票系統</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= htmlspecialchars((string) $styleVersion) ?>">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="index.php" aria-label="ConcertNow 首頁">
            <span class="brand-mark">CN</span>
            <span>ConcertNow</span>
        </a>

        <nav class="main-nav" aria-label="主要導覽">
            <a href="#concerts">近期演唱會</a>
            <a class="login-button" href="<?= htmlspecialchars($memberLink) ?>"><?= htmlspecialchars($memberText) ?></a>
        </nav>
    </header>

    <main>
        <section class="ad-carousel" aria-label="近期演唱會廣告">
            <div class="carousel-track">
                <?php foreach ($concerts as $index => $ad): ?>
                    <?php $adImageVersion = filemtime(__DIR__ . '/' . $ad['image']); ?>
                    <article class="carousel-slide <?= $index === 0 ? 'is-active' : '' ?>" data-slide="<?= $index ?>">
                        <div class="poster-frame">
                            <img src="<?= htmlspecialchars($ad['image']) ?>?v=<?= htmlspecialchars((string) $adImageVersion) ?>" alt="<?= htmlspecialchars($ad['title']) ?> 演唱會廣告">
                        </div>
                        <div class="slide-overlay">
                            <p class="slide-kicker">Upcoming Concert</p>
                            <h1><?= htmlspecialchars($ad['title']) ?></h1>
                            <p><?= htmlspecialchars($ad['artist']) ?></p>
                            <div class="slide-meta">
                                <span><?= htmlspecialchars(concertScheduleText($ad)) ?></span>
                                <span><?= htmlspecialchars($ad['venue']) ?></span>
                            </div>
                            <a class="primary-action" href="customer/concert_detail.php?id=<?= htmlspecialchars((string) $ad['concert_id']) ?>">查看詳情</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <button class="carousel-control prev" type="button" aria-label="上一張廣告">‹</button>
            <button class="carousel-control next" type="button" aria-label="下一張廣告">›</button>

            <div class="carousel-dots" aria-label="廣告切換">
                <?php foreach ($concerts as $index => $ad): ?>
                    <button class="<?= $index === 0 ? 'is-active' : '' ?>" type="button" data-dot="<?= $index ?>" aria-label="切換到第 <?= $index + 1 ?> 張廣告"></button>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="concert-section" id="concerts">
            <div class="section-title">
                <p>Ticket Booking</p>
                <h2>近期演唱會</h2>
            </div>

            <div class="ticket-grid">
                <?php foreach ($concerts as $concert): ?>
                    <article class="ticket-card">
                        <div class="ticket-status"><?= htmlspecialchars($concert['status']) ?></div>
                        <h3><?= htmlspecialchars($concert['title']) ?></h3>
                        <p class="ticket-title"><?= htmlspecialchars($concert['artist']) ?></p>

                        <dl class="ticket-info">
                            <div>
                                <dt>日期</dt>
                                <dd><?= htmlspecialchars(concertScheduleText($concert)) ?></dd>
                            </div>
                            <div>
                                <dt>地點</dt>
                                <dd><?= htmlspecialchars($concert['venue']) ?></dd>
                            </div>
                            <div>
                                <dt>票價</dt>
                                <dd><?= htmlspecialchars($concert['price']) ?></dd>
                            </div>
                        </dl>

                        <a class="ticket-link" href="customer/concert_detail.php?id=<?= htmlspecialchars((string) $concert['concert_id']) ?>">查看詳情</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <span>ConcertNow Database Final Project</span>
        <span>Homepage prototype</span>
    </footer>

    <script src="assets/js/homepage.js"></script>
</body>
</html>
