<?php
// 引入剛剛寫好的連線檔
include_once __DIR__ . '/../includes/db_config.php';

// 撈出所有演唱會
$stmt = $pdo->query("SELECT * FROM Concert");
$concerts = $stmt->fetchAll();

foreach ($concerts as $row) {
    echo "<h1>" . htmlspecialchars($row['title']) . "</h1>";
    echo "<p>" . htmlspecialchars($row['description']) . "</p>";
}
?>