<?php
// 資料庫連線設定
$host = '127.0.0.1';
$db   = 'concert_system';
$user = 'root';
$pass = '你的密碼'; // 記得更新成全組統一的密碼
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}
?>