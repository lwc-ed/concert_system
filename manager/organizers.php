<?php
require_once __DIR__ . '/../includes/manager_auth.php';
require_once __DIR__ . '/../includes/db_config.php';

requireManager();

$errors = [];
$notice = '';
$organizers = [];
$editOrganizer = null;
$dbReady = $pdo instanceof PDO;

function validateOrganizerForm($postData)
{
    $organizerName = trim((string) ($postData['organizer_name'] ?? ''));
    $contactPerson = trim((string) ($postData['contact_person'] ?? ''));
    $contactEmail = trim((string) ($postData['contact_email'] ?? ''));
    $contactPhone = trim((string) ($postData['contact_phone'] ?? ''));
    $organizerAddress = trim((string) ($postData['organizer_address'] ?? ''));
    $note = trim((string) ($postData['note'] ?? ''));
    $errors = [];

    if ($organizerName === '' || strlen($organizerName) > 100) {
        $errors[] = '請輸入 100 字以內的主辦單位名稱。';
    }

    if ($contactPerson !== '' && strlen($contactPerson) > 100) {
        $errors[] = '聯絡人不可超過 100 字。';
    }

    if ($contactEmail !== '' && (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL) || strlen($contactEmail) > 100)) {
        $errors[] = '請輸入正確的聯絡 Email。';
    }

    if ($contactPhone !== '' && strlen($contactPhone) > 30) {
        $errors[] = '聯絡電話不可超過 30 字。';
    }

    if ($organizerAddress !== '' && strlen($organizerAddress) > 255) {
        $errors[] = '地址不可超過 255 字。';
    }

    return [
        'errors' => $errors,
        'organizer_name' => $organizerName,
        'contact_person' => $contactPerson,
        'contact_email' => $contactEmail,
        'contact_phone' => $contactPhone,
        'organizer_address' => $organizerAddress,
        'note' => $note,
    ];
}

if (!$dbReady) {
    $errors[] = '資料庫連線失敗，請檢查 MySQL 與 includes/db_config.php 設定。';
}

if (isset($_GET['message'])) {
    $messages = [
        'created' => '主辦單位已新增。',
        'updated' => '主辦單位已更新。',
        'deleted' => '主辦單位已刪除，相關演唱會已改為未指定主辦單位。',
    ];
    $notice = $messages[$_GET['message']] ?? '';
}

if ($dbReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $validated = validateOrganizerForm($_POST);
        $errors = array_merge($errors, $validated['errors']);
        $organizerId = null;

        if ($action === 'update') {
            $organizerId = filter_var($_POST['organizer_id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if (!$organizerId) {
                $errors[] = '缺少要修改的主辦單位 ID。';
            }
        }

        if (!$errors) {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO Organizer
                         (organizer_name, contact_person, contact_email, contact_phone, organizer_address, note)
                         VALUES
                         (:organizer_name, :contact_person, :contact_email, :contact_phone, :organizer_address, :note)'
                    );
                    $stmt->execute([
                        ':organizer_name' => $validated['organizer_name'],
                        ':contact_person' => $validated['contact_person'] ?: null,
                        ':contact_email' => $validated['contact_email'] ?: null,
                        ':contact_phone' => $validated['contact_phone'] ?: null,
                        ':organizer_address' => $validated['organizer_address'] ?: null,
                        ':note' => $validated['note'] ?: null,
                    ]);

                    header('Location: /concert_system/manager/organizers.php?message=created');
                    exit;
                }

                $stmt = $pdo->prepare(
                    'UPDATE Organizer
                     SET organizer_name = :organizer_name,
                         contact_person = :contact_person,
                         contact_email = :contact_email,
                         contact_phone = :contact_phone,
                         organizer_address = :organizer_address,
                         note = :note
                     WHERE organizer_id = :organizer_id'
                );
                $stmt->execute([
                    ':organizer_name' => $validated['organizer_name'],
                    ':contact_person' => $validated['contact_person'] ?: null,
                    ':contact_email' => $validated['contact_email'] ?: null,
                    ':contact_phone' => $validated['contact_phone'] ?: null,
                    ':organizer_address' => $validated['organizer_address'] ?: null,
                    ':note' => $validated['note'] ?: null,
                    ':organizer_id' => $organizerId,
                ]);

                header('Location: /concert_system/manager/organizers.php?message=updated');
                exit;
            } catch (PDOException $exception) {
                error_log('Save organizer failed: ' . $exception->getMessage());
                $errors[] = '儲存主辦單位失敗，請確認名稱沒有重複，且資料表已更新。';
            }
        }

        $editOrganizer = $validated;
        $editOrganizer['organizer_id'] = $organizerId;
    } elseif ($action === 'delete') {
        $organizerId = filter_var($_POST['organizer_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (!$organizerId) {
            $errors[] = '缺少要刪除的主辦單位 ID。';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM Organizer WHERE organizer_id = :organizer_id');
                $stmt->execute([':organizer_id' => $organizerId]);

                header('Location: /concert_system/manager/organizers.php?message=deleted');
                exit;
            } catch (PDOException $exception) {
                error_log('Delete organizer failed: ' . $exception->getMessage());
                $errors[] = '刪除主辦單位失敗，請稍後再試。';
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
                'SELECT organizer_id, organizer_name, contact_person, contact_email, contact_phone, organizer_address, note
                 FROM Organizer
                 WHERE organizer_id = :organizer_id'
            );
            $stmt->execute([':organizer_id' => $editId]);
            $editOrganizer = $stmt->fetch();

            if (!$editOrganizer) {
                $errors[] = '找不到要修改的主辦單位。';
            }
        } catch (PDOException $exception) {
            error_log('Fetch organizer failed: ' . $exception->getMessage());
            $errors[] = '讀取主辦單位失敗。';
        }
    }
}

if ($dbReady) {
    try {
        $stmt = $pdo->query(
            'SELECT
                o.organizer_id,
                o.organizer_name,
                o.contact_person,
                o.contact_email,
                o.contact_phone,
                o.organizer_address,
                o.note,
                COUNT(c.concert_id) AS concert_count
             FROM Organizer o
             LEFT JOIN Concert c ON c.organizer_id = o.organizer_id
             GROUP BY
                o.organizer_id,
                o.organizer_name,
                o.contact_person,
                o.contact_email,
                o.contact_phone,
                o.organizer_address,
                o.note
             ORDER BY o.organizer_id DESC'
        );
        $organizers = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch organizers failed: ' . $exception->getMessage());
        $errors[] = '讀取主辦單位列表失敗，請確認已重新匯入 schema.sql 或執行 organizer_migration.sql。';
    }
}

$isEditing = is_array($editOrganizer);
$formAction = $isEditing ? 'update' : 'create';
$formTitle = $isEditing ? '修改主辦單位' : '新增主辦單位';
$formData = [
    'organizer_name' => $isEditing ? ($editOrganizer['organizer_name'] ?? '') : ($_POST['organizer_name'] ?? ''),
    'contact_person' => $isEditing ? ($editOrganizer['contact_person'] ?? '') : ($_POST['contact_person'] ?? ''),
    'contact_email' => $isEditing ? ($editOrganizer['contact_email'] ?? '') : ($_POST['contact_email'] ?? ''),
    'contact_phone' => $isEditing ? ($editOrganizer['contact_phone'] ?? '') : ($_POST['contact_phone'] ?? ''),
    'organizer_address' => $isEditing ? ($editOrganizer['organizer_address'] ?? '') : ($_POST['organizer_address'] ?? ''),
    'note' => $isEditing ? ($editOrganizer['note'] ?? '') : ($_POST['note'] ?? ''),
];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>主辦單位管理 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .manager-organizers {
            margin-top: 34px;
        }

        .organizers-panel {
            width: 100%;
            max-width: none;
        }

        .organizers-layout,
        .organizer-form {
            display: grid;
            gap: 18px;
        }

        .organizer-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .organizer-form label {
            display: grid;
            gap: 7px;
            font-weight: 800;
        }

        .organizer-form input,
        .organizer-form textarea {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }

        .organizer-form textarea {
            min-height: 88px;
            resize: vertical;
        }

        .field-wide {
            grid-column: 1 / -1;
        }

        .organizer-actions,
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

        .organizer-table {
            width: 100%;
            min-width: 1080px;
            border-collapse: collapse;
        }

        .organizer-table th,
        .organizer-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
            white-space: nowrap;
        }

        .organizer-table th {
            background: #f8f4ed;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }

        .organizer-table tr:last-child td {
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
            .organizer-form-grid {
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
            <a href="/concert_system/manager/seats.php">座位管理</a>
            <a href="/concert_system/manager/promocodes.php">優惠碼管理</a>
            <a class="login-button" href="/concert_system/manager/logout.php">登出</a>
        </nav>
    </header>

    <main class="concert-section manager-organizers">
        <div class="section-title">
            <div>
                <p>Organizer Management</p>
                <h2>主辦單位管理</h2>
            </div>
            <a class="secondary-action" href="/concert_system/manager/dashboard.php">返回 Dashboard</a>
        </div>

        <section class="placeholder-card organizers-panel">
            <div class="organizers-layout">
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

                <form class="organizer-form" method="post" action="/concert_system/manager/organizers.php">
                    <input type="hidden" name="action" value="<?= h($formAction) ?>">
                    <?php if ($isEditing): ?>
                        <input type="hidden" name="organizer_id" value="<?= h($editOrganizer['organizer_id'] ?? '') ?>">
                    <?php endif; ?>

                    <h1><?= h($formTitle) ?></h1>
                    <div class="organizer-form-grid">
                        <label>
                            主辦單位名稱
                            <input type="text" name="organizer_name" value="<?= h($formData['organizer_name']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>
                        <label>
                            聯絡人
                            <input type="text" name="contact_person" value="<?= h($formData['contact_person']) ?>" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>
                        <label>
                            聯絡 Email
                            <input type="email" name="contact_email" value="<?= h($formData['contact_email']) ?>" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>
                        <label>
                            聯絡電話
                            <input type="text" name="contact_phone" value="<?= h($formData['contact_phone']) ?>" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>
                        <label class="field-wide">
                            地址
                            <input type="text" name="organizer_address" value="<?= h($formData['organizer_address']) ?>" <?= !$dbReady ? 'disabled' : '' ?>>
                        </label>
                        <label class="field-wide">
                            備註
                            <textarea name="note" <?= !$dbReady ? 'disabled' : '' ?>><?= h($formData['note']) ?></textarea>
                        </label>
                    </div>

                    <div class="organizer-actions">
                        <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>
                            <?= $isEditing ? '更新主辦單位' : '新增主辦單位' ?>
                        </button>
                        <?php if ($isEditing): ?>
                            <a class="secondary-action" href="/concert_system/manager/organizers.php">取消編輯</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div>
                    <h1>主辦單位列表</h1>
                    <div class="table-wrap">
                        <?php if ($organizers): ?>
                            <table class="organizer-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>主辦單位</th>
                                        <th>聯絡人</th>
                                        <th>Email</th>
                                        <th>電話</th>
                                        <th>地址</th>
                                        <th>關聯演唱會</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($organizers as $organizer): ?>
                                        <tr>
                                            <td><?= h($organizer['organizer_id']) ?></td>
                                            <td><strong><?= h($organizer['organizer_name']) ?></strong></td>
                                            <td><?= h($organizer['contact_person'] ?: '-') ?></td>
                                            <td><?= h($organizer['contact_email'] ?: '-') ?></td>
                                            <td><?= h($organizer['contact_phone'] ?: '-') ?></td>
                                            <td><?= h($organizer['organizer_address'] ?: '-') ?></td>
                                            <td><?= h((int) $organizer['concert_count']) ?> 場</td>
                                            <td>
                                                <div class="table-actions">
                                                    <a class="secondary-action" href="/concert_system/manager/organizers.php?edit_id=<?= h($organizer['organizer_id']) ?>">編輯</a>
                                                    <form method="post" action="/concert_system/manager/organizers.php" onsubmit="return confirm('確定要刪除此主辦單位嗎？相關演唱會會改成未指定主辦單位。');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="organizer_id" value="<?= h($organizer['organizer_id']) ?>">
                                                        <button class="danger-action" type="submit">刪除</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有主辦單位。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
