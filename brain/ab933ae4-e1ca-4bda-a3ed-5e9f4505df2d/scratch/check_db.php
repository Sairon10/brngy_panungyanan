<?php
require_once 'config.php';
$pdo = get_db_connection();

echo "--- USERS TABLE ---\n";
foreach($pdo->query("SELECT id, full_name, role, email, is_active FROM users") as $r) {
    echo "ID: {$r['id']} | Name: {$r['full_name']} | Role: {$r['role']} | Email: {$r['email']} | Active: {$r['is_active']}\n";
}

echo "\n--- RESIDENTS TABLE ---\n";
foreach($pdo->query("SELECT id, user_id, address, phone FROM residents") as $r) {
    echo "ID: {$r['id']} | UserID: {$r['user_id']} | Address: {$r['address']}\n";
}

echo "\n--- RESIDENT_RECORDS TABLE ---\n";
foreach($pdo->query("SELECT id, full_name, email FROM resident_records") as $r) {
    echo "ID: {$r['id']} | Name: {$r['full_name']} | Email: {$r['email']}\n";
}
