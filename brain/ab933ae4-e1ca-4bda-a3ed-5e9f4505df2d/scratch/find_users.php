<?php
require_once 'config.php';
$pdo = get_db_connection();

$names = ['Sairon Mark Manalad', 'Francis John Ramelb'];

foreach ($names as $name) {
    echo "Searching for: $name\n";
    
    // Check main users/residents
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE full_name LIKE ?");
    $stmt->execute(["%$name%"]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        echo "FOUND USER: ID={$u['id']}, Name={$u['full_name']}, Email={$u['email']}\n";
    }

    // Check family members
    $stmt = $pdo->prepare("SELECT id, full_name FROM family_members WHERE full_name LIKE ?");
    $stmt->execute(["%$name%"]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($members as $m) {
        echo "FOUND FAMILY MEMBER: ID={$m['id']}, Name={$m['full_name']}\n";
    }
    echo "-------------------\n";
}
