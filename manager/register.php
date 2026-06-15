<?php
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/manager_auth.php';

redirectIfManagerLoggedIn();

$errors = [];
$username = '';
$email = '';
$realName = '';
$birthDate = '';
$phoneNum = '';
$idNumber = '';
$userAddress = '';
$managerExists = true;

if ($pdo === null) {
    $errors[] = '資料庫連線失敗，請檢查 includes/db_config.php 設定。';
} else {
    $stmt = $pdo->query("SELECT COUNT(*) FROM User WHERE role = 'manager'");
    $managerExists = ((int) $stmt->fetchColumn()) > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo !== null) {
    if ($managerExists) {
        $errors[] = '系統已存在管理員，不能再從公開頁面註冊。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $realName = trim($_POST['real_name'] ?? '');
        $birthDate = trim($_POST['birth_date'] ?? '');
        $phoneNum = trim($_POST['phone_num'] ?? '');
        $idNumber = strtoupper(trim($_POST['id_number'] ?? ''));
        $userAddress = trim($_POST['user_address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($username === '' || strlen($username) > 50) {
            $errors[] = '帳號必填，且長度不可超過 50 個字。';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = '請輸入正確的 Email。';
        }

        if ($realName === '' || strlen($realName) > 50) {
            $errors[] = '真實姓名必填，且長度不可超過 50 個字。';
        }

        if ($birthDate === '') {
            $errors[] = '生日必填。';
        }

        if ($phoneNum === '' || strlen($phoneNum) > 20) {
            $errors[] = '電話必填，且長度不可超過 20 個字。';
        }

        if ($idNumber === '' || strlen($idNumber) > 20) {
            $errors[] = '身分證字號必填，且長度不可超過 20 個字。';
        } elseif (!preg_match('/^[A-Za-z]/', $idNumber)) {
            $errors[] = '身分證字號首字必須為英文字母。';
        }

        if (strlen($password) < 8) {
            $errors[] = '密碼至少需要 8 個字元。';
        }

        if ($password !== $confirmPassword) {
            $errors[] = '兩次輸入的密碼不一致。';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM User WHERE username = ? OR email = ? OR id_number = ?');
            $stmt->execute([$username, $email, $idNumber]);

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = '帳號、Email 或身分證字號已被使用。';
            }
        }

        if (!$errors) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO User
                 (username, real_name, birth_date, phone_num, id_number, email, user_address, password, role)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'manager')"
            );
            $stmt->execute([
                $username,
                $realName,
                $birthDate,
                $phoneNum,
                $idNumber,
                $email,
                $userAddress,
                $passwordHash,
            ]);

            $_SESSION['manager_notice'] = '管理員註冊成功，請登入。';
            header('Location: ../customer/login.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理員註冊 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="placeholder-page">
    <main class="placeholder-card auth-card">
        <p class="auth-kicker">Manager</p>
        <h1>管理員註冊</h1>

        <?php if ($pdo === null): ?>
            <div class="auth-alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= h($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php elseif ($managerExists): ?>
            <div class="auth-alert">系統已存在管理員，請直接登入。</div>
            <a class="placeholder-link" href="../customer/login.php">前往登入</a>
        <?php else: ?>
            <?php if ($errors): ?>
                <div class="auth-alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= h($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="register.php" novalidate>
                <label>
                    <span>帳號</span>
                    <input type="text" name="username" maxlength="50" required value="<?= h($username) ?>">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" maxlength="100" required value="<?= h($email) ?>">
                </label>
                <label>
                    <span>真實姓名</span>
                    <input type="text" name="real_name" maxlength="50" required value="<?= h($realName) ?>">
                </label>
                <label>
                    <span>生日</span>
                    <input type="date" name="birth_date" required value="<?= h($birthDate) ?>">
                </label>
                <label>
                    <span>電話</span>
                    <input type="text" name="phone_num" maxlength="20" required value="<?= h($phoneNum) ?>">
                </label>
                <label>
                    <span>身分證字號</span>
                    <input type="text" name="id_number" maxlength="20" pattern="[A-Za-z].*" title="第一個字元請輸入英文字母" required value="<?= h($idNumber) ?>">
                </label>
                <label>
                    <span>地址</span>
                    <input type="text" name="user_address" maxlength="255" value="<?= h($userAddress) ?>">
                </label>
                <label>
                    <span>密碼</span>
                    <input type="password" name="password" minlength="8" required>
                </label>
                <label>
                    <span>確認密碼</span>
                    <input type="password" name="confirm_password" minlength="8" required>
                </label>
                <button class="placeholder-link" type="submit">建立管理員</button>
            </form>
            <p class="auth-switch">已有帳號？<a href="../customer/login.php">登入管理後台</a></p>
        <?php endif; ?>
    </main>
</body>
</html>
