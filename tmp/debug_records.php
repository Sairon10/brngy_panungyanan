<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

echo "RESIDENTS TABLE:\n";
$stmt = $pdo->query('SELECT r.id, u.full_name, u.email, r.verification_status FROM residents r JOIN users u ON r.user_id = u.id ORDER BY r.id DESC LIMIT 5');
while($row = $stmt->fetch()) {
    print_r($row);
}

echo "RESIDENT_RECORDS TABLE:\n";
$stmt = $pdo->query('SELECT id, full_name, email FROM resident_records ORDER BY id DESC LIMIT 5');
while($row = $stmt->fetch()) {
    print_r($row);
}
