<?php
require_once __DIR__ . '/../includes/manager_auth.php';

unset(
    $_SESSION['manager_id'],
    $_SESSION['manager_username'],
    $_SESSION['manager_role'],
    $_SESSION['manager_login_captcha']
);

$_SESSION['manager_notice'] = '你已登出管理後台。';
header('Location: ../customer/login.php');
exit;
