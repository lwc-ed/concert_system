<?php
// Sales Report for System Manager.
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

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

function moneyText($value)
{
    return '$' . number_format((float) $value);
}

$errors = [];
$summary = [
    'total_revenue' => 0,
    'paid_orders' => 0,
    'sold_tickets' => 0,
    'cancelled_orders' => 0,
];
$showReports = [];
$ticketStatusReports = [];
$promoReports = [];
$dbReady = $pdo instanceof PDO;

if (!$dbReady) {
    $errors[] = '目前無法連線資料庫，請確認 MySQL 與 includes/db_config.php 設定。';
} else {
    try {
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_price ELSE 0 END), 0) AS total_revenue,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
             FROM Orders"
        );
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row) {
            $summary['total_revenue'] = $row['total_revenue'];
            $summary['paid_orders'] = (int) $row['paid_orders'];
            $summary['cancelled_orders'] = (int) $row['cancelled_orders'];
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS sold_tickets
             FROM Ticket ticket
             INNER JOIN Orders orders_table ON orders_table.order_id = ticket.order_id
             WHERE orders_table.status = 'paid'"
        );
        $stmt->execute();
        $summary['sold_tickets'] = (int) $stmt->fetchColumn();
    } catch (PDOException $exception) {
        error_log('Fetch sales summary failed: ' . $exception->getMessage());
        $errors[] = '讀取銷售總覽失敗，請稍後再試。';
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT
                show_date.show_id,
                show_date.show_datetime,
                show_date.status AS show_status,
                concert.title AS concert_title,
                COUNT(DISTINCT seat.seat_id) AS total_seats,
                COUNT(DISTINCT CASE WHEN orders_table.status = 'paid' THEN ticket.ticket_id END) AS sold_tickets,
                COALESCE(order_summary.paid_orders, 0) AS paid_orders,
                COALESCE(order_summary.revenue, 0) AS revenue
             FROM ShowDate show_date
             INNER JOIN Concert concert ON concert.concert_id = show_date.concert_id
             LEFT JOIN Seat seat ON seat.show_id = show_date.show_id
             LEFT JOIN Ticket ticket ON ticket.seat_id = seat.seat_id
             LEFT JOIN Orders orders_table ON orders_table.order_id = ticket.order_id
             LEFT JOIN (
                SELECT show_id, COUNT(*) AS paid_orders, SUM(total_price) AS revenue
                FROM Orders
                WHERE status = 'paid'
                GROUP BY show_id
             ) order_summary ON order_summary.show_id = show_date.show_id
             GROUP BY
                show_date.show_id,
                show_date.show_datetime,
                show_date.status,
                concert.title,
                order_summary.paid_orders,
                order_summary.revenue
             ORDER BY show_date.show_datetime, show_date.show_id"
        );
        $stmt->execute();
        $showReports = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch show sales report failed: ' . $exception->getMessage());
        $errors[] = '讀取各場次銷售狀況失敗，請稍後再試。';
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT
                show_date.show_id,
                show_date.show_datetime,
                concert.title AS concert_title,
                COUNT(DISTINCT seat.seat_id) AS total_seats,
                COUNT(DISTINCT CASE WHEN seat.status = 'available' THEN seat.seat_id END) AS available_seats,
                COUNT(DISTINCT CASE WHEN seat.status = 'reserved' THEN seat.seat_id END) AS reserved_seats,
                COUNT(DISTINCT CASE WHEN seat.status = 'sold' THEN seat.seat_id END) AS sold_seats,
                COUNT(DISTINCT ticket.ticket_id) AS total_tickets,
                COUNT(DISTINCT CASE WHEN orders_table.status = 'pending_payment' THEN ticket.ticket_id END) AS pending_tickets,
                COUNT(DISTINCT CASE WHEN orders_table.status = 'paid' THEN ticket.ticket_id END) AS paid_tickets,
                COUNT(DISTINCT cancelled_ticket.cancelled_ticket_id) AS cancelled_tickets
             FROM ShowDate show_date
             INNER JOIN Concert concert ON concert.concert_id = show_date.concert_id
             LEFT JOIN Seat seat ON seat.show_id = show_date.show_id
             LEFT JOIN Ticket ticket ON ticket.seat_id = seat.seat_id
             LEFT JOIN Orders orders_table ON orders_table.order_id = ticket.order_id
             LEFT JOIN CancelledTicket cancelled_ticket ON cancelled_ticket.show_id = show_date.show_id
             GROUP BY show_date.show_id, show_date.show_datetime, concert.title
             ORDER BY show_date.show_datetime, show_date.show_id"
        );
        $stmt->execute();
        $ticketStatusReports = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch ticket status report failed: ' . $exception->getMessage());
        $errors[] = '讀取票券狀態報表失敗，請稍後再試。';
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT
                promo.promo_id,
                promo.code_name,
                promo.discount_amount,
                promo.is_active,
                COUNT(orders_table.order_id) AS used_orders,
                COALESCE(SUM(CASE WHEN orders_table.status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_used_orders,
                COALESCE(SUM(CASE WHEN orders_table.status = 'paid' THEN promo.discount_amount ELSE 0 END), 0) AS estimated_discount_total,
                COALESCE(SUM(CASE WHEN orders_table.status = 'paid' THEN orders_table.total_price ELSE 0 END), 0) AS paid_revenue_after_discount
             FROM PromoCode promo
             LEFT JOIN Orders orders_table ON orders_table.promo_id = promo.promo_id
             GROUP BY promo.promo_id, promo.code_name, promo.discount_amount, promo.is_active
             ORDER BY paid_used_orders DESC, promo.code_name"
        );
        $stmt->execute();
        $promoReports = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch promo report failed: ' . $exception->getMessage());
        $errors[] = '讀取折扣碼使用統計失敗，請稍後再試。';
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>銷售報表 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .manager-reports { margin-top: 34px; }
        .reports-panel { width: 100%; max-width: none; }
        .reports-layout { display: grid; gap: 22px; }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }
        .metric-card {
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }
        .metric-card p {
            margin: 0 0 8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }
        .metric-card strong {
            color: var(--ink);
            font-size: 28px;
            line-height: 1.1;
        }
        .report-section { display: grid; gap: 12px; }
        .report-section h3 { margin: 0; font-size: 24px; }
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }
        .report-table {
            width: 100%;
            min-width: 920px;
            border-collapse: collapse;
        }
        .report-table th,
        .report-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }
        .report-table th {
            background: #f8f4ed;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }
        .report-table tr:last-child td { border-bottom: 0; }
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
        }
        .empty-row {
            padding: 22px;
            color: var(--muted);
            font-weight: 800;
            text-align: center;
        }
        @media (max-width: 920px) {
            .metric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 560px) {
            .metric-grid { grid-template-columns: 1fr; }
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
            <a href="/concert_system/manager/orders.php">訂單管理</a>
            <a href="/concert_system/manager/promocodes.php">優惠碼管理</a>
            <a href="/concert_system/manager/seats.php">座位管理</a>
            <a class="login-button" href="/concert_system/manager/logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-reports">
        <div class="section-title">
            <div>
                <p>Sales Report</p>
                <h2>銷售報表</h2>
            </div>
            <a class="secondary-action" href="/concert_system/manager/dashboard.php">返回 Dashboard</a>
        </div>

        <section class="placeholder-card reports-panel">
            <div class="reports-layout">
                <?php if ($errors): ?>
                    <div class="auth-alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?= h($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="metric-grid">
                    <div class="metric-card">
                        <p>總營收</p>
                        <strong><?= h(moneyText($summary['total_revenue'])) ?></strong>
                    </div>
                    <div class="metric-card">
                        <p>已售票數</p>
                        <strong><?= h($summary['sold_tickets']) ?></strong>
                    </div>
                    <div class="metric-card">
                        <p>已付款訂單</p>
                        <strong><?= h($summary['paid_orders']) ?></strong>
                    </div>
                    <div class="metric-card">
                        <p>已取消訂單</p>
                        <strong><?= h($summary['cancelled_orders']) ?></strong>
                    </div>
                </div>

                <div class="report-section">
                    <h3>票券狀態報表</h3>
                    <div class="table-wrap">
                        <?php if ($ticketStatusReports): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>show_id</th>
                                        <th>演唱會</th>
                                        <th>場次時間</th>
                                        <th>總座位數</th>
                                        <th>available</th>
                                        <th>reserved</th>
                                        <th>sold</th>
                                        <th>全部票券</th>
                                        <th>待付款票券</th>
                                        <th>已付款票券</th>
                                        <th>已取消票券</th>
                                        <th>售出率</th>
                                        <th>保留率</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketStatusReports as $ticketStatus): ?>
                                        <?php
                                        $totalSeats = (int) $ticketStatus['total_seats'];
                                        $soldSeats = (int) $ticketStatus['sold_seats'];
                                        $reservedSeats = (int) $ticketStatus['reserved_seats'];
                                        $soldRate = $totalSeats > 0 ? round($soldSeats / $totalSeats * 100, 1) . '%' : '0%';
                                        $reservedRate = $totalSeats > 0 ? round($reservedSeats / $totalSeats * 100, 1) . '%' : '0%';
                                        ?>
                                        <tr>
                                            <td>#<?= h($ticketStatus['show_id']) ?></td>
                                            <td><?= h($ticketStatus['concert_title']) ?></td>
                                            <td><?= h($ticketStatus['show_datetime']) ?></td>
                                            <td><?= h($totalSeats) ?></td>
                                            <td><?= h((int) $ticketStatus['available_seats']) ?></td>
                                            <td><?= h($reservedSeats) ?></td>
                                            <td><?= h($soldSeats) ?></td>
                                            <td><?= h((int) $ticketStatus['total_tickets']) ?></td>
                                            <td><?= h((int) $ticketStatus['pending_tickets']) ?></td>
                                            <td><?= h((int) $ticketStatus['paid_tickets']) ?></td>
                                            <td><?= h((int) $ticketStatus['cancelled_tickets']) ?></td>
                                            <td><?= h($soldRate) ?></td>
                                            <td><?= h($reservedRate) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有票券狀態資料。</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="report-section">
                    <h3>各場次銷售狀況</h3>
                    <div class="table-wrap">
                        <?php if ($showReports): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>show_id</th>
                                        <th>演唱會</th>
                                        <th>場次時間</th>
                                        <th>場次狀態</th>
                                        <th>已售票數</th>
                                        <th>座位總數</th>
                                        <th>售出率</th>
                                        <th>已付款訂單</th>
                                        <th>營收</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($showReports as $show): ?>
                                        <?php
                                        $totalSeats = (int) $show['total_seats'];
                                        $soldTickets = (int) $show['sold_tickets'];
                                        $soldRate = $totalSeats > 0 ? round($soldTickets / $totalSeats * 100, 1) . '%' : '0%';
                                        ?>
                                        <tr>
                                            <td>#<?= h($show['show_id']) ?></td>
                                            <td><?= h($show['concert_title']) ?></td>
                                            <td><?= h($show['show_datetime']) ?></td>
                                            <td><span class="status-pill"><?= h($show['show_status']) ?></span></td>
                                            <td><?= h($soldTickets) ?></td>
                                            <td><?= h($totalSeats) ?></td>
                                            <td><?= h($soldRate) ?></td>
                                            <td><?= h((int) $show['paid_orders']) ?></td>
                                            <td><?= h(moneyText($show['revenue'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有場次資料。</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="report-section">
                    <h3>折扣碼使用統計</h3>
                    <div class="table-wrap">
                        <?php if ($promoReports): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>折扣碼</th>
                                        <th>折扣金額</th>
                                        <th>啟用狀態</th>
                                        <th>使用訂單數</th>
                                        <th>已付款使用數</th>
                                        <th>估計折扣總額</th>
                                        <th>折扣後營收</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($promoReports as $promo): ?>
                                        <tr>
                                            <td><?= h($promo['code_name']) ?></td>
                                            <td><?= h(moneyText($promo['discount_amount'])) ?></td>
                                            <td><?= ((int) $promo['is_active'] === 1) ? '啟用' : '停用' ?></td>
                                            <td><?= h((int) $promo['used_orders']) ?></td>
                                            <td><?= h((int) $promo['paid_used_orders']) ?></td>
                                            <td><?= h(moneyText($promo['estimated_discount_total'])) ?></td>
                                            <td><?= h(moneyText($promo['paid_revenue_after_discount'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有折扣碼資料。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
