<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();

$pdo->query("DELETE FROM document_requests WHERE purpose LIKE '%[Walk-in Requestor: Web UI Tester]%' OR purpose LIKE '%[Walk-in Requestor: Test User]%'");
$pdo->query("DELETE FROM barangay_clearances WHERE purpose LIKE '%[Walk-in Requestor: Web UI Tester]%' OR purpose LIKE '%[Walk-in Requestor: Test User]%'");

echo "Test data deleted.\n";
