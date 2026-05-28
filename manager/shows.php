<?php
require_once __DIR__ . '/../includes/manager_auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireManager();

$allowedStatuses = ['available', 'sold_out', 'ended'];
$statusLabels = [
    'available' => '可購買',
    'sold_out' => '已售完',
    'ended' => '已結束',
];

$errors = [];
$notice = '';
$concerts = [];
$shows = [];
$editShow = null;
$dbReady = $pdo instanceof PDO;

function normalizeShowDateTime($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $value);

    return $dateTime ? $dateTime->format('Y-m-d H:i:s') : '';
}

function showDateTimeInputValue($value)
{
    if (!$value) {
        return '';
    }

    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', (string) $value);

    return $dateTime ? $dateTime->format('Y-m-d\TH:i') : '';
}

function validateShowForm($postData, $allowedStatuses)
{
    $concertId = filter_var($postData['concert_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $showDateTime = normalizeShowDateTime($postData['show_datetime'] ?? '');
    $status = (string) ($postData['status'] ?? '');
    $errors = [];

    if (!$concertId) {
        $errors[] = '請選擇有效的演唱會。';
    }

    if ($showDateTime === '') {
        $errors[] = '請輸入有效的場次時間。';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = '請選擇有效的場次狀態。';
    }

    return [
        'errors' => $errors,
        'concert_id' => $concertId,
        'show_datetime' => $showDateTime,
        'status' => $status,
    ];
}

if (!$dbReady) {
    $errors[] = '目前無法連線資料庫，請確認 MySQL 與 includes/db_config.php 設定。';
}

if (isset($_GET['message'])) {
    $messages = [
        'created' => '場次新增成功。',
        'updated' => '場次更新成功。',
        'deleted' => '場次刪除成功。',
    ];
    $notice = $messages[$_GET['message']] ?? '';
}

if ($dbReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $validated = validateShowForm($_POST, $allowedStatuses);
        $errors = array_merge($errors, $validated['errors']);

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO ShowDate (concert_id, show_datetime, status)
                     VALUES (:concert_id, :show_datetime, :status)'
                );
                $stmt->execute([
                    ':concert_id' => $validated['concert_id'],
                    ':show_datetime' => $validated['show_datetime'],
                    ':status' => $validated['status'],
                ]);

                header('Location: /concert_system/manager/shows.php?message=created');
                exit;
            } catch (PDOException $exception) {
                error_log('Create show failed: ' . $exception->getMessage());
                $errors[] = '新增場次失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'update') {
        $showId = filter_var($_POST['show_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $validated = validateShowForm($_POST, $allowedStatuses);
        $errors = array_merge($errors, $validated['errors']);

        if (!$showId) {
            $errors[] = '找不到要更新的場次。';
        }

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE ShowDate
                     SET concert_id = :concert_id,
                         show_datetime = :show_datetime,
                         status = :status
                     WHERE show_id = :show_id'
                );
                $stmt->execute([
                    ':concert_id' => $validated['concert_id'],
                    ':show_datetime' => $validated['show_datetime'],
                    ':status' => $validated['status'],
                    ':show_id' => $showId,
                ]);

                header('Location: /concert_system/manager/shows.php?message=updated');
                exit;
            } catch (PDOException $exception) {
                error_log('Update show failed: ' . $exception->getMessage());
                $errors[] = '更新場次失敗，請稍後再試。';
            }
        }

        $editShow = [
            'show_id' => $showId,
            'concert_id' => $_POST['concert_id'] ?? '',
            'show_datetime' => $validated['show_datetime'],
            'status' => $_POST['status'] ?? 'available',
        ];
    } elseif ($action === 'delete') {
        $showId = filter_var($_POST['show_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (!$showId) {
            $errors[] = '找不到要刪除的場次。';
        } else {
            try {
                // 注意：如果此場次已有 Seat 或 Orders，刪除可能會受到外鍵限制，
                // 或因資料表設定 ON DELETE CASCADE 而連帶刪除相關資料。
                $stmt = $pdo->prepare('DELETE FROM ShowDate WHERE show_id = :show_id');
                $stmt->execute([':show_id' => $showId]);

                header('Location: /concert_system/manager/shows.php?message=deleted');
                exit;
            } catch (PDOException $exception) {
                error_log('Delete show failed: ' . $exception->getMessage());
                $errors[] = '刪除場次失敗，可能已有座位或訂單資料與此場次相關。';
            }
        }
    } else {
        $errors[] = '不支援的操作。';
    }
}

if ($dbReady && $_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit_id'])) {
    $editId = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($editId) {
        try {
            $stmt = $pdo->prepare(
                'SELECT show_id, concert_id, show_datetime, status
                 FROM ShowDate
                 WHERE show_id = :show_id'
            );
            $stmt->execute([':show_id' => $editId]);
            $editShow = $stmt->fetch();

            if (!$editShow) {
                $errors[] = '找不到要編輯的場次。';
            }
        } catch (PDOException $exception) {
            error_log('Fetch show for edit failed: ' . $exception->getMessage());
            $errors[] = '讀取編輯資料失敗，請稍後再試。';
        }
    } else {
        $errors[] = '編輯場次編號不正確。';
    }
}

if ($dbReady) {
    try {
        $stmt = $pdo->query(
            'SELECT concert_id, title
             FROM Concert
             ORDER BY title, concert_id'
        );
        $concerts = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch concerts failed: ' . $exception->getMessage());
        $errors[] = '讀取演唱會資料失敗，請稍後再試。';
    }

    try {
        $stmt = $pdo->query(
            'SELECT s.show_id, s.concert_id, s.show_datetime, s.status, c.title AS concert_title
             FROM ShowDate s
             INNER JOIN Concert c ON c.concert_id = s.concert_id
             ORDER BY s.show_datetime DESC, s.show_id DESC'
        );
        $shows = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch shows failed: ' . $exception->getMessage());
        $errors[] = '讀取場次列表失敗，請稍後再試。';
    }
}

$isEditing = is_array($editShow);
$formAction = $isEditing ? 'update' : 'create';
$formTitle = $isEditing ? '編輯場次' : '新增場次';
$formConcertId = $isEditing ? ($editShow['concert_id'] ?? '') : ($_POST['concert_id'] ?? '');
$formDateTime = $isEditing ? showDateTimeInputValue($editShow['show_datetime'] ?? '') : ($_POST['show_datetime'] ?? '');
$formStatus = $isEditing ? ($editShow['status'] ?? 'available') : ($_POST['status'] ?? 'available');
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>場次管理 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .manager-shows {
            margin-top: 34px;
        }

        .shows-panel {
            width: 100%;
            max-width: none;
        }

        .shows-layout {
            display: grid;
            gap: 22px;
        }

        .shows-form {
            display: grid;
            gap: 16px;
        }

        .shows-form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .shows-form label {
            display: grid;
            gap: 7px;
            font-weight: 800;
        }

        .shows-form select,
        .shows-form input {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }

        .shows-form select:focus,
        .shows-form input:focus {
            outline: 2px solid rgba(184, 50, 50, 0.22);
            border-color: var(--accent);
        }

        .shows-actions,
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

        .shows-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        .shows-table th,
        .shows-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .shows-table th {
            background: #f8f4ed;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }

        .shows-table tr:last-child td {
            border-bottom: 0;
        }

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

        @media (max-width: 820px) {
            .shows-form-grid {
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
            <a href="/concert_system/manager/change_password.php">修改密碼</a>
            <a class="login-button" href="/concert_system/manager/logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-shows">
        <div class="section-title">
            <div>
                <p>ShowDate Management</p>
                <h2>場次管理</h2>
            </div>
            <a class="secondary-action" href="/concert_system/manager/dashboard.php">返回 Dashboard</a>
        </div>

        <section class="placeholder-card shows-panel">
            <div class="shows-layout">
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

                <form class="shows-form" method="post" action="/concert_system/manager/shows.php">
                    <input type="hidden" name="action" value="<?= h($formAction) ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="show_id" value="<?= h($editShow['show_id'] ?? '') ?>">
                    <?php endif; ?>

                    <h1><?= h($formTitle) ?></h1>
                    <div class="shows-form-grid">
                        <label>
                            演唱會
                            <select name="concert_id" required <?= !$dbReady ? 'disabled' : '' ?>>
                                <option value="">請選擇演唱會</option>
                                <?php foreach ($concerts as $concert): ?>
                                    <option value="<?= h($concert['concert_id']) ?>" <?= (string) $formConcertId === (string) $concert['concert_id'] ? 'selected' : '' ?>>
                                        #<?= h($concert['concert_id']) ?> <?= h($concert['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            場次時間
                            <input type="datetime-local" name="show_datetime" value="<?= h($formDateTime) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            狀態
                            <select name="status" required <?= !$dbReady ? 'disabled' : '' ?>>
                                <?php foreach ($allowedStatuses as $status): ?>
                                    <option value="<?= h($status) ?>" <?= $formStatus === $status ? 'selected' : '' ?>>
                                        <?= h($status) ?> - <?= h($statusLabels[$status]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="shows-actions">
                        <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>
                            <?= $isEditing ? '更新場次' : '新增場次' ?>
                        </button>
                        <?php if ($isEditing): ?>
                            <a class="secondary-action" href="/concert_system/manager/shows.php">取消編輯</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div>
                    <h1>場次列表</h1>
                    <div class="table-wrap">
                        <?php if ($shows): ?>
                            <table class="shows-table">
                                <thead>
                                    <tr>
                                        <th>show_id</th>
                                        <th>concert title</th>
                                        <th>show_datetime</th>
                                        <th>status</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($shows as $show): ?>
                                        <tr>
                                            <td><?= h($show['show_id']) ?></td>
                                            <td><?= h($show['concert_title']) ?></td>
                                            <td><?= h($show['show_datetime']) ?></td>
                                            <td>
                                                <span class="status-pill">
                                                    <?= h($show['status']) ?> - <?= h($statusLabels[$show['status']] ?? $show['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <a class="secondary-action" href="/concert_system/manager/shows.php?edit_id=<?= h($show['show_id']) ?>">編輯</a>
                                                    <form method="post" action="/concert_system/manager/shows.php" onsubmit="return confirm('確定要刪除此場次嗎？');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="show_id" value="<?= h($show['show_id']) ?>">
                                                        <button class="danger-action" type="submit">刪除</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有場次資料。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
