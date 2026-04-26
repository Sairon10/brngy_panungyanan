<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$incident_id = 24;
$stmt = $pdo->prepare('
    SELECT 
        i.*,
        u.full_name as resident_name,
        u.email as resident_email
    FROM incidents i
    JOIN users u ON u.id = i.user_id
    WHERE i.id = ?
');
$stmt->execute([$incident_id]);
$incident = $stmt->fetch();
echo "ID: " . $incident['id'] . "\n";
echo "Status: " . $incident['status'] . "\n";
echo "Notes: [" . $incident['notes'] . "]\n";
echo "Notes Empty? " . (empty($incident['notes']) ? 'YES' : 'NO') . "\n";
