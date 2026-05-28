<?php
require_once __DIR__ . '/../includes/manager_auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireManager();

$errors = [];
$notice = '';
$promos = [];
$editPromo = null;
$dbReady = $pdo instanceof PDO;

function normalizePromoDateTime($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $value);

    return $dateTime ? $dateTime->format('Y-m-d H:i:s') : false;
}

function promoDateTimeInputValue($value)
{
    if (!$value) {
        return '';
    }

    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', (string) $value);

    return $dateTime ? $dateTime->format('Y-m-d\TH:i') : '';
}

function validatePromoForm($postData)
{
    $codeName = strtoupper(trim((string) ($postData['code_name'] ?? '')));
    $discountAmount = filter_var($postData['discount_amount'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $usageLimitRaw = trim((string) ($postData['usage_limit'] ?? ''));
    $usageLimit = null;
    $startsAt = normalizePromoDateTime($postData['starts_at'] ?? '');
    $expiresAt = normalizePromoDateTime($postData['expires_at'] ?? '');
    $isActive = isset($postData['is_active']) ? 1 : 0;
    $errors = [];

    if ($codeName === '' || strlen($codeName) > 50) {
        $errors[] = '請輸入 50 字以內的優惠碼。';
    }

    if (!preg_match('/^[A-Z0-9_-]+$/', $codeName)) {
        $errors[] = '優惠碼只能使用英文、數字、底線或減號。';
    }

    if (!$discountAmount) {
        $errors[] = '折扣金額必須大於 0。';
    }

    if ($usageLimitRaw !== '') {
        $usageLimit = filter_var($usageLimitRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (!$usageLimit) {
            $errors[] = '使用上限必須是大於 0 的整數，留空代表不限次數。';
        }
    }

    if ($startsAt === false) {
        $errors[] = '開始時間格式不正確。';
    }

    if ($expiresAt === false) {
        $errors[] = '到期時間格式不正確。';
    }

    if ($startsAt && $expiresAt && strtotime($startsAt) > strtotime($expiresAt)) {
        $errors[] = '開始時間不可晚於到期時間。';
    }

    return [
        'errors' => $errors,
        'code_name' => $codeName,
        'discount_amount' => $discountAmount ?: 0,
        'usage_limit' => $usageLimit,
        'starts_at' => $startsAt ?: null,
        'expires_at' => $expiresAt ?: null,
        'is_active' => $isActive,
    ];
}

function promoStatusText($promo)
{
    $now = time();

    if ((int) $promo['is_active'] !== 1) {
        return '已停用';
    }

    if (!empty($promo['starts_at']) && strtotime($promo['starts_at']) > $now) {
        return '尚未開始';
    }

    if (!empty($promo['expires_at']) && strtotime($promo['expires_at']) < $now) {
        return '已過期';
    }

    if ($promo['usage_limit'] !== null && (int) $promo['used_count'] >= (int) $promo['usage_limit']) {
        return '已達上限';
    }

    return '可使用';
}

function promoStatusClass($promo)
{
    $status = promoStatusText($promo);
    $classMap = [
        '可使用' => 'status-active',
        '尚未開始' => 'status-pending',
        '已過期' => 'status-expired',
        '已達上限' => 'status-limit',
        '已停用' => 'status-disabled',
    ];

    return $classMap[$status] ?? 'status-disabled';
}

function displayDateTime($value)
{
    if (!$value) {
        return '不限';
    }

    $timestamp = strtotime((string) $value);

    return $timestamp ? date('Y/m/d H:i', $timestamp) : (string) $value;
}

if (!$dbReady) {
    $errors[] = '資料庫連線失敗，請檢查 MySQL 與 includes/db_config.php 設定。';
}

if (isset($_GET['message'])) {
    $messages = [
        'created' => '優惠碼已新增。',
        'updated' => '優惠碼已更新。',
        'deleted' => '優惠碼已停用。',
    ];
    $notice = $messages[$_GET['message']] ?? '';
}

if ($dbReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $validated = validatePromoForm($_POST);
        $errors = array_merge($errors, $validated['errors']);
        $promoId = null;

        if ($action === 'update') {
            $promoId = filter_var($_POST['promo_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if (!$promoId) {
                $errors[] = '缺少要修改的優惠碼 ID。';
            }
        }

        if (!$errors) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO PromoCode
                         (code_name, discount_amount, usage_limit, starts_at, expires_at, is_active)
                         VALUES
                         (:code_name, :discount_amount, :usage_limit, :starts_at, :expires_at, :is_active)'
                    );
                    $stmt->execute([
                        ':code_name' => $validated['code_name'],
                        ':discount_amount' => $validated['discount_amount'],
                        ':usage_limit' => $validated['usage_limit'],
                        ':starts_at' => $validated['starts_at'],
                        ':expires_at' => $validated['expires_at'],
                        ':is_active' => $validated['is_active'],
                    ]);

                    header('Location: /concert_system/manager/promocodes.php?message=created');
                    exit;
                }

                $stmt = $pdo->prepare(
                    'UPDATE PromoCode
                     SET code_name = :code_name,
                         discount_amount = :discount_amount,
                         usage_limit = :usage_limit,
                         starts_at = :starts_at,
                         expires_at = :expires_at,
                         is_active = :is_active
                     WHERE promo_id = :promo_id'
                );
                $stmt->execute([
                    ':code_name' => $validated['code_name'],
                    ':discount_amount' => $validated['discount_amount'],
                    ':usage_limit' => $validated['usage_limit'],
                    ':starts_at' => $validated['starts_at'],
                    ':expires_at' => $validated['expires_at'],
                    ':is_active' => $validated['is_active'],
                    ':promo_id' => $promoId,
                ]);

                header('Location: /concert_system/manager/promocodes.php?message=updated');
                exit;
            } catch (PDOException $exception) {
                error_log('Save promo failed: ' . $exception->getMessage());
                $errors[] = '儲存優惠碼失敗，請確認優惠碼沒有重複，且資料表已更新。';
            }
        }

        $editPromo = $validated;
        $editPromo['promo_id'] = $promoId;
    } elseif ($action === 'delete') {
        $promoId = filter_var($_POST['promo_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (!$promoId) {
            $errors[] = '缺少要刪除的優惠碼 ID。';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE PromoCode SET is_active = 0 WHERE promo_id = :promo_id');
                $stmt->execute([':promo_id' => $promoId]);

                header('Location: /concert_system/manager/promocodes.php?message=deleted');
                exit;
            } catch (PDOException $exception) {
                error_log('Disable promo failed: ' . $exception->getMessage());
                $errors[] = '停用優惠碼失敗，請稍後再試。';
            }
        }
    } else {
        $errors[] = '未知的操作。';
    }
}

if ($dbReady && $_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit_id'])) {
    $editId = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($editId) {
        try {
            $stmt = $pdo->prepare(
                'SELECT promo_id, code_name, discount_amount, usage_limit, starts_at, expires_at, is_active
                 FROM PromoCode
                 WHERE promo_id = :promo_id'
            );
            $stmt->execute([':promo_id' => $editId]);
            $editPromo = $stmt->fetch();

            if (!$editPromo) {
                $errors[] = '找不到要修改的優惠碼。';
            }
        } catch (PDOException $exception) {
            error_log('Fetch promo failed: ' . $exception->getMessage());
            $errors[] = '讀取優惠碼失敗。';
        }
    }
}

if ($dbReady) {
    try {
        $stmt = $pdo->query(
            'SELECT
                p.promo_id,
                p.code_name,
                p.discount_amount,
                p.usage_limit,
                p.starts_at,
                p.expires_at,
                p.is_active,
                COUNT(o.order_id) AS total_used_count,
                SUM(CASE WHEN o.status IN ("pending_payment", "paid") THEN 1 ELSE 0 END) AS used_count,
                SUM(CASE WHEN o.status = "paid" THEN 1 ELSE 0 END) AS paid_count,
                COALESCE(SUM(CASE WHEN o.status = "paid" THEN o.total_price ELSE 0 END), 0) AS paid_sales
             FROM PromoCode p
             LEFT JOIN Orders o ON o.promo_id = p.promo_id
             GROUP BY
                p.promo_id,
                p.code_name,
                p.discount_amount,
                p.usage_limit,
                p.starts_at,
                p.expires_at,
                p.is_active
             ORDER BY p.promo_id DESC'
        );
        $promos = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch promos failed: ' . $exception->getMessage());
        $errors[] = '讀取優惠碼列表失敗，請確認已重新匯入 schema.sql。';
    }
}

$isEditing = is_array($editPromo);
$formAction = $isEditing ? 'update' : 'create';
$formTitle = $isEditing ? '修改優惠碼' : '新增優惠碼';
$formData = [
    'code_name' => $isEditing ? ($editPromo['code_name'] ?? '') : ($_POST['code_name'] ?? ''),
    'discount_amount' => $isEditing ? ($editPromo['discount_amount'] ?? '') : ($_POST['discount_amount'] ?? ''),
    'usage_limit' => $isEditing ? ($editPromo['usage_limit'] ?? '') : ($_POST['usage_limit'] ?? ''),
    'starts_at' => $isEditing ? promoDateTimeInputValue($editPromo['starts_at'] ?? '') : ($_POST['starts_at'] ?? ''),
    'expires_at' => $isEditing ? promoDateTimeInputValue($editPromo['expires_at'] ?? '') : ($_POST['expires_at'] ?? ''),
    'is_active' => $isEditing ? (int) ($editPromo['is_active'] ?? 0) : (isset($_POST['is_active']) ? 1 : 1),
];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>優惠碼管理 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .manager-promos {
            margin-top: 34px;
        }

        .promos-panel {
            width: 100%;
            max-width: none;
        }

        .promos-layout,
        .promo-form {
            display: grid;
            gap: 18px;
        }

        .promo-form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .promo-form label {
            display: grid;
            gap: 7px;
            font-weight: 800;
        }

        .promo-form input {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }

        .promo-form input[type="checkbox"] {
            width: 20px;
            min-height: 20px;
        }

        .checkbox-label {
            align-content: end;
            grid-template-columns: 20px 1fr;
            align-items: center;
        }

        .promo-actions,
        .table-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .promo-table {
            width: 100%;
            min-width: 1080px;
            border-collapse: collapse;
        }

        .promo-table th,
        .promo-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .promo-table th {
            background: #f8f4ed;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }

        .promo-table tr:last-child td {
            border-bottom: 0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 900;
        }

        .status-active {
            background: #edf8f1;
            color: #245b38;
        }

        .status-pending {
            background: #eef4ff;
            color: #24507a;
        }

        .status-expired,
        .status-limit {
            background: #fff4dc;
            color: #80520e;
        }

        .status-disabled {
            background: #f1f1f1;
            color: #6a7079;
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

        .danger-action:hover {
            background: #631717;
        }

        .empty-row {
            padding: 22px;
            color: var(--muted);
            font-weight: 800;
            text-align: center;
        }

        @media (max-width: 860px) {
            .promo-form-grid {
                grid-template-columns: 1fr;
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
            <a href="/concert_system/manager/change_password.php">修改密碼</a>
            <a class="login-button" href="/concert_system/manager/logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-promos">
        <div class="section-title">
            <div>
                <p>Promo Code Management</p>
                <h2>優惠碼管理</h2>
            </div>
            <a class="secondary-action" href="/concert_system/manager/dashboard.php">回 Dashboard</a>
        </div>

        <section class="placeholder-card promos-panel">
            <div class="promos-layout">
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

                <form class="promo-form" method="post" action="/concert_system/manager/promocodes.php">
                    <input type="hidden" name="action" value="<?= h($formAction) ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="promo_id" value="<?= h($editPromo['promo_id'] ?? '') ?>">
                    <?php endif; ?>

                    <h1><?= h($formTitle) ?></h1>
                    <div class="promo-form-grid">
                        <label>
                            優惠碼
                            <input type="text" name="code_name" value="<?= h($formData['code_name']) ?>" placeholder="WELCOME100" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            折扣金額
                            <input type="number" name="discount_amount" min="1" value="<?= h($formData['discount_amount']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            使用上限
                            <input type="number" name="usage_limit" min="1" value="<?= h($formData['usage_limit']) ?>" placeholder="留空代表不限" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            開始時間
                            <input type="datetime-local" name="starts_at" value="<?= h($formData['starts_at']) ?>" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            到期時間
                            <input type="datetime-local" name="expires_at" value="<?= h($formData['expires_at']) ?>" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" value="1" <?= (int) $formData['is_active'] === 1 ? 'checked' : '' ?> <?= !$dbReady ? 'disabled' : '' ?>>
                            啟用優惠碼
                        </label>
                    </div>

                    <div class="promo-actions">
                        <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>
                            <?= $isEditing ? '更新優惠碼' : '新增優惠碼' ?>
                        </button>
                        <?php if ($isEditing): ?>
                            <a class="secondary-action" href="/concert_system/manager/promocodes.php">取消編輯</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div>
                    <h1>優惠碼列表與使用統計</h1>
                    <div class="table-wrap">
                        <?php if ($promos): ?>
                            <table class="promo-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>優惠碼</th>
                                        <th>折扣</th>
                                        <th>狀態</th>
                                        <th>使用次數 / 上限</th>
                                        <th>已付款訂單</th>
                                        <th>已付款金額</th>
                                        <th>開始</th>
                                        <th>到期</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($promos as $promo): ?>
                                        <tr>
                                            <td><?= h($promo['promo_id']) ?></td>
                                            <td><strong><?= h($promo['code_name']) ?></strong></td>
                                            <td>NT$<?= h(number_format((int) $promo['discount_amount'])) ?></td>
                                            <td>
                                                <span class="status-pill <?= h(promoStatusClass($promo)) ?>">
                                                    <?= h(promoStatusText($promo)) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?= h((int) $promo['used_count']) ?> /
                                                <?= $promo['usage_limit'] === null ? '不限' : h((int) $promo['usage_limit']) ?>
                                            </td>
                                            <td><?= h((int) $promo['paid_count']) ?></td>
                                            <td>NT$<?= h(number_format((int) $promo['paid_sales'])) ?></td>
                                            <td><?= h(displayDateTime($promo['starts_at'])) ?></td>
                                            <td><?= h(displayDateTime($promo['expires_at'])) ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <a class="secondary-action" href="/concert_system/manager/promocodes.php?edit_id=<?= h($promo['promo_id']) ?>">編輯</a>
                                                    <form method="post" action="/concert_system/manager/promocodes.php" onsubmit="return confirm('確定要停用這組優惠碼嗎？歷史訂單仍會保留統計。');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="promo_id" value="<?= h($promo['promo_id']) ?>">
                                                        <button class="danger-action" type="submit">停用</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有優惠碼。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
