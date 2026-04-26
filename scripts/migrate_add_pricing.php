<?php
/**
 * Migration script to add price column to document_types table
 * Run this once to add pricing functionality
 */

require_once __DIR__ . '/../config.php';

$pdo = get_db_connection();

try {
    // Add price column to document_types table
    $pdo->exec("
        ALTER TABLE document_types 
        ADD COLUMN IF NOT EXISTS price DECIMAL(10, 2) DEFAULT 0.00 AFTER display_order
    ");
    
    echo "✓ Price column added to document_types table successfully!\n";
    echo "\nYou can now set prices for document types from the admin panel.\n";
    
} catch (PDOException $e) {
    // Check if column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✓ Price column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

