<?php
/**
 * Migration script to add purok field to residents and resident_records tables
 * Run this script once to update the database schema
 */
require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db_connection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting migration to add purok field...\n\n";
    
    // Add purok column to residents table
    echo "Updating residents table...\n";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM residents LIKE 'purok'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE residents ADD COLUMN purok VARCHAR(100) DEFAULT NULL AFTER barangay_id");
            echo "✓ Added column residents.purok.\n";
        } else {
            echo "✓ Column residents.purok already exists.\n";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "✓ Column residents.purok already exists.\n";
        } else {
            throw $e;
        }
    }
    
    // Add purok column to resident_records table
    echo "Updating resident_records table...\n";
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM resident_records LIKE 'purok'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE resident_records ADD COLUMN purok VARCHAR(100) DEFAULT NULL AFTER barangay_id");
            echo "✓ Added column resident_records.purok.\n";
        } else {
            echo "✓ Column resident_records.purok already exists.\n";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "✓ Column resident_records.purok already exists.\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✓ Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

