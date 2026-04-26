<?php
require_once 'config.php';
$pdo = get_db_connection();

$userIds = [8, 10];

foreach ($userIds as $id) {
    echo "Processing User ID: $id\n";
    
    // 1. Delete associated data
    
    // Incident messages first (requires join to incidents)
    $stmt = $pdo->prepare("DELETE FROM incident_messages WHERE incident_id IN (SELECT id FROM incidents WHERE user_id = ?)");
    $stmt->execute([$id]);
    echo "Deleted from incident_messages: " . $stmt->rowCount() . " rows\n";

    $tables = ['document_requests', 'incidents', 'residents', 'notifications', 'family_members', 'admin_activity', 'password_resets', 'support_messages'];
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
