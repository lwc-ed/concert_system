<?php
// Copy this file to includes/db_config.php and update credentials for your local MySQL.
$host = '127.0.0.1';
$db = 'concert_system';
$user = 'root';
$pass = 'root';
$charset = 'utf8mb4';
$pdo = null;
$dbConnectionError = null;

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $exception) {
    $dbConnectionError = $exception->getMessage();
}
