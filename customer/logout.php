<?php
session_start();

unset($_SESSION['customer_id'], $_SESSION['customer_username']);

header('Location: login.php');
exit;
