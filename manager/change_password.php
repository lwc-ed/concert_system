<?php
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/manager_auth.php';

requireManager();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($pdo === null) {
        $errors[] = '資料庫連線失敗，請檢查 includes/db_config.php 設定。';
    }

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $errors[] = '請完整填寫所有欄位。';
    }

    if (strlen($newPassword) < 8) {
        $errors[] = '新密碼至少需要 8 個字元。';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = '兩次輸入的新密碼不一致。';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT password FROM User WHERE user_id = ? AND role = 'manager'");
        $stmt->execute([$_SESSION['manager_id']]);
        $manager = $stmt->fetch();

        if (!$manager || !password_verify($currentPassword, $manager['password'])) {
            $errors[] = '目前密碼錯誤。';
        } else {
            $stmt = $pdo->prepare('UPDATE User SET password = ? WHERE user_id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['manager_id']]);
            $success = '密碼已更新。';
        }
    }
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>修改密碼 | ConcertNow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="placeholder-page">
    <main class="placeholder-card auth-card">
        <p class="auth-kicker">Manager</p>
        <h1>修改密碼</h1>

        <?php if ($success !== ''): ?>
            <div class="auth-success"><?= h($success) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="auth-alert">
                <?php foreach ($errors as $error): ?>
                    <p><?= h($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="auth-form" method="post" action="change_password.php" novalidate>
            <label>
                <span>目前密碼</span>
                <input type="password" name="current_password" required>
            </label>
            <label>
                <span>新密碼</span>
                <input type="password" name="new_password" minlength="8" required>
            </label>
            <label>
                <span>確認新密碼</span>
                <input type="password" name="confirm_password" minlength="8" required>
            </label>
            <button class="placeholder-link" type="submit">更新密碼</button>
        </form>

        <p class="auth-switch"><a href="dashboard.php">回管理後台</a></p>
    </main>
</body>
</html>
