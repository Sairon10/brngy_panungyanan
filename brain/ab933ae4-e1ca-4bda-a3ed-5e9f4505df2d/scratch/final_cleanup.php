<?php
require_once 'config.php';
$pdo = get_db_connection();

$names = ['Sairon Mark Caguitla Manalad', 'Francis John Ramelb'];

foreach ($names as $name) {
    echo "Deleting from resident_records: $name\n";
    $stmt = $pdo->prepare("DELETE FROM resident_records WHERE full_name = ?");
    $stmt->execute([$name]);
    echo "Deleted: " . $stmt->rowCount() . " rows\n";
}
echo "CLEANUP FINISHED.\n";
