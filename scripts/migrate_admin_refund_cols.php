<?php
/**
 * Migration: Add admin_refund_number and admin_refund_notes columns
 * to separate admin's refund processing details from resident's refund request details.
 */
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

$queries = [
    "ALTER TABLE barangay_clearances ADD COLUMN IF NOT EXISTS admin_refund_number VARCHAR(100) DEFAULT NULL AFTER refund_receipt",
    "ALTER TABLE barangay_clearances ADD COLUMN IF NOT EXISTS admin_refund_notes TEXT DEFAULT NULL AFTER admin_refund_number",
    "ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS admin_refund_number VARCHAR(100) DEFAULT NULL AFTER refund_receipt",
    "ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS admin_refund_notes TEXT DEFAULT NULL AFTER admin_refund_number",
];

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "OK: $sql\n";
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
