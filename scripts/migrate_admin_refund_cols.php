<?php
/**
 * Migration: Robustly add all required payment and refund columns
 * to barangay_clearances and document_requests tables.
 */
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

$tables = ['barangay_clearances', 'document_requests'];

$columns_to_add = [
    'payment_receipt' => [
        'definition' => 'VARCHAR(255) DEFAULT NULL',
        'after' => 'status'
    ],
    'payment_status' => [
        'definition' => "VARCHAR(50) DEFAULT 'pending'",
        'after' => 'payment_receipt'
    ],
    'payment_reference_no' => [
        'definition' => 'VARCHAR(100) DEFAULT NULL',
        'after' => 'payment_receipt'
    ],
    'payment_amount_paid' => [
        'definition' => 'DECIMAL(10,2) DEFAULT NULL',
        'after' => 'payment_reference_no'
    ],
    'refund_number' => [
        'definition' => 'VARCHAR(100) DEFAULT NULL',
        'after' => 'notes'
    ],
    'refund_notes' => [
        'definition' => 'TEXT DEFAULT NULL',
        'after' => 'refund_number'
    ],
    'refund_receipt' => [
        'definition' => 'VARCHAR(255) DEFAULT NULL',
        'after' => 'refund_notes'
    ],
    'admin_refund_number' => [
        'definition' => 'VARCHAR(100) DEFAULT NULL',
        'after' => 'refund_receipt'
    ],
    'admin_refund_notes' => [
        'definition' => 'TEXT DEFAULT NULL',
        'after' => 'admin_refund_number'
    ],
];

echo "<pre>";
echo "Starting robust payment & refund database migration...\n\n";

foreach ($tables as $table) {
    echo "Processing table: `{$table}`...\n";
    try {
        // Fetch existing columns in this table
        $stmt = $pdo->query("DESCRIBE `{$table}`");
        $existing_cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($columns_to_add as $col_name => $info) {
            if (in_array($col_name, $existing_cols)) {
                echo "  ✓ Column `{$col_name}` already exists. Skipped.\n";
            } else {
                $after_clause = "";
                if (!empty($info['after']) && in_array($info['after'], $existing_cols)) {
                    $after_clause = " AFTER `{$info['after']}`";
                }
                
                $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$col_name}` {$info['definition']}{$after_clause}";
                $pdo->exec($sql);
                echo "  + Column `{$col_name}` added successfully!\n";
                
                // Add to array so subsequent AFTER clauses can reference it
                $existing_cols[] = $col_name;
            }
        }
    } catch (PDOException $e) {
        echo "  ✗ Error on table `{$table}`: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "Migration finished. All payment and refund columns are up to date!\n";
echo "</pre>";

