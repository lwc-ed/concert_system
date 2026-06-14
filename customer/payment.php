<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/order_expiration.php';

if (!isset($_SESSION['customer_id'])) {
    $redirect = 'payment.php';

    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirect .= '?' . $_SERVER['QUERY_STRING'];
    }

    header('Location: login.php?redirect=' . rawurlencode($redirect));
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function paymentDateTimeText($value)
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('Y/m/d H:i', $timestamp) : (string) $value;
}

function paymentStatusText($status)
{
    $labels = [
        'pending_payment' => '待付款',
        'paid' => '已付款',
        'cancelled' => '已取消',
    ];

    return $labels[$status] ?? (string) $status;
}

function paymentStatusClass($status)
{
    $classes = [
        'pending_payment' => 'is-pending',
        'paid' => 'is-paid',
        'cancelled' => 'is-cancelled',
    ];

    return $classes[$status] ?? 'is-unknown';
}

function fetchPaymentOrder($pdo, $orderId, $customerId)
{
    $stmt = $pdo->prepare(
        'SELECT
            o.order_id,
            o.total_price,
            o.status,
            o.created_at,
            sd.show_datetime,
            c.artist,
            c.title,
            c.venue,
            p.code_name,
            p.discount_amount,
            COUNT(t.ticket_id) AS ticket_count,
            GROUP_CONCAT(s.seat_number ORDER BY s.seat_number SEPARATOR "、") AS seat_numbers
         FROM Orders o
         LEFT JOIN ShowDate sd ON o.show_id = sd.show_id
         LEFT JOIN Concert c ON sd.concert_id = c.concert_id
         LEFT JOIN PromoCode p ON o.promo_id = p.promo_id
         LEFT JOIN Ticket t ON o.order_id = t.order_id
         LEFT JOIN Seat s ON t.seat_id = s.seat_id
         WHERE o.order_id = ?
           AND o.user_id = ?
         GROUP BY
            o.order_id,
            o.total_price,
            o.status,
            o.created_at,
            sd.show_datetime,
            c.artist,
            c.title,
            c.venue,
            p.code_name,
            p.discount_amount
         LIMIT 1'
    );
    $stmt->execute([$orderId, $customerId]);

    return $stmt->fetch();
}

$stylePath = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($stylePath) ? filemtime($stylePath) : time();
$customerId = (int) $_SESSION['customer_id'];
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$order = null;
$errors = [];
$notice = '';

if ($pdo === null) {
    $errors[] = '資料庫連線失敗，請檢查 MySQL 與 includes/db_config.php 設定。';
} elseif (!$orderId) {
    $errors[] = '缺少訂單編號，請回會員中心查看訂單。';
} else {
    try {
        cancelExpiredPendingOrders($pdo, $customerId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo->beginTransaction();

            try {
                $lockStmt = $pdo->prepare(
                    'SELECT order_id, status
                     FROM Orders
                     WHERE order_id = ?
                       AND user_id = ?
                     FOR UPDATE'
                );
                $lockStmt->execute([$orderId, $customerId]);
                $lockedOrder = $lockStmt->fetch();

                if (!$lockedOrder) {
                    throw new RuntimeException('找不到這筆訂單，或這筆訂單不屬於目前登入會員。');
                }

                if ($lockedOrder['status'] === 'cancelled') {
                    throw new RuntimeException('此訂單已取消，無法付款。');
                }

                if ($lockedOrder['status'] === 'pending_payment') {
                    $orderUpdateStmt = $pdo->prepare(
                        "UPDATE Orders
                         SET status = 'paid'
                         WHERE order_id = ?
                           AND status = 'pending_payment'"
                    );
                    $orderUpdateStmt->execute([$orderId]);

                    $seatUpdateStmt = $pdo->prepare(
                        "UPDATE Seat s
                         INNER JOIN Ticket t ON t.seat_id = s.seat_id
                         SET s.status = 'sold'
                         WHERE t.order_id = ?"
                    );
                    $seatUpdateStmt->execute([$orderId]);
                }

                $pdo->commit();
                header('Location: payment.php?order_id=' . $orderId . '&paid=1');
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = $exception->getMessage();
            }
        }

        if (isset($_GET['paid']) && $_GET['paid'] === '1') {
            $notice = '付款成功，訂單狀態已更新為已付款。';
        }

        $order = fetchPaymentOrder($pdo, $orderId, $customerId);

        if (!$order && !$errors) {
            $errors[] = '找不到這筆訂單，或這筆訂單不屬於目前登入會員。';
        }
    } catch (PDOException $exception) {
        $errors[] = '讀取付款資料失敗：' . $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>付款 | ConcertNow</title>
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
        <section class="member-hero" aria-labelledby="payment-title">
            <div>
                <p class="member-kicker">Payment</p>
                <h1 id="payment-title">訂單付款</h1>
                <span>付款成功後，訂單狀態會從待付款更新為已付款。</span>
            </div>
        </section>

        <section class="member-panel">
            <?php if ($notice !== ''): ?>
                <p class="auth-success payment-message"><?= h($notice) ?></p>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= h($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($order): ?>
                <div class="member-section-title">
                    <p>Order #<?= h($order['order_id']) ?></p>
                    <h2><?= h($order['title'] ?? '演唱會資料已移除') ?></h2>
                </div>

                <dl class="member-order-info">
                    <div>
                        <dt>狀態</dt>
                        <dd><span class="member-status <?= h(paymentStatusClass($order['status'])) ?>"><?= h(paymentStatusText($order['status'])) ?></span></dd>
                    </div>
                    <div>
                        <dt>活動名稱</dt>
                        <dd><?= h($order['artist'] ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt>演出時間</dt>
                        <dd><?= h(paymentDateTimeText($order['show_datetime'] ?? null)) ?></dd>
                    </div>
                    <div>
                        <dt>場地</dt>
                        <dd><?= h($order['venue'] ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt>座位</dt>
                        <dd><?= h($order['seat_numbers'] ?: '-') ?></dd>
                    </div>
                    <div>
                        <dt>票數</dt>
                        <dd><?= h((int) $order['ticket_count']) ?> 張</dd>
                    </div>
                    <div>
                        <dt>促銷碼</dt>
                        <dd>
                            <?php if ($order['code_name']): ?>
                                <?= h($order['code_name']) ?>，折抵 NT$<?= h(number_format((int) $order['discount_amount'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt>建立時間</dt>
                        <dd><?= h(paymentDateTimeText($order['created_at'])) ?></dd>
                    </div>
                </dl>

                <div class="payment-total" aria-label="付款金額">
                    <span>應付金額</span>
                    <strong>NT$<?= h(number_format((int) $order['total_price'])) ?></strong>
                </div>

                <?php if ($order['status'] === 'pending_payment'): ?>
                    <?php $paymentSecondsRemaining = orderPaymentSecondsRemaining($order['created_at']); ?>
                    <div class="auth-alert auth-success" data-order-countdown data-order-id="<?= h($order['order_id']) ?>" data-seconds="<?= h($paymentSecondsRemaining) ?>">
                        付款倒數：<strong data-countdown-text><?= h(gmdate('i:s', $paymentSecondsRemaining)) ?></strong>
                    </div>
                    <form method="post" action="payment.php?order_id=<?= h($order['order_id']) ?>" class="payment-card-form">
                        <div class="member-section-title payment-form-title">
                            <p>Credit Card</p>
                            <h2>信用卡付款</h2>
                        </div>

                        <div class="payment-card-grid">
                            <label class="payment-card-full">
                                <span>信用卡卡號</span>
                                <input type="text" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" required>
                            </label>
                            <label>
                                <span>有效期限</span>
                                <input type="text" name="card_expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="MM/YY" required>
                            </label>
                            <label>
                                <span>認證碼</span>
                                <input type="text" name="card_cvv" inputmode="numeric" autocomplete="cc-csc" placeholder="123" required>
                            </label>
                            <label class="payment-card-full">
                                <span>持卡人姓名</span>
                                <input type="text" name="card_name" autocomplete="cc-name" placeholder="請輸入持卡人姓名" required>
                            </label>
                        </div>

                        <p class="checkout-note">此頁為專題模擬付款，不會儲存信用卡資料；送出後訂單會直接更新為已付款。</p>

                        <div class="payment-actions">
                            <button class="placeholder-link payment-submit-button" type="submit">完成付款</button>
                            <a class="secondary-action payment-delay-button" href="order_detail.php?order_id=<?= h($order['order_id']) ?>">延後付款</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="payment-actions">
                        <a class="placeholder-link" href="member.php">回會員中心</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <a class="secondary-action" href="member.php">回會員中心</a>
            <?php endif; ?>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-order-countdown]').forEach((timer) => {
            let seconds = Number.parseInt(timer.dataset.seconds || '0', 10);
            const orderId = timer.dataset.orderId;
            const text = timer.querySelector('[data-countdown-text]');

            function render() {
                const minutes = Math.floor(Math.max(seconds, 0) / 60).toString().padStart(2, '0');
                const remainSeconds = (Math.max(seconds, 0) % 60).toString().padStart(2, '0');
                if (text) {
                    text.textContent = `${minutes}:${remainSeconds}`;
                }
            }

            async function expireOrder() {
                if (!orderId) {
                    window.location.reload();
                    return;
                }

                await fetch('expire_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({order_id: orderId}),
                }).catch(() => null);
                window.location.reload();
            }

            render();
            const intervalId = window.setInterval(() => {
                seconds -= 1;
                render();
                if (seconds <= 0) {
                    window.clearInterval(intervalId);
                    expireOrder();
                }
            }, 1000);
        });
    </script>
</body>
</html>
