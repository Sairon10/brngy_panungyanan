<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$stmt = $pdo->query("SELECT u.full_name, r.phone, r.verification_status FROM residents r JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 5");
$residents = $stmt->fetchAll();

header('Content-Type: text/plain');
foreach ($residents as $r) {
    echo "Name: " . $r['full_name'] . "\n";
    echo "Phone: " . ($r['phone'] ?: 'EMPTY') . "\n";
    echo "Status: " . $r['verification_status'] . "\n";
    echo "-------------------\n";
}
