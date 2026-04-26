<?php
require_once 'config.php';
$pdo = get_db_connection();
$stmt = $pdo->query("SELECT id, full_name, email, role, is_active FROM users");
$users = $stmt->fetchAll();
echo "ID | Name | Email | Role | Active\n";
echo "---|---|---|---|---\n";
foreach ($users as $u) {
    echo "{$u['id']} | {$u['full_name']} | {$u['email']} | {$u['role']} | {$u['is_active']}\n";
}
