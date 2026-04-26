<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();

// List all Sairons to be sure
$stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE full_name LIKE '%Sairon%'");
$stmt->execute();
$users = $stmt->fetchAll();

echo "Users found:\n";
foreach ($users as $u) {
    echo "ID: " . $u['id'] . " | Name: '" . $u['full_name'] . "' | Email: '" . $u['email'] . "'\n";
}
?>
