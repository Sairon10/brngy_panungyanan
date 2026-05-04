<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$stmt = $pdo->query('SHOW TABLES');
while($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo $row[0] . "\n";
}
