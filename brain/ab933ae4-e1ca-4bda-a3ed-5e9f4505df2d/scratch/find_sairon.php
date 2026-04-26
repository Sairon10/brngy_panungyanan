<?php
require_once 'config.php';
$pdo = get_db_connection();

$search = "Sairon";
echo "Searching for: $search\n";

$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE full_name LIKE ?");
$stmt->execute(["%$search%"]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt = $pdo->prepare("SELECT id, full_name FROM family_members WHERE full_name LIKE ?");
$stmt->execute(["%$search%"]);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
