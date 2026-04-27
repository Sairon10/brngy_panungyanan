<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'config.php';
$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = 'admin@panungyanan.local'");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

print_r($admin);
