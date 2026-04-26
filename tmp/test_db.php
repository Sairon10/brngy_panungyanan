<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$stmt = $pdo->query('SELECT id, email, full_name FROM users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
