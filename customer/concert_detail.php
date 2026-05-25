<?php
session_start();
require_once __DIR__ . '/../includes/concerts.php';

/*
 * Database notes for future MySQL integration:
 *
 * concerts table
 * - concert_id: concert primary key
 * - artist: concert or performer name
 * - title: tour title or subtitle
 * - venue: venue name if all show dates share the same venue
 * - address: venue address if all show dates share the same venue
 * - image: poster image path
 * - sale_start: ticket sale start datetime
 * - sale_end: ticket sale end datetime
 * - description: concert introduction
 * - notice: ticket notice text
 * Do not store one Concert row per show date. Store one Concert row and many ShowDate rows.
 * date/time should be calculated from ShowDate for list and detail pages.
 *
 * ShowDate table
 * - show_id: primary key, auto increment INT
 * - concert_id: foreign key to concerts.id, ON DELETE CASCADE
 * - show_datetime: performance datetime, DATETIME NOT NULL
 * - status: ENUM('available', 'sold_out', 'ended')
 *
 * Seat table
 * - seat_id: primary key, auto increment INT
 * - show_id: foreign key to ShowDate.show_id, ON DELETE CASCADE
 * - seat_number: seat label, for example A區_12號, VARCHAR(20) NOT NULL
 * - price: seat ticket price, INT NOT NULL
 * - status: ENUM('available', 'reserved', 'sold')
 *
 * Future order-related tables can include:
 * - orders: id, customer_id, concert_id, show_id, seat_id, total_amount, status, created_at
 * - customers: id, name, email, password_hash, phone, created_at
 */

$isLoggedIn = isset($_SESSION['customer_id']);
$memberLink = $isLoggedIn ? 'member.php' : 'login.php';
$memberText = $isLoggedIn ? '會員資訊' : '會員登入';
$concertId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Future DB connection point:
// Replace findConcertById($concertId) with a SELECT query by concerts.id,
// then query ShowDate by concert_id. Query available Seat rows by show_id after the user selects a show.
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

$showDates = $concert['show_dates'] ?? [];
$showDateCount = count($showDates);
$primaryShowDate = $showDates[0] ?? null;
$hasAvailableShowDate = false;
$totalRemaining = $concert ? countAvailableSeatsByConcertId($concert['concert_id']) : 0;
$detailRedirect = $concert ? rawurlencode('concert_detail.php?id=' . $concert['concert_id']) : '';
$styleVersion = filemtime(__DIR__ . '/../assets/css/style.css');
$posterVersion = $concert ? filemtime(__DIR__ . '/../' . $concert['image']) : time();

if ($concert) {
    foreach ($showDates as $showDate) {
        if ($showDate['status'] === 'available') {
            $hasAvailableShowDate = true;
        }
    }
}

$isBookable = $concert && ($concert['status'] === '開放購票' || $hasAvailableShowDate);
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
                    <img src="../<?= h($concert['image']) ?>?v=<?= h($posterVersion) ?>" alt="<?= h($concert['artist']) ?> 演唱會海報">
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
                            <dd>
                                <?php if ($showDateCount > 1): ?>
                                    共 <?= h($showDateCount) ?> 場 · 首場 <?= h(showDateDateText($primaryShowDate)) ?> · <?= h(showDateTimeText($primaryShowDate)) ?>
                                <?php else: ?>
                                    <?php if ($primaryShowDate): ?>
                                        <?= h(showDateDateText($primaryShowDate)) ?> · <?= h(showDateTimeText($primaryShowDate)) ?>
                                    <?php else: ?>
                                        尚未公布
                                    <?php endif; ?>
                                <?php endif; ?>
                            </dd>
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
                            <a class="primary-action" href="#show-dates">選擇場次</a>
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

            <?php if ($showDateCount > 0): ?>
                <section class="show-date-section" id="show-dates" aria-label="場次資訊">
                    <div class="section-heading">
                        <p>Show Dates</p>
                        <h2>場次資訊</h2>
                    </div>

                    <div class="show-date-table" role="table" aria-label="演唱會場次">
                        <div class="show-date-row show-date-head" role="row">
                            <span role="columnheader">開演時間</span>
                            <span role="columnheader">狀態</span>
                        </div>

                        <?php foreach ($showDates as $showDate): ?>
                            <div class="show-date-row" role="row">
                                <span role="cell"><?= h(showDateDateText($showDate)) ?> · <?= h(showDateTimeText($showDate)) ?></span>
                                <span role="cell">
                                    <?php if ($showDate['status'] === 'available'): ?>
                                        <button class="show-select-action" type="button" data-show-id="<?= h($showDate['show_id']) ?>" data-show-label="<?= h(showDateDateText($showDate) . ' · ' . showDateTimeText($showDate)) ?>">
                                            可購買
                                        </button>
                                    <?php else: ?>
                                        <span class="show-status-text"><?= h(showDateStatusText($showDate['status'])) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="seat-zone-section is-hidden" id="seat-zones" data-seat-zone-section>
                <div class="section-heading">
                    <p>Seat Zones</p>
                    <h2>座位區與剩餘票數</h2>
                    <span class="selected-show-label" data-selected-show-label></span>
                </div>

                <?php foreach ($showDates as $showDate): ?>
                    <?php $seatZones = getSeatZoneSummariesByShowId($showDate['show_id']); ?>
                    <div class="seat-zone-table is-hidden" data-seat-zones-for="<?= h($showDate['show_id']) ?>" role="table" aria-label="座位區與票價">
                        <div class="seat-zone-row seat-zone-head" role="row">
                            <span role="columnheader">座位區</span>
                            <span role="columnheader">票價</span>
                            <span role="columnheader">剩餘</span>
                            <span role="columnheader">操作</span>
                        </div>

                        <?php foreach ($seatZones as $seatZone): ?>
                            <div class="seat-zone-row" role="row">
                                <span role="cell"><?= h($seatZone['zone']) ?></span>
                                <span role="cell"><?= h(formatTicketPrice($seatZone['price'])) ?></span>
                                <span role="cell"><?= h($seatZone['remaining']) ?> 張</span>
                                <span role="cell">
                                    <?php if ((int) $seatZone['remaining'] > 0): ?>
                                        <?php
                                        // Future order point:
                                        // Send concert_id, selected show_id, and seat_id to a booking page or POST handler.
                                        // The current zone button is a summary; the booking page should list/select real Seat rows.
                                        ?>
                                        <a class="seat-zone-action" href="login.php?redirect=<?= h($detailRedirect) ?>">訂票</a>
                                    <?php else: ?>
                                        <span class="seat-zone-action is-disabled">售完</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
            </section>

            <section class="notice-section">
                <div class="section-heading">
                    <p>Notice</p>
                    <h2>訂票提醒</h2>
                </div>
                <p><?= h($concert['notice']) ?></p>
                <p>資料庫串接時建議將演唱會主資料、場次、座位、訂單與會員資料拆成不同資料表。使用者選定場次後，再用 show_id 查詢可購買座位。</p>
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
    <script>
        const showButtons = Array.from(document.querySelectorAll(".show-select-action"));
        const seatZoneSection = document.querySelector("[data-seat-zone-section]");
        const selectedShowLabel = document.querySelector("[data-selected-show-label]");
        const seatZoneTables = Array.from(document.querySelectorAll("[data-seat-zones-for]"));

        showButtons.forEach((button) => {
            button.addEventListener("click", () => {
                showButtons.forEach((item) => item.classList.toggle("is-active", item === button));
                seatZoneTables.forEach((table) => {
                    table.classList.toggle("is-hidden", table.dataset.seatZonesFor !== button.dataset.showId);
                });

                if (seatZoneSection) {
                    seatZoneSection.classList.remove("is-hidden");
                }

                if (selectedShowLabel) {
                    selectedShowLabel.textContent = `已選場次：${button.dataset.showLabel}`;
                }

                seatZoneSection?.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        });
    </script>
</body>
</html>
