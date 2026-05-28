<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php?redirect=member.php');
    exit;
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function memberDateTimeText($value) {
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('Y/m/d H:i', $timestamp) : (string) $value;
}

function orderStatusText($status) {
    $labels = [
        'pending_payment' => '待付款',
        'paid' => '已付款',
        'cancelled' => '已取消',
    ];

    return $labels[$status] ?? (string) $status;
}

$stylePath = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($stylePath) ? filemtime($stylePath) : time();
$customerId = (int) $_SESSION['customer_id'];
$member = null;
$orders = [];
$pageError = null;

if ($pdo === null) {
    $pageError = '目前無法連線到資料庫，請確認 MAMP / MySQL 已啟動，且 includes/db_config.php 設定正確。';
} else {
    try {
        $memberStatement = $pdo->prepare(
            'SELECT user_id, username, email, password, role, created_at
             FROM `User`
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $memberStatement->execute(['user_id' => $customerId]);
        $member = $memberStatement->fetch();

        if (!$member) {
            unset($_SESSION['customer_id']);
            header('Location: login.php?redirect=member.php');
            exit;
        }

        $orderStatement = $pdo->prepare(
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
             WHERE o.user_id = :user_id
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
             ORDER BY o.created_at DESC'
        );
        $orderStatement->execute(['user_id' => $customerId]);
        $orders = $orderStatement->fetchAll();
    } catch (PDOException $exception) {
        $pageError = '會員資料讀取失敗：' . $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>會員資訊 | ConcertNow</title>
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
            <a href="../index.php">回首頁</a>
            <a class="login-button" href="logout.php">登出</a>
        </nav>
    </header>

    <main class="member-main">
        <section class="member-hero" aria-labelledby="member-title">
            <div>
                <p class="member-kicker">Member Center</p>
                <h1 id="member-title">會員資訊</h1>
                <span>查看會員帳號資料與訂單紀錄。</span>
            </div>
        </section>

        <?php if ($pageError): ?>
            <section class="member-panel">
                <p class="member-alert"><?= h($pageError) ?></p>
            </section>
        <?php elseif ($member): ?>
            <section class="member-panel" aria-labelledby="profile-title">
                <div class="member-section-title">
                    <p>Profile</p>
                    <h2 id="profile-title">帳號資料</h2>
                </div>

                <dl class="member-info-grid">
                    <div>
                        <dt>會員編號</dt>
                        <dd>#<?= h($member['user_id']) ?></dd>
                    </div>
                    <div>
                        <dt>帳號</dt>
                        <dd><?= h($member['username']) ?></dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd><?= h($member['email']) ?></dd>
                    </div>
                    <div>
                        <dt>密碼</dt>
                        <dd class="masked-password">••••••••</dd>
                    </div>
                    <div>
                        <dt>角色</dt>
                        <dd><?= h($member['role']) ?></dd>
                    </div>
                    <div>
                        <dt>建立時間</dt>
                        <dd><?= h(memberDateTimeText($member['created_at'])) ?></dd>
                    </div>
                </dl>
            </section>

            <section class="member-panel" aria-labelledby="orders-title">
                <div class="member-section-title">
                    <p>Orders</p>
                    <h2 id="orders-title">訂單紀錄</h2>
                </div>

                <?php if (!$orders): ?>
                    <p class="member-empty">目前沒有訂單紀錄。</p>
                <?php else: ?>
                    <div class="member-order-list">
                        <?php foreach ($orders as $order): ?>
                            <article class="member-order-card">
                                <div class="member-order-head">
                                    <div>
                                        <p>訂單 #<?= h($order['order_id']) ?></p>
                                        <h3><?= h($order['title'] ?? '演唱會資料已移除') ?></h3>
                                    </div>
                                    <span class="member-status"><?= h(orderStatusText($order['status'])) ?></span>
                                </div>

                                <dl class="member-order-info">
                                    <div>
                                        <dt>活動名稱</dt>
                                        <dd><?= h($order['artist'] ?? '-') ?></dd>
                                    </div>
                                    <div>
                                        <dt>演出時間</dt>
                                        <dd><?= h(memberDateTimeText($order['show_datetime'] ?? null)) ?></dd>
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
                                        <dt>訂單金額</dt>
                                        <dd>NT$<?= h(number_format((int) $order['total_price'])) ?></dd>
                                    </div>
                                    <div>
                                        <dt>建立時間</dt>
                                        <dd><?= h(memberDateTimeText($order['created_at'])) ?></dd>
                                    </div>
                                </dl>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
