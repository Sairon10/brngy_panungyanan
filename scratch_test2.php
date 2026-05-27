<?php
require 'config.php';
$pdo = get_db_connection();

echo "Users:\n";
$stmt = $pdo->query("SELECT id, full_name, first_name, last_name, middle_name FROM users WHERE full_name LIKE '%Sairon%' OR first_name LIKE '%Sairon%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "Family Members:\n";
$stmt = $pdo->query("SELECT id, user_id, full_name, first_name, last_name, middle_name FROM family_members WHERE full_name LIKE '%Sairon%' OR first_name LIKE '%Sairon%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
