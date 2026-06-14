<?php
session_start();
require_once __DIR__ . '/../includes/db_config.php';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function safeCustomerRedirect($value) {
    $target = trim((string) $value);

    if ($target === '' || strpos($target, ':') !== false || strpos($target, '//') === 0) {
        return 'member.php';
    }

    return $target;
}

$stylePath = __DIR__ . '/../assets/css/style.css';
$styleVersion = file_exists($stylePath) ? filemtime($stylePath) : time();
$redirect = safeCustomerRedirect($_GET['redirect'] ?? $_POST['redirect'] ?? 'member.php');
$loginError = null;
$loginNotice = $_SESSION['manager_notice'] ?? '';
unset($_SESSION['manager_notice']);

if (isset($_SESSION['manager_id'])) {
    header('Location: ../manager/dashboard.php');
    exit;
}

if (isset($_SESSION['customer_id'])) {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account = trim($_POST['account'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $captcha = strtoupper(trim((string) ($_POST['captcha'] ?? '')));
    $expectedCaptcha = strtoupper((string) ($_SESSION['manager_login_captcha'] ?? ''));

    if ($pdo === null) {
        $loginError = '目前無法連線到資料庫，請稍後再試。';
    } elseif ($account === '' || $password === '') {
        $loginError = '請輸入帳號與密碼。';
    } elseif ($captcha === '' || $expectedCaptcha === '' || !hash_equals($expectedCaptcha, $captcha)) {
        $loginError = '驗證碼錯誤，請重新輸入。';
        unset($_SESSION['manager_login_captcha']);
    } else {
        unset($_SESSION['manager_login_captcha']);
        $statement = $pdo->prepare(
            'SELECT user_id, username, email, password, role
             FROM `User`
             WHERE username = :account OR email = :account
             LIMIT 1'
        );
        $statement->execute(['account' => $account]);
        $user = $statement->fetch();

        $passwordMatches = $user
            && (password_verify($password, $user['password']) || hash_equals((string) $user['password'], $password));

        if (!$passwordMatches) {
            $loginError = '帳號或密碼錯誤。';
        } elseif ($user['role'] === 'manager') {
            unset($_SESSION['customer_id'], $_SESSION['customer_username']);
            $_SESSION['manager_id'] = (int) $user['user_id'];
            $_SESSION['manager_username'] = $user['username'];
            $_SESSION['manager_role'] = $user['role'];
            header('Location: ../manager/dashboard.php');
            exit;
        } elseif ($user['role'] === 'customer') {
            unset($_SESSION['manager_id'], $_SESSION['manager_username'], $_SESSION['manager_role']);
            $_SESSION['customer_id'] = (int) $user['user_id'];
            $_SESSION['customer_username'] = $user['username'];
            header('Location: ' . $redirect);
            exit;
        } else {
            $loginError = '此帳號角色無法登入前台。';
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>會員登入 | ConcertNow</title>
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
            <a class="login-button" href="../index.php">回首頁</a>
        </nav>
    </header>

    <main class="auth-main">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-heading">
                <p>Member Login</p>
                <h1 id="login-title">會員登入</h1>
                <span>登入後即可查看會員資訊與訂票紀錄。</span>
            </div>

            <?php if ($loginError): ?>
                <p class="auth-alert"><?= h($loginError) ?></p>
            <?php endif; ?>

            <?php if ($loginNotice !== ''): ?>
                <p class="auth-alert auth-success"><?= h($loginNotice) ?></p>
            <?php endif; ?>

            <form class="auth-form" action="login.php" method="post">
                <input type="hidden" name="redirect" value="<?= h($redirect) ?>">

                <label for="account">帳號</label>
                <input id="account" name="account" type="text" placeholder="請輸入帳號或 Email" autocomplete="username" value="<?= h($_POST['account'] ?? '') ?>" required>

                <label for="password">密碼</label>
                <input id="password" name="password" type="password" placeholder="請輸入密碼" autocomplete="current-password" required>

                <label for="captcha">驗證碼</label>
                <div class="captcha-row">
                    <img src="../manager/captcha.php?v=<?= h((string) time()) ?>" alt="登入驗證碼">
                    <input id="captcha" name="captcha" type="text" maxlength="5" autocomplete="off" placeholder="請輸入驗證碼" required>
                </div>

                <button class="auth-submit" type="submit">登入</button>
            </form>

            <div class="auth-links" aria-label="會員登入相關連結">
                <a class="secondary-action" href="register.php">註冊帳號</a>
                <a class="auth-text-link" href="#">忘記密碼？</a>
            </div>
        </section>
    </main>
</body>
</html>
