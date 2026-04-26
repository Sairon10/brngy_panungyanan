<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

echo "Checking Users:\n";
$users = $pdo->query('SELECT id, full_name, email FROM users')->fetchAll();
foreach ($users as $u) {
    echo "ID: {$u['id']}, Name: {$u['full_name']}, Email: {$u['email']}\n";
}

echo "\nChecking Family Members:\n";
$fm = $pdo->query('SELECT user_id, full_name, relationship FROM family_members')->fetchAll();
foreach ($fm as $f) {
    echo "User ID: {$f['user_id']}, Name: {$f['full_name']}, Rel: {$f['relationship']}\n";
}

echo "\nChecking Resident Records:\n";
$rr = $pdo->query('SELECT id, full_name, email FROM resident_records')->fetchAll();
foreach ($rr as $r) {
    echo "ID: {$r['id']}, Name: {$r['full_name']}, Email: {$r['email']}\n";
}
