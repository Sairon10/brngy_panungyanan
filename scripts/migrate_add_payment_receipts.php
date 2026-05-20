<?php
/**
 * Migration script to add payment_receipt and payment_status columns to barangay_clearances and document_requests tables.
 * Run this once to setup E-Wallet / GCash payment receipt columns.
 */

require_once __DIR__ . '/../config.php';

$pdo = get_db_connection();

try {
    // 1. Update barangay_clearances table
    $pdo->exec("
        ALTER TABLE barangay_clearances 
        ADD COLUMN IF NOT EXISTS payment_receipt VARCHAR(255) DEFAULT NULL AFTER status,
        ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'pending' AFTER payment_receipt
    ");
    echo "✓ Payment receipt columns added to barangay_clearances table successfully!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✓ Payment receipt columns already exist in barangay_clearances.\n";
    } else {
        echo "Error (barangay_clearances): " . $e->getMessage() . "\n";
    }
}

try {
    // 2. Update document_requests table
    $pdo->exec("
        ALTER TABLE document_requests 
        ADD COLUMN IF NOT EXISTS payment_receipt VARCHAR(255) DEFAULT NULL AFTER status,
        ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'pending' AFTER payment_receipt
    ");
    echo "✓ Payment receipt columns added to document_requests table successfully!\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✓ Payment receipt columns already exist in document_requests.\n";
    } else {
        echo "Error (document_requests): " . $e->getMessage() . "\n";
    }
}
