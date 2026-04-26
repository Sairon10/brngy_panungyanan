<?php
require 'config.php';
$pdo = get_db_connection();
echo "Checking resident_records for admin...\n";
$stmt = $pdo->prepare("SELECT * FROM resident_records WHERE full_name LIKE '%Admin%' OR email LIKE '%admin%'");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\nChecking residents for admin users...\n";
$stmt = $pdo->query("SELECT u.id, u.full_name, r.id as res_id FROM users u LEFT JOIN residents r ON r.user_id = u.id WHERE u.role = 'admin'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
