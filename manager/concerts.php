<?php
require_once __DIR__ . '/../includes/manager_auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireManager();

$errors = [];
$notice = '';
$concerts = [];
$organizers = [];
$editConcert = null;
$dbReady = $pdo instanceof PDO;
$addressColumn = 'address';

function normalizeConcertDateTime($value)
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $value);

    return $dateTime ? $dateTime->format('Y-m-d H:i:s') : '';
}

function concertDateTimeInputValue($value)
{
    if (!$value) {
        return '';
    }

    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', (string) $value);

    return $dateTime ? $dateTime->format('Y-m-d\TH:i') : '';
}

function detectConcertAddressColumn($connection)
{
    try {
        $columns = $connection->query('SHOW COLUMNS FROM Concert')->fetchAll();
    } catch (PDOException $exception) {
        error_log('Detect Concert columns failed: ' . $exception->getMessage());
        return 'address';
    }

    $columnNames = array_map(function ($column) {
        return $column['Field'];
    }, $columns);

    if (in_array('address', $columnNames, true)) {
        return 'address';
    }

    if (in_array('concert_address', $columnNames, true)) {
        return 'concert_address';
    }

    return 'address';
}

function validateConcertForm($postData)
{
    $organizerId = filter_var($postData['organizer_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $artist = trim((string) ($postData['artist'] ?? ''));
    $title = trim((string) ($postData['title'] ?? ''));
    $venue = trim((string) ($postData['venue'] ?? ''));
    $address = trim((string) ($postData['address'] ?? ''));
    $image = trim((string) ($postData['image'] ?? ''));
    $saleStart = normalizeConcertDateTime($postData['sale_start'] ?? '');
    $saleEnd = normalizeConcertDateTime($postData['sale_end'] ?? '');
    $description = trim((string) ($postData['description'] ?? ''));
    $notice = trim((string) ($postData['notice'] ?? ''));
    $errors = [];

    if ($artist === '') {
        $errors[] = '請輸入藝人或表演者名稱。';
    }

    if ($title === '') {
        $errors[] = '請輸入演唱會標題。';
    }

    if ($venue === '') {
        $errors[] = '請輸入演出場館。';
    }

    if ($address === '') {
        $errors[] = '請輸入場館地址。';
    }

    if ($saleStart === '') {
        $errors[] = '請輸入有效的開賣時間。';
    }

    if ($saleEnd === '') {
        $errors[] = '請輸入有效的截止售票時間。';
    }

    if ($saleStart !== '' && $saleEnd !== '' && strtotime($saleStart) > strtotime($saleEnd)) {
        $errors[] = '截止售票時間不可早於開賣時間。';
    }

    return [
        'errors' => $errors,
        'organizer_id' => $organizerId ?: null,
        'artist' => $artist,
        'title' => $title,
        'venue' => $venue,
        'address' => $address,
        'image' => $image,
        'sale_start' => $saleStart,
        'sale_end' => $saleEnd,
        'description' => $description,
        'notice' => $notice,
    ];
}

if (!$dbReady) {
    $errors[] = '目前無法連線資料庫，請確認 MySQL 與 includes/db_config.php 設定。';
} else {
    $addressColumn = detectConcertAddressColumn($pdo);
}

if (isset($_GET['message'])) {
    $messages = [
        'created' => '演唱會新增成功。',
        'updated' => '演唱會更新成功。',
        'deleted' => '演唱會刪除成功。',
    ];
    $notice = $messages[$_GET['message']] ?? '';
}

if ($dbReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $validated = validateConcertForm($_POST);
        $errors = array_merge($errors, $validated['errors']);

        if (!$errors) {
            try {
                $sql = "INSERT INTO Concert
                        (organizer_id, artist, title, venue, {$addressColumn}, image, sale_start, sale_end, description, notice)
                        VALUES
                        (:organizer_id, :artist, :title, :venue, :address, :image, :sale_start, :sale_end, :description, :notice)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':organizer_id' => $validated['organizer_id'],
                    ':artist' => $validated['artist'],
                    ':title' => $validated['title'],
                    ':venue' => $validated['venue'],
                    ':address' => $validated['address'],
                    ':image' => $validated['image'],
                    ':sale_start' => $validated['sale_start'],
                    ':sale_end' => $validated['sale_end'],
                    ':description' => $validated['description'],
                    ':notice' => $validated['notice'],
                ]);

                header('Location: /concert_system/manager/concerts.php?message=created');
                exit;
            } catch (PDOException $exception) {
                error_log('Create concert failed: ' . $exception->getMessage());
                $errors[] = '新增演唱會失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'update') {
        $concertId = filter_var($_POST['concert_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $validated = validateConcertForm($_POST);
        $errors = array_merge($errors, $validated['errors']);

        if (!$concertId) {
            $errors[] = '找不到要更新的演唱會。';
        }

        if (!$errors) {
            try {
                $sql = "UPDATE Concert
                        SET artist = :artist,
                            organizer_id = :organizer_id,
                            title = :title,
                            venue = :venue,
                            {$addressColumn} = :address,
                            image = :image,
                            sale_start = :sale_start,
                            sale_end = :sale_end,
                            description = :description,
                            notice = :notice
                        WHERE concert_id = :concert_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':organizer_id' => $validated['organizer_id'],
                    ':artist' => $validated['artist'],
                    ':title' => $validated['title'],
                    ':venue' => $validated['venue'],
                    ':address' => $validated['address'],
                    ':image' => $validated['image'],
                    ':sale_start' => $validated['sale_start'],
                    ':sale_end' => $validated['sale_end'],
                    ':description' => $validated['description'],
                    ':notice' => $validated['notice'],
                    ':concert_id' => $concertId,
                ]);

                header('Location: /concert_system/manager/concerts.php?message=updated');
                exit;
            } catch (PDOException $exception) {
                error_log('Update concert failed: ' . $exception->getMessage());
                $errors[] = '更新演唱會失敗，請稍後再試。';
            }
        }

        $editConcert = $validated;
        $editConcert['concert_id'] = $concertId;
    } elseif ($action === 'delete') {
        $concertId = filter_var($_POST['concert_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (!$concertId) {
            $errors[] = '找不到要刪除的演唱會。';
        } else {
            try {
                // 注意：Concert 刪除後，依目前 schema 可能會連帶刪除 ShowDate 與 Seat。
                // 如果 Orders 或 Ticket 已引用相關場次，刪除也可能受到外鍵限制。
                $stmt = $pdo->prepare('DELETE FROM Concert WHERE concert_id = :concert_id');
                $stmt->execute([':concert_id' => $concertId]);

                header('Location: /concert_system/manager/concerts.php?message=deleted');
                exit;
            } catch (PDOException $exception) {
                error_log('Delete concert failed: ' . $exception->getMessage());
                $errors[] = '刪除演唱會失敗，可能已有場次、座位或訂單資料與此演唱會相關。';
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
            $sql = "SELECT concert_id, organizer_id, artist, title, venue, {$addressColumn} AS address,
                           image, sale_start, sale_end, description, notice
                    FROM Concert
                    WHERE concert_id = :concert_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':concert_id' => $editId]);
            $editConcert = $stmt->fetch();

            if (!$editConcert) {
                $errors[] = '找不到要編輯的演唱會。';
            }
        } catch (PDOException $exception) {
            error_log('Fetch concert for edit failed: ' . $exception->getMessage());
            $errors[] = '讀取編輯資料失敗，請稍後再試。';
        }
    } else {
        $errors[] = '編輯演唱會編號不正確。';
    }
}

if ($dbReady) {
    try {
        $stmt = $pdo->query(
            'SELECT organizer_id, organizer_name
             FROM Organizer
             ORDER BY organizer_name, organizer_id'
        );
        $organizers = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch organizers for concert form failed: ' . $exception->getMessage());
        $errors[] = '讀取主辦單位列表失敗，請確認已建立 Organizer 資料表。';
    }

    try {
        $sql = "SELECT c.concert_id, c.organizer_id, c.artist, c.title, c.venue, c.{$addressColumn} AS address,
                       c.image, c.sale_start, c.sale_end, c.description, c.notice,
                       o.organizer_name
                FROM Concert c
                LEFT JOIN Organizer o ON o.organizer_id = c.organizer_id
                ORDER BY concert_id DESC";
        $stmt = $pdo->query($sql);
        $concerts = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch concerts failed: ' . $exception->getMessage());
        $errors[] = '讀取演唱會列表失敗，請稍後再試。';
    }
}

$isEditing = is_array($editConcert);
$formAction = $isEditing ? 'update' : 'create';
$formTitle = $isEditing ? '編輯演唱會' : '新增演唱會';
$formData = [
    'organizer_id' => $isEditing ? ($editConcert['organizer_id'] ?? '') : ($_POST['organizer_id'] ?? ''),
    'artist' => $isEditing ? ($editConcert['artist'] ?? '') : ($_POST['artist'] ?? ''),
    'title' => $isEditing ? ($editConcert['title'] ?? '') : ($_POST['title'] ?? ''),
    'venue' => $isEditing ? ($editConcert['venue'] ?? '') : ($_POST['venue'] ?? ''),
    'address' => $isEditing ? ($editConcert['address'] ?? '') : ($_POST['address'] ?? ''),
    'image' => $isEditing ? ($editConcert['image'] ?? '') : ($_POST['image'] ?? ''),
    'sale_start' => $isEditing ? concertDateTimeInputValue($editConcert['sale_start'] ?? '') : ($_POST['sale_start'] ?? ''),
    'sale_end' => $isEditing ? concertDateTimeInputValue($editConcert['sale_end'] ?? '') : ($_POST['sale_end'] ?? ''),
    'description' => $isEditing ? ($editConcert['description'] ?? '') : ($_POST['description'] ?? ''),
    'notice' => $isEditing ? ($editConcert['notice'] ?? '') : ($_POST['notice'] ?? ''),
];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>演唱會管理 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .manager-concerts {
            margin-top: 34px;
        }

        .concerts-panel {
            width: 100%;
            max-width: none;
        }

        .concerts-layout,
        .concert-form {
            display: grid;
            gap: 18px;
        }

        .concert-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .concert-form label {
            display: grid;
            gap: 7px;
            font-weight: 800;
        }

        .concert-form input,
        .concert-form select,
        .concert-form textarea {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }

        .concert-form textarea {
            min-height: 96px;
            resize: vertical;
        }

        .concert-form input:focus,
        .concert-form select:focus,
        .concert-form textarea:focus {
            outline: 2px solid rgba(184, 50, 50, 0.22);
            border-color: var(--accent);
        }

        .field-wide {
            grid-column: 1 / -1;
        }

        .concert-actions,
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

        .concert-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }

        .concert-table th,
        .concert-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .concert-table th {
            background: #f8f4ed;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }

        .concert-table tr:last-child td {
            border-bottom: 0;
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
            .concert-form-grid {
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
            <a href="/concert_system/manager/organizers.php">主辦單位管理</a>
            <a href="/concert_system/manager/shows.php">場次管理</a>
            <a href="/concert_system/manager/seats.php">座位管理</a>
            <a href="/concert_system/manager/change_password.php">修改密碼</a>
            <a class="login-button" href="/concert_system/manager/logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-concerts">
        <div class="section-title">
            <div>
                <p>Concert Management</p>
                <h2>演唱會管理</h2>
            </div>
            <a class="secondary-action" href="/concert_system/manager/dashboard.php">返回 Dashboard</a>
        </div>

        <section class="placeholder-card concerts-panel">
            <div class="concerts-layout">
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

                <form class="concert-form" method="post" action="/concert_system/manager/concerts.php">
                    <input type="hidden" name="action" value="<?= h($formAction) ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="concert_id" value="<?= h($editConcert['concert_id'] ?? '') ?>">
                    <?php endif; ?>

                    <h1><?= h($formTitle) ?></h1>
                    <div class="concert-form-grid">
                        <label class="field-wide">
                            主辦單位
                            <select name="organizer_id" <?= !$dbReady ? 'disabled' : '' ?>>
                                <option value="">未指定主辦單位</option>
                                <?php foreach ($organizers as $organizer): ?>
                                    <option value="<?= h($organizer['organizer_id']) ?>" <?= (string) $formData['organizer_id'] === (string) $organizer['organizer_id'] ? 'selected' : '' ?>>
                                        <?= h($organizer['organizer_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            藝人 / 表演者
                            <input type="text" name="artist" value="<?= h($formData['artist']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            演唱會標題
                            <input type="text" name="title" value="<?= h($formData['title']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            演出場館
                            <input type="text" name="venue" value="<?= h($formData['venue']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            場館地址
                            <input type="text" name="address" value="<?= h($formData['address']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            開賣時間
                            <input type="datetime-local" name="sale_start" value="<?= h($formData['sale_start']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label>
                            截止售票時間
                            <input type="datetime-local" name="sale_end" value="<?= h($formData['sale_end']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label class="field-wide">
                            海報圖片路徑
                            <input type="text" name="image" value="<?= h($formData['image']) ?>" placeholder="assets/images/concert-1.png" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>

                        <label class="field-wide">
                            演唱會介紹
                            <textarea name="description" <?= !$dbReady ? 'disabled' : '' ?>><?= h($formData['description']) ?></textarea>
                        </label>

                        <label class="field-wide">
                            購票注意事項
                            <textarea name="notice" <?= !$dbReady ? 'disabled' : '' ?>><?= h($formData['notice']) ?></textarea>
                        </label>
                    </div>

                    <div class="concert-actions">
                        <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>
                            <?= $isEditing ? '更新演唱會' : '新增演唱會' ?>
                        </button>
                        <?php if ($isEditing): ?>
                            <a class="secondary-action" href="/concert_system/manager/concerts.php">取消編輯</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div>
                    <h1>演唱會列表</h1>
                    <div class="table-wrap">
                        <?php if ($concerts): ?>
                            <table class="concert-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>藝人</th>
                                        <th>標題</th>
                                        <th>主辦單位</th>
                                        <th>場館</th>
                                        <th>開賣</th>
                                        <th>截止</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($concerts as $concert): ?>
                                        <tr>
                                            <td><?= h($concert['concert_id']) ?></td>
                                            <td><?= h($concert['artist']) ?></td>
                                            <td><?= h($concert['title']) ?></td>
                                            <td><?= h($concert['organizer_name'] ?? '未指定') ?></td>
                                            <td><?= h($concert['venue']) ?></td>
                                            <td><?= h($concert['sale_start']) ?></td>
                                            <td><?= h($concert['sale_end']) ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <a class="secondary-action" href="/concert_system/manager/concerts.php?edit_id=<?= h($concert['concert_id']) ?>">編輯</a>
                                                    <form method="post" action="/concert_system/manager/concerts.php" onsubmit="return confirm('確定要刪除此演唱會嗎？相關場次與座位可能會一起被刪除。');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="concert_id" value="<?= h($concert['concert_id']) ?>">
                                                        <button class="danger-action" type="submit">刪除</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有演唱會資料。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
