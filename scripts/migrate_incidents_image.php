<?php
/**
 * Migration script to add image_path column to incidents table
 * Run this once to update the database schema
 */

require_once __DIR__ . '/../config.php';

$pdo = get_db_connection();

try {
    // Check if image_path column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM incidents LIKE 'image_path'");
    $column_exists = $stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Add image_path column to incidents table
        $pdo->exec("
            ALTER TABLE incidents 
            ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER longitude
        ");
        
        echo "✓ Successfully added 'image_path' column to incidents table.\n";
    } else {
        echo "✓ Column 'image_path' already exists. No changes needed.\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nThe incidents table is now ready for image uploads.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

