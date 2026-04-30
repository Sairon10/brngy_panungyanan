<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=barangay_system;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('DESCRIBE resident_records');
$res = $stmt->fetchAll();
foreach($res as $r) {
    echo $r[0] . " ";
}
