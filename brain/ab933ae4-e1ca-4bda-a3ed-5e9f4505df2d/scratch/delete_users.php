<?php
require_once 'config.php';
$pdo = get_db_connection();

$userIds = [8, 10];

foreach ($userIds as $id) {
    echo "Processing User ID: $id\n";
    
    // 1. Delete associated data
    $tables = ['requests', 'incidents', 'residents', 'notifications', 'family_members'];
    foreach ($tables as $t) {
        $stmt = $pdo->prepare("DELETE FROM $t WHERE user_id = ?");
        $stmt->execute([$id]);
        echo "Deleted from $t: " . $stmt->rowCount() . " rows\n";
    }

    // 2. Delete from users
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    echo "Deleted from users: " . $stmt->rowCount() . " rows\n";
    
    echo "--------------------------\n";
}
echo "DELETION COMPLETE.\n";
