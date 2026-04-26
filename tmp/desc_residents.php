<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$stmt = $pdo->query('DESC residents');
while($row = $stmt->fetch()) {
    print_r($row);
}
