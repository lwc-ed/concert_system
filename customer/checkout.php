<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatMoney($value)
{
    return 'NT$' . number_format((int) $value);
}

function checkoutDateTimeText($value)
{
    $timestamp = strtotime((string) $value);

    return $timestamp ? date('Y/m/d H:i', $timestamp) : (string) $value;
}

function selectedSeatNumbersFromRequest()
{
    $raw = trim((string) ($_GET['seats'] ?? ''));

    if ($raw === '') {
        return [];
    }

    $items = array_map('trim', explode(',', $raw));
    $items = array_filter($items, function ($item) {
        return $item !== '';
    });

    return array_values(array_unique($items));
}

function fetchSelectedSeatsByNumbers($pdo, $showId, $seatNumbers)
{
    if (!$seatNumbers) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($seatNumbers), '?'));
    $sql = "SELECT
                s.seat_id,
                s.show_id,
                s.seat_number,
                s.price,
                s.status,
                sd.show_datetime,
                c.concert_id,
                c.artist,
                c.title,
                c.venue
            FROM Seat s
            INNER JOIN ShowDate sd ON sd.show_id = s.show_id
            INNER JOIN Concert c ON c.concert_id = sd.concert_id
            WHERE s.show_id = ?
              AND s.seat_number IN ($placeholders)
              AND s.status = 'available'
            ORDER BY s.seat_number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$showId], $seatNumbers));

    return $stmt->fetchAll();
}

function fetchFallbackSeatByZone($pdo, $showId, $zone)
{
    if ($zone === '') {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT
            s.seat_id,
            s.show_id,
            s.seat_number,
            s.price,
            s.status,
            sd.show_datetime,
            c.concert_id,
            c.artist,
            c.title,
            c.venue
         FROM Seat s
         INNER JOIN ShowDate sd ON sd.show_id = s.show_id
         INNER JOIN Concert c ON c.concert_id = sd.concert_id
         WHERE s.show_id = ?
           AND SUBSTRING_INDEX(s.seat_number, '_', 1) = ?
           AND s.status = 'available'
         ORDER BY s.seat_id
         LIMIT 1"
    );
    $stmt->execute([$showId, $zone]);

    return $stmt->fetchAll();
}

function fetchSelectedSeatsByIdsForUpdate($pdo, $showId, $seatIds)
{
    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $sql = "SELECT seat_id, show_id, seat_number, price, status
            FROM Seat
            WHERE show_id = ?
              AND seat_id IN ($placeholders)
              AND status = 'available'
            FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$showId], $seatIds));

    return $stmt->fetchAll();
}

function deleteCancelledTicketReferencesForSeats($pdo, $seatIds)
{
    if (!$seatIds) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
    $stmt = $pdo->prepare(
        "DELETE t
         FROM Ticket t
         INNER JOIN Orders o ON o.order_id = t.order_id
         WHERE o.status = 'cancelled'
           AND t.seat_id IN ($placeholders)"
    );
    $stmt->execute($seatIds);
}

function customerHasActiveOrderForShow($pdo, $customerId, $showId)
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM Orders
         WHERE user_id = ?
           AND show_id = ?
           AND status <> 'cancelled'"
    );
    $stmt->execute([$customerId, $showId]);

    return (int) $stmt->fetchColumn() > 0;
}

function ticketHolderExistsForShow($pdo, $showId, $idNumber)
{
    $idNumber = strtoupper(trim((string) $idNumber));

    if ($idNumber === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM Ticket t
         INNER JOIN Orders o ON o.order_id = t.order_id
         WHERE o.show_id = ?
           AND o.status <> 'cancelled'
           AND UPPER(t.id_number) = ?"
    );
    $stmt->execute([$showId, $idNumber]);

    return (int) $stmt->fetchColumn() > 0;
}

function validatePromoCode($pdo, $code, $subtotal)
{
    $code = strtoupper(trim((string) $code));

    if ($code === '') {
        return [null, 0, null];
    }

    $stmt = $pdo->prepare(
        'SELECT promo_id, code_name, discount_amount, usage_limit, starts_at, expires_at, is_active
         FROM PromoCode
         WHERE code_name = ?
         LIMIT 1'
    );
    $stmt->execute([$code]);
    $promo = $stmt->fetch();

    if (!$promo || (int) $promo['is_active'] !== 1) {
        return [null, 0, '優惠碼不存在或尚未啟用。'];
    }

    $now = time();

    if (!empty($promo['starts_at']) && strtotime($promo['starts_at']) > $now) {
        return [null, 0, '優惠碼尚未開始使用。'];
    }

    if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < $now) {
        return [null, 0, '優惠碼已過期。'];
    }

    if ($promo['usage_limit'] !== null) {
        $usageStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM Orders
             WHERE promo_id = ?
               AND status IN ('pending_payment', 'paid')"
        );
        $usageStmt->execute([$promo['promo_id']]);
        $usedCount = (int) $usageStmt->fetchColumn();

        if ($usedCount >= (int) $promo['usage_limit']) {
            return [null, 0, '優惠碼已達使用次數上限。'];
        }
    }

    $discount = min((int) $promo['discount_amount'], (int) $subtotal);

    return [$promo, $discount, null];
}

if (!isset($_SESSION['customer_id'])) {
    $redirect = 'checkout.php';

    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirect .= '?' . $_SERVER['QUERY_STRING'];
    }

    header('Location: login.php?redirect=' . rawurlencode($redirect));
    exit;
}

$stylePath = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($stylePath) ? filemtime($stylePath) : time();
$errors = [];
$notice = '';
$selectedSeats = [];
$promo = null;
$discount = 0;
$promoCode = strtoupper(trim((string) ($_POST['promo_code'] ?? $_GET['promo_code'] ?? '')));
$showId = filter_input(INPUT_GET, 'show_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$zone = trim((string) ($_GET['zone'] ?? ''));
$customerId = (int) $_SESSION['customer_id'];
$customer = null;
$hasExistingOrder = false;
$companionName = trim((string) ($_POST['companion_real_name'] ?? ''));
$companionIdNumber = strtoupper(trim((string) ($_POST['companion_id_number'] ?? '')));

if ($pdo === null) {
    $errors[] = '資料庫連線失敗，請檢查 MySQL 與 includes/db_config.php 設定。';
} elseif (!$showId) {
    $errors[] = '缺少場次資訊，請重新選擇場次與座位。';
} else {
    try {
        $customerStmt = $pdo->prepare(
            'SELECT user_id, username, real_name, id_number
             FROM `User`
             WHERE user_id = ?
               AND role = "customer"
             LIMIT 1'
        );
        $customerStmt->execute([$customerId]);
        $customer = $customerStmt->fetch();

        if (!$customer) {
            unset($_SESSION['customer_id'], $_SESSION['customer_username']);
            header('Location: login.php');
            exit;
        }

        $hasExistingOrder = customerHasActiveOrderForShow($pdo, $customerId, $showId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'apply_promo') {
            $seatIds = array_map('intval', $_POST['seat_ids'] ?? []);
            $seatIds = array_values(array_filter(array_unique($seatIds)));

            if (count($seatIds) < 1 || count($seatIds) > 2) {
                $errors[] = '每筆訂單需選擇 1 到 2 張票。';
            } elseif ($hasExistingOrder) {
                $errors[] = '此會員已在同一場次建立過訂單，不能重複訂購。';
            } elseif ($customer['id_number'] === '') {
                $errors[] = '會員資料缺少身分證字號，請先補齊會員資料。';
            } elseif (ticketHolderExistsForShow($pdo, $showId, $customer['id_number'])) {
                $errors[] = '會員本人已在同一場次建立過票券，不能重複訂購。';
            } elseif (count($seatIds) === 2 && ($companionName === '' || $companionIdNumber === '')) {
                $errors[] = '購買兩張票時，第二位訂購人需填寫真實姓名與身分證字號。';
            } elseif (count($seatIds) === 2 && !preg_match('/^[A-Za-z][A-Za-z0-9]{9}$/', $companionIdNumber)) {
                $errors[] = '第二位訂購人身分證字號首字必須為英文字母，且總長度為 10 個字。';
            } elseif (count($seatIds) === 2 && hash_equals(strtoupper((string) $customer['id_number']), $companionIdNumber)) {
                $errors[] = '第二位訂購人不可與會員本人使用相同身分證字號。';
            } elseif (count($seatIds) === 2 && ticketHolderExistsForShow($pdo, $showId, $companionIdNumber)) {
                $errors[] = '第二位訂購人已在同一場次建立過票券，不能重複訂購。';
            } else {
                $pdo->beginTransaction();

                try {
                    // Older seed data may leave cancelled tickets pointing at seats
                    // already marked available. Remove those stale UNIQUE references
                    // before creating the new active ticket.
                    deleteCancelledTicketReferencesForSeats($pdo, $seatIds);
                    $lockedSeats = fetchSelectedSeatsByIdsForUpdate($pdo, $showId, $seatIds);

                    if (count($lockedSeats) !== count($seatIds)) {
                        throw new RuntimeException('部分座位已被購買，請重新選位。');
                    }

                    $subtotal = array_sum(array_map(function ($seat) {
                        return (int) $seat['price'];
                    }, $lockedSeats));

                    [$promo, $discount, $promoError] = validatePromoCode($pdo, $promoCode, $subtotal);

                    if ($promoError !== null) {
                        throw new RuntimeException($promoError);
                    }

                    $totalPrice = max(0, $subtotal - $discount);
                    $orderStmt = $pdo->prepare(
                        "INSERT INTO Orders (user_id, show_id, promo_id, total_price, status)
                         VALUES (?, ?, ?, ?, 'pending_payment')"
                    );
                    $orderStmt->execute([
                        $customerId,
                        $showId,
                        $promo['promo_id'] ?? null,
                        $totalPrice,
                    ]);
                    $orderId = (int) $pdo->lastInsertId();

                    $ticketStmt = $pdo->prepare(
                        'INSERT INTO Ticket (order_id, seat_id, real_name, id_number)
                         VALUES (?, ?, ?, ?)'
                    );
                    $seatUpdateStmt = $pdo->prepare("UPDATE Seat SET status = 'reserved' WHERE seat_id = ?");

                    foreach ($lockedSeats as $index => $seat) {
                        $ticketName = $index === 0
                            ? ($customer['real_name'] ?: $customer['username'])
                            : $companionName;
                        $ticketIdNumber = $index === 0
                            ? $customer['id_number']
                            : $companionIdNumber;

                        $ticketStmt->execute([
                            $orderId,
                            $seat['seat_id'],
                            $ticketName,
                            $ticketIdNumber,
                        ]);
                        $seatUpdateStmt->execute([$seat['seat_id']]);
                    }

                    $pdo->commit();
                    header('Location: payment.php?order_id=' . $orderId);
                    exit;
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    if ($exception instanceof PDOException
                        && ($exception->getCode() === '23000'
                            || (int) ($exception->errorInfo[1] ?? 0) === 1062)
                    ) {
                        error_log('Checkout duplicate seat failed: ' . $exception->getMessage());
                        $errors[] = '此座位已被其他訂單保留或售出，請重新選擇座位。';
                    } elseif ($exception instanceof PDOException) {
                        error_log('Checkout order failed: ' . $exception->getMessage());
                        $errors[] = '建立訂單時發生錯誤，請稍後再試。';
                    } else {
                        $errors[] = $exception->getMessage();
                    }
                }
            }
        }

        $seatNumbers = selectedSeatNumbersFromRequest();
        $selectedSeats = fetchSelectedSeatsByNumbers($pdo, $showId, $seatNumbers);

        if (!$selectedSeats && $zone !== '') {
            $selectedSeats = fetchFallbackSeatByZone($pdo, $showId, $zone);
        }

        if (!$selectedSeats) {
            $errors[] = '找不到可購買的座位，請重新選位。';
        } elseif (count($selectedSeats) > 2) {
            $errors[] = '每筆訂單最多可購買 2 張票。';
            $selectedSeats = array_slice($selectedSeats, 0, 2);
        }

        if ($hasExistingOrder && !in_array('此會員已在同一場次建立過訂單，不能重複訂購。', $errors, true)) {
            $errors[] = '此會員已在同一場次建立過訂單，不能重複訂購。';
        }

        $subtotal = array_sum(array_map(function ($seat) {
            return (int) $seat['price'];
        }, $selectedSeats));

        [$promo, $discount, $promoError] = validatePromoCode($pdo, $promoCode, $subtotal);

        if ($promoError !== null && $promoCode !== '') {
            $errors[] = $promoError;
        } elseif ($promo !== null) {
            $notice = '優惠碼已套用：' . $promo['code_name'];
        }
    } catch (PDOException $exception) {
        error_log('Checkout page failed: ' . $exception->getMessage());
        $errors[] = '讀取訂票資料失敗，請稍後再試。';
    }
}

$subtotal = array_sum(array_map(function ($seat) {
    return (int) $seat['price'];
}, $selectedSeats));
$totalPrice = max(0, $subtotal - $discount);
$firstSeat = $selectedSeats[0] ?? null;
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>確認訂單 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= h($styleVersion) ?>">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="../index.php" aria-label="ConcertNow 首頁">
            <span class="brand-mark">CN</span>
            <span>ConcertNow</span>
        </a>

        <nav class="main-nav" aria-label="主選單">
            <a href="../index.php#concerts">演唱會列表</a>
            <a href="member.php">會員中心</a>
        </nav>
    </header>

    <main class="member-main">
        <section class="member-hero" aria-labelledby="checkout-title">
            <div>
                <p class="member-kicker">Checkout</p>
                <h1 id="checkout-title">確認訂單</h1>
                <span>確認演唱會資訊、座位與訂購人資料後即可建立訂單並前往付款。</span>
            </div>
        </section>

        <section class="member-panel">
            <?php if ($notice !== ''): ?>
                <p class="auth-success"><?= h($notice) ?></p>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= h($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($firstSeat): ?>
                <div class="member-section-title">
                    <p>Concert Info</p>
                    <h2>演唱會資訊</h2>
                </div>

                <dl class="member-order-info">
                    <div>
                        <dt>活動名稱</dt>
                        <dd><?= h($firstSeat['title']) ?></dd>
                    </div>
                    <div>
                        <dt>場館</dt>
                        <dd><?= h($firstSeat['venue']) ?></dd>
                    </div>
                    <div>
                        <dt>演出時間</dt>
                        <dd><?= h(checkoutDateTimeText($firstSeat['show_datetime'])) ?></dd>
                    </div>
                    <div>
                        <dt>票數</dt>
                        <dd><?= h(count($selectedSeats)) ?> 張</dd>
                    </div>
                    <div>
                        <dt>座位</dt>
                        <dd><?= h(implode('、', array_column($selectedSeats, 'seat_number'))) ?></dd>
                    </div>
                    <div>
                        <dt>原價</dt>
                        <dd><?= h(formatMoney($subtotal)) ?></dd>
                    </div>
                </dl>

                <div class="checkout-info-actions">
                    <a class="secondary-action" href="concert_detail.php?id=<?= h($firstSeat['concert_id']) ?>#show-dates">重新選擇場次</a>
                </div>

                <form id="checkout-order-form" method="post" action="checkout.php?show_id=<?= h($showId) ?>&seats=<?= h(rawurlencode(implode(',', array_column($selectedSeats, 'seat_number')))) ?>&promo_code=<?= h(rawurlencode($promoCode)) ?>">
                    <input type="hidden" name="action" value="place_order">
                    <?php foreach ($selectedSeats as $seat): ?>
                        <input type="hidden" name="seat_ids[]" value="<?= h($seat['seat_id']) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="promo_code" value="<?= h($promoCode) ?>">
                </form>

                <div class="checkout-block">
                    <div class="member-section-title">
                        <p>Ticket Holders</p>
                        <h2>訂購人</h2>
                    </div>

                    <div class="checkout-holder-grid">
                        <section class="checkout-holder-card" aria-label="第一位訂購人">
                            <div class="checkout-holder-head">
                                <span>第 1 張票</span>
                                <strong><?= h($selectedSeats[0]['seat_number']) ?></strong>
                            </div>
                            <dl class="member-order-info checkout-holder-info">
                                <div>
                                    <dt>訂購人</dt>
                                    <dd><?= h($customer['real_name'] ?: $customer['username']) ?></dd>
                                </div>
                                <div>
                                    <dt>身分證字號</dt>
                                    <dd><?= h($customer['id_number']) ?></dd>
                                </div>
                            </dl>
                            <p class="checkout-note">第一位訂購人固定為登入會員，不能更改。</p>
                        </section>

                        <?php if (count($selectedSeats) === 2): ?>
                            <section class="checkout-holder-card" aria-label="第二位訂購人">
                                <div class="checkout-holder-head">
                                    <span>第 2 張票</span>
                                    <strong><?= h($selectedSeats[1]['seat_number']) ?></strong>
                                </div>
                                <div class="checkout-holder-fields">
                                    <label>
                                        <span>真實姓名</span>
                                        <input form="checkout-order-form" type="text" name="companion_real_name" value="<?= h($companionName) ?>" placeholder="請輸入第二位訂購人姓名" required>
                                    </label>
                                    <label>
                                        <span>身分證字號</span>
                                        <input form="checkout-order-form" type="text" name="companion_id_number" value="<?= h($companionIdNumber) ?>" placeholder="A123456789" maxlength="10" pattern="[A-Za-z][A-Za-z0-9]{9}" title="首字元請輸入英文字母，總長度為 10 個字" required>
                                    </label>
                                </div>
                                <p class="checkout-note">第二位訂購人需填寫真實姓名與身分證字號，且同一場次不可重複建立票券。</p>
                            </section>
                        <?php endif; ?>
                    </div>
                </div>

                <form class="auth-form checkout-promo-form" id="checkout-promo-form" method="post" action="checkout.php?show_id=<?= h($showId) ?>&seats=<?= h(rawurlencode(implode(',', array_column($selectedSeats, 'seat_number')))) ?><?= $zone !== '' ? '&zone=' . h(rawurlencode($zone)) : '' ?>">
                    <input type="hidden" name="action" value="apply_promo">
                    <input type="hidden" name="companion_real_name" id="promo-companion-name" value="<?= h($companionName) ?>">
                    <input type="hidden" name="companion_id_number" id="promo-companion-id-number" value="<?= h($companionIdNumber) ?>">

                    <label>
                        <span>優惠碼</span>
                        <input type="text" name="promo_code" value="<?= h($promoCode) ?>" placeholder="WELCOME100">
                    </label>
                    <button class="secondary-action" type="submit">套用優惠碼</button>
                </form>

                <div class="checkout-price-summary" aria-label="金額摘要">
                    <div>
                        <span>原價</span>
                        <strong><?= h(formatMoney($subtotal)) ?></strong>
                    </div>
                    <div>
                        <span>- 折扣金額</span>
                        <strong><?= h(formatMoney($discount)) ?></strong>
                    </div>
                    <div class="checkout-price-total">
                        <span>應付金額</span>
                        <strong><?= h(formatMoney($totalPrice)) ?></strong>
                    </div>
                </div>

                <button class="placeholder-link checkout-pay-button" form="checkout-order-form" type="submit" <?= $errors ? 'disabled' : '' ?>>下一步</button>
            <?php endif; ?>
        </section>
    </main>
    <script>
        const promoForm = document.getElementById('checkout-promo-form');
        const companionNameInput = document.querySelector('[name="companion_real_name"][form="checkout-order-form"]');
        const companionIdInput = document.querySelector('[name="companion_id_number"][form="checkout-order-form"]');
        const promoCompanionName = document.getElementById('promo-companion-name');
        const promoCompanionIdNumber = document.getElementById('promo-companion-id-number');

        promoForm?.addEventListener('submit', () => {
            if (companionNameInput && promoCompanionName) {
                promoCompanionName.value = companionNameInput.value;
            }

            if (companionIdInput && promoCompanionIdNumber) {
                promoCompanionIdNumber.value = companionIdInput.value;
            }
        });
    </script>
</body>
</html>
