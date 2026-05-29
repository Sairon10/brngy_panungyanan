<?php
require 'config.php';
$pdo = get_db_connection();
try {
    $pdo->exec('ALTER TABLE barangay_clearances ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL AFTER status');
    echo 'Success';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
