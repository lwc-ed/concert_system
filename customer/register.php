<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function textLength($value) {
    return function_exists('mb_strlen') ? mb_strlen((string) $value, 'UTF-8') : strlen((string) $value);
}

$stylePath = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($stylePath) ? filemtime($stylePath) : time();
$registerError = null;
$today = date('Y-m-d');

if (isset($_SESSION['customer_id'])) {
    header('Location: member.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $realName = trim($_POST['real_name'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $phoneNum = trim($_POST['phone_num'] ?? '');
    $idNumber = strtoupper(trim($_POST['id_number'] ?? ''));
    $userAddress = trim($_POST['user_address'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($pdo === null) {
        $registerError = '目前無法連線到資料庫，請確認 MAMP / MySQL 已啟動，且 includes/db_config.php 設定正確。';
    } elseif ($username === '' || $email === '' || $realName === '' || $birthDate === '' || $phoneNum === '' || $idNumber === '' || $password === '' || $confirmPassword === '') {
        $registerError = '請完整填寫註冊資料。';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = '請輸入有效的 Email。';
    } elseif (textLength($username) < 3 || textLength($username) > 50) {
        $registerError = '帳號長度需為 3 到 50 個字。';
    } elseif (textLength($realName) > 50) {
        $registerError = '真實姓名不可超過 50 個字。';
    } elseif (strtotime($birthDate) === false || strtotime($birthDate) > strtotime($today)) {
        $registerError = '生日不可填寫未來日期。';
    } elseif (textLength($phoneNum) > 20) {
        $registerError = '電話號碼不可超過 20 個字。';
    } elseif (textLength($idNumber) > 20) {
        $registerError = '身分證字號不可超過 20 個字。';
    } elseif (!preg_match('/^[A-Za-z]/', $idNumber)) {
        $registerError = '身分證字號首字必須為英文字母。';
    } elseif (textLength($userAddress) > 255) {
        $registerError = '地址不可超過 255 個字。';
    } elseif (textLength($password) < 6) {
        $registerError = '密碼至少需要 6 個字。';
    } elseif ($password !== $confirmPassword) {
        $registerError = '兩次輸入的密碼不一致。';
    } else {
        try {
            $checkStatement = $pdo->prepare(
                'SELECT user_id
                 FROM `User`
                 WHERE username = :username OR email = :email OR id_number = :id_number
                 LIMIT 1'
            );
            $checkStatement->execute([
                'username' => $username,
                'email' => $email,
                'id_number' => $idNumber,
            ]);

            if ($checkStatement->fetch()) {
                $registerError = '此帳號、Email 或身分證字號已被使用。';
            } else {
                $insertStatement = $pdo->prepare(
                    'INSERT INTO `User`
                     (username, real_name, birth_date, phone_num, id_number, email, user_address, password, role)
                     VALUES
                     (:username, :real_name, :birth_date, :phone_num, :id_number, :email, :user_address, :password, "customer")'
                );
                $insertStatement->execute([
                    'username' => $username,
                    'real_name' => $realName,
                    'birth_date' => $birthDate,
                    'phone_num' => $phoneNum,
                    'id_number' => $idNumber,
                    'email' => $email,
                    'user_address' => $userAddress !== '' ? $userAddress : null,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $_SESSION['customer_id'] = (int) $pdo->lastInsertId();
                $_SESSION['customer_username'] = $username;

                header('Location: member.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $registerError = '此帳號、Email 或身分證字號已被使用。';
            } else {
                $registerError = '註冊失敗：' . $exception->getMessage();
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
    <title>會員註冊 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= h($styleVersion) ?>">
</head>
<body class="auth-page">
    <header class="site-header">
        <a class="brand" href="../index.php" aria-label="ConcertNow 首頁">
            <span class="brand-mark">CN</span>
            <span>ConcertNow</span>
        </a>

        <nav class="main-nav" aria-label="主要導覽">
            <a href="../index.php#concerts">近期演唱會</a>
            <a class="login-button" href="login.php">會員登入</a>
        </nav>
    </header>

    <main class="auth-main">
        <section class="auth-card" aria-labelledby="register-title">
            <div class="auth-heading">
                <p>Member Register</p>
                <h1 id="register-title">會員註冊</h1>
                <span>建立帳號後即可登入會員中心，之後可查看訂票紀錄。</span>
            </div>

            <?php if ($registerError): ?>
                <p class="auth-alert"><?= h($registerError) ?></p>
            <?php endif; ?>

            <form class="auth-form" action="register.php" method="post">
                <label for="username">帳號</label>
                <input id="username" name="username" type="text" placeholder="請輸入 3 到 50 個字的帳號" autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>" maxlength="50" required>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" placeholder="請輸入 Email" autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>" required>

                <label for="real_name">真實姓名</label>
                <input id="real_name" name="real_name" type="text" placeholder="請輸入真實姓名" value="<?= h($_POST['real_name'] ?? '') ?>" maxlength="50" required>

                <label for="birth_date">生日</label>
                <input id="birth_date" name="birth_date" type="date" value="<?= h($_POST['birth_date'] ?? '') ?>" max="<?= h($today) ?>" required>

                <label for="phone_num">電話號碼</label>
                <input id="phone_num" name="phone_num" type="tel" placeholder="請輸入電話號碼" autocomplete="tel" value="<?= h($_POST['phone_num'] ?? '') ?>" maxlength="20" required>

                <label for="id_number">身分證字號</label>
                <input id="id_number" name="id_number" type="text" placeholder="請輸入身分證字號或護照號碼，首字需為英文字母" value="<?= h($_POST['id_number'] ?? '') ?>" maxlength="20" pattern="[A-Za-z].*" title="第一個字元請輸入英文字母" required>

                <label for="user_address">地址</label>
                <input id="user_address" name="user_address" type="text" placeholder="可留空" autocomplete="street-address" value="<?= h($_POST['user_address'] ?? '') ?>" maxlength="255">

                <label for="password">密碼</label>
                <input id="password" name="password" type="password" placeholder="至少 6 個字" autocomplete="new-password" minlength="6" required>

                <label for="confirm_password">確認密碼</label>
                <input id="confirm_password" name="confirm_password" type="password" placeholder="請再次輸入密碼" autocomplete="new-password" minlength="6" required>

                <button class="auth-submit" type="submit">建立會員帳號</button>
            </form>

            <div class="auth-links" aria-label="會員註冊相關連結">
                <a class="secondary-action" href="login.php">已有帳號，前往登入</a>
                <a class="auth-text-link" href="../index.php">先看看近期演唱會</a>
            </div>
        </section>
    </main>
</body>
</html>
