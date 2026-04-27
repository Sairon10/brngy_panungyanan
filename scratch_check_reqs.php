<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'config.php';
$pdo = get_db_connection();

$stmt = $pdo->query("SELECT * FROM document_requests ORDER BY id DESC LIMIT 5");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $pdo->query("SELECT * FROM barangay_clearances ORDER BY id DESC LIMIT 5");
$clears = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "DOCUMENTS:\n";
print_r($docs);
echo "\nCLEARANCES:\n";
print_r($clears);
