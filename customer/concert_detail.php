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
$seatMapByShow = [];
$seatMapLayout = $concert ? getSeatMapLayout($concert['concert_id']) : [];

if ($concert) {
    foreach ($showDates as $showDate) {
        if ($showDate['status'] === 'available') {
            $hasAvailableShowDate = true;
        }

        $seatMapByShow[(string) $showDate['show_id']] = array_map(function ($seat) {
            return [
                'seat_number' => $seat['seat_number'],
                'zone' => seatZoneFromSeatNumber($seat['seat_number']),
                'price' => (int) $seat['price'],
                'status' => $seat['status'],
                'unit' => isPrivateBoxZone(seatZoneFromSeatNumber($seat['seat_number'])) ? 'box' : 'seat',
            ];
        }, getSeatsByShowId($showDate['show_id']));
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

            <section class="seat-map-section" id="seat-map" aria-label="座位分區圖">
                <div class="section-heading">
                    <p>Seat Map</p>
                    <h2>座位分區圖</h2>
                    <span class="selected-show-label" data-seat-map-label>請先選擇場次</span>
                </div>

                <div class="seat-map-canvas">
                    <div class="stage-block">STAGE<br><span>舞台</span></div>
                    <div class="seat-map-legend" aria-label="座位狀態圖例">
                        <span><i class="seat-dot is-available"></i>可購買</span>
                        <span><i class="seat-dot is-reserved"></i>保留中</span>
                        <span><i class="seat-dot is-sold"></i>已售出</span>
                    </div>
                    <div class="seat-map-grid" data-seat-map-grid>
                        <?php foreach ($seatMapLayout as $layoutItem): ?>
                            <section
                                class="seat-map-zone"
                                data-map-zone="<?= h($layoutItem['zone']) ?>"
                                style="grid-row: <?= h($layoutItem['row']) ?>; grid-column: <?= h($layoutItem['col']) ?> / span <?= h($layoutItem['colspan']) ?>;"
                            >
                                <div class="seat-map-zone-title">
                                    <strong><?= h($layoutItem['label']) ?></strong>
                                    <span data-zone-count></span>
                                </div>
                                <div class="seat-map-seats"<?= isset($layoutItem['seat_cols']) ? ' style="grid-template-columns: repeat(' . h($layoutItem['seat_cols']) . ', 1fr);"' : '' ?>></div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                                <span role="cell"><?= h($seatZone['remaining']) ?> <?= h($seatZone['unit']) ?></span>
                                <span role="cell">
                                    <?php if ((int) $seatZone['remaining'] > 0): ?>
                                        <button class="seat-zone-action"
                                            type="button"
                                            data-zone-book
                                            data-show-id="<?= h($showDate['show_id']) ?>"
                                            data-zone="<?= h($seatZone['zone']) ?>"
                                            data-price="<?= h($seatZone['price']) ?>"
                                            data-remaining="<?= h($seatZone['remaining']) ?>"
                                            data-unit="<?= h($seatZone['unit']) ?>"
                                            data-book-href="login.php?redirect=<?= h($detailRedirect) ?>"
                                        >訂票</button>
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

            <div class="zone-modal-overlay is-hidden" id="zone-modal" role="dialog" aria-modal="true" aria-labelledby="zone-modal-title">
                <div class="zone-modal zone-modal--map">
                    <div class="zone-modal-header">
                        <div>
                            <p class="zone-modal-kicker">Seat Map</p>
                            <h3 id="zone-modal-title" data-modal-zone-name></h3>
                            <p class="zone-modal-info" data-modal-zone-info></p>
                        </div>
                        <button class="zone-modal-close" type="button" aria-label="關閉">✕</button>
                    </div>
                    <div class="zone-modal-body">
                        <div class="seat-map-canvas">
                            <div class="stage-block">STAGE<br><span>舞台</span></div>
                            <div class="seat-map-legend" aria-label="座位狀態圖例">
                                <span><i class="seat-dot is-available"></i>可購買（點擊選位）</span>
                                <span><i class="seat-dot is-reserved"></i>保留中</span>
                                <span><i class="seat-dot is-sold"></i>已售出</span>
                                <span><i class="seat-dot is-selected"></i>已選取</span>
                            </div>
                            <div class="seat-map-grid" data-modal-map-grid></div>
                        </div>
                    </div>
                    <div class="zone-modal-footer">
                        <span class="zone-modal-selection-count" data-modal-selected-count>尚未選位</span>
                        <button class="secondary-action zone-modal-cancel" type="button">取消</button>
                        <a class="primary-action" id="zone-modal-book-link" href="#">前往訂票</a>
                    </div>
                </div>
            </div>
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
        const seatMapData = <?= json_encode($seatMapByShow, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const seatMapLayoutConfig = <?= json_encode($seatMapLayout, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const seatMapZones = Array.from(document.querySelectorAll("[data-map-zone]"));
        const seatMapLabel = document.querySelector("[data-seat-map-label]");

        function renderSeatMap(showId, showLabel) {
            const seats = seatMapData[showId] || [];
            const zones = seats.reduce((groups, seat) => {
                if (!groups[seat.zone]) {
                    groups[seat.zone] = [];
                }

                groups[seat.zone].push(seat);
                return groups;
            }, {});

            const zonePanels = seatMapZones.reduce((groups, panel) => {
                const zoneName = panel.dataset.mapZone;

                if (!groups[zoneName]) {
                    groups[zoneName] = [];
                }

                groups[zoneName].push(panel);
                return groups;
            }, {});

            seatMapZones.forEach((panel) => {
                panel.classList.add("has-seat-status");
                panel.querySelector(".seat-map-seats").innerHTML = "";
                panel.querySelector("[data-zone-count]").textContent = "";
            });

            Object.keys(zonePanels).forEach((zoneName) => {
                const panels = zonePanels[zoneName];
                const zoneSeats = zones[zoneName] || [];
                const chunkSize = Math.ceil(zoneSeats.length / panels.length) || 0;

                panels.forEach((panel, panelIndex) => {
                    const startIndex = panelIndex * chunkSize;
                    const panelSeats = zoneSeats.slice(startIndex, startIndex + chunkSize);
                    const availableCount = panelSeats.filter((seat) => seat.status === "available").length;
                    const seatList = panel.querySelector(".seat-map-seats");

                    panel.querySelector("[data-zone-count]").textContent = panelSeats.length > 0
                        ? `${availableCount} / ${panelSeats.length} 可購買`
                        : "";

                    if (panelSeats.length === 1 && panelSeats[0].unit === "box") {
                        const boxSeat = panelSeats[0];
                        const boxStatusText = {
                            available: "整個包廂可購買",
                            reserved: "包廂保留中",
                            sold: "包廂已售出"
                        }[boxSeat.status] || boxSeat.status;
                        const boxUnit = document.createElement("span");
                        panel.querySelector("[data-zone-count]").textContent = boxStatusText;
                        boxUnit.className = `seat-unit is-${boxSeat.status}`;
                        boxUnit.textContent = boxStatusText;
                        boxUnit.title = `${boxSeat.seat_number} · ${boxStatusText}`;
                        seatList.appendChild(boxUnit);
                        return;
                    }

                    panelSeats.forEach((seat) => {
                        const statusText = {
                            available: "可購買",
                            reserved: "保留中",
                            sold: "已售出"
                        }[seat.status] || seat.status;

                        const seatDot = document.createElement("span");
                        seatDot.className = `seat-dot is-${seat.status}`;
                        seatDot.title = `${seat.seat_number} · ${statusText}`;
                        seatList.appendChild(seatDot);
                    });
                });
            });

            if (seatMapLabel) {
                seatMapLabel.textContent = `已選場次：${showLabel}`;
            }
        }

        showButtons.forEach((button) => {
            button.addEventListener("click", () => {
                showButtons.forEach((item) => item.classList.toggle("is-active", item === button));
                seatZoneTables.forEach((table) => {
                    table.classList.toggle("is-hidden", table.dataset.seatZonesFor !== button.dataset.showId);
                });
                renderSeatMap(button.dataset.showId, button.dataset.showLabel);

                if (seatZoneSection) {
                    seatZoneSection.classList.remove("is-hidden");
                }

                if (selectedShowLabel) {
                    selectedShowLabel.textContent = `已選場次：${button.dataset.showLabel}`;
                }

                seatZoneSection?.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        });

        // Zone modal
        const zoneModal = document.getElementById("zone-modal");
        const modalZoneName = document.querySelector("[data-modal-zone-name]");
        const modalZoneInfo = document.querySelector("[data-modal-zone-info]");
        const modalMapGrid = document.querySelector("[data-modal-map-grid]");
        const modalSelectedCount = document.querySelector("[data-modal-selected-count]");
        const modalBookLink = document.getElementById("zone-modal-book-link");

        function updateModalSelection() {
            const selected = Array.from(
                document.querySelectorAll("[data-modal-map-grid] .is-selected")
            );
            if (modalSelectedCount) {
                modalSelectedCount.textContent = selected.length > 0
                    ? `已選取 ${selected.length} 張`
                    : "尚未選位";
            }
        }

        function renderModalMap(showId, targetZone) {
            if (!modalMapGrid) return;
            modalMapGrid.innerHTML = "";

            const seats = seatMapData[showId] || [];
            const zoneGroups = seats.reduce((groups, seat) => {
                if (!groups[seat.zone]) groups[seat.zone] = [];
                groups[seat.zone].push(seat);
                return groups;
            }, {});

            seatMapLayoutConfig.forEach((item) => {
                const panel = document.createElement("section");
                panel.className = "seat-map-zone" + (item.zone === targetZone ? " is-modal-target" : "");
                panel.style.gridRow = item.row;
                panel.style.gridColumn = `${item.col} / span ${item.colspan}`;

                const titleDiv = document.createElement("div");
                titleDiv.className = "seat-map-zone-title";

                const labelEl = document.createElement("strong");
                labelEl.textContent = item.label;

                const countEl = document.createElement("span");
                titleDiv.appendChild(labelEl);
                titleDiv.appendChild(countEl);

                const seatList = document.createElement("div");
                seatList.className = "seat-map-seats";
                if (item.seat_cols) {
                    seatList.style.gridTemplateColumns = `repeat(${item.seat_cols}, 1fr)`;
                }

                const zoneSeats = zoneGroups[item.zone] || [];
                const availableCount = zoneSeats.filter((s) => s.status === "available").length;
                countEl.textContent = zoneSeats.length > 0
                    ? `${availableCount} / ${zoneSeats.length} 可購買`
                    : "";

                const isTargetZone = item.zone === targetZone;

                if (zoneSeats.length === 1 && zoneSeats[0].unit === "box") {
                    const boxSeat = zoneSeats[0];
                    const boxStatusText = {
                        available: "整個包廂可購買",
                        reserved: "包廂保留中",
                        sold: "包廂已售出"
                    }[boxSeat.status] || boxSeat.status;
                    const boxUnit = document.createElement("span");
                    boxUnit.className = `seat-unit is-${boxSeat.status}`;
                    boxUnit.textContent = boxStatusText;
                    if (isTargetZone && boxSeat.status === "available") {
                        boxUnit.classList.add("is-selectable");
                        boxUnit.dataset.seatNumber = boxSeat.seat_number;
                        boxUnit.addEventListener("click", () => {
                            if (boxUnit.classList.contains("is-selected")) {
                                boxUnit.classList.remove("is-selected");
                            } else {
                                const currentCount = modalMapGrid.querySelectorAll(".is-selected").length;
                                if (currentCount < 2) {
                                    boxUnit.classList.add("is-selected");
                                } else {
                                    alert("每位會員最多能購買 2 張票");
                                }
                            }
                            updateModalSelection();
                        });
                    }
                    seatList.appendChild(boxUnit);
                } else {
                    zoneSeats.forEach((seat) => {
                        const statusText = {
                            available: "可購買",
                            reserved: "保留中",
                            sold: "已售出"
                        }[seat.status] || seat.status;
                        const dot = document.createElement("span");
                        dot.className = `seat-dot is-${seat.status}`;
                        dot.title = `${seat.seat_number} · ${statusText}`;
                        if (isTargetZone && seat.status === "available") {
                            dot.classList.add("is-selectable");
                            dot.dataset.seatNumber = seat.seat_number;
                            dot.addEventListener("click", () => {
                                if (dot.classList.contains("is-selected")) {
                                    dot.classList.remove("is-selected");
                                } else {
                                    const currentCount = modalMapGrid.querySelectorAll(".is-selected").length;
                                    if (currentCount < 2) {
                                        dot.classList.add("is-selected");
                                    } else {
                                        alert("每位會員最多能購買 2 張票");
                                    }
                                }
                                updateModalSelection();
                            });
                        }
                        seatList.appendChild(dot);
                    });
                }

                panel.appendChild(titleDiv);
                panel.appendChild(seatList);
                modalMapGrid.appendChild(panel);
            });
        }

        function openZoneModal(showId, zone, price, remaining, unit, bookHref) {
            modalZoneName.textContent = zone;
            modalZoneInfo.textContent = `NT$${Number(price).toLocaleString()} · 剩餘 ${remaining} ${unit}`;
            if (modalSelectedCount) modalSelectedCount.textContent = "尚未選位";

            renderModalMap(showId, zone);

            if (modalBookLink) modalBookLink.href = bookHref;
            zoneModal.classList.remove("is-hidden");
            document.body.style.overflow = "hidden";

            requestAnimationFrame(() => {
                modalMapGrid?.querySelector(".is-modal-target")
                    ?.scrollIntoView({ behavior: "smooth", block: "center" });
            });
        }

        function closeZoneModal() {
            zoneModal.classList.add("is-hidden");
            document.body.style.overflow = "";
        }

        document.querySelectorAll("[data-zone-book]").forEach((btn) => {
            btn.addEventListener("click", () => {
                openZoneModal(
                    btn.dataset.showId,
                    btn.dataset.zone,
                    btn.dataset.price,
                    btn.dataset.remaining,
                    btn.dataset.unit,
                    btn.dataset.bookHref
                );
            });
        });

        document.querySelectorAll(".zone-modal-close, .zone-modal-cancel").forEach((el) => {
            el.addEventListener("click", closeZoneModal);
        });

        zoneModal?.addEventListener("click", (e) => {
            if (e.target === zoneModal) closeZoneModal();
        });

        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") closeZoneModal();
        });
    </script>
</body>
</html>
