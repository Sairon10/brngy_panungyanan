<?php
/**
 * Migration: Add payment_reference_no and payment_amount_paid columns
 * to barangay_clearances and document_requests tables.
 * Run once.
 */
require_once __DIR__ . '/../config.php';
$pdo = get_db_connection();

$tables = [
    'barangay_clearances' => [
        "ADD COLUMN IF NOT EXISTS payment_reference_no VARCHAR(100) DEFAULT NULL AFTER payment_receipt",
        "ADD COLUMN IF NOT EXISTS payment_amount_paid DECIMAL(10,2) DEFAULT NULL AFTER payment_reference_no",
    ],
    'document_requests' => [
        "ADD COLUMN IF NOT EXISTS payment_reference_no VARCHAR(100) DEFAULT NULL AFTER payment_receipt",
        "ADD COLUMN IF NOT EXISTS payment_amount_paid DECIMAL(10,2) DEFAULT NULL AFTER payment_reference_no",
    ],
];

foreach ($tables as $table => $alters) {
    foreach ($alters as $alter) {
        try {
            $pdo->exec("ALTER TABLE `{$table}` {$alter}");
            echo "✓ {$table}: {$alter}\n";
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate column')) {
                echo "✓ {$table}: column already exists — skipped.\n";
            } else {
                echo "✗ {$table}: " . $e->getMessage() . "\n";
            }
        }
    }
}
echo "\nDone.\n";
