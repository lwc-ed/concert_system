<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function isManagerLoggedIn()
{
    return isset($_SESSION['manager_id']) && ($_SESSION['manager_role'] ?? '') === 'manager';
}

function requireManager()
{
    if (!isManagerLoggedIn()) {
        header('Location: ../customer/login.php');
        exit;
    }
}

function redirectIfManagerLoggedIn()
{
    if (isManagerLoggedIn()) {
        header('Location: dashboard.php');
        exit;
    }
}
