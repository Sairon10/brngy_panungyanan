<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$stmt = $pdo->query("SELECT id, status, notes, created_at FROM incidents ORDER BY id DESC LIMIT 5");
while ($row = $stmt->fetch()) {
    echo "ID: " . $row['id'] . " | Status: " . $row['status'] . " | Created: " . $row['created_at'] . " | Notes: [" . $row['notes'] . "]\n";
}
