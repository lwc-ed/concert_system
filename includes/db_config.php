<?php
// Database connection setting.
// Keep this file local-environment friendly: if MySQL is not ready yet, pages can
// still fall back to mock data instead of stopping with a fatal error.
$host = '127.0.0.1';
$db = 'concert_system';
$user = 'DBfinal';
$pass = 'DB123456'; // Update this to match each teammate's local MySQL account.
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
