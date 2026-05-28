<?php
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/manager_auth.php';

redirectIfManagerLoggedIn();

$errors = [];
$username = '';
$email = '';
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
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($username === '' || strlen($username) > 50) {
            $errors[] = '帳號必填，且長度不可超過 50 個字。';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = '請輸入正確的 Email。';
        }

        if (strlen($password) < 8) {
            $errors[] = '密碼至少需要 8 個字元。';
        }

        if ($password !== $confirmPassword) {
            $errors[] = '兩次輸入的密碼不一致。';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM User WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = '帳號或 Email 已被使用。';
            }
        }

        if (!$errors) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO User (username, email, password, role) VALUES (?, ?, ?, 'manager')"
            );
            $stmt->execute([$username, $email, $passwordHash]);

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
