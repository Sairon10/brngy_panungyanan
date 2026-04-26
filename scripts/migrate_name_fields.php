<?php
/**
 * Migration script to add separate name fields and additional user information
 * Run this script once to update the database schema
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = get_db_connection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Starting migration...\n";
    
    // Add new columns to users table
    echo "Updating users table...\n";
    $columns_to_add = [
        "first_name VARCHAR(100) DEFAULT NULL AFTER full_name",
        "last_name VARCHAR(100) DEFAULT NULL AFTER first_name",
        "middle_name VARCHAR(100) DEFAULT NULL AFTER last_name",
        "suffix VARCHAR(20) DEFAULT NULL AFTER middle_name"
    ];
    
    foreach ($columns_to_add as $column_def) {
        $column_name = explode(' ', $column_def)[0];
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$column_def}");
            echo "  Added column: {$column_name}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  Column {$column_name} already exists, skipping...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Add new columns to residents table
    echo "Updating residents table...\n";
    $columns_to_add = [
        "citizenship VARCHAR(100) DEFAULT NULL AFTER birthdate",
        "civil_status ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') DEFAULT NULL AFTER citizenship"
    ];
    
    foreach ($columns_to_add as $column_def) {
        $column_name = explode(' ', $column_def)[0];
        try {
            $pdo->exec("ALTER TABLE residents ADD COLUMN {$column_def}");
            echo "  Added column: {$column_name}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  Column {$column_name} already exists, skipping...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Note: residents table already has birthdate and sex (gender) fields
    
    // Add new columns to resident_records table
    echo "Updating resident_records table...\n";
    $columns_to_add = [
        "first_name VARCHAR(100) DEFAULT NULL AFTER full_name",
        "last_name VARCHAR(100) DEFAULT NULL AFTER first_name",
        "middle_name VARCHAR(100) DEFAULT NULL AFTER last_name",
        "suffix VARCHAR(20) DEFAULT NULL AFTER middle_name",
        "citizenship VARCHAR(100) DEFAULT NULL AFTER sex",
        "civil_status ENUM('Single', 'Married', 'Widowed', 'Divorced', 'Separated') DEFAULT NULL AFTER citizenship"
    ];
    
    foreach ($columns_to_add as $column_def) {
        $column_name = explode(' ', $column_def)[0];
        try {
            $pdo->exec("ALTER TABLE resident_records ADD COLUMN {$column_def}");
            echo "  Added column: {$column_name}\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  Column {$column_name} already exists, skipping...\n";
            } else {
                throw $e;
            }
        }
    }
    
    // Note: resident_records table already has birthdate and sex (gender) fields
    
    echo "Migration completed successfully!\n";
    echo "\nNote: Existing full_name values are preserved. You may want to run a script to split existing full_name values into the new fields.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

