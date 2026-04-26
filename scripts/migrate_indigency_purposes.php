<?php
/**
 * Migration script to add indigency_purposes field to document_requests table
 * Run this once to add support for storing selected purposes for Indigency certificates
 */

require_once __DIR__ . '/../config.php';

$pdo = get_db_connection();

try {
    // Check if column already exists
    $check_stmt = $pdo->query("SHOW COLUMNS FROM document_requests LIKE 'indigency_purposes'");
    $column_exists = $check_stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Add indigency_purposes column to document_requests table
        // Using VARCHAR to store a single selected purpose
        $pdo->exec("
            ALTER TABLE document_requests 
            ADD COLUMN indigency_purposes VARCHAR(255) DEFAULT NULL
        ");
        
        echo "✓ indigency_purposes column added successfully to document_requests table!\n";
    } else {
        echo "✓ indigency_purposes column already exists.\n";
        // Check if it's JSON type and suggest migration
        $col_info = $pdo->query("SHOW COLUMNS FROM document_requests WHERE Field = 'indigency_purposes'")->fetch();
        if (isset($col_info['Type']) && stripos($col_info['Type'], 'json') !== false) {
            echo "  Note: Column is currently JSON type. Consider migrating to VARCHAR for single purpose storage.\n";
        }
    }
    
    echo "\nYou can now select purposes when approving/releasing Indigency certificates.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

