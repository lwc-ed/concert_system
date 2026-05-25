<?php
session_start();
require_once __DIR__ . '/../includes/concerts.php';

/*
 * Database notes for future MySQL integration:
 *
 * concerts table
 * - id: concert primary key
 * - artist: concert or performer name
 * - title: tour title or subtitle
 * - date: concert date
 * - time: concert start time
 * - venue: venue name
 * - address: venue address
 * - price: display price range, or calculate it from seat_zones
 * - status: ticket status, for example 開放購票 / 已售完 / 已結束
 * - image: poster image path
 * - sale_start: ticket sale start datetime
 * - sale_end: ticket sale end datetime
 * - description: concert introduction
 * - notice: ticket notice text
 *
 * seat_zones table
 * - id: seat zone primary key
 * - concert_id: foreign key to concerts.id
 * - zone: ticket zone name
 * - price: ticket price
 * - remaining: remaining ticket quantity
 *
 * Future order-related tables can include:
 * - orders: id, customer_id, concert_id, seat_zone_id, quantity, total_amount, status, created_at
 * - customers: id, name, email, password_hash, phone, created_at
 */

$isLoggedIn = isset($_SESSION['customer_id']);
$memberLink = $isLoggedIn ? 'member.php' : 'login.php';
$memberText = $isLoggedIn ? '會員資訊' : '會員登入';
$concertId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Future DB connection point:
// Replace findConcertById($concertId) with a SELECT query by concerts.id,
// then query seat_zones by concert_id and attach them to $concert['seat_zones'].
$concert = $concertId ? findConcertById($concertId) : null;

if ($concert === null) {
    // Future DB fallback point:
    // Replace getConcerts() with a SELECT query that returns the default or nearest concert.
    $allConcerts = getConcerts();
    $concert = $allConcerts[0] ?? null;
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$isBookable = $concert && $concert['status'] === '開放購票';
$totalRemaining = 0;
$detailRedirect = $concert ? rawurlencode('concert_detail.php?id=' . $concert['id']) : '';
$styleVersion = filemtime(__DIR__ . '/../assets/css/style.css');

if ($concert) {
    foreach ($concert['seat_zones'] as $seatZone) {
        $totalRemaining += (int) $seatZone['remaining'];
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $concert ? h($concert['artist']) . ' | ConcertNow' : '演唱會詳情 | ConcertNow' ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= h($styleVersion) ?>">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="../index.php" aria-label="ConcertNow 首頁">
            <span class="brand-mark">CN</span>
            <span>ConcertNow</span>
        </a>

        <nav class="main-nav" aria-label="主要導覽">
            <a href="../index.php#concerts">近期演唱會</a>
            <a href="concert_detail.php">訂票資訊</a>
            <a class="login-button" href="<?= h($memberLink) ?>"><?= h($memberText) ?></a>
        </nav>
    </header>

    <main>
        <?php if ($concert): ?>
            <section class="detail-hero">
                <div class="detail-poster">
                    <img src="../<?= h($concert['image']) ?>" alt="<?= h($concert['artist']) ?> 演唱會海報">
                </div>

                <div class="detail-summary">
                    <div class="detail-title-row">
                        <div>
                            <p class="detail-kicker">Concert Detail</p>
                            <h1><?= h($concert['artist']) ?></h1>
                        </div>
                        <span class="detail-status-badge"><?= h($concert['status']) ?></span>
                    </div>
                    <p class="detail-subtitle"><?= h($concert['title']) ?></p>

                    <dl class="detail-meta">
                        <div>
                            <dt>日期時間</dt>
                            <dd><?= h($concert['date']) ?> · <?= h($concert['time']) ?></dd>
                        </div>
                        <div>
                            <dt>演出地點</dt>
                            <dd><?= h($concert['venue']) ?></dd>
                        </div>
                        <div>
                            <dt>售票狀態</dt>
                            <dd><?= h($concert['status']) ?></dd>
                        </div>
                        <div>
                            <dt>票價範圍</dt>
                            <dd><?= h($concert['price']) ?></dd>
                        </div>
                    </dl>

                    <div class="detail-actions">
                        <a class="secondary-action" href="../index.php#concerts">返回列表</a>
                        <?php if ($isBookable && $totalRemaining > 0): ?>
                            <a class="primary-action" href="#ticket-zones">選擇票區</a>
                        <?php else: ?>
                            <span class="disabled-action"><?= h($concert['status']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="detail-layout" aria-label="演唱會資訊">
                <article class="detail-panel">
                    <div class="section-heading">
                        <p>Overview</p>
                        <h2>活動介紹</h2>
                    </div>
                    <p class="detail-copy"><?= h($concert['description']) ?></p>

                    <div class="info-list">
                        <div>
                            <span>場館地址</span>
                            <strong><?= h($concert['address']) ?></strong>
                        </div>
                        <div>
                            <span>開賣時間</span>
                            <strong><?= h($concert['sale_start']) ?></strong>
                        </div>
                        <div>
                            <span>結束售票</span>
                            <strong><?= h($concert['sale_end']) ?></strong>
                        </div>
                    </div>
                </article>

                <aside class="booking-panel">
                    <div class="booking-number"><?= h($totalRemaining) ?></div>
                    <p>剩餘可售票數</p>
                    <span><?= $isBookable ? '可建立訂單資料' : '目前不可訂票' ?></span>
                </aside>
            </section>

            <section class="ticket-zone-section" id="ticket-zones">
                <div class="section-heading">
                    <p>Ticket Zones</p>
                    <h2>票區與剩餘票數</h2>
                </div>

                <div class="ticket-zone-table" role="table" aria-label="票區與票價">
                    <div class="ticket-zone-row ticket-zone-head" role="row">
                        <span role="columnheader">票區</span>
                        <span role="columnheader">票價</span>
                        <span role="columnheader">剩餘</span>
                        <span role="columnheader">操作</span>
                    </div>

                    <?php foreach ($concert['seat_zones'] as $seatZone): ?>
                        <?php $canSelectZone = $isBookable && (int) $seatZone['remaining'] > 0; ?>
                        <div class="ticket-zone-row" role="row">
                            <span role="cell"><?= h($seatZone['zone']) ?></span>
                            <span role="cell"><?= h(formatTicketPrice($seatZone['price'])) ?></span>
                            <span role="cell"><?= h($seatZone['remaining']) ?> 張</span>
                            <span role="cell">
                                <?php if ($canSelectZone): ?>
                                    <?php
                                    // Future order point:
                                    // Send concert_id and seat_zone_id to a booking page or POST handler,
                                    // then create an order row after checking login and remaining ticket quantity.
                                    ?>
                                    <a class="zone-action" href="login.php?redirect=<?= h($detailRedirect) ?>">訂票</a>
                                <?php else: ?>
                                    <span class="zone-action is-disabled">不可購買</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="notice-section">
                <div class="section-heading">
                    <p>Notice</p>
                    <h2>訂票提醒</h2>
                </div>
                <p><?= h($concert['notice']) ?></p>
                <p>資料庫串接時建議將演唱會主資料、票區、訂單與會員資料拆成不同資料表，頁面上方只需要用目前的 concert id 查詢即可。</p>
            </section>
        <?php else: ?>
            <div class="placeholder-card">
                <h1>找不到演唱會資料</h1>
                <p>目前沒有可顯示的演唱會，請回首頁確認資料是否存在。</p>
                <a class="placeholder-link" href="../index.php">回首頁</a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <span>ConcertNow Database Final Project</span>
        <span>Concert detail prototype</span>
    </footer>
</body>
</html>
