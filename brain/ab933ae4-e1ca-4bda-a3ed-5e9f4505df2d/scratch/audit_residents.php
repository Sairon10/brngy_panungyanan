<?php
require_once 'config.php';
$pdo = get_db_connection();

echo "--- USERS TABLE ---\n";
$stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role = 'resident'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- RESIDENTS TABLE ---\n";
$stmt = $pdo->query("SELECT user_id, address FROM residents");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- FAMILY MEMBERS TABLE ---\n";
$stmt = $pdo->query("SELECT id, full_name, user_id FROM family_members");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- RESIDENT RECORDS TABLE ---\n";
$stmt = $pdo->query("SELECT id, full_name FROM resident_records");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
