<?php
/**
 * Migration script to add PDF generation tracking to barangay_clearances table
 * Run this once to update the database schema
 */

require_once __DIR__ . '/../config.php';

$pdo = get_db_connection();

try {
    // Check if pdf_generated_at column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM barangay_clearances LIKE 'pdf_generated_at'");
    $column_exists = $stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Add pdf_generated_at column to track when PDF was generated
        $pdo->exec("
            ALTER TABLE barangay_clearances 
            ADD COLUMN pdf_generated_at TIMESTAMP NULL DEFAULT NULL AFTER approved_at
        ");
        
        echo "✓ Successfully added 'pdf_generated_at' column to barangay_clearances table.\n";
    } else {
        echo "✓ Column 'pdf_generated_at' already exists. No changes needed.\n";
    }
    
    // Check if pdf_generated_by column exists (to track which admin generated it)
    $stmt = $pdo->query("SHOW COLUMNS FROM barangay_clearances LIKE 'pdf_generated_by'");
    $column_exists = $stmt->rowCount() > 0;
    
    if (!$column_exists) {
        // Add pdf_generated_by column to track which admin generated the PDF
        $pdo->exec("
            ALTER TABLE barangay_clearances 
            ADD COLUMN pdf_generated_by INT DEFAULT NULL AFTER pdf_generated_at,
            ADD FOREIGN KEY (pdf_generated_by) REFERENCES users(id) ON DELETE SET NULL
        ");
        
        echo "✓ Successfully added 'pdf_generated_by' column to barangay_clearances table.\n";
    } else {
        echo "✓ Column 'pdf_generated_by' already exists. No changes needed.\n";
    }
    
    // Ensure notifications table supports 'clearance_pdf' type (optional enhancement)
    $stmt = $pdo->query("SHOW COLUMNS FROM notifications WHERE Field = 'type'");
    $result = $stmt->fetch();
    
    if ($result && strpos($result['Type'], 'clearance_pdf') === false) {
        // Note: MySQL doesn't support easy ENUM modification, so we'll just note this
        // The 'general' type already works for our purposes
        echo "ℹ Note: Notifications table uses 'general' type for clearance PDF notifications (this is fine).\n";
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nThe database is now ready for PDF generation tracking.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

