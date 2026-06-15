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

function paymentMethodText($method)
{
    $labels = [
        'credit_card' => '信用卡付款',
        'atm_transfer' => 'ATM 虛擬帳號付款',
    ];

    return $labels[$method] ?? '-';
}

function deliveryMethodText($method)
{
    $labels = [
        'ibon' => '7-11 ibon 取票',
        'venue_pickup' => '演出現場票口取票',
    ];

    return $labels[$method] ?? '-';
}

function fetchPaymentOrder($pdo, $orderId, $customerId)
{
    $stmt = $pdo->prepare(
        'SELECT
            o.order_id,
            o.total_price,
            o.status,
            o.payment_method,
            o.delivery_method,
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
            o.payment_method,
            o.delivery_method,
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
$selectedPaymentMethod = (string) ($_POST['payment_method'] ?? 'credit_card');
$selectedDeliveryMethod = (string) ($_POST['delivery_method'] ?? 'ibon');
$paymentFieldValues = [
    'card_number' => trim((string) ($_POST['card_number'] ?? '')),
    'card_expiry' => trim((string) ($_POST['card_expiry'] ?? '')),
    'card_cvv' => trim((string) ($_POST['card_cvv'] ?? '')),
    'card_name' => trim((string) ($_POST['card_name'] ?? '')),
    'atm_bank_code' => trim((string) ($_POST['atm_bank_code'] ?? '')),
    'atm_account_note' => trim((string) ($_POST['atm_account_note'] ?? '')),
];

if ($pdo === null) {
    $errors[] = '資料庫連線失敗，請檢查 MySQL 與 includes/db_config.php 設定。';
} elseif (!$orderId) {
    $errors[] = '缺少訂單編號，請回會員中心查看訂單。';
} else {
    try {
        cancelExpiredPendingOrders($pdo, $customerId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'abandon_order') {
            if (abandonPendingOrderAndReleaseSeats($pdo, $orderId, $customerId)) {
                header('Location: ../index.php');
                exit;
            }

            $errors[] = '此訂單目前無法放棄，請確認訂單是否仍為待付款狀態。';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_payment') {
            $allowedPaymentMethods = ['credit_card', 'atm_transfer'];
            $allowedDeliveryMethods = ['ibon', 'venue_pickup'];

            if (!in_array($selectedPaymentMethod, $allowedPaymentMethods, true)) {
                $errors[] = '請選擇有效的付款方式。';
            }

            if (!in_array($selectedDeliveryMethod, $allowedDeliveryMethods, true)) {
                $errors[] = '請選擇有效的配送方式。';
            }

            $requiredPaymentFields = [
                'credit_card' => [
                    'card_number' => '信用卡卡號',
                    'card_expiry' => '有效期限',
                    'card_cvv' => '認證碼',
                    'card_name' => '持卡人姓名',
                ],
                'atm_transfer' => [
                    'atm_bank_code' => '銀行代碼',
                    'atm_account_note' => '轉帳帳號或備註',
                ],
            ];

            foreach ($requiredPaymentFields[$selectedPaymentMethod] ?? [] as $fieldName => $fieldLabel) {
                if ($paymentFieldValues[$fieldName] === '') {
                    $errors[] = '請輸入' . $fieldLabel . '。';
                }
            }

            if (!$errors) {
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

                    if ($lockedOrder['status'] !== 'pending_payment') {
                        throw new RuntimeException('此訂單已取消或完成付款，無法再次付款。');
                    }

                    $orderUpdateStmt = $pdo->prepare(
                        "UPDATE Orders
                         SET status = 'paid',
                             payment_method = ?,
                             delivery_method = ?
                         WHERE order_id = ?
                           AND status = 'pending_payment'"
                    );
                    $orderUpdateStmt->execute([
                        $selectedPaymentMethod,
                        $selectedDeliveryMethod,
                        $orderId,
                    ]);

                    if ($orderUpdateStmt->rowCount() !== 1) {
                        throw new RuntimeException('訂單狀態已變更，無法完成付款。');
                    }

                    $seatUpdateStmt = $pdo->prepare(
                        "UPDATE Seat s
                         INNER JOIN Ticket t ON t.seat_id = s.seat_id
                         SET s.status = 'sold'
                         WHERE t.order_id = ?"
                    );
                    $seatUpdateStmt->execute([$orderId]);

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
        }

        if (isset($_GET['paid']) && $_GET['paid'] === '1') {
            $notice = '付款成功，訂單狀態已更新為已付款。';
        }

        $order = fetchPaymentOrder($pdo, $orderId, $customerId);

        if (!$order && !$errors) {
            $errors[] = '找不到這筆訂單，或這筆訂單不屬於目前登入會員。';
        } elseif ($order
            && $_SERVER['REQUEST_METHOD'] !== 'POST'
            && in_array($order['delivery_method'], ['ibon', 'venue_pickup'], true)
        ) {
            $selectedDeliveryMethod = $order['delivery_method'];
        }
    } catch (Throwable $exception) {
        error_log('Payment action failed: ' . $exception->getMessage());
        $errors[] = '處理付款資料時發生錯誤，請稍後再試。';
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
                    <?php if ($order['status'] === 'paid'): ?>
                        <div>
                            <dt>付款方式</dt>
                            <dd><?= h(paymentMethodText($order['payment_method'])) ?></dd>
                        </div>
                        <div>
                            <dt>取票方式</dt>
                            <dd><?= h(deliveryMethodText($order['delivery_method'])) ?></dd>
                        </div>
                    <?php endif; ?>
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
                    <form method="post" action="payment.php?order_id=<?= h($order['order_id']) ?>" class="payment-choice-form">
                        <input type="hidden" name="action" value="complete_payment">
                        <fieldset class="payment-choice-group">
                            <legend>付款方式</legend>

                            <label class="payment-choice-card">
                                <span class="payment-choice-title">
                                    <input type="radio" name="payment_method" value="credit_card" <?= $selectedPaymentMethod === 'credit_card' ? 'checked' : '' ?>>
                                    <strong>信用卡付款（Credit Card）</strong>
                                </span>
                                <span class="payment-choice-description">
                                    支援 VISA、Mastercard、JCB 與銀聯卡。本頁為專題模擬付款，輸入資料不會被儲存。
                                </span>
                                <span class="payment-method-fields" data-payment-fields="credit_card">
                                    <input type="text" name="card_number" value="<?= h($paymentFieldValues['card_number']) ?>" placeholder="信用卡卡號" data-required-payment-field>
                                    <input type="text" name="card_expiry" value="<?= h($paymentFieldValues['card_expiry']) ?>" placeholder="有效期限 MM/YY" data-required-payment-field>
                                    <input type="text" name="card_cvv" value="<?= h($paymentFieldValues['card_cvv']) ?>" placeholder="認證碼" data-required-payment-field>
                                    <input type="text" name="card_name" value="<?= h($paymentFieldValues['card_name']) ?>" placeholder="持卡人姓名" data-required-payment-field>
                                </span>
                            </label>

                            <label class="payment-choice-card">
                                <span class="payment-choice-title">
                                    <input type="radio" name="payment_method" value="atm_transfer" <?= $selectedPaymentMethod === 'atm_transfer' ? 'checked' : '' ?>>
                                    <strong>ATM 虛擬帳號付款（ATM Funds Transfer）</strong>
                                </span>
                                <span class="payment-choice-description">
                                    系統將產生專屬虛擬帳號。請於期限內完成轉帳，逾期未付款時訂單可能被取消。
                                </span>
                                <span class="payment-method-fields" data-payment-fields="atm_transfer">
                                    <input type="text" name="atm_bank_code" value="<?= h($paymentFieldValues['atm_bank_code']) ?>" placeholder="銀行代碼，例如 822" data-required-payment-field>
                                    <input type="text" name="atm_account_note" value="<?= h($paymentFieldValues['atm_account_note']) ?>" placeholder="轉帳帳號或備註" data-required-payment-field>
                                </span>
                            </label>
                        </fieldset>

                        <fieldset class="payment-choice-group">
                            <legend>配送方式</legend>

                            <label class="payment-choice-card">
                                <span class="payment-choice-title">
                                    <input type="radio" name="delivery_method" value="ibon" <?= $selectedDeliveryMethod === 'ibon' ? 'checked' : '' ?>>
                                    <strong>7-11 ibon 取票（Ticket Collection - ibon）</strong>
                                </span>
                                <span class="payment-choice-description">
                                    付款完成後可至全台 7-11 門市 ibon 機台列印取票繳費單，再至櫃檯領取票券。每筆訂單酌收取票手續費。
                                </span>
                            </label>

                            <label class="payment-choice-card">
                                <span class="payment-choice-title">
                                    <input type="radio" name="delivery_method" value="venue_pickup" <?= $selectedDeliveryMethod === 'venue_pickup' ? 'checked' : '' ?>>
                                    <strong>演出現場票口取票（Venue Pickup）</strong>
                                </span>
                                <span class="payment-choice-description">
                                    請於演出當日提早至場館票口，出示訂單編號、會員證件與訂票人身分證件後領取票券。
                                </span>
                            </label>
                        </fieldset>

                        <p class="checkout-note">此頁為專題模擬付款，兩種付款方式皆不會連接真實金流或儲存付款資料。</p>

                        <div class="payment-actions">
                            <button class="placeholder-link payment-submit-button" type="submit">完成付款</button>
                            <a class="secondary-action payment-delay-button" href="order_detail.php?order_id=<?= h($order['order_id']) ?>">延後付款</a>
                            <button class="secondary-action danger-action payment-abandon-button" type="submit" form="abandon-order-form">放棄購票</button>
                        </div>
                    </form>
                    <form id="abandon-order-form" method="post" action="payment.php?order_id=<?= h($order['order_id']) ?>" onsubmit="return confirm('確定要放棄購票嗎？訂單與訂票資料將永久刪除。');">
                        <input type="hidden" name="action" value="abandon_order">
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
        const paymentMethodInputs = document.querySelectorAll('input[name="payment_method"]');
        const paymentMethodFields = document.querySelectorAll('[data-payment-fields]');
        const paymentForm = document.querySelector('.payment-choice-form');

        function updatePaymentMethodFields() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;

            paymentMethodFields.forEach((fields) => {
                const isSelected = fields.dataset.paymentFields === selectedMethod;
                fields.hidden = !isSelected;
                fields.querySelectorAll('[data-required-payment-field]').forEach((input) => {
                    input.disabled = !isSelected;
                    input.required = isSelected;
                    input.setCustomValidity('');
                });
            });
        }

        paymentMethodInputs.forEach((input) => input.addEventListener('change', updatePaymentMethodFields));
        paymentForm?.addEventListener('submit', (event) => {
            const selectedFields = document.querySelector('[data-payment-fields]:not([hidden])');
            let firstBlankInput = null;

            selectedFields?.querySelectorAll('[data-required-payment-field]').forEach((input) => {
                const isBlank = input.value.trim() === '';
                input.setCustomValidity(isBlank ? '此欄位不能空白。' : '');
                firstBlankInput ??= isBlank ? input : null;
            });

            if (firstBlankInput) {
                event.preventDefault();
                firstBlankInput.reportValidity();
            }
        });
        updatePaymentMethodFields();
    </script>
</body>
</html>
