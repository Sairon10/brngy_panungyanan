<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$stmt = $pdo->query('DESC resident_records');
while($row = $stmt->fetch()) {
    print_r($row);
}
