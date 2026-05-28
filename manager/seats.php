<?php
// Seat & Price Management
// This page is for system managers only. The original assignment mentions
// includes/auth_guard.php, but the current project uses includes/manager_auth.php.
// The fallback below keeps this page compatible with both versions.
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

$allowedStatuses = ['available', 'reserved', 'sold'];
$showStatusLabels = [
    'available' => '可購買',
    'sold_out' => '已售完',
    'ended' => '已結束',
];
$statusLabels = [
    'available' => '可購買',
    'reserved' => '已保留',
    'sold' => '已售出',
];

$errors = [];
$notice = '';
$shows = [];
$seats = [];
$seatCharts = [];
$seatZones = [];
$editSeat = null;
$dbReady = $pdo instanceof PDO;

// Validate positive integer values such as show_id and seat_id.
function positiveInt($value)
{
    return filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
}

// Validate ticket price. DECIMAL columns can accept integer or decimal values.
function validPrice($value)
{
    if ($value === '' || !is_numeric($value)) {
        return false;
    }

    $price = (float) $value;
    return $price >= 0 ? number_format($price, 2, '.', '') : false;
}

function validateSeatForm($postData, $allowedStatuses)
{
    $showId = positiveInt($postData['show_id'] ?? null);
    $seatNumber = trim((string) ($postData['seat_number'] ?? ''));
    $price = validPrice($postData['price'] ?? '');
    $status = (string) ($postData['status'] ?? '');
    $errors = [];

    if (!$showId) {
        $errors[] = '請選擇有效的場次。';
    }

    if ($seatNumber === '') {
        $errors[] = '請輸入座位編號。';
    }

    if ($price === false) {
        $errors[] = '票價必須是大於或等於 0 的數字。';
    }

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = '請選擇有效的座位狀態。';
    }

    return [
        'errors' => $errors,
        'show_id' => $showId,
        'seat_number' => $seatNumber,
        'price' => $price,
        'status' => $status,
    ];
}

function validateBatchForm($postData)
{
    $showId = positiveInt($postData['show_id'] ?? null);
    $seatPrefix = trim((string) ($postData['seat_prefix'] ?? ''));
    $startNo = positiveInt($postData['start_no'] ?? null);
    $endNo = positiveInt($postData['end_no'] ?? null);
    $price = validPrice($postData['price'] ?? '');
    $errors = [];

    if (!$showId) {
        $errors[] = '請選擇有效的場次。';
    }

    if ($seatPrefix === '') {
        $errors[] = '請輸入座位前綴或區域名稱。';
    }

    if (!$startNo) {
        $errors[] = '起始號碼必須是正整數。';
    }

    if (!$endNo) {
        $errors[] = '結束號碼必須是正整數。';
    }

    if ($startNo && $endNo && $endNo < $startNo) {
        $errors[] = '結束號碼必須大於或等於起始號碼。';
    }

    if ($startNo && $endNo && ($endNo - $startNo + 1) > 200) {
        $errors[] = '一次最多只能新增 200 個座位。';
    }

    if ($price === false) {
        $errors[] = '票價必須是大於或等於 0 的數字。';
    }

    return [
        'errors' => $errors,
        'show_id' => $showId,
        'seat_prefix' => rtrim($seatPrefix, '_'),
        'start_no' => $startNo,
        'end_no' => $endNo,
        'price' => $price,
    ];
}

function validateZonePriceForm($postData)
{
    $zoneKey = trim((string) ($postData['zone_key'] ?? ''));
    $zoneParts = explode('|', $zoneKey, 2);
    $showId = positiveInt($zoneParts[0] ?? null);
    $seatZone = trim((string) ($zoneParts[1] ?? ''));
    $price = validPrice($postData['zone_price'] ?? '');
    $errors = [];

    if (!$showId || $seatZone === '') {
        $errors[] = '請選擇要修改票價的場次與座位區。';
    }

    if ($price === false) {
        $errors[] = '新票價必須是大於或等於 0 的數字。';
    }

    return [
        'errors' => $errors,
        'show_id' => $showId,
        'seat_zone' => $seatZone,
        'price' => $price,
    ];
}

function seatExists($connection, $showId, $seatNumber, $ignoreSeatId = null)
{
    $sql = 'SELECT seat_id
            FROM Seat
            WHERE show_id = :show_id
              AND seat_number = :seat_number';
    $params = [
        ':show_id' => $showId,
        ':seat_number' => $seatNumber,
    ];

    if ($ignoreSeatId) {
        $sql .= ' AND seat_id <> :seat_id';
        $params[':seat_id'] = $ignoreSeatId;
    }

    $stmt = $connection->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetch();
}

// The database seat_number can include a long zone name, such as "一般區10_1號".
// The chart keeps only the compact seat code after "_" so the label stays inside
// the colored block. The full seat_number is still shown in the hover title.
function chartSeatLabel($seatNumber)
{
    $seatNumber = trim((string) $seatNumber);

    if (preg_match('/_([^_]+)$/u', $seatNumber, $matches)) {
        return $matches[1];
    }

    return $seatNumber;
}

function chartSeatZone($seatNumber)
{
    $seatNumber = trim((string) $seatNumber);

    if (preg_match('/^(.+)_([^_]+)$/u', $seatNumber, $matches)) {
        return $matches[1];
    }

    return '未分區';
}

if (!$dbReady) {
    $errors[] = '目前無法連線資料庫，請確認 MySQL 與 includes/db_config.php 設定。';
}

if (isset($_GET['message'])) {
    $messages = [
        'created' => '座位新增成功。',
        'updated' => '座位更新成功。',
        'deleted' => '座位刪除成功。',
        'batch_created' => '座位區新增完成。',
        'zone_price_updated' => '座位區票價更新成功。',
    ];
    $notice = $messages[$_GET['message']] ?? '';
}

$filterShowId = null;
if (isset($_GET['show_id']) && $_GET['show_id'] !== '') {
    $filterShowId = positiveInt($_GET['show_id']);
    if (!$filterShowId) {
        $errors[] = '篩選場次編號不正確。';
    }
}

if ($dbReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_single') {
        $validated = validateSeatForm($_POST, $allowedStatuses);
        $errors = array_merge($errors, $validated['errors']);

        if (!$errors) {
            try {
                if (seatExists($pdo, $validated['show_id'], $validated['seat_number'])) {
                    $errors[] = '同一個場次中已經有相同的座位編號。';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO Seat (show_id, seat_number, price, status)
                         VALUES (:show_id, :seat_number, :price, :status)'
                    );
                    $stmt->execute([
                        ':show_id' => $validated['show_id'],
                        ':seat_number' => $validated['seat_number'],
                        ':price' => $validated['price'],
                        ':status' => $validated['status'],
                    ]);

                    header('Location: /concert_system/manager/seats.php?message=created');
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('Create seat failed: ' . $exception->getMessage());
                $errors[] = '新增座位失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'create_batch') {
        $validated = validateBatchForm($_POST);
        $errors = array_merge($errors, $validated['errors']);

        if (!$errors) {
            $createdCount = 0;
            $skippedCount = 0;

            try {
                $pdo->beginTransaction();

                $insertStmt = $pdo->prepare(
                    'INSERT INTO Seat (show_id, seat_number, price, status)
                     VALUES (:show_id, :seat_number, :price, :status)'
                );

                for ($number = $validated['start_no']; $number <= $validated['end_no']; $number++) {
                    $seatNumber = $validated['seat_prefix'] . '_' . $number . '號';

                    if (seatExists($pdo, $validated['show_id'], $seatNumber)) {
                        $skippedCount++;
                        continue;
                    }

                    $insertStmt->execute([
                        ':show_id' => $validated['show_id'],
                        ':seat_number' => $seatNumber,
                        ':price' => $validated['price'],
                        ':status' => 'available',
                    ]);
                    $createdCount++;
                }

                $pdo->commit();
                header(
                    'Location: /concert_system/manager/seats.php?message=batch_created'
                    . '&created=' . $createdCount
                    . '&skipped=' . $skippedCount
                );
                exit;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Batch create seats failed: ' . $exception->getMessage());
                $errors[] = '批次新增座位失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'update_zone_price') {
        $validated = validateZonePriceForm($_POST);
        $errors = array_merge($errors, $validated['errors']);

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    "UPDATE Seat
                     SET price = :price
                     WHERE show_id = :show_id
                       AND SUBSTRING_INDEX(seat_number, '_', 1) = :seat_zone"
                );
                $stmt->execute([
                    ':price' => $validated['price'],
                    ':show_id' => $validated['show_id'],
                    ':seat_zone' => $validated['seat_zone'],
                ]);

                header(
                    'Location: /concert_system/manager/seats.php?message=zone_price_updated'
                    . '&updated=' . (int) $stmt->rowCount()
                    . '&show_id=' . (int) $validated['show_id']
                );
                exit;
            } catch (PDOException $exception) {
                error_log('Update zone price failed: ' . $exception->getMessage());
                $errors[] = '修改座位區票價失敗，請稍後再試。';
            }
        }
    } elseif ($action === 'update') {
        $seatId = positiveInt($_POST['seat_id'] ?? null);
        $validated = validateSeatForm($_POST, $allowedStatuses);
        $errors = array_merge($errors, $validated['errors']);

        if (!$seatId) {
            $errors[] = '找不到要更新的座位。';
        }

        if (!$errors) {
            try {
                if (seatExists($pdo, $validated['show_id'], $validated['seat_number'], $seatId)) {
                    $errors[] = '同一個場次中已經有相同的座位編號。';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE Seat
                         SET show_id = :show_id,
                             seat_number = :seat_number,
                             price = :price,
                             status = :status
                         WHERE seat_id = :seat_id'
                    );
                    $stmt->execute([
                        ':show_id' => $validated['show_id'],
                        ':seat_number' => $validated['seat_number'],
                        ':price' => $validated['price'],
                        ':status' => $validated['status'],
                        ':seat_id' => $seatId,
                    ]);

                    header('Location: /concert_system/manager/seats.php?message=updated');
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('Update seat failed: ' . $exception->getMessage());
                $errors[] = '更新座位失敗，請稍後再試。';
            }
        }

        $editSeat = [
            'seat_id' => $seatId,
            'show_id' => $_POST['show_id'] ?? '',
            'seat_number' => $_POST['seat_number'] ?? '',
            'price' => $_POST['price'] ?? '',
            'status' => $_POST['status'] ?? 'available',
        ];
    } elseif ($action === 'delete') {
        $seatId = positiveInt($_POST['seat_id'] ?? null);

        if (!$seatId) {
            $errors[] = '找不到要刪除的座位。';
        } else {
            try {
                // Delete guard: seats that are sold or reserved should not be deleted.
                $stmt = $pdo->prepare('SELECT status FROM Seat WHERE seat_id = :seat_id');
                $stmt->execute([':seat_id' => $seatId]);
                $seat = $stmt->fetch();

                if (!$seat) {
                    $errors[] = '找不到要刪除的座位。';
                } elseif ($seat['status'] !== 'available') {
                    $errors[] = '只有 available 狀態的座位可以刪除；sold 或 reserved 座位不可刪除。';
                } else {
                    $deleteStmt = $pdo->prepare('DELETE FROM Seat WHERE seat_id = :seat_id');
                    $deleteStmt->execute([':seat_id' => $seatId]);

                    header('Location: /concert_system/manager/seats.php?message=deleted');
                    exit;
                }
            } catch (PDOException $exception) {
                error_log('Delete seat failed: ' . $exception->getMessage());
                $errors[] = '刪除座位失敗，請稍後再試。';
            }
        }
    } else {
        $errors[] = '不支援的操作。';
    }
}

if ($notice !== '' && isset($_GET['created'], $_GET['skipped'])) {
    $notice .= ' 新增 ' . (int) $_GET['created'] . ' 筆，略過重複座位 ' . (int) $_GET['skipped'] . ' 筆。';
}

if ($notice !== '' && isset($_GET['updated'])) {
    $notice .= ' 共更新 ' . (int) $_GET['updated'] . ' 個座位。';
}

// Load show options for filters and forms.
if ($dbReady) {
    try {
        $stmt = $pdo->prepare(
            'SELECT s.show_id, s.show_datetime, s.status, c.title AS concert_title
             FROM ShowDate s
             INNER JOIN Concert c ON c.concert_id = s.concert_id
             ORDER BY s.show_datetime, s.show_id'
        );
        $stmt->execute();
        $shows = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch show options failed: ' . $exception->getMessage());
        $errors[] = '讀取場次下拉選單失敗，請稍後再試。';
    }
}

// Load edit data by GET edit_id.
if ($dbReady && $_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit_id'])) {
    $editId = positiveInt($_GET['edit_id']);

    if ($editId) {
        try {
            $stmt = $pdo->prepare(
                'SELECT seat_id, show_id, seat_number, price, status
                 FROM Seat
                 WHERE seat_id = :seat_id'
            );
            $stmt->execute([':seat_id' => $editId]);
            $editSeat = $stmt->fetch();

            if (!$editSeat) {
                $errors[] = '找不到要編輯的座位。';
            }
        } catch (PDOException $exception) {
            error_log('Fetch seat for edit failed: ' . $exception->getMessage());
            $errors[] = '讀取編輯資料失敗，請稍後再試。';
        }
    } else {
        $errors[] = '編輯座位編號不正確。';
    }
}

// Load seat list. If show_id is selected, list only seats for that show.
if ($dbReady) {
    try {
        $sql = 'SELECT seat.seat_id,
                       seat.show_id,
                       seat.seat_number,
                       seat.price,
                       seat.status,
                       show_date.show_datetime,
                       show_date.status AS show_status,
                       concert.title AS concert_title
                FROM Seat seat
                INNER JOIN ShowDate show_date ON show_date.show_id = seat.show_id
                INNER JOIN Concert concert ON concert.concert_id = show_date.concert_id';
        $params = [];

        if ($filterShowId) {
            $sql .= ' WHERE seat.show_id = :show_id';
            $params[':show_id'] = $filterShowId;
        }

        $sql .= ' ORDER BY show_date.show_datetime, seat.seat_number, seat.seat_id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $seats = $stmt->fetchAll();
    } catch (PDOException $exception) {
        error_log('Fetch seats failed: ' . $exception->getMessage());
        $errors[] = '讀取座位列表失敗，請稍後再試。';
    }
}

// Build chart data from the same seat list shown in the table.
// If a show filter is selected, this chart naturally follows that filter.
foreach ($seats as $seat) {
    $chartKey = (string) $seat['show_id'];

    if (!isset($seatCharts[$chartKey])) {
        $seatCharts[$chartKey] = [
            'show_id' => $seat['show_id'],
            'concert_title' => $seat['concert_title'],
            'show_datetime' => $seat['show_datetime'],
            'show_status' => $seat['show_status'],
            'counts' => [
                'available' => 0,
                'reserved' => 0,
                'sold' => 0,
            ],
            'zones' => [],
            'seats' => [],
        ];
    }

    if (isset($seatCharts[$chartKey]['counts'][$seat['status']])) {
        $seatCharts[$chartKey]['counts'][$seat['status']]++;
    }

    $seatCharts[$chartKey]['seats'][] = $seat;
    $zoneName = chartSeatZone($seat['seat_number']);

    if (!isset($seatCharts[$chartKey]['zones'][$zoneName])) {
        $seatCharts[$chartKey]['zones'][$zoneName] = [
            'name' => $zoneName,
            'counts' => [
                'available' => 0,
                'reserved' => 0,
                'sold' => 0,
            ],
            'seats' => [],
        ];
    }

    if (isset($seatCharts[$chartKey]['zones'][$zoneName]['counts'][$seat['status']])) {
        $seatCharts[$chartKey]['zones'][$zoneName]['counts'][$seat['status']]++;
    }

    $seatCharts[$chartKey]['zones'][$zoneName]['seats'][] = $seat;
}

foreach ($seatCharts as $chartKey => $chart) {
    uksort($seatCharts[$chartKey]['zones'], 'strnatcmp');

    foreach ($seatCharts[$chartKey]['zones'] as $zoneName => $zone) {
        usort($seatCharts[$chartKey]['zones'][$zoneName]['seats'], function ($firstSeat, $secondSeat) {
            return strnatcmp(chartSeatLabel($firstSeat['seat_number']), chartSeatLabel($secondSeat['seat_number']));
        });
    }
}

foreach ($seatCharts as $chart) {
    foreach ($chart['zones'] as $zoneName => $zone) {
        $zoneKey = $chart['show_id'] . '|' . $zoneName;
        $firstSeat = $zone['seats'][0] ?? null;
        $seatZones[$zoneKey] = [
            'show_id' => $chart['show_id'],
            'concert_title' => $chart['concert_title'],
            'show_datetime' => $chart['show_datetime'],
            'zone' => $zoneName,
            'price' => $firstSeat['price'] ?? '',
            'count' => count($zone['seats']),
        ];
    }
}

$isEditing = is_array($editSeat);
$singleFormAction = $isEditing ? 'update' : 'create_single';
$singleFormTitle = $isEditing ? '編輯座位' : '新增單一座位';
$singleFormData = [
    'show_id' => $isEditing ? ($editSeat['show_id'] ?? '') : ($_POST['show_id'] ?? ''),
    'seat_number' => $isEditing ? ($editSeat['seat_number'] ?? '') : ($_POST['seat_number'] ?? ''),
    'price' => $isEditing ? ($editSeat['price'] ?? '') : ($_POST['price'] ?? ''),
    'status' => $isEditing ? ($editSeat['status'] ?? 'available') : ($_POST['status'] ?? 'available'),
];

$batchFormData = [
    'show_id' => $_POST['show_id'] ?? '',
    'seat_prefix' => $_POST['seat_prefix'] ?? '',
    'start_no' => $_POST['start_no'] ?? '1',
    'end_no' => $_POST['end_no'] ?? '20',
    'price' => $_POST['price'] ?? '',
];

$zonePriceFormData = [
    'zone_key' => $_POST['zone_key'] ?? '',
    'zone_price' => $_POST['zone_price'] ?? '',
];
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>座位與票價管理 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .manager-seats {
            margin-top: 34px;
        }

        .seats-panel {
            width: 100%;
            max-width: none;
        }

        .seats-layout,
        .seat-form,
        .filter-form {
            display: grid;
            gap: 16px;
        }

        .seat-form-grid,
        .batch-form-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .filter-form {
            grid-template-columns: minmax(260px, 1fr) auto auto;
            align-items: end;
        }

        .field-wide {
            grid-column: 1 / -1;
        }

        label {
            display: grid;
            gap: 7px;
            font-weight: 800;
        }

        select,
        input {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: var(--text);
            font: inherit;
        }

        select:focus,
        input:focus {
            outline: 2px solid rgba(184, 50, 50, 0.22);
            border-color: var(--accent);
        }

        .seat-actions,
        .table-actions,
        .top-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .sub-panel {
            display: grid;
            gap: 14px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fffaf3;
        }

        .sub-panel h1 {
            margin: 0;
            font-size: 24px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .seats-table {
            width: 100%;
            min-width: 980px;
            border-collapse: collapse;
        }

        .seats-table th,
        .seats-table td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }

        .seats-table th {
            background: #f8f4ed;
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
        }

        .seats-table tr:last-child td {
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

        .seat-chart-section {
            display: grid;
            gap: 14px;
        }

        .seat-chart-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .seat-chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        .legend-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .legend-swatch {
            width: 14px;
            height: 14px;
            border-radius: 4px;
            border: 1px solid rgba(17, 19, 24, 0.16);
        }

        .legend-swatch.is-available,
        .seat-cell.is-available,
        .show-status.is-available {
            background: #2e8b57;
            color: #fff;
        }

        .legend-swatch.is-reserved,
        .seat-cell.is-reserved {
            background: #d9b26f;
            color: var(--ink);
        }

        .legend-swatch.is-sold,
        .seat-cell.is-sold,
        .show-status.is-sold_out {
            background: #b83232;
            color: #fff;
        }

        .show-status.is-ended {
            background: #6a7079;
            color: #fff;
        }

        .seat-chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 18px;
        }

        .seat-chart-card {
            display: grid;
            gap: 12px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
        }

        .seat-chart-header {
            display: grid;
            gap: 8px;
        }

        .seat-chart-title {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .seat-chart-title h3 {
            margin: 0;
            color: var(--ink);
            font-size: 18px;
            line-height: 1.35;
        }

        .show-status {
            display: inline-flex;
            align-items: center;
            min-height: 28px;
            padding: 0 9px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 900;
        }

        .seat-chart-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            color: var(--muted);
            font-size: 14px;
            font-weight: 800;
        }

        .seat-stage {
            display: grid;
            place-items: center;
            min-height: 34px;
            border-radius: 8px;
            background: var(--ink);
            color: #fff;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0;
        }

        .seat-visual-grid {
            display: grid;
            gap: 12px;
            max-height: 360px;
            overflow: auto;
            padding: 4px;
        }

        .seat-zone-block {
            display: grid;
            gap: 8px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fffaf3;
        }

        .seat-zone-heading {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .seat-zone-heading strong {
            color: var(--ink);
            font-size: 15px;
        }

        .seat-zone-counts {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
        }

        .seat-zone-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, 44px);
            gap: 8px;
        }

        .seat-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 38px;
            padding: 0 5px;
            border: 0;
            border-radius: 7px;
            color: #fff;
            font-size: 12px;
            font-weight: 900;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

        @media (max-width: 920px) {
            .seat-form-grid,
            .batch-form-grid,
            .filter-form {
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

    <main class="concert-section manager-seats">
        <div class="section-title">
            <div>
                <p>Seat & Price Management</p>
                <h2>座位與票價管理</h2>
            </div>
            <div class="top-links">
                <a class="secondary-action" href="/concert_system/manager/dashboard.php">返回 Dashboard</a>
                <a class="secondary-action" href="/concert_system/manager/shows.php">前往場次管理</a>
            </div>
        </div>

        <section class="placeholder-card seats-panel">
            <div class="seats-layout">
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

                <form class="filter-form" method="get" action="/concert_system/manager/seats.php">
                    <label>
                        依場次篩選
                        <select name="show_id" <?= !$dbReady ? 'disabled' : '' ?>>
                            <option value="">全部場次</option>
                            <?php foreach ($shows as $show): ?>
                                <option value="<?= h($show['show_id']) ?>" <?= (string) $filterShowId === (string) $show['show_id'] ? 'selected' : '' ?>>
                                    #<?= h($show['show_id']) ?> <?= h($show['concert_title']) ?> /
                                    <?= h($show['show_datetime']) ?> /
                                    <?= h($show['status']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>套用篩選</button>
                    <a class="secondary-action" href="/concert_system/manager/seats.php">清除篩選</a>
                </form>

                <div class="sub-panel seat-chart-section">
                    <div class="seat-chart-toolbar">
                        <h1>座位圖表</h1>
                        <div class="seat-chart-legend" aria-label="座位狀態圖例">
                            <span class="legend-item"><span class="legend-swatch is-available"></span>available 可購買</span>
                            <span class="legend-item"><span class="legend-swatch is-reserved"></span>reserved 已保留</span>
                            <span class="legend-item"><span class="legend-swatch is-sold"></span>sold 已售出</span>
                        </div>
                    </div>

                    <?php if ($seatCharts): ?>
                        <div class="seat-chart-grid">
                            <?php foreach ($seatCharts as $chart): ?>
                                <article class="seat-chart-card">
                                    <div class="seat-chart-header">
                                        <div class="seat-chart-title">
                                            <h3>#<?= h($chart['show_id']) ?> <?= h($chart['concert_title']) ?></h3>
                                            <span class="show-status is-<?= h($chart['show_status']) ?>">
                                                <?= h($chart['show_status']) ?> - <?= h($showStatusLabels[$chart['show_status']] ?? $chart['show_status']) ?>
                                            </span>
                                        </div>
                                        <div class="seat-chart-meta">
                                            <span><?= h($chart['show_datetime']) ?></span>
                                            <span>available <?= h($chart['counts']['available']) ?></span>
                                            <span>reserved <?= h($chart['counts']['reserved']) ?></span>
                                            <span>sold <?= h($chart['counts']['sold']) ?></span>
                                        </div>
                                    </div>

                                    <div class="seat-stage">STAGE</div>
                                    <div class="seat-visual-grid" aria-label="<?= h($chart['concert_title']) ?> 座位圖">
                                        <?php foreach ($chart['zones'] as $zone): ?>
                                            <section class="seat-zone-block">
                                                <div class="seat-zone-heading">
                                                    <strong><?= h($zone['name']) ?></strong>
                                                    <span class="seat-zone-counts">
                                                        <span>A <?= h($zone['counts']['available']) ?></span>
                                                        <span>R <?= h($zone['counts']['reserved']) ?></span>
                                                        <span>S <?= h($zone['counts']['sold']) ?></span>
                                                    </span>
                                                </div>
                                                <div class="seat-zone-grid">
                                                    <?php foreach ($zone['seats'] as $seat): ?>
                                                        <span
                                                            class="seat-cell is-<?= h($seat['status']) ?>"
                                                            title="<?= h($seat['seat_number']) ?> / <?= h($seat['status']) ?> / $<?= h($seat['price']) ?>"
                                                        >
                                                            <?= h(chartSeatLabel($seat['seat_number'])) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </section>
                                        <?php endforeach; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-row">目前沒有座位可以產生圖表。</div>
                    <?php endif; ?>
                </div>

                <div class="sub-panel">
                    <form class="seat-form" method="post" action="/concert_system/manager/seats.php">
                        <input type="hidden" name="action" value="<?= h($singleFormAction) ?>">
                        <?php if ($isEditing): ?>
                            <input type="hidden" name="seat_id" value="<?= h($editSeat['seat_id'] ?? '') ?>">
                        <?php endif; ?>

                        <h1><?= h($singleFormTitle) ?></h1>
                        <div class="seat-form-grid">
                            <label>
                                場次
                                <select name="show_id" required <?= !$dbReady ? 'disabled' : '' ?>>
                                    <option value="">請選擇場次</option>
                                    <?php foreach ($shows as $show): ?>
                                        <option value="<?= h($show['show_id']) ?>" <?= (string) $singleFormData['show_id'] === (string) $show['show_id'] ? 'selected' : '' ?>>
                                            #<?= h($show['show_id']) ?> <?= h($show['concert_title']) ?> / <?= h($show['show_datetime']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                座位編號
                                <input type="text" name="seat_number" value="<?= h($singleFormData['seat_number']) ?>" placeholder="A1" required <?= !$dbReady ? 'disabled' : '' ?>>
                            </label>

                            <label>
                                票價
                                <input type="number" name="price" min="0" step="0.01" value="<?= h($singleFormData['price']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                            </label>

                            <label>
                                狀態
                                <select name="status" required <?= !$dbReady ? 'disabled' : '' ?>>
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option value="<?= h($status) ?>" <?= $singleFormData['status'] === $status ? 'selected' : '' ?>>
                                            <?= h($status) ?> - <?= h($statusLabels[$status]) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>

                        <div class="seat-actions">
                            <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>
                                <?= $isEditing ? '更新座位' : '新增座位' ?>
                            </button>
                            <?php if ($isEditing): ?>
                                <a class="secondary-action" href="/concert_system/manager/seats.php">取消編輯</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div class="sub-panel">
                    <form class="seat-form" method="post" action="/concert_system/manager/seats.php">
                        <input type="hidden" name="action" value="create_batch">
                        <h1>新增座位區</h1>
                        <div class="batch-form-grid">
                            <label>
                                場次
                                <select name="show_id" required <?= !$dbReady ? 'disabled' : '' ?>>
                                    <option value="">請選擇場次</option>
                                    <?php foreach ($shows as $show): ?>
                                        <option value="<?= h($show['show_id']) ?>" <?= (string) $batchFormData['show_id'] === (string) $show['show_id'] ? 'selected' : '' ?>>
                                            #<?= h($show['show_id']) ?> <?= h($show['concert_title']) ?> / <?= h($show['show_datetime']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                座位區名稱
                                <input type="text" name="seat_prefix" value="<?= h($batchFormData['seat_prefix']) ?>" placeholder="A區 / 搖滾區" required <?= !$dbReady ? 'disabled' : '' ?>>
                            </label>

                            <label>
                                起始號碼
                                <input type="number" name="start_no" min="1" step="1" value="<?= h($batchFormData['start_no']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                            </label>

                            <label>
                                結束號碼
                                <input type="number" name="end_no" min="1" step="1" value="<?= h($batchFormData['end_no']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                            </label>

                            <label>
                                票價
                                <input type="number" name="price" min="0" step="0.01" value="<?= h($batchFormData['price']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                            </label>
                        </div>

                        <div class="seat-actions">
                            <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>新增座位區</button>
                        </div>
                    </form>
                </div>

                <div class="sub-panel">
                    <form class="seat-form" method="post" action="/concert_system/manager/seats.php">
                        <input type="hidden" name="action" value="update_zone_price">
                        <h1>設定 / 修改座位區票價</h1>
                        <div class="batch-form-grid">
                            <label class="field-wide">
                                場次與座位區
                                <select name="zone_key" required <?= !$dbReady ? 'disabled' : '' ?>>
                                    <option value="">請選擇場次與座位區</option>
                                    <?php foreach ($seatZones as $zone): ?>
                                        <?php $zoneKey = $zone['show_id'] . '|' . $zone['zone']; ?>
                                        <option value="<?= h($zoneKey) ?>" <?= (string) $zonePriceFormData['zone_key'] === (string) $zoneKey ? 'selected' : '' ?>>
                                            #<?= h($zone['show_id']) ?> <?= h($zone['concert_title']) ?> / <?= h($zone['zone']) ?> / <?= h($zone['count']) ?> 席 / 目前 $<?= h($zone['price']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                新票價
                                <input type="number" name="zone_price" min="0" step="0.01" value="<?= h($zonePriceFormData['zone_price']) ?>" required <?= !$dbReady ? 'disabled' : '' ?>>
                            </label>
                        </div>

                        <div class="seat-actions">
                            <button class="placeholder-link" type="submit" <?= !$dbReady ? 'disabled' : '' ?>>更新座位區票價</button>
                        </div>
                    </form>
                </div>

                <div>
                    <h1>座位列表</h1>
                    <div class="table-wrap">
                        <?php if ($seats): ?>
                            <table class="seats-table">
                                <thead>
                                    <tr>
                                        <th>seat_id</th>
                                        <th>concert title</th>
                                        <th>show_datetime</th>
                                        <th>seat_number</th>
                                        <th>price</th>
                                        <th>status</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($seats as $seat): ?>
                                        <tr>
                                            <td><?= h($seat['seat_id']) ?></td>
                                            <td><?= h($seat['concert_title']) ?></td>
                                            <td><?= h($seat['show_datetime']) ?></td>
                                            <td><?= h($seat['seat_number']) ?></td>
                                            <td><?= h($seat['price']) ?></td>
                                            <td>
                                                <span class="status-pill">
                                                    <?= h($seat['status']) ?> - <?= h($statusLabels[$seat['status']] ?? $seat['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="table-actions">
                                                    <a class="secondary-action" href="/concert_system/manager/seats.php?edit_id=<?= h($seat['seat_id']) ?>">編輯</a>
                                                    <form method="post" action="/concert_system/manager/seats.php" onsubmit="return confirm('確定要刪除此座位嗎？');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="seat_id" value="<?= h($seat['seat_id']) ?>">
                                                        <button class="danger-action" type="submit">刪除</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-row">目前沒有座位資料。</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
