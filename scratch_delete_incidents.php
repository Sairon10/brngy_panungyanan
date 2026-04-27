<?php
// Override HTTP_HOST for CLI so config.php uses local credentials
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/config.php';

$pdo = get_db_connection();

// Delete incidents created by an admin that do NOT have the [Walk-in Reporter:] tag, 
// or specifically the ones from January 7, 2026.
$stmt = $pdo->prepare("DELETE FROM incidents WHERE description NOT LIKE '[Walk-in Reporter:%' AND user_id = (SELECT id FROM users WHERE email = 'admin@panungyanan.local' LIMIT 1)");
$stmt->execute();
echo "Deleted " . $stmt->rowCount() . " old test incidents.\n";
