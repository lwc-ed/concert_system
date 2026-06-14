<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php?redirect=edit_member.php');
    exit;
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function textLength($value) {
    return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
}

function editMemberDateText($value) {
    if (!$value) {
        return '';
    }

    $timestamp = strtotime((string) $value);
    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

$stylePath = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($stylePath) ? filemtime($stylePath) : time();
$customerId = (int) $_SESSION['customer_id'];
$today = date('Y-m-d');
$member = null;
$errors = [];

if ($pdo === null) {
    $errors[] = '目前無法連線到資料庫，請確認 MAMP / MySQL 已啟動，且 includes/db_config.php 設定正確。';
} else {
    try {
        $memberStatement = $pdo->prepare(
            'SELECT username, real_name, birth_date, phone_num, id_number, email, user_address
             FROM `User`
             WHERE user_id = :user_id AND role = "customer"
             LIMIT 1'
        );
        $memberStatement->execute(['user_id' => $customerId]);
        $member = $memberStatement->fetch();

        if (!$member) {
            unset($_SESSION['customer_id'], $_SESSION['customer_username']);
            header('Location: login.php?redirect=edit_member.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $birthDate = trim($_POST['birth_date'] ?? '');
            $phoneNum = trim($_POST['phone_num'] ?? '');
            $userAddress = trim($_POST['user_address'] ?? '');

            $member['email'] = $email;
            $member['birth_date'] = $birthDate;
            $member['phone_num'] = $phoneNum;
            $member['user_address'] = $userAddress;

            if ($email === '' || $birthDate === '' || $phoneNum === '') {
                $errors[] = '請完整填寫必填資料。';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || textLength($email) > 100) {
                $errors[] = '請輸入有效且不超過 100 個字的 Email。';
            } elseif (strtotime($birthDate) === false || strtotime($birthDate) > strtotime($today)) {
                $errors[] = '生日不可填寫未來日期。';
            } elseif (textLength($phoneNum) > 20) {
                $errors[] = '電話號碼不可超過 20 個字。';
            } elseif (textLength($userAddress) > 255) {
                $errors[] = '地址不可超過 255 個字。';
            }

            if (!$errors) {
                $duplicateStatement = $pdo->prepare(
                    'SELECT user_id
                     FROM `User`
                     WHERE user_id <> :user_id
                       AND email = :email
                     LIMIT 1'
                );
                $duplicateStatement->execute([
                    'user_id' => $customerId,
                    'email' => $email,
                ]);

                if ($duplicateStatement->fetch()) {
                    $errors[] = '此 Email 已被其他會員使用。';
                }
            }

            if (!$errors) {
                $updateStatement = $pdo->prepare(
                    'UPDATE `User`
                     SET birth_date = :birth_date,
                         phone_num = :phone_num,
                         email = :email,
                         user_address = :user_address
                     WHERE user_id = :user_id AND role = "customer"'
                );
                $updateStatement->execute([
                    'birth_date' => $birthDate,
                    'phone_num' => $phoneNum,
                    'email' => $email,
                    'user_address' => $userAddress !== '' ? $userAddress : null,
                    'user_id' => $customerId,
                ]);

                header('Location: member.php?updated=1');
                exit;
            }
        }
    } catch (PDOException $exception) {
        $errors[] = '會員資料處理失敗：' . $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>編輯會員資料 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= h($styleVersion) ?>">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="../index.php" aria-label="ConcertNow 首頁">
            <span class="brand-mark">CN</span>
            <span>ConcertNow</span>
        </a>

        <nav class="main-nav" aria-label="主要導覽">
            <a href="member.php">會員中心</a>
            <a href="../index.php">回首頁</a>
            <a class="login-button" href="logout.php">登出</a>
        </nav>
    </header>

    <main class="member-main">
        <section class="member-hero" aria-labelledby="edit-member-title">
            <div>
                <p class="member-kicker">Edit Profile</p>
                <h1 id="edit-member-title">編輯會員資料</h1>
                <span>以下資料由目前登入會員在 User table 的紀錄載入。</span>
            </div>
        </section>

        <section class="member-panel">
            <?php if ($errors): ?>
                <div class="member-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= h($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($member): ?>
                <div class="member-section-title">
                    <p>Account Data</p>
                    <h2>會員完整資料</h2>
                </div>

                <form class="auth-form member-edit-form" action="edit_member.php" method="post">
                    <label for="username">帳號</label>
                    <input id="username" type="text" value="<?= h($member['username']) ?>" readonly aria-describedby="readonly-note">

                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= h($member['email']) ?>" maxlength="100" autocomplete="email" required>

                    <label for="real_name">姓名</label>
                    <input id="real_name" type="text" value="<?= h($member['real_name']) ?>" readonly aria-describedby="readonly-note">

                    <label for="birth_date">生日</label>
                    <input id="birth_date" name="birth_date" type="date" value="<?= h(editMemberDateText($member['birth_date'])) ?>" max="<?= h($today) ?>" required>

                    <label for="phone_num">電話號碼</label>
                    <input id="phone_num" name="phone_num" type="tel" value="<?= h($member['phone_num']) ?>" maxlength="20" autocomplete="tel" required>

                    <label for="id_number">身分證字號</label>
                    <input id="id_number" type="text" value="<?= h($member['id_number']) ?>" readonly aria-describedby="readonly-note">

                    <label for="user_address">地址</label>
                    <input id="user_address" name="user_address" type="text" value="<?= h($member['user_address']) ?>" maxlength="255" autocomplete="street-address">

                    <div class="member-edit-actions">
                        <a class="secondary-action" href="member.php">取消</a>
                        <button class="auth-submit" type="submit">儲存會員資料</button>
                    </div>
                </form>

                <p class="member-security-note" id="readonly-note">帳號、姓名與身分證字號僅供顯示，不可修改。密碼不會從資料庫顯示於頁面。</p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
