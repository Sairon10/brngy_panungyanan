<?php
require_once 'config.php';
$pdo = get_db_connection();
$stmt = $pdo->query('SHOW TABLES');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
