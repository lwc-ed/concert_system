<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/order_expiration.php';

if (!isset($_SESSION['customer_id'])) {
    $redirect = 'order_detail.php';

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

function orderDetailDateTimeText($value)
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('Y/m/d H:i', $timestamp) : (string) $value;
}

function orderDetailStatusText($status)
{
    $labels = [
        'pending_payment' => '待付款',
        'paid' => '已付款',
        'cancelled' => '已取消',
    ];

    return $labels[$status] ?? (string) $status;
}

function orderDetailStatusClass($status)
{
    $classes = [
        'pending_payment' => 'is-pending',
        'paid' => 'is-paid',
        'cancelled' => 'is-cancelled',
    ];

    return $classes[$status] ?? 'is-unknown';
}

function orderDetailSeatStatusText($status)
{
    $labels = [
        'available' => '可購買',
        'reserved' => '保留中',
        'sold' => '已售出',
    ];

    return $labels[$status] ?? (string) $status;
}

function orderDetailPaymentMethodText($method)
{
    $labels = [
        'credit_card' => '信用卡付款',
        'atm_transfer' => 'ATM 虛擬帳號付款',
    ];

    return $labels[$method] ?? '-';
}

function orderDetailDeliveryMethodText($method)
{
    $labels = [
        'ibon' => '7-11 ibon 取票',
        'venue_pickup' => '演出現場票口取票',
    ];

    return $labels[$method] ?? '-';
}

function fetchOrderDetail($pdo, $orderId, $customerId)
{
    $stmt = $pdo->prepare(
        'SELECT
            o.order_id,
            o.total_price,
            o.status,
            o.payment_method,
            o.delivery_method,
            o.created_at,
            sd.show_id,
            sd.show_datetime,
            c.concert_id,
            c.artist,
            c.title,
            c.venue,
            c.concert_address,
            p.code_name,
            p.discount_amount
         FROM Orders o
         LEFT JOIN ShowDate sd ON o.show_id = sd.show_id
         LEFT JOIN Concert c ON sd.concert_id = c.concert_id
         LEFT JOIN PromoCode p ON o.promo_id = p.promo_id
         WHERE o.order_id = ?
           AND o.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$orderId, $customerId]);

    return $stmt->fetch();
}

function fetchOrderTickets($pdo, $orderId)
{
    $stmt = $pdo->prepare(
        'SELECT
            t.ticket_id,
            t.real_name,
            t.id_number,
            s.seat_number,
            s.price,
            s.status AS seat_status
         FROM Ticket t
         INNER JOIN Seat s ON t.seat_id = s.seat_id
         WHERE t.order_id = ?
         ORDER BY t.ticket_id'
    );
    $stmt->execute([$orderId]);

    return $stmt->fetchAll();
}

$stylePath = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($stylePath) ? filemtime($stylePath) : time();
$customerId = (int) $_SESSION['customer_id'];
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$order = null;
$tickets = [];
$errors = [];
$notice = '';

if ($pdo === null) {
    $errors[] = '資料庫連線失敗，請檢查 MySQL 與 includes/db_config.php 設定。';
} elseif (!$orderId) {
    $errors[] = '缺少訂單編號，請回會員中心查看訂單。';
} else {
    try {
        cancelExpiredPendingOrders($pdo, $customerId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
            if (cancelOrderAndReleaseSeats($pdo, $orderId, $customerId)) {
                header('Location: order_detail.php?order_id=' . $orderId . '&cancelled=1');
                exit;
            }

            $errors[] = '此訂單目前無法取消，請確認訂單是否仍為可取消狀態。';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_delivery_method') {
            $deliveryMethod = (string) ($_POST['delivery_method'] ?? '');
            $allowedDeliveryMethods = ['ibon', 'venue_pickup'];
            $editableOrder = fetchOrderDetail($pdo, $orderId, $customerId);

            if (!in_array($deliveryMethod, $allowedDeliveryMethods, true)) {
                $errors[] = '請選擇有效的取票方式。';
            } elseif (!$editableOrder || $editableOrder['status'] === 'cancelled') {
                $errors[] = '此訂單目前無法修改，請確認訂單是否存在或已取消。';
            } else {
                $updateStmt = $pdo->prepare(
                    'UPDATE Orders
                     SET delivery_method = ?
                     WHERE order_id = ?
                       AND user_id = ?
                       AND status <> \'cancelled\''
                );
                $updateStmt->execute([$deliveryMethod, $orderId, $customerId]);

                $updatedOrder = fetchOrderDetail($pdo, $orderId, $customerId);

                if ($updatedOrder
                    && $updatedOrder['status'] !== 'cancelled'
                    && $updatedOrder['delivery_method'] === $deliveryMethod
                ) {
                    header('Location: order_detail.php?order_id=' . $orderId . '&updated=1');
                    exit;
                }

                $errors[] = '此訂單目前無法修改，請確認訂單是否存在或已取消。';
            }
        }

        if (isset($_GET['cancelled']) && $_GET['cancelled'] === '1') {
            $notice = '訂單已取消，您可以重新訂購同一場次。';
        } elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
            $notice = '取票方式已更新。';
        }

        $order = fetchOrderDetail($pdo, $orderId, $customerId);

        if (!$order) {
            $errors[] = '找不到這筆訂單，或這筆訂單不屬於目前登入會員。';
        } else {
            $tickets = fetchOrderTickets($pdo, $orderId);
        }
    } catch (Throwable $exception) {
        error_log('Order detail action failed: ' . $exception->getMessage());
        $errors[] = '處理訂單時發生錯誤，請稍後再試。';
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>訂單詳情 | ConcertNow</title>
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
        <section class="member-hero" aria-labelledby="order-detail-title">
            <div>
                <p class="member-kicker">Order Detail</p>
                <h1 id="order-detail-title">訂單詳情</h1>
                <span>查看訂票人、座位資訊與演唱會基本資料。</span>
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
                <a class="secondary-action" href="member.php">回會員中心</a>
            <?php elseif ($order): ?>
                <div class="member-section-title">
                    <p>Order #<?= h($order['order_id']) ?></p>
                    <h2><?= h($order['title'] ?? '演唱會資料已移除') ?></h2>
                </div>

                <dl class="member-order-info">
                    <div>
                        <dt>活動名稱</dt>
                        <dd><?= h($order['artist'] ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt>場館</dt>
                        <dd><?= h($order['venue'] ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt>場館地址</dt>
                        <dd><?= h($order['concert_address'] ?? '-') ?></dd>
                    </div>
                    <div>
                        <dt>演出時間</dt>
                        <dd><?= h(orderDetailDateTimeText($order['show_datetime'] ?? null)) ?></dd>
                    </div>
                    <div>
                        <dt>訂單狀態</dt>
                        <dd><span class="member-status <?= h(orderDetailStatusClass($order['status'])) ?>"><?= h(orderDetailStatusText($order['status'])) ?></span></dd>
                    </div>
                    <div>
                        <dt>票數</dt>
                        <dd><?= h(count($tickets)) ?> 張</dd>
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
                        <dt>訂單金額</dt>
                        <dd>NT$<?= h(number_format((int) $order['total_price'])) ?></dd>
                    </div>
                    <div>
                        <dt>建立時間</dt>
                        <dd><?= h(orderDetailDateTimeText($order['created_at'])) ?></dd>
                    </div>
                    <div>
                        <dt>付款方式</dt>
                        <dd><?= h(orderDetailPaymentMethodText($order['payment_method'])) ?></dd>
                    </div>
                    <div>
                        <dt>取票方式</dt>
                        <dd><?= h(orderDetailDeliveryMethodText($order['delivery_method'])) ?></dd>
                    </div>
                </dl>

                <div class="checkout-block">
                    <div class="member-section-title">
                        <p>Tickets</p>
                        <h2>訂票人與座位資訊</h2>
                    </div>

                    <?php if (!$tickets): ?>
                        <p class="member-empty">此訂單目前沒有票券資料。</p>
                    <?php else: ?>
                        <div class="order-ticket-list">
                            <?php foreach ($tickets as $index => $ticket): ?>
                                <article class="checkout-holder-card">
                                    <div class="checkout-holder-head">
                                        <span>第 <?= h($index + 1) ?> 張票</span>
                                        <strong><?= h($ticket['seat_number']) ?></strong>
                                    </div>
                                    <dl class="member-order-info checkout-holder-info">
                                        <div>
                                            <dt>訂票人</dt>
                                            <dd><?= h($ticket['real_name']) ?></dd>
                                        </div>
                                        <div>
                                            <dt>身分證字號</dt>
                                            <dd><?= h($ticket['id_number']) ?></dd>
                                        </div>
                                        <div>
                                            <dt>票價</dt>
                                            <dd>NT$<?= h(number_format((int) $ticket['price'])) ?></dd>
                                        </div>
                                        <div>
                                            <dt>座位狀態</dt>
                                            <dd><?= h(orderDetailSeatStatusText($ticket['seat_status'])) ?></dd>
                                        </div>
                                    </dl>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($order['status'] === 'pending_payment'): ?>
                    <?php $orderSecondsRemaining = orderPaymentSecondsRemaining($order['created_at']); ?>
                    <div class="auth-alert auth-success" data-order-countdown data-order-id="<?= h($order['order_id']) ?>" data-seconds="<?= h($orderSecondsRemaining) ?>">
                        付款倒數：<strong data-countdown-text><?= h(gmdate('i:s', $orderSecondsRemaining)) ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($order['status'] !== 'cancelled'): ?>
                    <form method="post" action="order_detail.php?order_id=<?= h($order['order_id']) ?>" class="order-edit-form" data-order-edit-form hidden>
                        <input type="hidden" name="action" value="update_delivery_method">
                        <fieldset class="payment-choice-group">
                            <legend>修改取票方式</legend>

                            <label class="payment-choice-card">
                                <span class="payment-choice-title">
                                    <input type="radio" name="delivery_method" value="ibon" <?= $order['delivery_method'] === 'ibon' || !$order['delivery_method'] ? 'checked' : '' ?>>
                                    <strong>7-11 ibon 取票</strong>
                                </span>
                            </label>

                            <label class="payment-choice-card">
                                <span class="payment-choice-title">
                                    <input type="radio" name="delivery_method" value="venue_pickup" <?= $order['delivery_method'] === 'venue_pickup' ? 'checked' : '' ?>>
                                    <strong>演出現場票口取票</strong>
                                </span>
                            </label>
                        </fieldset>

                        <div class="member-order-actions">
                            <button class="secondary-action" type="button" data-order-edit-cancel>取消修改</button>
                            <button class="placeholder-link" type="submit">儲存取票方式</button>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="member-order-actions">
                    <a class="secondary-action" href="member.php">回會員中心</a>
                    <?php if ($order['status'] === 'pending_payment'): ?>
                        <a class="placeholder-link" href="payment.php?order_id=<?= h($order['order_id']) ?>">前往付款</a>
                    <?php endif; ?>
                    <?php if ($order['status'] !== 'cancelled'): ?>
                        <button class="secondary-action" type="button" data-order-edit-toggle>修改訂單</button>
                        <form method="post" action="order_detail.php?order_id=<?= h($order['order_id']) ?>" class="inline-action-form" onsubmit="return confirm('確定要取消此訂單嗎？');">
                            <input type="hidden" name="action" value="cancel_order">
                            <button class="secondary-action danger-action" type="submit">取消訂票</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <script>
        const orderEditForm = document.querySelector('[data-order-edit-form]');
        const orderEditToggle = document.querySelector('[data-order-edit-toggle]');
        const orderEditCancel = document.querySelector('[data-order-edit-cancel]');

        function setOrderEditFormVisible(isVisible) {
            if (!orderEditForm || !orderEditToggle) {
                return;
            }

            orderEditForm.hidden = !isVisible;
            orderEditToggle.hidden = isVisible;

            if (isVisible) {
                orderEditForm.querySelector('input[name="delivery_method"]:checked')?.focus();
            }
        }

        orderEditToggle?.addEventListener('click', () => setOrderEditFormVisible(true));
        orderEditCancel?.addEventListener('click', () => setOrderEditFormVisible(false));

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
