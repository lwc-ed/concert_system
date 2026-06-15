<?php
// Order Management: list orders, inspect ticket details, cancel unpaid orders,
// and auto-cancel pending orders older than 10 minutes when this page loads.
$authGuardPath = __DIR__ . '/../includes/auth_guard.php';
if (file_exists($authGuardPath)) {
    require_once $authGuardPath;
    if (function_exists('require_role')) {
        require_role('manager');
    }
} else {
    require_once __DIR__ . '/../includes/manager_auth.php';
    requireManager();
}

require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/order_expiration.php';

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function positiveInt($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
}

$errors = [];
$notice = '';
$orders = [];
$selectedOrder = null;
$tickets = [];
$dbReady = $pdo instanceof PDO;
$selectedOrderId = isset($_GET['order_id']) ? positiveInt($_GET['order_id']) : null;

$statusLabels = [
    'pending_payment' => '待付款',
    'paid' => '已付款',
    'cancelled' => '已取消',
];

if (isset($_GET['order_id']) && !$selectedOrderId) {
    $errors[] = '訂單編號不正確。';
}

if (isset($_GET['message']) && $_GET['message'] === 'cancelled') {
    $notice = '未付款訂單已取消，相關保留座位已釋放。';
}

if (!$dbReady) {
    $errors[] = '目前無法連線資料庫，請確認 MySQL 與 includes/db_config.php 設定。';
} else {
    try {
        $autoCancelledCount = cancelExpiredPendingOrders($pdo);

        if ($autoCancelledCount > 0 && $notice === '') {
            $notice = '系統已自動取消超過 10 分鐘未付款的訂單 ' . $autoCancelledCount . ' 筆，並釋放相關座位。';
        }
    } catch (Throwable $exception) {
        error_log('Auto-cancel expired orders failed: ' . $exception->getMessage());
        $errors[] = '自動取消逾時訂單失敗，請稍後再試。';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'cancel_unpaid') {
            $orderId = positiveInt($_POST['order_id'] ?? null);

            if (!$orderId) {
                $errors[] = '找不到要取消的訂單。';
            } else {
                try {
                    if (cancelPendingOrderAndReleaseSeats($pdo, $orderId)) {
                        header('Location: /concert_system/manager/orders.php?message=cancelled');
                        exit;
                    }

                    $errors[] = '只有 pending_payment 狀態的訂單可以取消。';
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('Cancel unpaid order failed: ' . $exception->getMessage());
                    $errors[] = '取消訂單失敗，請稍後再試。';
                }
            }
        } else {
            $errors[] = '不支援的操作。';
        }
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT o.order_id,
                    o.user_id,
                    o.show_id,
                    o.total_price,
                    o.status,
                    o.created_at,
                    u.username,
                    u.real_name AS buyer_real_name,
                    u.email,
                    c.title AS concert_title,
                    s.show_datetime
             FROM Orders o
             LEFT JOIN User u ON u.user_id = o.user_id
             LEFT JOIN ShowDate s ON s.show_id = o.show_id
             LEFT JOIN Concert c ON c.concert_id = s.concert_id
             ORDER BY o.created_at DESC, o.order_id DESC'
        );
        $stmt->execute();
        $orders = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch orders failed: ' . $exception->getMessage());
        $errors[] = '讀取訂單列表失敗，請稍後再試。';
    }

    if ($selectedOrderId) {
        try {
            $stmt = $pdo->prepare(
                'SELECT o.order_id,
                        o.user_id,
                        o.show_id,
                        o.total_price,
                        o.status,
                        o.created_at,
                        u.username,
                        u.real_name AS buyer_real_name,
                        u.email,
                        c.title AS concert_title,
                        s.show_datetime
                 FROM Orders o
                 LEFT JOIN User u ON u.user_id = o.user_id
                 LEFT JOIN ShowDate s ON s.show_id = o.show_id
                 LEFT JOIN Concert c ON c.concert_id = s.concert_id
                 WHERE o.order_id = :order_id'
            );
            $stmt->execute([':order_id' => $selectedOrderId]);
            $selectedOrder = $stmt->fetch();

            if (!$selectedOrder) {
                $errors[] = '找不到指定的訂單。';
            }
        } catch (PDOException $exception) {
            error_log('Fetch order detail failed: ' . $exception->getMessage());
            $errors[] = '讀取訂單明細失敗，請稍後再試。';
        }

        if ($selectedOrder) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT t.ticket_id,
                            t.order_id,
                            t.real_name,
                            t.id_number,
                            seat.seat_id,
                            seat.seat_number,
                            seat.price,
                            seat.status AS seat_status
                     FROM Ticket t
                     LEFT JOIN Seat seat ON seat.seat_id = t.seat_id
                     WHERE t.order_id = :order_id
                     ORDER BY seat.seat_number, t.ticket_id'
                );
                $stmt->execute([':order_id' => $selectedOrderId]);
                $tickets = $stmt->fetchAll();
            } catch (PDOException $exception) {
                error_log('Fetch order tickets failed: ' . $exception->getMessage());
                $errors[] = '讀取票券明細失敗，請稍後再試。';
            }
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>訂單管理 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .manager-orders { margin-top: 34px; }
        .orders-panel { width: 100%; max-width: none; }
        .orders-layout { display: grid; gap: 22px; }

        .orders-summary,
        .detail-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            color: var(--muted);
            font-weight: 800;
        }

        .detail-card {
            display: grid;
            gap: 16px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .detail-card h3 {
            margin: 0;
            color: var(--ink);
            font-size: 24px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: hidden;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .orders-table,
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .orders-table th,
        .orders-table td,
        .tickets-table th,
        .tickets-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .orders-table th,
        .tickets-table th {
            background: #f8f4ed;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }

        .orders-table tr:last-child td,
        .tickets-table tr:last-child td {
            border-bottom: 0;
        }

        .orders-table th:nth-child(1),
        .orders-table td:nth-child(1) { width: 76px; }
        .orders-table th:nth-child(2),
        .orders-table td:nth-child(2) { width: 18%; }
        .orders-table th:nth-child(3),
        .orders-table td:nth-child(3) { width: 22%; }
        .orders-table th:nth-child(4),
        .orders-table td:nth-child(4) { width: 150px; }
        .orders-table th:nth-child(5),
        .orders-table td:nth-child(5) { width: 105px; }
        .orders-table th:nth-child(6),
        .orders-table td:nth-child(6) { width: 160px; }
        .orders-table th:nth-child(7),
        .orders-table td:nth-child(7) { width: 150px; }
        .orders-table th:nth-child(8),
        .orders-table td:nth-child(8) { width: 132px; }

        .buyer-cell { display: grid; gap: 2px; }
        .buyer-cell span { color: var(--muted); font-size: 13px; font-weight: 700; }

        .status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 10px;
            border-radius: 8px;
            background: #efe0c7;
            color: #80520e;
            font-size: 13px;
            font-weight: 900;
            white-space: normal;
        }

        .status-pill.is-paid { background: #dff3e8; color: #245b38; }
        .status-pill.is-pending_payment { background: #fff2cc; color: #80520e; }
        .status-pill.is-cancelled { background: #f0e4e4; color: #8f2323; }

        .row-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: stretch;
        }

        .row-actions .secondary-action,
        .row-actions .danger-action {
            width: 100%;
            min-height: 38px;
            padding: 0 10px;
            font-size: 13px;
        }

        .danger-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 0;
            border-radius: 8px;
            background: #7f1d1d;
            color: #fff;
            font-weight: 800;
            cursor: pointer;
        }

        .danger-action:hover { background: #631717; }
        .empty-row { padding: 22px; color: var(--muted); font-weight: 800; text-align: center; }

        @media (max-width: 980px) {
            .table-wrap {
                overflow-x: auto;
            }

            .orders-table,
            .tickets-table {
                min-width: 900px;
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
            <a href="/concert_system/manager/dashboard.php">Dashboard</a>
            <a href="/concert_system/manager/concerts.php">演唱會管理</a>
            <a href="/concert_system/manager/shows.php">場次管理</a>
            <a href="/concert_system/manager/seats.php">座位管理</a>
            <a href="/concert_system/manager/promocodes.php">優惠碼管理</a>
            <a href="/concert_system/manager/change_password.php">修改密碼</a>
            <a class="login-button" href="/concert_system/manager/logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-orders">
        <div class="section-title">
            <div>
                <p>Order Management</p>
                <h2>訂單管理</h2>
            </div>
            <a class="secondary-action" href="/concert_system/manager/dashboard.php">返回 Dashboard</a>
        </div>

        <section class="placeholder-card orders-panel">
            <div class="orders-layout">
                <?php if ($notice !== ''): ?>
                    <div class="auth-success"><?= h($notice) ?></div>
                <?php endif; ?>

                <?php if ($errors): ?>
                    <div class="auth-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?= h($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($selectedOrder): ?>
                    <div class="detail-card">
                        <div>
                            <h3>訂單明細 #<?= h($selectedOrder['order_id']) ?></h3>
                            <div class="detail-meta">
                                <span>購買者：<?= h($selectedOrder['buyer_real_name'] ?: $selectedOrder['username'] ?: '未知會員') ?></span>
                                <span>Email：<?= h($selectedOrder['email'] ?: '無 email') ?></span>
                                <span>演唱會：<?= h($selectedOrder['concert_title'] ?: '未找到演唱會') ?></span>
                                <span>場次：<?= h($selectedOrder['show_datetime'] ?: '未找到場次') ?></span>
                                <span>金額：$<?= h(number_format((float) $selectedOrder['total_price'])) ?></span>
                                <span>建立時間：<?= h($selectedOrder['created_at']) ?></span>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <?php if ($tickets): ?>
                                <table class="tickets-table">
                                    <thead>
                                        <tr>
                                            <th>票券編號</th>
                                            <th>座位編號</th>
                                            <th>票價</th>
                                            <th>座位狀態</th>
                                            <th>實名制姓名</th>
                                            <th>證件資料</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td>#<?= h($ticket['ticket_id']) ?></td>
                                                <td><?= h($ticket['seat_number'] ?: '未找到座位') ?></td>
                                                <td>$<?= h(number_format((float) $ticket['price'])) ?></td>
                                                <td><?= h($ticket['seat_status'] ?: '未知') ?></td>
                                                <td><?= h($ticket['real_name']) ?></td>
                                                <td><?= h($ticket['id_number']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-row">此訂單目前沒有票券明細。</div>
                            <?php endif; ?>
                        </div>

                        <div class="row-actions">
                            <a class="secondary-action" href="/concert_system/manager/orders.php">收起明細</a>
                            <?php if ($selectedOrder['status'] === 'pending_payment'): ?>
                                <form method="post" action="/concert_system/manager/orders.php" onsubmit="return confirm('確定要取消此未付款訂單嗎？');">
                                    <input type="hidden" name="action" value="cancel_unpaid">
                                    <input type="hidden" name="order_id" value="<?= h($selectedOrder['order_id']) ?>">
                                    <button class="danger-action" type="submit">取消未付款訂單</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="orders-summary">
                        <span>共 <?= h(count($orders)) ?> 筆訂單</span>
                    </div>

                    <div class="table-wrap">
                        <?php if ($orders): ?>
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>訂單編號</th>
                                        <th>購買者</th>
                                        <th>演唱會</th>
                                        <th>場次</th>
                                        <th>金額</th>
                                        <th>狀態</th>
                                        <th>建立時間</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?= h($order['order_id']) ?></td>
                                            <td>
                                                <div class="buyer-cell">
                                                    <strong><?= h($order['buyer_real_name'] ?: $order['username'] ?: '未知會員') ?></strong>
                                                    <span><?= h($order['email'] ?: '無 email') ?></span>
                                                </div>
                                            </td>
                                            <td><?= h($order['concert_title'] ?: '未找到演唱會') ?></td>
                                            <td><?= h($order['show_datetime'] ?: '未找到場次') ?></td>
                                            <td>$<?= h(number_format((float) $order['total_price'])) ?></td>
                                            <td>
                                                <span class="status-pill is-<?= h($order['status']) ?>">
                                                    <?= h($order['status']) ?> - <?= h($statusLabels[$order['status']] ?? $order['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= h($order['created_at']) ?></td>
                                            <td>
                                                <div class="row-actions">
                                                    <a class="secondary-action" href="/concert_system/manager/orders.php?order_id=<?= h($order['order_id']) ?>">
                                                        查看明細
                                                    </a>
                                                    <?php if ($order['status'] === 'pending_payment'): ?>
                                                        <form method="post" action="/concert_system/manager/orders.php" onsubmit="return confirm('確定要取消此未付款訂單嗎？');">
                                                            <input type="hidden" name="action" value="cancel_unpaid">
                                                            <input type="hidden" name="order_id" value="<?= h($order['order_id']) ?>">
                                                            <button class="danger-action" type="submit">取消未付款</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有訂單資料。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
