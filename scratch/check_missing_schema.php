<?php
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();
$tables = ['incident_messages', 'support_messages', 'support_chats', 'admin_activity'];
foreach ($tables as $table) {
    echo "--- $table ---\n";
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        echo $row[1] . "\n\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}
