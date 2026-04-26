<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

echo "Resident Records:\n";
$rr = $pdo->query('SELECT id, full_name, LENGTH(full_name) as len FROM resident_records')->fetchAll();
foreach ($rr as $r) {
    echo "ID: {$r['id']}, Name: '{$r['full_name']}', Len: {$r['len']}\n";
}

echo "\nUsers:\n";
$uu = $pdo->query('SELECT id, full_name, LENGTH(full_name) as len FROM users')->fetchAll();
foreach ($uu as $u) {
    echo "ID: {$u['id']}, Name: '{$u['full_name']}', Len: {$u['len']}\n";
}
