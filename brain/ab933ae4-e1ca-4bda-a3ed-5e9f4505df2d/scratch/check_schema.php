<?php
require 'c:\\xampp\\htdocs\\config.php';
$pdo = get_db_connection();
echo "--- residents ---\n";
print_r($pdo->query('DESCRIBE residents')->fetchAll(PDO::FETCH_ASSOC));
echo "\n--- family_members ---\n";
print_r($pdo->query('DESCRIBE family_members')->fetchAll(PDO::FETCH_ASSOC));
echo "\n--- resident_records ---\n";
print_r($pdo->query('DESCRIBE resident_records')->fetchAll(PDO::FETCH_ASSOC));
