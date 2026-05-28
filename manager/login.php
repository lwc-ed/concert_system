<?php
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/manager_auth.php';

redirectIfManagerLoggedIn();

$errors = [];
$account = '';
$notice = $_SESSION['manager_notice'] ?? '';
unset($_SESSION['manager_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = trim($_POST['account'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');
    $expectedCaptcha = $_SESSION['manager_login_captcha'] ?? '';
    unset($_SESSION['manager_login_captcha']);

    if ($pdo === null) {
        $errors[] = '資料庫連線失敗，請檢查 includes/db_config.php 設定。';
    }

    if ($account === '' || $password === '' || $captcha === '') {
        $errors[] = '請輸入帳號、密碼與驗證碼。';
    }

    if ($expectedCaptcha === '' || strcasecmp($captcha, $expectedCaptcha) !== 0) {
        $errors[] = '驗證碼錯誤，請重新輸入。';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            "SELECT user_id, username, password, role FROM User
             WHERE (username = ? OR email = ?) AND role = 'manager'
             LIMIT 1"
        );
        $stmt->execute([$account, $account]);
        $manager = $stmt->fetch();

        $passwordMatches = $manager
            && (password_verify($password, $manager['password']) || hash_equals((string) $manager['password'], $password));

        if (!$passwordMatches) {
            $errors[] = '管理員帳號或密碼錯誤。';
        } else {
            session_regenerate_id(true);
            $_SESSION['manager_id'] = (int) $manager['user_id'];
            $_SESSION['manager_username'] = $manager['username'];
            $_SESSION['manager_role'] = 'manager';

            header('Location: dashboard.php');
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
    <title>管理員登入 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="placeholder-page">
    <main class="placeholder-card auth-card">
        <p class="auth-kicker">Manager</p>
        <h1>管理員登入</h1>

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

        <form class="auth-form" method="post" action="login.php" novalidate>
            <label>
                <span>帳號或 Email</span>
                <input type="text" name="account" required value="<?= h($account) ?>">
            </label>
            <label>
                <span>密碼</span>
                <input type="password" name="password" required>
            </label>
            <label>
                <span>登入驗證碼</span>
                <div class="captcha-row">
                    <input type="text" name="captcha" maxlength="6" required autocomplete="off">
                    <img src="captcha.php?ts=<?= time() ?>" alt="登入驗證碼">
                </div>
            </label>
            <button class="placeholder-link" type="submit">登入後台</button>
        </form>
        <p class="auth-switch">第一次使用？<a href="register.php">建立第一位管理員</a></p>
    </main>
</body>
</html>
